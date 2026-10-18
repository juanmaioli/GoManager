<?php
// servidor.php - Detalle completo de métricas de un servidor

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/ssh.php';

$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = 'info';

// Procesar actualización desde el modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $nombre = trim($_POST['nombre'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $puerto = (int)($_POST['puerto'] ?? 22);
    $usuario = trim($_POST['usuario'] ?? '');
    $authTipo = $_POST['auth_tipo'] ?? 'password';
    $password = $_POST['password'] ?? '';
    $clavePrivada = $_POST['clave_privada'] ?? '';
    $rutaBinario = trim($_POST['ruta_binario'] ?? 'gosvrinfo');
    if (empty($rutaBinario)) $rutaBinario = 'gosvrinfo';

    if (empty($nombre) || empty($host) || empty($usuario)) {
        $mensaje = 'Por favor complete Nombre, Host y Usuario.';
        $tipoMensaje = 'danger';
    } else {
        $query = "UPDATE servidores SET nombre = :nombre, host = :host, puerto = :puerto, usuario = :usuario, auth_tipo = :auth_tipo, ruta_binario = :ruta_binario";
        $params = [
            ':nombre' => $nombre,
            ':host' => $host,
            ':puerto' => $puerto,
            ':usuario' => $usuario,
            ':auth_tipo' => $authTipo,
            ':ruta_binario' => $rutaBinario,
            ':id' => $id
        ];

        if (!empty($password)) {
            $query .= ", password = :password";
            $params[':password'] = $password;
        }
        if (!empty($clavePrivada)) {
            $query .= ", clave_privada = :clave_privada";
            $params[':clave_privada'] = $clavePrivada;
        }

        $query .= " WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $mensaje = "Configuración de '{$nombre}' actualizada correctamente.";
        $tipoMensaje = 'success';
    }
}

// Sincronizar bajo demanda si se solicita
if (isset($_GET['sync'])) {
    $stmtS = $pdo->prepare("SELECT * FROM servidores WHERE id = :id");
    $stmtS->execute([':id' => $id]);
    $srvData = $stmtS->fetch();
    if ($srvData) {
        $res = ejecutarSSH($srvData, $srvData['ruta_binario'], 8);
        if ($res['success'] && is_array($res['data'])) {
            guardarMetricas($pdo, $id, $res['data'], $res['output']);
        }
    }
    header("Location: servidor.php?id={$id}");
    exit;
}

// Consultar Servidor
$stmt = $pdo->prepare("SELECT * FROM servidores WHERE id = :id");
$stmt->execute([':id' => $id]);
$servidor = $stmt->fetch();

if (!$servidor) {
    die("Servidor no encontrado.");
}

// Escanear dinámicamente los binarios disponibles en ./clientes
$clientesDir = __DIR__ . '/clientes';
$binariosDisponibles = [];
if (is_dir($clientesDir)) {
    $archivos = scandir($clientesDir);
    foreach ($archivos as $archivo) {
        if ($archivo !== '.' && $archivo !== '..' && !str_starts_with($archivo, '.') && is_file($clientesDir . '/' . $archivo)) {
            $binariosDisponibles[] = $archivo;
        }
    }
    sort($binariosDisponibles);
}

// Consultar última métrica
$stmtMetrica = $pdo->prepare("SELECT * FROM metricas WHERE servidor_id = :id ORDER BY id DESC LIMIT 1");
$stmtMetrica->execute([':id' => $id]);
$ultimaMetrica = $stmtMetrica->fetch();

$data = !empty($ultimaMetrica['raw_json']) ? json_decode($ultimaMetrica['raw_json'], true) : [];

// Historial reciente
$stmtHist = $pdo->prepare("SELECT id, cpu_usage, ram_usage, uptime, creado_en FROM metricas WHERE servidor_id = :id ORDER BY id DESC LIMIT 10");
$stmtHist->execute([':id' => $id]);
$historial = $stmtHist->fetchAll();

$cpuVal = $data['cpu_usage'] ?? '0%';
$ramVal = $data['ram_usage'] ?? '0%';
$cpuNum = (float)str_replace(['%', ' '], '', (string)$cpuVal);
$ramNum = (float)str_replace(['%', ' '], '', (string)$ramVal);

$soNombre = $data['operating_system']['name'] ?? '';
$iconoSO = iconoDistroSO($soNombre);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($servidor['nombre']) ?> - Detalle - GoManager SvrInfo</title>
    <link rel="icon" href='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🖥️</text></svg>'>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="./css/bootstrap.min.css" rel="stylesheet">
    <link href="./css/bootstrap-color-extension.css" rel="stylesheet">
    <link href="./css/all.css" rel="stylesheet">
    <link href="./css/styles.css" rel="stylesheet">
    <link href="./css/xterm.css" rel="stylesheet">
    <style>
        .progress { height: 22px; border-radius: 6px; }
        pre.json-viewer { max-height: 400px; padding: 1rem; border-radius: 6px; border: 1px solid var(--border-color); }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="container-fluid px-3 px-md-4 py-4 app-main">
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show mb-4" role="alert">
            <i class="fa-light <?= $tipoMensaje === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i><?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- Barra Superior -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm mb-2"><i class="fa-light fa-arrow-left me-1"></i>Volver al Dashboard</a>
            <h1 class="h2 fw-bold text-light mb-0">
                <i class="<?= $iconoSO ?> me-2"></i><?= e($servidor['nombre']) ?> 
                <span class="badge bg-<?= $servidor['ultimo_estado'] === 'online' ? 'success' : 'danger' ?> fs-6 ms-2">
                    <?= strtoupper(e($servidor['ultimo_estado'])) ?>
                </span>
            </h1>
            <p class="text-secondary mb-0">Host: <code><?= e($servidor['host']) ?>:<?= e((string)$servidor['puerto']) ?></code> | Usuario: <code><?= e($servidor['usuario']) ?></code> | Último chequeo: <strong class="text-light"><i class="fa-light fa-clock me-1"></i><?= formatearFechaLocal($servidor['ultimo_check']) ?></strong></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-dark border border-secondary text-light fw-bold px-3 py-2" onclick="abrirTerminalSSH(<?= $id ?>, '<?= e($servidor['nombre']) ?>')">
                <i class="fa-light fa-terminal me-1 text-info"></i>Ssh
            </button>
            <a href="servidor.php?id=<?= $id ?>&sync=1" class="btn btn-primary fw-bold px-3 py-2">
                <i class="fa-light fa-arrows-rotate me-1"></i>Recargar
            </a>
            <button type="button" class="btn btn-darkmagenta fw-bold px-3 py-2" onclick="abrirModalEditarServidor()">
                <i class="fa-light fa-pen-to-square me-1"></i>Configurar
            </button>
            <button type="button" class="btn btn-purple fw-bold px-3 py-2" id="btnInstalarCliente" onclick="abrirModalInstalarCliente()">
                <i class="fa-light fa-cloud-arrow-up me-1"></i>Instalar gosvrinfo
            </button>
        </div>
    </div>

    <!-- Resumen Principal CPU / RAM / UPTIME -->
    <div class="row g-4 mb-4">
        <!-- CPU -->
        <div class="col-md-4">
            <div class="card p-3 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold fs-5 text-light"><i class="fa-light fa-microchip me-2 text-primary"></i>Procesador (CPU)</span>
                    <span class="badge bg-<?= colorPorcentaje($cpuNum) ?> fs-6"><?= e((string)$cpuVal) ?></span>
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar bg-<?= colorPorcentaje($cpuNum) ?>" style="width: <?= min(100, max(5, $cpuNum)) ?>%"></div>
                </div>
                <div class="small text-secondary">
                    <div><strong>Modelo:</strong> <span class="text-light"><?= e($data['CPU'] ?? 'N/A') ?></span></div>
                    <div><strong>Núcleos:</strong> <span class="text-light"><?= e((string)($data['cores'] ?? 'N/A')) ?></span></div>
                    <div><strong>Frecuencia:</strong> <span class="text-light"><?= e($data['cpu_speed'] ?? 'N/A') ?></span></div>
                </div>
            </div>
        </div>

        <!-- RAM -->
        <div class="col-md-4">
            <div class="card p-3 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold fs-5 text-light"><i class="fa-light fa-memory me-2 text-primary"></i>Memoria RAM</span>
                    <span class="badge bg-<?= colorPorcentaje($ramNum) ?> fs-6"><?= e((string)$ramVal) ?></span>
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar bg-<?= colorPorcentaje($ramNum) ?>" style="width: <?= min(100, max(5, $ramNum)) ?>%"></div>
                </div>
                <div class="small text-secondary">
                    <div><strong>Capacidad Total:</strong> <span class="text-light"><?= e($data['RAM'] ?? 'N/A') ?></span></div>
                    <div><strong>Uso Actual:</strong> <span class="text-light"><?= e((string)$ramVal) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Sistema y Uptime -->
        <div class="col-md-4">
            <div class="card p-3 shadow-sm h-100">
                <div class="fw-bold fs-5 text-light mb-2"><i class="<?= $iconoSO ?> me-2"></i>Sistema Operativo</div>
                <div class="fs-4 fw-bold text-info mb-2">
                    <i class="fa-light fa-clock me-1"></i><?= formatearUptime($data['system_uptime'] ?? $ultimaMetrica['uptime'] ?? null) ?>
                </div>
                <div class="small text-secondary">
                    <div><strong>Distribución:</strong> <span class="text-light"><?= e($data['operating_system']['name'] ?? 'Linux') ?> <?= e($data['operating_system']['version'] ?? '') ?></span></div>
                    <div><strong>Kernel:</strong> <code><?= e($data['operating_system']['kernel_version'] ?? 'N/A') ?></code></div>
                    <div><strong>Hostname:</strong> <span class="text-light"><?= e($data['hostname'] ?? 'N/A') ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ubicación, Red y GPU -->
    <div class="row g-4 mb-4">
        <!-- Geolocalización e ISP -->
        <div class="col-md-6">
            <div class="card p-3 shadow-sm h-100">
                <h2 class="h5 fw-bold text-light mb-3"><i class="fa-light fa-globe me-2 text-primary"></i>Conectividad Pública</h2>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <th class="text-secondary" style="width: 35%;">IP Pública:</th>
                            <td class="text-light fw-bold font-monospace"><?= e($data['ip_public_address'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th class="text-secondary">Proveedor (ISP):</th>
                            <td class="text-light"><?= e($data['isp'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th class="text-secondary">Ubicación:</th>
                            <td class="text-light"><?= e($data['city'] ?? '') ?>, <?= e($data['country'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="text-secondary">Zona Horaria:</th>
                            <td class="text-light"><?= e($data['timezone'] ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- GPU si está presente -->
        <div class="col-md-6">
            <div class="card p-3 shadow-sm h-100">
                <h2 class="h5 fw-bold text-light mb-3"><i class="fa-light fa-gamepad me-2 text-primary"></i>Tarjeta Gráfica (GPU)</h2>
                <?php if (!empty($data['GPU']['name'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="text-secondary" style="width: 35%;">Modelo:</th>
                                <td class="text-light fw-bold"><?= e($data['GPU']['name']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Memoria VRAM:</th>
                                <td class="text-light"><?= e($data['GPU']['memory'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Driver:</th>
                                <td class="text-light font-monospace"><?= e($data['GPU']['driver_version'] ?? 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-secondary py-3 text-center">No se detectó GPU dedicada o acelerador gráfico.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Almacenamiento Discos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-transparent border-secondary py-3">
            <h2 class="h5 fw-bold text-light mb-0"><i class="fa-light fa-hard-drive me-2 text-primary"></i>Almacenamiento y Discos</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Dispositivo</th>
                            <th>Tamaño Total</th>
                            <th>Espacio Libre</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['disk_space']) && is_array($data['disk_space'])): ?>
                            <?php foreach ($data['disk_space'] as $disk): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-light"><i class="fa-light fa-database text-secondary me-1"></i><?= e($disk['name']) ?></td>
                                    <td><?= e($disk['size']) ?></td>
                                    <td class="text-success fw-bold"><?= e($disk['free']) ?></td>
                                    <td><span class="badge bg-success">Montado</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-secondary py-3">Sin información de discos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Interfaces de Red -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-transparent border-secondary py-3">
            <h2 class="h5 fw-bold text-light mb-0"><i class="fa-light fa-network-wired me-2 text-primary"></i>Interfaces de Red</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Interfaz</th>
                            <th>Dirección IP</th>
                            <th>Dirección MAC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['network_interfaces']) && is_array($data['network_interfaces'])): ?>
                            <?php foreach ($data['network_interfaces'] as $net): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-light"><i class="fa-light fa-globe text-secondary me-1"></i><?= e($net['name']) ?></td>
                                    <td class="font-monospace text-info"><?= !empty($net['ip_address']) ? e($net['ip_address']) : '<span class="text-secondary">-</span>' ?></td>
                                    <td class="font-monospace text-secondary"><?= e($net['mac_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-secondary py-3">Sin interfaces de red registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payload JSON en Bruto -->
    <div class="card shadow-sm">
        <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center py-3">
            <h2 class="h5 fw-bold text-light mb-0"><i class="fa-light fa-file-code me-2 text-primary"></i>JSON en Bruto de gosvrinfo</h2>
            <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseJson" aria-expanded="false">
                Ver / Ocultar JSON
            </button>
        </div>
        <div class="collapse" id="collapseJson">
            <div class="card-body p-3">
                <pre class="json-viewer mb-0 font-monospace"><?= e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            </div>
        </div>
    </div>
</main>

<!-- Modal Editar Servidor -->
<div class="modal fade" id="modalServidor" tabindex="-1" aria-labelledby="modalServidorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-light" id="modalServidorLabel"><i class="fa-light fa-pen-to-square me-2"></i>Editar Servidor: <?= e($servidor['nombre']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" id="formServidor">
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="srv_id" value="<?= $id ?>">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="srv_nombre" class="form-label fw-bold">Nombre del Equipo *</label>
                            <input type="text" class="form-control" id="srv_nombre" name="nombre" value="<?= e($servidor['nombre']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="srv_puerto" class="form-label fw-bold">Puerto SSH *</label>
                            <input type="number" class="form-control" id="srv_puerto" name="puerto" value="<?= e((string)$servidor['puerto']) ?>" required>
                        </div>

                        <div class="col-md-7">
                            <label for="srv_host" class="form-label fw-bold">Host o Dirección IP *</label>
                            <input type="text" class="form-control" id="srv_host" name="host" value="<?= e($servidor['host']) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="srv_usuario" class="form-label fw-bold">Usuario SSH *</label>
                            <input type="text" class="form-control" id="srv_usuario" name="usuario" value="<?= e($servidor['usuario']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="srv_auth_tipo" class="form-label fw-bold">Método de Autenticación *</label>
                            <select class="form-select" id="srv_auth_tipo" name="auth_tipo" onchange="toggleAuthFields()">
                                <option value="password" <?= $servidor['auth_tipo'] === 'password' ? 'selected' : '' ?>>Contraseña (password)</option>
                                <option value="key" <?= $servidor['auth_tipo'] === 'key' ? 'selected' : '' ?>>Clave Privada SSH (key)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="srv_ruta_binario" class="form-label fw-bold">Ruta del comando 'gosvrinfo'</label>
                            <input type="text" class="form-control" id="srv_ruta_binario" name="ruta_binario" value="<?= e($servidor['ruta_binario'] ?? 'gosvrinfo') ?>" placeholder="gosvrinfo o /usr/local/bin/gosvrinfo">
                        </div>

                        <!-- Campo Contraseña -->
                        <div class="col-12 <?= $servidor['auth_tipo'] === 'key' ? 'd-none' : '' ?>" id="divPassword">
                            <label for="srv_password" class="form-label fw-bold">Contraseña SSH</label>
                            <input type="password" class="form-control" id="srv_password" name="password" placeholder="Ingresá la nueva contraseña (o dejala vacía para conservar la actual)">
                            <div class="form-text text-secondary">Si no deseás cambiar la contraseña actual, dejala en blanco.</div>
                        </div>

                        <!-- Campo Clave Privada -->
                        <div class="col-12 <?= $servidor['auth_tipo'] === 'key' ? '' : 'd-none' ?>" id="divKey">
                            <label for="srv_clave_privada" class="form-label fw-bold">Clave Privada SSH (OpenSSH / RSA / Ed25519)</label>
                            <textarea class="form-control font-monospace" id="srv_clave_privada" name="clave_privada" rows="5" placeholder="-----BEGIN OPENSSH PRIVATE KEY----- ..."></textarea>
                            <div class="form-text text-secondary">Pegá el contenido de tu clave privada pem/id_rsa. Si ya existe y no la cambiás, dejala en blanco.</div>
                        </div>
                    </div>

                    <!-- Resultado de Test SSH -->
                    <div id="resultadoTest" class="mt-3 d-none alert alert-info mb-0" role="alert"></div>
                </div>

                <div class="modal-footer border-secondary justify-content-between flex-wrap gap-2">
                    <button type="button" class="btn btn-info text-dark fw-bold" id="btnTestConexion" onclick="probarConexionForm()">
                        <i class="fa-light fa-network-wired me-1"></i>Probar Conexión Ahora
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Elegir Binario y Copiar gosvrinfo vía SCP -->
<div class="modal fade" id="modalInstalarCliente" tabindex="-1" aria-labelledby="modalInstalarClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-light" id="modalInstalarClienteLabel">
                    <i class="fa-light fa-cloud-arrow-up text-info me-2"></i>Instalar gosvrinfo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary small mb-3">
                    Seleccioná la versión del ejecutable disponible en <code class="text-info">./clientes</code> para transferir por SCP a <code class="text-info">/tmp/gosvrinfo</code> en el servidor:
                </p>

                <div class="mb-3">
                    <label for="scp_binario" class="form-label fw-bold">Ejecutable de gosvrinfo</label>
                    <select class="form-select bg-dark text-light border-secondary" id="scp_binario">
                        <?php if (empty($binariosDisponibles)): ?>
                            <option value="" disabled selected>No se encontraron binarios en ./clientes</option>
                        <?php else: ?>
                            <?php foreach ($binariosDisponibles as $bin): ?>
                                <?php
                                $etiqueta = match ($bin) {
                                    'gosvrinfo' => 'gosvrinfo (Linux x86_64 / amd64)',
                                    'gosvrinfo-arm64' => 'gosvrinfo-arm64 (Linux aarch64 / ARM64)',
                                    'gosvrinfo-armv7l' => 'gosvrinfo-armv7l (Linux ARMv7 / 32-bit)',
                                    'gosvrinfo-armv6l' => 'gosvrinfo-armv6l (Linux ARMv6 / armhf)',
                                    default => $bin
                                };
                                ?>
                                <option value="<?= htmlspecialchars($bin) ?>" <?= $bin === 'gosvrinfo' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($etiqueta) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="alert alert-secondary py-2 small mb-3">
                    <i class="fa-light fa-server me-1 text-info"></i> Destino: <strong id="scp_destino_texto">-</strong>
                </div>

                <div id="resultadoScp" class="d-none alert mb-0" role="alert"></div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-light fa-xmark me-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-primary fw-bold" id="btnEjecutarScp" onclick="ejecutarTransferenciaScp()">
                    <i class="fa-light fa-paper-plane me-1"></i>Copiar con SCP
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Instrucción Post-Instalación -->
<div class="modal fade" id="modalInstruccionCliente" tabindex="-1" aria-labelledby="modalInstruccionClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-success" id="modalInstruccionClienteLabel">
                    <i class="fa-light fa-circle-check me-2"></i>Cliente Copiado a /tmp
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="fs-5 text-light mb-3">
                    Ejecute '<code>sudo install -m 755 /tmp/gosvrinfo /usr/local/bin/gosvrinfo && rm /tmp/gosvrinfo</code>'
                </p>

                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace bg-dark text-warning border-secondary" id="txtComandoInstalar" value="sudo install -m 755 /tmp/gosvrinfo /usr/local/bin/gosvrinfo && rm /tmp/gosvrinfo" readonly>
                    <button class="btn btn-primary fw-bold" type="button" onclick="copiarComandoInstalar()">
                        <i class="fa-light fa-copy me-1"></i>Copiar
                    </button>
                </div>

                <div id="msgCopiado" class="text-success small fw-bold d-none mb-2">
                    <i class="fa-light fa-check me-1"></i>Comando copiado al portapapeles
                </div>

                <div class="small text-secondary">
                    Pegá este comando en la terminal SSH del servidor para ubicar el ejecutable en <code class="text-info">/usr/local/bin</code> con permisos correspondientes.
                </div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-dark border border-secondary text-light" id="btnAbrirTerminalInstalado" onclick="abrirTerminalDesdeInstalacion()">
                    <i class="fa-light fa-terminal me-1 text-info"></i>Abrir Terminal SSH
                </button>
                <button type="button" class="btn btn-success fw-bold px-4" data-bs-dismiss="modal">
                    Entendido
                </button>
            </div>
        </div>
    </div>
</div>

<script src="./js/bootstrap.bundle.min.js"></script>
<script src="./js/app.js"></script>
<script src="./js/xterm.js"></script>
<script src="./js/xterm-addon-fit.js"></script>
<script src="./js/terminal.js"></script>
<script>
const modalEl = document.getElementById('modalServidor');
const bsModal = new bootstrap.Modal(modalEl);

const modalScpEl = document.getElementById('modalInstalarCliente');
const bsModalScp = new bootstrap.Modal(modalScpEl);

const modalInstruccionEl = document.getElementById('modalInstruccionCliente');
const bsModalInstruccion = new bootstrap.Modal(modalInstruccionEl);

function abrirModalEditarServidor() {
    toggleAuthFields();
    document.getElementById('resultadoTest').classList.add('d-none');
    bsModal.show();
}

function toggleAuthFields() {
    const tipo = document.getElementById('srv_auth_tipo').value;
    const divPassword = document.getElementById('divPassword');
    const divKey = document.getElementById('divKey');

    if (tipo === 'key') {
        divKey.classList.remove('d-none');
        divPassword.classList.add('d-none');
    } else {
        divPassword.classList.remove('d-none');
        divKey.classList.add('d-none');
    }
}

async function probarConexionForm() {
    const resDiv = document.getElementById('resultadoTest');
    const btn = document.getElementById('btnTestConexion');

    const formData = new FormData();
    formData.append('host', document.getElementById('srv_host').value);
    formData.append('puerto', document.getElementById('srv_puerto').value);
    formData.append('usuario', document.getElementById('srv_usuario').value);
    formData.append('auth_tipo', document.getElementById('srv_auth_tipo').value);
    formData.append('password', document.getElementById('srv_password').value);
    formData.append('clave_privada', document.getElementById('srv_clave_privada').value);
    formData.append('ruta_binario', document.getElementById('srv_ruta_binario').value);

    resDiv.className = 'mt-3 alert alert-info mb-0';
    resDiv.innerHTML = '<i class="fa-light fa-spinner fa-spin me-2"></i>Conectando vía SSH y ejecutando gosvrinfo...';
    resDiv.classList.remove('d-none');
    btn.disabled = true;

    try {
        const resp = await fetch('api/test_ssh.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.success) {
            resDiv.className = 'mt-3 alert alert-success mb-0';
            resDiv.innerHTML = `<strong><i class="fa-light fa-circle-check text-success me-1"></i>Éxito:</strong> ${data.message}<br><small>Host: ${data.hostname} | SO: ${data.os}</small>`;
        } else {
            resDiv.className = 'mt-3 alert alert-danger mb-0';
            resDiv.innerHTML = `<strong><i class="fa-light fa-circle-exclamation text-danger me-1"></i>Error SSH:</strong> ${data.error}`;
        }
    } catch (err) {
        resDiv.className = 'mt-3 alert alert-danger mb-0';
        resDiv.innerHTML = `<strong><i class="fa-light fa-circle-exclamation text-danger me-1"></i>Error de red:</strong> ${err.message}`;
    } finally {
        btn.disabled = false;
    }
}

function abrirModalInstalarCliente() {
    const host = document.getElementById('srv_host').value.trim();
    const usuario = document.getElementById('srv_usuario').value.trim();
    const puerto = document.getElementById('srv_puerto').value.trim() || '22';

    if (!host || !usuario) {
        alert('Por favor completá los campos "Host o Dirección IP" y "Usuario SSH" antes de instalar el cliente.');
        return;
    }

    document.getElementById('scp_destino_texto').textContent = `${usuario}@${host}:${puerto}`;
    const resScp = document.getElementById('resultadoScp');
    resScp.className = 'd-none alert mb-0';
    resScp.innerHTML = '';
    document.getElementById('btnEjecutarScp').disabled = false;

    if (typeof bsModal !== 'undefined' && bsModal) {
        bsModal.hide();
    }
    bsModalScp.show();
}

function volverModalServidor() {
    bsModalScp.hide();
    bsModal.show();
}

async function ejecutarTransferenciaScp() {
    const btn = document.getElementById('btnEjecutarScp');
    const resScp = document.getElementById('resultadoScp');
    const binario = document.getElementById('scp_binario').value;

    const formData = new FormData();
    formData.append('id', document.getElementById('srv_id').value);
    formData.append('host', document.getElementById('srv_host').value);
    formData.append('puerto', document.getElementById('srv_puerto').value);
    formData.append('usuario', document.getElementById('srv_usuario').value);
    formData.append('auth_tipo', document.getElementById('srv_auth_tipo').value);
    formData.append('password', document.getElementById('srv_password').value);
    formData.append('clave_privada', document.getElementById('srv_clave_privada').value);
    formData.append('binario', binario);

    btn.disabled = true;
    resScp.className = 'alert alert-info mb-0';
    resScp.innerHTML = '<i class="fa-light fa-spinner fa-spin me-2"></i>Copiando con SCP a /tmp/gosvrinfo...';
    resScp.classList.remove('d-none');

    try {
        const resp = await fetch('api/install_client.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.success) {
            bsModalScp.hide();
            bsModalInstruccion.show();
        } else {
            resScp.className = 'alert alert-danger mb-0';
            resScp.innerHTML = `<strong><i class="fa-light fa-circle-exclamation text-danger me-1"></i>Error en transferencia:</strong> ${data.error}`;
            btn.disabled = false;
        }
    } catch (err) {
        resScp.className = 'alert alert-danger mb-0';
        resScp.innerHTML = `<strong><i class="fa-light fa-circle-exclamation text-danger me-1"></i>Error de red:</strong> ${err.message}`;
        btn.disabled = false;
    }
}

function copiarComandoInstalar() {
    const cmd = document.getElementById('txtComandoInstalar').value;
    navigator.clipboard.writeText(cmd).then(() => {
        const msg = document.getElementById('msgCopiado');
        msg.classList.remove('d-none');
        setTimeout(() => msg.classList.add('d-none'), 3000);
    });
}

function abrirTerminalDesdeInstalacion() {
    const srvId = parseInt(document.getElementById('srv_id').value, 10);
    const srvNombre = document.getElementById('srv_nombre').value || 'Servidor';

    if (srvId > 0) {
        bsModalInstruccion.hide();
        abrirTerminalSSH(srvId, srvNombre);
    }
}

function sincronizarTodos() {
    window.location.href = 'index.php?sync_all=1';
}
</script>
</body>
</html>
