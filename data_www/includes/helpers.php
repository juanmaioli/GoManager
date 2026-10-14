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
