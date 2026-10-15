<?php
// index.php - Dashboard Principal de Control y Métricas

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/ssh.php';

// Si se pidió sincronizar todo vía GET
if (isset($_GET['sync_all'])) {
    $servidoresActivos = $pdo->query("SELECT * FROM servidores WHERE activo = 1")->fetchAll();
    foreach ($servidoresActivos as $srv) {
        $res = ejecutarSSH($srv, $srv['ruta_binario'], 6);
        if ($res['success'] && is_array($res['data'])) {
            guardarMetricas($pdo, (int)$srv['id'], $res['data'], $res['output']);
        } else {
            $stmtUpd = $pdo->prepare("UPDATE servidores SET ultimo_estado = 'error', ultimo_check = CURRENT_TIMESTAMP WHERE id = :id");
            $stmtUpd->execute([':id' => $srv['id']]);
        }
    }
    header('Location: index.php');
    exit;
}

// Obtener servidores con su última métrica asociada
$query = "
    SELECT s.*, 
           m.cpu_usage, m.ram_usage, m.uptime, m.raw_json, m.creado_en as fecha_metrica
    FROM servidores s
    LEFT JOIN metricas m ON m.id = (
        SELECT id FROM metricas WHERE servidor_id = s.id ORDER BY id DESC LIMIT 1
    )
    ORDER BY s.id DESC
";
$servidores = $pdo->query($query)->fetchAll();

$totalServidores = count($servidores);
$onlineServidores = 0;
$errorServidores = 0;

foreach ($servidores as $srv) {
    if ($srv['ultimo_estado'] === 'online') $onlineServidores++;
    elseif ($srv['ultimo_estado'] === 'error') $errorServidores++;
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GoManager SvrInfo</title>
    <link rel="icon" href='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚡</text></svg>'>
    <link href="./css/bootstrap.min.css" rel="stylesheet">
    <link href="./css/bootstrap-color-extension.css" rel="stylesheet">
    <link href="./css/all.css" rel="stylesheet">
    <style>
        body { background-color: #0d1117; color: #c9d1d9; font-size: 1.05rem; }
        .card { background-color: #161b22; border-color: #30363d; transition: transform 0.15s ease-in-out; }
        .card-server:hover { border-color: #58a6ff; }
        .progress { background-color: #21262d; height: 18px; border-radius: 6px; }
        .metric-label { font-size: 0.95rem; font-weight: 600; }
        .metric-val { font-size: 0.95rem; font-weight: 700; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="container-fluid px-3 px-md-4 py-4 app-main">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h2 fw-bold text-light mb-1"><i class="fa-light fa-chart-line text-primary me-2"></i>Panel Principal</h1>
            <p class="text-secondary mb-0">Estado general y métricas de los servidores monitoreados.</p>
        </div>
    </div>

    <!-- Métricas Globales -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 shadow-sm border-secondary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small fw-bold text-uppercase">Total Equipos</div>
                        <div class="fs-2 fw-bold text-light"><?= $totalServidores ?></div>
                    </div>
                    <div class="fs-1 text-secondary"><i class="fa-light fa-server"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 shadow-sm border-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-success small fw-bold text-uppercase">Online</div>
                        <div class="fs-2 fw-bold text-success"><?= $onlineServidores ?></div>
                    </div>
                    <div class="fs-1 text-success"><i class="fa-light fa-circle-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 shadow-sm border-danger">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-danger small fw-bold text-uppercase">Con Alertas / Offline</div>
                        <div class="fs-2 fw-bold text-danger"><?= $errorServidores ?></div>
                    </div>
                    <div class="fs-1 text-danger"><i class="fa-light fa-circle-exclamation"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Encabezado de Sección -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="h3 fw-bold text-light mb-0">Servidores Monitoreados</h2>
        <div>
            <a href="config.php" class="btn btn-outline-primary fw-bold btn-sm px-3 py-2">
                <i class="fa-light fa-plus me-1"></i>Agregar Equipo
            </a>
        </div>
    </div>

    <!-- Grilla de Tarjetas -->
    <?php if (empty($servidores)): ?>
        <div class="card p-5 text-center shadow-sm">
            <div class="fs-1 mb-2 text-secondary"><i class="fa-light fa-tower-broadcast"></i></div>
            <h3 class="h4 text-light fw-bold">Aún no hay equipos configurados</h3>
            <p class="text-secondary mb-4">Empezá agregando un servidor con SSH para consultar <code>gosvrinfo</code>.</p>
            <div>
                <a href="config.php" class="btn btn-primary fw-bold px-4 py-2"><i class="fa-light fa-plus me-1"></i>Configurar Primer Servidor</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4" id="gridServidores">
            <?php foreach ($servidores as $srv): 
                $data = !empty($srv['raw_json']) ? json_decode($srv['raw_json'], true) : null;
                $cpuVal = $data['cpu_usage'] ?? ($srv['cpu_usage'] ?? '0%');
                $ramVal = $data['ram_usage'] ?? ($srv['ram_usage'] ?? '0%');
                $cpuNum = (float)str_replace(['%', ' '], '', (string)$cpuVal);
                $ramNum = (float)str_replace(['%', ' '], '', (string)$ramVal);

                // Disco principal
                $discoInfo = 'N/A';
                $discoPct = 0;
                if (!empty($data['disk_space']) && is_array($data['disk_space'])) {
                    $d = $data['disk_space'][0];
                    $discoInfo = ($d['free'] ?? '') . ' libre de ' . ($d['size'] ?? '');
                }

                $estado = $srv['ultimo_estado'];
                $badgeClase = $estado === 'online' ? 'bg-success' : ($estado === 'error' ? 'bg-danger' : 'bg-secondary');
            ?>
                <div class="col-12 col-md-6 col-xl-4" id="card-srv-<?= $srv['id'] ?>">
                    <div class="card h-100 shadow-sm card-server">
                        <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h3 class="h5 fw-bold mb-0 text-light">
                                    <a href="servidor.php?id=<?= $srv['id'] ?>" class="text-decoration-none text-light">
                                        <?= e($srv['nombre']) ?>
                                    </a>
                                </h3>
                                <small class="text-secondary font-monospace"><?= e($srv['host']) ?>:<?= e((string)$srv['puerto']) ?></small>
                            </div>
                            <span class="badge <?= $badgeClase ?> fs-6 status-badge">
                                <?= strtoupper(e($estado)) ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <!-- SO y Uptime -->
                            <div class="mb-3 d-flex justify-content-between text-secondary small border-bottom border-secondary pb-2">
                                <div>
                                    <i class="fa-brands fa-linux me-1"></i><strong>SO:</strong> 
                                    <?= e($data['operating_system']['name'] ?? 'Linux') ?> 
                                    <?= e($data['operating_system']['version'] ?? '') ?>
                                </div>
                                <div>
                                    <i class="fa-light fa-clock me-1"></i><strong>Uptime:</strong> 
                                    <span class="text-light fw-bold uptime-val"><?= formatearUptime($data['system_uptime'] ?? $srv['uptime']) ?></span>
                                </div>
                            </div>

                            <!-- Barra de CPU -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between metric-label mb-1">
                                    <span><i class="fa-light fa-microchip me-1"></i>CPU: <small class="text-secondary fw-normal"><?= e($data['CPU'] ?? 'Procesador') ?></small></span>
                                    <span class="metric-val text-<?= colorPorcentaje($cpuNum) ?> cpu-text"><?= e((string)$cpuVal) ?></span>
                                </div>
                                <div class="progress" role="progressbar" aria-valuenow="<?= $cpuNum ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-<?= colorPorcentaje($cpuNum) ?> cpu-bar" style="width: <?= min(100, max(5, $cpuNum)) ?>%"></div>
                                </div>
                            </div>

                            <!-- Barra de RAM -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between metric-label mb-1">
                                    <span><i class="fa-light fa-memory me-1"></i>RAM: <small class="text-secondary fw-normal"><?= e($data['RAM'] ?? '') ?></small></span>
                                    <span class="metric-val text-<?= colorPorcentaje($ramNum) ?> ram-text"><?= e((string)$ramVal) ?></span>
                                </div>
                                <div class="progress" role="progressbar" aria-valuenow="<?= $ramNum ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-<?= colorPorcentaje($ramNum) ?> ram-bar" style="width: <?= min(100, max(5, $ramNum)) ?>%"></div>
                                </div>
                            </div>

                            <!-- Almacenamiento -->
                            <div class="mb-2 text-secondary small">
                                <i class="fa-light fa-hard-drive me-1"></i><strong>Almacenamiento:</strong> <span class="text-light"><?= e($discoInfo) ?></span>
                            </div>

                            <!-- IP Pública e ISP -->
                            <?php if (!empty($data['ip_public_address'])): ?>
                                <div class="text-secondary small">
                                    <i class="fa-light fa-globe me-1"></i><strong>IP Pública:</strong> <?= e($data['ip_public_address']) ?> 
                                    <?php if (!empty($data['city'])): ?>(<?= e($data['city']) ?>, <?= e($data['country'] ?? '') ?>)<?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer bg-transparent border-secondary d-flex justify-content-between align-items-center py-2">
                            <small class="text-secondary check-time">
                                <i class="fa-light fa-clock me-1"></i><?= !empty($srv['ultimo_check']) ? date('H:i:s d/m', strtotime($srv['ultimo_check'])) : 'Sin datos' ?>
                            </small>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-info fw-bold" onclick="refrescarServidor(<?= $srv['id'] ?>, this)">
                                    <i class="fa-light fa-arrows-rotate me-1"></i>Actualizar
                                </button>
                                <a href="servidor.php?id=<?= $srv['id'] ?>" class="btn btn-sm btn-primary fw-bold">
                                    Detalle <i class="fa-light fa-magnifying-glass ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script src="./js/bootstrap.bundle.min.js"></script>
<script>
async function refrescarServidor(id, btn) {
    const card = document.getElementById(`card-srv-${id}`);
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-light fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const resp = await fetch(`api/sync.php?id=${id}`);
        const res = await resp.json();

        if (res.success && res.data) {
            // Recargar la página para refrescar todas las métricas completas o actualizar en caliente
            location.reload();
        } else {
            alert('Error al refrescar servidor: ' + (res.error || 'Desconocido'));
            location.reload();
        }
    } catch (e) {
        alert('Error de conexión: ' + e.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function sincronizarTodos() {
    window.location.href = 'index.php?sync_all=1';
}
</script>
</body>
</html>