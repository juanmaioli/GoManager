<?php
// api/install_client.php - Endpoint para transferir gosvrinfo vía SCP al servidor remoto

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ssh.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$host = trim((string)($_POST['host'] ?? ''));
$puerto = (int)($_POST['puerto'] ?? 22);
$usuario = trim((string)($_POST['usuario'] ?? ''));
$authTipo = trim((string)($_POST['auth_tipo'] ?? 'password'));
$password = (string)($_POST['password'] ?? '');
$clavePrivada = (string)($_POST['clave_privada'] ?? '');
$binarioNombre = basename(trim((string)($_POST['binario'] ?? 'gosvrinfo')));

if (empty($host) || empty($usuario)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El host y el usuario son requeridos.']);
    exit;
}

// Si estamos editando un servidor existente y no se enviaron credenciales, buscarlas en SQLite
if ($id > 0 && empty($password) && empty($clavePrivada)) {
    $stmt = $pdo->prepare("SELECT password, clave_privada FROM servidores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $srvDb = $stmt->fetch();
    if ($srvDb) {
        $password = $srvDb['password'] ?? '';
        $clavePrivada = $srvDb['clave_privada'] ?? '';
    }
}

$rutaLocal = realpath(__DIR__ . '/../clientes/' . $binarioNombre);
$clientesDir = realpath(__DIR__ . '/../clientes');

if (!$rutaLocal || !$clientesDir || !str_starts_with($rutaLocal, $clientesDir) || !is_file($rutaLocal)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "El ejecutable seleccionado no existe en ./clientes: {$binarioNombre}"]);
    exit;
}

$servidorData = [
    'id'            => $id,
    'host'          => $host,
    'puerto'        => $puerto > 0 ? $puerto : 22,
    'usuario'       => $usuario,
    'auth_tipo'     => $authTipo === 'key' ? 'key' : 'password',
    'password'      => $password,
    'clave_privada' => $clavePrivada
];

// Ejecutar SCP hacia /tmp/gosvrinfo
$resultado = ejecutarSCP($servidorData, $rutaLocal, '/tmp/gosvrinfo', 25);

if ($resultado['success']) {
    echo json_encode([
        'success' => true,
        'mensaje' => "Ejecutable '{$binarioNombre}' transferido con éxito a /tmp/gosvrinfo",
        'binario' => $binarioNombre,
        'comando' => "sudo install -m 755 /tmp/gosvrinfo /usr/local/bin/gosvrinfo && rm /tmp/gosvrinfo"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $resultado['error']
    ]);
}
