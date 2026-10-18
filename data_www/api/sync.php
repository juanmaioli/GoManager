<?php
// api/sync.php - Endpoint para sincronizar métricas de un servidor

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ssh.php';
require_once __DIR__ . '/../includes/helpers.php';

$servidorId = (int)($_REQUEST['id'] ?? 0);

if ($servidorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de servidor inválido']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM servidores WHERE id = :id");
$stmt->execute([':id' => $servidorId]);
$servidor = $stmt->fetch();

if (!$servidor) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Servidor no encontrado']);
    exit;
}

$resultado = ejecutarSSH($servidor, $servidor['ruta_binario'], 8);

if ($resultado['success'] && is_array($resultado['data'])) {
    guardarMetricas($pdo, $servidorId, $resultado['data'], $resultado['output']);

    echo json_encode([
        'success' => true,
        'servidor_id' => $servidorId,
        'estado' => 'online',
        'data' => $resultado['data'],
        'ultimo_check' => date('Y-m-d H:i:s')
    ]);
} else {
    // Registrar fallo de conexión e incidente
    registrarFalloServidor($pdo, $servidorId, $resultado['error'] ?? 'Error de conexión SSH');

    echo json_encode([
        'success' => false,
        'servidor_id' => $servidorId,
        'estado' => 'error',
        'error' => $resultado['error']
    ]);
}
