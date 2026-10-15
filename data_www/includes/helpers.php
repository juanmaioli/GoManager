<?php
// includes/helpers.php - Utilidades de formato y presentación

declare(strict_types=1);

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Convierte segundos a formato legible en español.
 */
function formatearUptime(int|float|string|null $segundos): string
{
    if ($segundos === null || !is_numeric($segundos)) {
        return 'No disponible';
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
 * Inserta o actualiza las métricas de un servidor.
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

    // Actualizar estado del servidor
    $stmtUpd = $pdo->prepare("
        UPDATE servidores
        SET ultimo_estado = 'online', ultimo_check = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmtUpd->execute([':id' => $servidorId]);
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

