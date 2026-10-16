<?php
// api/sync_all.php - Sincronizar todos los servidores activos vía AJAX / Web

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ssh.php';
require_once __DIR__ . '/../includes/helpers.php';

$servidores = $pdo->query("SELECT * FROM servidores WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();

if (empty($servidores)) {
    echo json_encode([
        'success' => true,
        'total' => 0,
        'online' => 0,
        'error' => 0,
        'mensaje' => 'No hay servidores activos',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$online = 0;
$error = 0;
$detalles = [];

foreach ($servidores as $srv) {
    $res = ejecutarSSH($srv, $srv['ruta_binario'], 6);
    if ($res['success'] && is_array($res['data'])) {
        guardarMetricas($pdo, (int)$srv['id'], $res['data'], $res['output']);
        $online++;
        $detalles[] = [
            'id' => (int)$srv['id'],
            'nombre' => $srv['nombre'],
            'estado' => 'online',
            'cpu' => $res['data']['cpu_usage'] ?? '0%',
            'ram' => $res['data']['ram_usage'] ?? '0%'
        ];
    } else {
        $stmtUpd = $pdo->prepare("UPDATE servidores SET ultimo_estado = 'error', ultimo_check = CURRENT_TIMESTAMP WHERE id = :id");
        $stmtUpd->execute([':id' => $srv['id']]);
        $error++;
        $detalles[] = [
            'id' => (int)$srv['id'],
            'nombre' => $srv['nombre'],
            'estado' => 'error',
            'error' => $res['error'] ?? 'Error desconocido'
        ];
    }
}

echo json_encode([
    'success' => true,
    'total' => count($servidores),
    'online' => $online,
    'error' => $error,
    'timestamp' => date('H:i:s'),
    'detalles' => $detalles
]);
