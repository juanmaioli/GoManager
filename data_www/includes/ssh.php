<?php
// includes/ssh.php - Conector SSH para ejecución de comandos remotos

declare(strict_types=1);

/**
 * Ejecuta un comando en un servidor remoto vía SSH.
 *
 * @param array $servidor Datos del servidor (host, puerto, usuario, auth_tipo, password, clave_privada)
 * @param string $comando Comando a ejecutar (por defecto la ruta del binario gosvrinfo)
 * @param int $timeout Segundos de espera para la conexión
 * @return array ['success' => bool, 'output' => string, 'error' => string, 'data' => ?array]
 */
function ejecutarSSH(array $servidor, ?string $comando = null, int $timeout = 6): array
{
    $host = escapeshellarg($servidor['host']);
    $puerto = (int)($servidor['puerto'] ?? 22);
    $usuario = escapeshellarg($servidor['usuario']);
    $authTipo = $servidor['auth_tipo'] ?? 'password';
    $cmd = escapeshellcmd($comando ?? ($servidor['ruta_binario'] ?? 'gosvrinfo'));

    $keyFile = null;
    $sshCmd = '';

    // Opciones base de SSH seguras y sin prompts interactivos
    $sshOpts = "-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout={$timeout} -o LogLevel=ERROR -p {$puerto}";

    if ($authTipo === 'key') {
        if (empty(trim($servidor['clave_privada'] ?? ''))) {
            return ['success' => false, 'output' => '', 'error' => 'No se configuró la clave privada SSH.', 'data' => null];
        }

        $keyFile = tempnam('/tmp', 'ssh_k_');
        file_put_contents($keyFile, $servidor['clave_privada']);
        chmod($keyFile, 0600);

        $sshCmd = sprintf(
            'ssh -i %s -o BatchMode=yes %s %s@%s %s',
            escapeshellarg($keyFile),
            $sshOpts,
            $usuario,
            $host,
            escapeshellarg($cmd)
        );
    } else {
        $password = escapeshellarg($servidor['password'] ?? '');
        $sshCmd = sprintf(
            'sshpass -p %s ssh %s %s@%s %s',
            $password,
            $sshOpts,
            $usuario,
            $host,
            escapeshellarg($cmd)
        );
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = proc_open($sshCmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        if ($keyFile && file_exists($keyFile)) {
            @unlink($keyFile);
        }
        return ['success' => false, 'output' => '', 'error' => 'No se pudo iniciar el proceso SSH local.', 'data' => null];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($keyFile && file_exists($keyFile)) {
        @unlink($keyFile);
    }

    $stdout = trim($stdout);
    $stderr = trim($stderr);

    if ($exitCode !== 0) {
        $msgError = !empty($stderr) ? $stderr : "Fallo de conexión SSH (código de salida: {$exitCode})";
        return [
            'success' => false,
            'output' => $stdout,
            'error' => $msgError,
            'data' => null
        ];
    }

    // Intentar decodificar JSON devuelto por gosvrinfo
    $jsonData = json_decode($stdout, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'output' => $stdout,
            'error' => 'La respuesta remota no es un JSON válido: ' . substr($stdout, 0, 150),
            'data' => null
        ];
    }

    return [
        'success' => true,
        'output' => $stdout,
        'error' => '',
        'data' => $jsonData
    ];
}
