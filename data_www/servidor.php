<?php
// servidor.php - Detalle completo de métricas de un servidor

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/ssh.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
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
            <p class="text-secondary mb-0">Host: <code><?= e($servidor['host']) ?>:<?= e((string)$servidor['puerto']) ?></code> | Usuario: <code><?= e($servidor['usuario']) ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-dark border border-secondary text-light fw-bold px-3 py-2" onclick="abrirTerminalSSH(<?= $id ?>, '<?= e($servidor['nombre']) ?>')">
                <i class="fa-light fa-terminal me-1 text-info"></i>Terminal SSH
            </button>
            <a href="servidor.php?id=<?= $id ?>&sync=1" class="btn btn-primary fw-bold px-3 py-2">
                <i class="fa-light fa-arrows-rotate me-1"></i>Refrescar Métricas
            </a>
            <a href="config.php" class="btn btn-warning text-dark fw-bold px-3 py-2">
                <i class="fa-light fa-pen-to-square me-1"></i>Editar Configuración
            </a>
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
                    <i class="fa-light fa-clock me-1"></i><?= formatearUptime($data['system_uptime'] ?? $servidor['uptime']) ?>
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

<script src="./js/bootstrap.bundle.min.js"></script>
<script src="./js/app.js"></script>
<script src="./js/xterm.js"></script>
<script src="./js/xterm-addon-fit.js"></script>
<script src="./js/terminal.js"></script>
<script>
function sincronizarTodos() {
    window.location.href = 'index.php?sync_all=1';
}
</script>
</body>
</html>
