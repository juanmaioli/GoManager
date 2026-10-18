<?php
// includes/helpers.php - Utilidades de formato y presentación

declare(strict_types=1);

// Zona horaria local por defecto
date_default_timezone_set('America/Argentina/Buenos_Aires');

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Convierte una fecha/hora guardada en UTC (formato SQLite) a la hora local para mostrar en pantalla.
 *
 * @param string|null $fechaUtc Fecha en formato 'YYYY-MM-DD HH:MM:SS'
 * @param string $formato Formato de salida de fecha (por defecto 'H:i:s d/m')
 * @return string Fecha formateada en hora local o 'Sin datos'
 */
function formatearFechaLocal(?string $fechaUtc, string $formato = 'H:i:s d/m'): string
{
    if (empty(trim((string)$fechaUtc))) {
        return 'Sin datos';
    }

    try {
        $fechaString = trim((string)$fechaUtc);
        $esIsoOConZona = str_ends_with($fechaString, 'Z') || str_contains($fechaString, '+') || str_contains($fechaString, 'T');
        $dt = new DateTime($esIsoOConZona ? $fechaString : $fechaString . ' UTC');
        $dt->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
        return $dt->format($formato);
    } catch (\Throwable $e) {
        return (string)$fechaUtc;
    }
}

/**
 * Convierte segundos a formato legible en español.
 */
function formatearUptime(int|float|string|null $segundos): string
{
    if ($segundos === null || !is_numeric($segundos) || (int)$segundos <= 0) {
        return 'N/A';
    }

    $sec = (int)$segundos;
    $dias = floor($sec / 86400);
    $horas = floor(($sec % 86400) / 3600);
    $minutos = floor(($sec % 3600) / 60);

    $resultado = [];
    if ($dias > 0) $resultado[] = "{$dias}d";
    if ($horas > 0) $resultado[] = "{$horas}h";
    $resultado[] = "{$minutos}m";

    return implode(' ', $resultado);
}

/**
 * Determina la clase de color Bootstrap según el porcentaje de uso.
 */
function colorPorcentaje(string|int|float|null $porcentaje): string
{
    $val = (float)str_replace(['%', ' '], '', (string)$porcentaje);
    if ($val >= 85) return 'danger';
    if ($val >= 70) return 'warning';
    return 'success';
}

/**
 * Inserta o actualiza las métricas de un servidor y resuelve incidentes abiertos.
 */
function guardarMetricas(PDO $pdo, int $servidorId, array $datos, string $rawJson): void
{
    $cpuUsage = $datos['cpu_usage'] ?? null;
    $ramUsage = $datos['ram_usage'] ?? null;
    $uptime = isset($datos['system_uptime']) && is_numeric($datos['system_uptime']) ? (int)$datos['system_uptime'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO metricas (servidor_id, cpu_usage, ram_usage, uptime, raw_json)
        VALUES (:servidor_id, :cpu_usage, :ram_usage, :uptime, :raw_json)
    ");
    $stmt->execute([
        ':servidor_id' => $servidorId,
        ':cpu_usage'   => $cpuUsage,
        ':ram_usage'   => $ramUsage,
        ':uptime'      => $uptime,
        ':raw_json'    => $rawJson
    ]);

    // Si había un incidente abierto, cerrarlo con la marca de tiempo actual
    $stmtInc = $pdo->prepare("
        SELECT id, desconectado_en FROM incidentes 
        WHERE servidor_id = :id AND reconectado_en IS NULL 
        ORDER BY id DESC LIMIT 1
    ");
    $stmtInc->execute([':id' => $servidorId]);
    $incAbierto = $stmtInc->fetch();

    if ($incAbierto) {
        $stmtClose = $pdo->prepare("
            UPDATE incidentes 
            SET reconectado_en = CURRENT_TIMESTAMP,
                duracion_segundos = CAST((strftime('%s', CURRENT_TIMESTAMP) - strftime('%s', desconectado_en)) AS INTEGER)
            WHERE id = :id
        ");
        $stmtClose->execute([':id' => $incAbierto['id']]);
    }

    // Actualizar estado del servidor
    $stmtUpd = $pdo->prepare("
        UPDATE servidores
        SET ultimo_estado = 'online', ultimo_check = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmtUpd->execute([':id' => $servidorId]);
}

/**
 * Registra un fallo o desconexión del servidor y crea un incidente si no existe uno abierto.
 */
function registrarFalloServidor(PDO $pdo, int $servidorId, string $motivo = 'Error de conexión SSH'): void
{
    // Verificar si ya tiene un incidente abierto
    $stmtCheck = $pdo->prepare("
        SELECT id FROM incidentes 
        WHERE servidor_id = :id AND reconectado_en IS NULL 
        ORDER BY id DESC LIMIT 1
    ");
    $stmtCheck->execute([':id' => $servidorId]);
    $abierto = $stmtCheck->fetch();

    if (!$abierto) {
        $stmtIns = $pdo->prepare("
            INSERT INTO incidentes (servidor_id, desconectado_en, motivo)
            VALUES (:id, CURRENT_TIMESTAMP, :motivo)
        ");
        $stmtIns->execute([
            ':id' => $servidorId,
            ':motivo' => $motivo
        ]);
    }

    $stmtUpd = $pdo->prepare("
        UPDATE servidores 
        SET ultimo_estado = 'error', ultimo_check = CURRENT_TIMESTAMP 
        WHERE id = :id
    ");
    $stmtUpd->execute([':id' => $servidorId]);
}

/**
 * Formatea una duración en segundos a una cadena legible (ej. "2h 15m", "45s", "< 1m").
 */
function formatearDuracion(?int $segundos): string
{
    if ($segundos === null || $segundos <= 0) {
        return '< 1 min';
    }
    $dias = intdiv($segundos, 86400);
    $horas = intdiv($segundos % 86400, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $segs = $segundos % 60;

    $partes = [];
    if ($dias > 0) $partes[] = "{$dias}d";
    if ($horas > 0) $partes[] = "{$horas}h";
    if ($minutos > 0) $partes[] = "{$minutos}m";
    if (empty($partes) || ($dias === 0 && $horas === 0)) $partes[] = "{$segs}s";

    return implode(' ', $partes);
}

/**
 * Retorna la clase de icono FontAwesome de la distribución de Linux / SO.
 * Si no se encuentra una distro específica, usa por defecto fa-brands fa-linux.
 */
function iconoDistroSO(?string $nombreSO): string
{
    if (empty($nombreSO)) {
        return 'fa-brands fa-linux';
    }

    $so = strtolower($nombreSO);

    if (str_contains($so, 'debian')) {
        return 'fa-brands fa-debian';
    }
    if (str_contains($so, 'ubuntu')) {
        return 'fa-brands fa-ubuntu';
    }
    if (str_contains($so, 'fedora')) {
        return 'fa-brands fa-fedora';
    }
    if (str_contains($so, 'centos')) {
        return 'fa-brands fa-centos';
    }
    if (str_contains($so, 'red hat') || str_contains($so, 'redhat') || str_contains($so, 'rhel')) {
        return 'fa-brands fa-redhat';
    }
    if (str_contains($so, 'arch')) {
        return 'fa-brands fa-arch-linux';
    }
    if (str_contains($so, 'suse')) {
        return 'fa-brands fa-opensuse';
    }
    if (str_contains($so, 'raspberry') || str_contains($so, 'raspbian')) {
        return 'fa-brands fa-raspberry-pi';
    }
    if (str_contains($so, 'freebsd')) {
        return 'fa-brands fa-freebsd';
    }
    if (str_contains($so, 'android')) {
        return 'fa-brands fa-android';
    }
    if (str_contains($so, 'windows')) {
        return 'fa-brands fa-windows';
    }
    if (str_contains($so, 'apple') || str_contains($so, 'darwin') || str_contains($so, 'macos') || str_contains($so, 'mac os')) {
        return 'fa-brands fa-apple';
    }
    if (str_contains($so, 'docker')) {
        return 'fa-brands fa-docker';
    }

    return 'fa-brands fa-linux';
}

