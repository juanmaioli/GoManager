<?php
// data_www/api/terminal_session.php - Generador de tokens temporales de sesión SSH para WebTTY

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$id = (int)($_REQUEST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de servidor inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, host, puerto, usuario, auth_tipo, password, clave_privada FROM servidores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $srv = $stmt->fetch();

    if (!$srv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Servidor no encontrado.']);
        exit;
    }

    $tokensDir = '/tmp/webtty_tokens';
    if (!is_dir($tokensDir)) {
        @mkdir($tokensDir, 0777, true);
        @chmod($tokensDir, 0777);
    }

    // Generar token criptográficamente seguro
    $token = bin2hex(random_bytes(24));
    $tokenFile = $tokensDir . '/' . $token . '.json';

    $payload = [
        'server_id'     => (int)$srv['id'],
        'server_nombre' => $srv['nombre'],
        'host'          => $srv['host'],
        'puerto'        => (int)($srv['puerto'] ?? 22),
        'usuario'       => $srv['usuario'],
        'auth_tipo'     => $srv['auth_tipo'] ?? 'password',
        'password'      => $srv['password'] ?? '',
        'clave_privada' => $srv['clave_privada'] ?? '',
        'created_at'    => time()
    ];

    file_put_contents($tokenFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    @chmod($tokenFile, 0666);

    echo json_encode([
        'success'       => true,
        'token'         => $token,
        'server_nombre' => $srv['nombre'],
        'usuario'       => $srv['usuario'],
        'host'          => $srv['host'],
        'puerto'        => (int)($srv['puerto'] ?? 22)
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno: ' . $e->getMessage()
    ]);
}
