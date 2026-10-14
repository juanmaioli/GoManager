<?php
// sync_cli.php - Script CLI para sincronización desatendida / cron

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("Este script solo se puede ejecutar desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ssh.php';
require_once __DIR__ . '/includes/helpers.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronización de servidores...\n";

$servidores = $pdo->query("SELECT * FROM servidores WHERE activo = 1")->fetchAll();

if (empty($servidores)) {
    echo "No hay servidores activos para sincronizar.\n";
    exit(0);
}

foreach ($servidores as $srv) {
    echo "Sincronizando '{$srv['nombre']}' ({$srv['host']}:{$srv['puerto']})... ";
    $res = ejecutarSSH($srv, $srv['ruta_binario'], 8);

    if ($res['success'] && is_array($res['data'])) {
        guardarMetricas($pdo, (int)$srv['id'], $res['data'], $res['output']);
        echo "[OK] CPU: {$res['data']['cpu_usage']} | RAM: {$res['data']['ram_usage']}\n";
    } else {
        $stmtUpd = $pdo->prepare("UPDATE servidores SET ultimo_estado = 'error', ultimo_check = CURRENT_TIMESTAMP WHERE id = :id");
        $stmtUpd->execute([':id' => $srv['id']]);
        echo "[ERROR] {$res['error']}\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Sincronización completada.\n";
