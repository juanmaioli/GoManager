#!/bin/sh
# Script compatible con ash (BusyBox / Alpine / OpenWrt / Linux estándar)
# Genera métricas del sistema en formato JSON idéntico a GoSvrInfo (host.json)

# Función para formatear KB a B/KB/MB/GB/TB
format_kb() {
    awk -v kb="$1" 'BEGIN {
        bytes = kb * 1024
        if (bytes < 1024) { printf "%d B\n", bytes; exit }
        div = 1024; e = 0
        for (n = int(bytes / 1024); n >= 1024; n = int(n / 1024)) {
            div = div * 1024
            e = e + 1
        }
        split("KB MB GB TB PB", u, " ")
        printf "%.0f %s\n", bytes / div, u[e + 1]
    }'
}

# 1. Tiempo y Uptime
NOW=$(date +%s)
UPTIME=$(awk '{print int($1)}' /proc/uptime 2>/dev/null || echo 0)
HOST_NAME=$(hostname 2>/dev/null || cat /proc/sys/kernel/hostname 2>/dev/null || uname -n)

# 2. Sistema Operativo
OS_NAME="Linux"
OS_VERSION="Unknown"
KERNEL_VERSION=$(uname -r 2>/dev/null || echo "Unknown")

if [ -f /etc/os-release ]; then
    OS_NAME=$(sed -n 's/^NAME="\{0,1\}\([^"]*\)"\{0,1\}/\1/p' /etc/os-release | head -n1)
    OS_VERSION=$(sed -n 's/^VERSION="\{0,1\}\([^"]*\)"\{0,1\}/\1/p' /etc/os-release | head -n1)
elif [ -f /usr/lib/os-release ]; then
    OS_NAME=$(sed -n 's/^NAME="\{0,1\}\([^"]*\)"\{0,1\}/\1/p' /usr/lib/os-release | head -n1)
    OS_VERSION=$(sed -n 's/^VERSION="\{0,1\}\([^"]*\)"\{0,1\}/\1/p' /usr/lib/os-release | head -n1)
elif [ -f /system/build.prop ]; then
    OS_NAME="Android"
    OS_VERSION=$(sed -n 's/^ro.build.version.release=\(.*\)/\1/p' /system/build.prop | head -n1)
fi
[ -z "$OS_NAME" ] && OS_NAME="Linux"
[ -z "$OS_VERSION" ] && OS_VERSION="Unknown"

# 3. IP Pública y Geolocalización (ip-api.com)
GEO_DATA=""
if command -v wget >/dev/null 2>&1; then
    GEO_DATA=$(wget -qO- -T 3 http://ip-api.com/json/ 2>/dev/null)
elif command -v curl >/dev/null 2>&1; then
    GEO_DATA=$(curl -s --max-time 3 http://ip-api.com/json/ 2>/dev/null)
fi

parse_json_str() {
    echo "$1" | sed -n 's/.*"'"$2"'"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p'
}

parse_json_num() {
    echo "$1" | sed -n 's/.*"'"$2"'"[[:space:]]*:[[:space:]]*\([-0-9.]*\).*/\1/p'
}

IP_PUBLIC=$(parse_json_str "$GEO_DATA" "query")
CITY=$(parse_json_str "$GEO_DATA" "city")
COUNTRY=$(parse_json_str "$GEO_DATA" "country")
LATITUDE=$(parse_json_num "$GEO_DATA" "lat")
LONGITUDE=$(parse_json_num "$GEO_DATA" "lon")
TIMEZONE=$(parse_json_str "$GEO_DATA" "timezone")
ISP=$(parse_json_str "$GEO_DATA" "isp")

[ -z "$LATITUDE" ] && LATITUDE=0
[ -z "$LONGITUDE" ] && LONGITUDE=0

# 4. CPU
CPU_MODEL=$(awk -F': ' '/model name|Hardware|Processor/ {print $2; exit}' /proc/cpuinfo 2>/dev/null)
[ -z "$CPU_MODEL" ] && CPU_MODEL="Desconocido"
CPU_CORES=$(grep -c "^processor" /proc/cpuinfo 2>/dev/null || echo 1)
CPU_SPEED=$(awk -F': ' '/cpu MHz/ {printf "%.2f GHz\n", $2/1000; exit}' /proc/cpuinfo 2>/dev/null)
[ -z "$CPU_SPEED" ] && CPU_SPEED="N/A"

# Uso de CPU (delta entre dos muestras)
CPU_USAGE="0%"
if [ -r /proc/stat ]; then
    L1=$(grep "^cpu " /proc/stat)
    busybox usleep 200000 2>/dev/null || sleep 1
    L2=$(grep "^cpu " /proc/stat)
    CPU_USAGE=$(awk -v l1="$L1" -v l2="$L2" 'BEGIN {
        split(l1, a, " ")
        split(l2, b, " ")
        idle1 = a[5] + a[6]
        total1 = 0
        for (i = 2; i <= length(a); i++) total1 += a[i]
        idle2 = b[5] + b[6]
        total2 = 0
        for (i = 2; i <= length(b); i++) total2 += b[i]
        diff_total = total2 - total1
        diff_idle = idle2 - idle1
        if (diff_total <= 0) {
            print "0%"
        } else {
            pct = 100 * (1 - (diff_idle / diff_total))
            if (pct < 0) pct = 0
            if (pct > 100) pct = 100
            printf "%.0f%%\n", pct
        }
    }')
fi

# 5. Memoria RAM
MEM_TOTAL_KB=$(awk '/MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)
MEM_AVAIL_KB=$(awk '/MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)

if [ "$MEM_TOTAL_KB" -gt 0 ]; then
    MEM_USED_KB=$((MEM_TOTAL_KB - MEM_AVAIL_KB))
    RAM_USAGE="$(( (MEM_USED_KB * 100) / MEM_TOTAL_KB ))%"
    RAM_TOTAL=$(format_kb "$MEM_TOTAL_KB")
else
    RAM_TOTAL="0 GB"
    RAM_USAGE="0%"
fi

# 6. GPU
GPU_NAME="N/A"
GPU_DRIVER="N/A"
GPU_MEMORY="N/A"

if command -v nvidia-smi >/dev/null 2>&1; then
    NV_OUT=$(nvidia-smi --query-gpu=name,driver_version,memory.total --format=csv,noheader,nounits 2>/dev/null | head -n1)
    if [ -n "$NV_OUT" ]; then
        GPU_NAME=$(echo "$NV_OUT" | awk -F', ' '{print $1}')
        GPU_DRIVER=$(echo "$NV_OUT" | awk -F', ' '{print $2}')
        NV_MEM_MB=$(echo "$NV_OUT" | awk -F', ' '{print $3}')
        GPU_MEMORY=$(format_kb "$((NV_MEM_MB * 1024))")
    fi
elif command -v lspci >/dev/null 2>&1; then
    PCI_LINE=$(lspci 2>/dev/null | grep -iE 'vga|3d|display' | head -n1)
    if [ -n "$PCI_LINE" ]; then
        GPU_NAME=$(echo "$PCI_LINE" | sed 's/^.*: //')
    fi
elif [ -f /sys/class/kgsl/kgsl-3d0/gpu_model ]; then
    GPU_NAME=$(cat /sys/class/kgsl/kgsl-3d0/gpu_model 2>/dev/null)
elif [ -f /sys/class/misc/mali0/device/gpuinfo ]; then
    GPU_NAME=$(cat /sys/class/misc/mali0/device/gpuinfo 2>/dev/null)
elif [ -f /sys/class/graphics/fb0/name ]; then
    FB_NAME=$(cat /sys/class/graphics/fb0/name 2>/dev/null)
    [ "$FB_NAME" != "dummy" ] && GPU_NAME="$FB_NAME"
fi

# 7. Discos montados
DISKS_JSON=""
FIRST_DISK=1

if [ -f /proc/mounts ]; then
    # Filtrar dispositivos físicos reales (/dev/sd*, /dev/nvme*, /dev/vd*, /dev/mmcblk*)
    MOUNTS=$(awk '$1 ~ /^\/dev\// && $1 !~ /^\/dev\/(loop|ram)/ {print $1, $2}' /proc/mounts 2>/dev/null | sort -u -k1,1)
    
    echo "$MOUNTS" | while read -r dev mnt; do
        [ -z "$dev" ] && continue
        # Obtener bloques en KB
        df_out=$(df -k "$mnt" 2>/dev/null | tail -n1)
        [ -z "$df_out" ] && continue
        
        total_kb=$(echo "$df_out" | awk '{print $(NF-4)}')
        free_kb=$(echo "$df_out" | awk '{print $(NF-2)}')
        
        [ -z "$total_kb" ] || [ "$total_kb" -le 0 ] 2>/dev/null && continue
        
        dev_name=$(basename "$dev")
        size_fmt=$(format_kb "$total_kb")
        free_fmt=$(format_kb "$free_kb")
        
        cat <<EOF
{"name":"$dev_name","size":"$size_fmt","free":"$free_fmt"}
EOF
    done > /tmp/disks_$$.tmp

    # Unir filas con comas para JSON
    while read -r dline; do
        if [ $FIRST_DISK -eq 1 ]; then
            DISKS_JSON="    $dline"
            FIRST_DISK=0
        else
            DISKS_JSON="$DISKS_JSON,
    $dline"
        fi
    done < /tmp/disks_$$.tmp
    rm -f /tmp/disks_$$.tmp
fi

# 8. Interfaces de Red
NET_JSON=""
FIRST_NET=1

for iface_path in /sys/class/net/*; do
    [ -d "$iface_path" ] || continue
    ifname=$(basename "$iface_path")
    
    # Omitir loopback y virtuales de contenedores veth
    [ "$ifname" = "lo" ] && continue
    case "$ifname" in veth*) continue ;; esac
    
    # Verificar si la interfaz está UP (flag & 1)
    flags=$(cat "$iface_path/flags" 2>/dev/null || echo 0)
    [ "$((flags & 1))" -eq 0 ] && continue
    
    mac_addr=$(cat "$iface_path/address" 2>/dev/null)
    
    # Obtener IP IPv4
    ip_addr=""
    if command -v ip >/dev/null 2>&1; then
        ip_addr=$(ip -4 addr show dev "$ifname" 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1 | head -n1)
    fi
    if [ -z "$ip_addr" ] && command -v ifconfig >/dev/null 2>&1; then
        ip_addr=$(ifconfig "$ifname" 2>/dev/null | awk '/inet / {print $2}' | sed 's/addr://' | head -n1)
    fi
    
    if [ -n "$ip_addr" ] || [ -n "$mac_addr" ]; then
        entry=$(cat <<EOF
    {
      "name": "$ifname",
      "mac_address": "$mac_addr",
      "ip_address": "$ip_addr"
    }
EOF
)
        if [ $FIRST_NET -eq 1 ]; then
            NET_JSON="$entry"
            FIRST_NET=0
        else
            NET_JSON="$NET_JSON,
$entry"
        fi
    fi
done

# 9. Salida en formato JSON
cat <<EOF
{
  "time": $NOW,
  "system_uptime": $UPTIME,
  "hostname": "$HOST_NAME",
  "operating_system": {
    "name": "$OS_NAME",
    "version": "$OS_VERSION",
    "kernel_version": "$KERNEL_VERSION"
  },
  "ip_public_address": "$IP_PUBLIC",
  "city": "$CITY",
  "country": "$COUNTRY",
  "latitude": $LATITUDE,
  "longitude": $LONGITUDE,
  "timezone": "$TIMEZONE",
  "isp": "$ISP",
  "CPU": "$CPU_MODEL",
  "cores": $CPU_CORES,
  "cpu_speed": "$CPU_SPEED",
  "cpu_usage": "$CPU_USAGE",
  "RAM": "$RAM_TOTAL",
  "ram_usage": "$RAM_USAGE",
  "GPU": {
    "name": "$GPU_NAME",
    "driver_version": "$GPU_DRIVER",
    "memory": "$GPU_MEMORY"
  },
  "disk_space": [
$DISKS_JSON
  ],
  "network_interfaces": [
$NET_JSON
  ]
}
EOF
