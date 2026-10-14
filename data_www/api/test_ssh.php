<?php
// api/test_ssh.php - Endpoint para testear conexión SSH

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/ssh.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$host = trim($_POST['host'] ?? '');
$puerto = (int)($_POST['puerto'] ?? 22);
$usuario = trim($_POST['usuario'] ?? '');
$authTipo = $_POST['auth_tipo'] ?? 'password';
$password = $_POST['password'] ?? '';
$clavePrivada = $_POST['clave_privada'] ?? '';
$rutaBinario = trim($_POST['ruta_binario'] ?? 'gosvrinfo');
if (empty($rutaBinario)) {
    $rutaBinario = 'gosvrinfo';
}

if (empty($host) || empty($usuario)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Host y Usuario son requeridos.']);
    exit;
}

$servidor = [
    'host' => $host,
    'puerto' => $puerto,
    'usuario' => $usuario,
    'auth_tipo' => $authTipo,
    'password' => $password,
    'clave_privada' => $clavePrivada,
    'ruta_binario' => $rutaBinario
];

$resultado = ejecutarSSH($servidor, $rutaBinario, 8);

if ($resultado['success']) {
    echo json_encode([
        'success' => true,
        'message' => '¡Conexión SSH exitosa y datos de gosvrinfo obtenidos correctamente!',
        'hostname' => $resultado['data']['hostname'] ?? 'Desconocido',
        'os' => ($resultado['data']['operating_system']['name'] ?? '') . ' ' . ($resultado['data']['operating_system']['version'] ?? '')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $resultado['error']
    ]);
}
