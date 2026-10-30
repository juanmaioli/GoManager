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
            registrarFalloServidor($pdo, (int)$srv['id'], $res['error'] ?? 'Error de conexión');
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
    ORDER BY s.nombre COLLATE NOCASE ASC
";
$servidores = $pdo->query($query)->fetchAll();

$totalServidores = count($servidores);
$onlineServidores = 0;
$errorServidores = 0;

foreach ($servidores as $srv) {
    if ($srv['ultimo_estado'] === 'online') {
        $onlineServidores++;
    } else {
        $errorServidores++;
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GoManager SvrInfo</title>
    <link rel="icon" href='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚡</text></svg>'>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            const savedView = localStorage.getItem('gomanager_vista') || 'tarjetas';
            document.documentElement.setAttribute('data-vista-equipos', savedView);
        })();
    </script>
    <link href="./css/bootstrap.min.css" rel="stylesheet">
    <link href="./css/bootstrap-color-extension.css" rel="stylesheet">
    <link href="./css/all.css" rel="stylesheet">
    <link href="./css/styles.css" rel="stylesheet">
    <link href="./css/xterm.css" rel="stylesheet">
    <style>
        .progress { height: 18px; border-radius: 6px; }
        .metric-label { font-size: 0.95rem; font-weight: 600; }
        .metric-val { font-size: 0.95rem; font-weight: 700; }

        .metric-filter-card {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            user-select: none;
        }
        .metric-filter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3) !important;
        }
        .metric-filter-card:focus-visible {
            outline: 2px solid var(--focus-ring-color, #38bdf8);
            outline-offset: 2px;
        }
        .metric-filter-card.active-filter {
            transform: translateY(-2px);
        }
        .metric-filter-card[data-filtro="todos"].active-filter {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 2px #38bdf8, 0 8px 20px rgba(56, 189, 248, 0.25) !important;
        }
        .metric-filter-card[data-filtro="online"].active-filter {
            border-color: #198754 !important;
            box-shadow: 0 0 0 2px #198754, 0 8px 20px rgba(25, 135, 84, 0.25) !important;
        }
        .metric-filter-card[data-filtro="alertas"].active-filter {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px #dc3545, 0 8px 20px rgba(220, 53, 69, 0.25) !important;
        }

        /* Estilos de Vista Tabla */
        .table-servidores th {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #94a3b8;
            background-color: rgba(18, 20, 28, 0.65);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .sortable-th {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .sortable-th:hover {
            background-color: rgba(56, 189, 248, 0.12) !important;
            color: #38bdf8 !important;
        }
        .sortable-th:focus-visible {
            outline: 2px solid var(--focus-ring-color, #38bdf8);
            outline-offset: -2px;
        }
        .sortable-th.active-sort {
            color: #38bdf8 !important;
        }
        .table-servidores td {
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }
        .table-servidores tbody tr {
            transition: background-color 0.15s ease;
        }
        .table-servidores tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04);
        }

        /* Ocultamiento instantáneo según vista seleccionada para evitar parpadeos */
        html[data-vista-equipos="tabla"] #gridServidores { display: none !important; }
        html[data-vista-equipos="tabla"] #tablaServidoresContainer { display: block !important; }
        html[data-vista-equipos="tabla"] #btnVistaTabla { background-color: #38bdf8; color: #0f172a !important; border-color: #38bdf8 !important; }
        html[data-vista-equipos="tabla"] #btnVistaTarjetas { background-color: transparent; color: #94a3b8 !important; }

        html[data-vista-equipos="tarjetas"] #gridServidores { display: flex !important; }
        html[data-vista-equipos="tarjetas"] #tablaServidoresContainer { display: none !important; }
        html[data-vista-equipos="tarjetas"] #btnVistaTarjetas { background-color: #38bdf8; color: #0f172a !important; border-color: #38bdf8 !important; }
        html[data-vista-equipos="tarjetas"] #btnVistaTabla { background-color: transparent; color: #94a3b8 !important; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="container-fluid px-3 px-md-4 py-4 app-main">

    <!-- Métricas Globales (Filtros Interactivos) -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 shadow-sm border-secondary metric-filter-card active-filter" role="button" tabindex="0" data-filtro="todos" aria-pressed="true" title="Mostrar todos los equipos">
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
            <div class="card p-3 shadow-sm border-success metric-filter-card" role="button" tabindex="0" data-filtro="online" aria-pressed="false" title="Filtrar solo equipos online">
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
            <div class="card p-3 shadow-sm border-danger metric-filter-card" role="button" tabindex="0" data-filtro="alertas" aria-pressed="false" title="Filtrar equipos con alertas u offline">
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
        <div class="d-flex align-items-center gap-2">
            <h2 class="h3 fw-bold text-light mb-0">Servidores Monitoreados</h2>
            <span id="filtroActualBadge" class="badge bg-secondary d-none"></span>
            <button id="btnLimpiarFiltro" class="btn btn-sm btn-outline-secondary d-none py-0 px-2" onclick="limpiarTodosFiltros()" title="Quitar filtros">
                <i class="fa-light fa-xmark me-1"></i>Ver todos
            </button>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Campo para filtrar por nombre de equipo -->
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-dark border-secondary text-secondary">
                    <i class="fa-light fa-magnifying-glass"></i>
                </span>
                <input type="text" id="filtroNombreInput" class="form-control bg-dark border-secondary text-light" placeholder="Filtrar por nombre..." aria-label="Filtrar por nombre de equipo" autocomplete="off">
                <button class="btn btn-outline-secondary d-none" type="button" id="btnLimpiarTexto" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                    <i class="fa-light fa-xmark"></i>
                </button>
            </div>

            <!-- Selector de Vista: Tarjetas / Tabla -->
            <div class="btn-group shadow-sm" role="group" aria-label="Selector de visualización">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold px-3 py-2" id="btnVistaTarjetas" onclick="cambiarVista('tarjetas')" title="Ver en tarjetas" aria-pressed="true">
                    <i class="fa-light fa-grid-2 me-1"></i>Tarjetas
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold px-3 py-2" id="btnVistaTabla" onclick="cambiarVista('tabla')" title="Ver en tabla" aria-pressed="false">
                    <i class="fa-light fa-table-list me-1"></i>Tabla
                </button>
            </div>
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


                $estado = $srv['ultimo_estado'];
                $badgeClase = $estado === 'online' ? 'bg-success' : ($estado === 'error' ? 'bg-danger' : 'bg-secondary');
                $soNombre = $data['operating_system']['name'] ?? '';
                $iconoSO = iconoDistroSO($soNombre);
            ?>
                <div class="col-12 col-md-6 col-lg-4 col-xl-3 server-card-item" id="card-srv-<?= $srv['id'] ?>" 
                     data-estado="<?= e($estado) ?>"
                     data-nombre="<?= e(mb_strtolower($srv['nombre'])) ?>"
                     data-host="<?= e(mb_strtolower($srv['host'])) ?>">
                    <div class="card h-100 shadow-sm card-server">
                        <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h3 class="h5 fw-bold mb-0 text-light">
                                    <a href="servidor.php?id=<?= $srv['id'] ?>" class="text-decoration-none text-light">
                                        <i class="<?= $iconoSO ?> me-2"></i><?= e($srv['nombre']) ?>
                                    </a>
                                </h3>
                                <div>
                                    <small class="text-secondary font-monospace"><?= e($srv['host']) ?>:<?= e((string)$srv['puerto']) ?></small>
                                </div>
                                <?php if (!empty($data['ip_public_address'])): ?>
                                    <div class="text-secondary small mt-1">
                                        <i class="fa-light fa-globe me-1"></i><span class="font-monospace text-light"><?= e($data['ip_public_address']) ?></span>
                                        <?php if (!empty($data['city'])): ?><span class="text-secondary ms-1">(<?= e($data['city']) ?>, <?= e($data['country'] ?? '') ?>)</span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?= $badgeClase ?> fs-6 status-badge">
                                <?= strtoupper(e($estado)) ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <!-- Barra de CPU -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between metric-label mb-1">
                                    <span><i class="fa-light fa-microchip me-1"></i>CPU</span>
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
                        </div>

                        <div class="card-footer bg-transparent border-secondary d-flex justify-content-between align-items-center py-2">
                            <small class="text-secondary check-time">
                                <i class="fa-light fa-clock me-1"></i><?= formatearFechaLocal($srv['ultimo_check']) ?>
                            </small>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-dark border border-secondary text-light fw-bold" onclick="abrirTerminalSSH(<?= $srv['id'] ?>, '<?= e($srv['nombre']) ?>')" title="Terminal SSH" aria-label="Terminal SSH">
                                    <i class="fa-light fa-terminal"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-info text-dark fw-bold" onclick="refrescarServidor(<?= $srv['id'] ?>, this)" title="Actualizar métricas" aria-label="Actualizar métricas">
                                    <i class="fa-light fa-arrows-rotate"></i>
                                </button>
                                <a href="servidor.php?id=<?= $srv['id'] ?>" class="btn btn-sm btn-primary fw-bold" title="Ver detalle del servidor" aria-label="Ver detalle del servidor">
                                    <i class="fa-light fa-magnifying-glass"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Vista en Tabla de Equipos -->
        <div class="card shadow-sm border-secondary mb-4" id="tablaServidoresContainer">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-servidores">
                    <thead>
                        <tr>
                            <th scope="col" class="py-3 px-3 text-center sortable-th" data-col="estado" tabindex="0" role="button" aria-sort="none" title="Ordenar por Estado" style="width: 120px;">
                                Estado <i class="fa-light fa-sort ms-1 sort-icon text-secondary"></i>
                            </th>
                            <th scope="col" class="py-3 px-3 sortable-th" data-col="nombre" tabindex="0" role="button" aria-sort="none" title="Ordenar por Nombre de Equipo">
                                Equipo <i class="fa-light fa-sort ms-1 sort-icon text-secondary"></i>
                            </th>
                            <th scope="col" class="py-3 px-3 d-none d-md-table-cell sortable-th" data-col="so" tabindex="0" role="button" aria-sort="none" title="Ordenar por Sistema Operativo">
                                SO / Distro <i class="fa-light fa-sort ms-1 sort-icon text-secondary"></i>
                            </th>
                            <th scope="col" class="py-3 px-3 sortable-th" data-col="cpu" tabindex="0" role="button" aria-sort="none" title="Ordenar por Uso de CPU" style="min-width: 150px;">
                                CPU <i class="fa-light fa-sort ms-1 sort-icon text-secondary"></i>
                            </th>
                            <th scope="col" class="py-3 px-3 sortable-th" data-col="ram" tabindex="0" role="button" aria-sort="none" title="Ordenar por Uso de RAM" style="min-width: 160px;">
                                RAM <i class="fa-light fa-sort ms-1 sort-icon text-secondary"></i>
                            </th>
                            <th scope="col" class="py-3 px-3 d-none d-lg-table-cell sortable-th" data-col="check" tabindex="0" role="button" aria-sort="none" title="Ordenar por Último Check">
                                Último Check <i class="fa-light fa-sort ms-1 sort-icon text-secondary"></i>
                            </th>
                            <th scope="col" class="py-3 px-3 text-end" style="min-width: 140px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servidores as $srv): 
                            $data = !empty($srv['raw_json']) ? json_decode($srv['raw_json'], true) : null;
                            $cpuVal = $data['cpu_usage'] ?? ($srv['cpu_usage'] ?? '0%');
                            $ramVal = $data['ram_usage'] ?? ($srv['ram_usage'] ?? '0%');
                            $cpuNum = (float)str_replace(['%', ' '], '', (string)$cpuVal);
                            $ramNum = (float)str_replace(['%', ' '], '', (string)$ramVal);

                            $estado = $srv['ultimo_estado'];
                            $badgeClase = $estado === 'online' ? 'bg-success' : ($estado === 'error' ? 'bg-danger' : 'bg-secondary');
                            $iconoEstado = $estado === 'online' ? 'fa-circle-check' : ($estado === 'error' ? 'fa-circle-exclamation' : 'fa-circle-question');
                            $soNombre = $data['operating_system']['name'] ?? '';
                            $iconoSO = iconoDistroSO($soNombre);
                            $checkTimestamp = !empty($srv['ultimo_check']) ? strtotime($srv['ultimo_check']) : 0;
                        ?>
                            <tr class="server-row-item" id="row-srv-<?= $srv['id'] ?>" 
                                data-estado="<?= e($estado) ?>" 
                                data-nombre="<?= e(mb_strtolower($srv['nombre'])) ?>" 
                                data-host="<?= e(mb_strtolower($srv['host'])) ?>" 
                                data-so="<?= e(mb_strtolower($soNombre ?: 'linux')) ?>" 
                                data-cpu="<?= $cpuNum ?>" 
                                data-ram="<?= $ramNum ?>" 
                                data-check="<?= $checkTimestamp ?>">
                                <!-- Estado -->
                                <td class="text-center px-3">
                                    <span class="badge <?= $badgeClase ?> px-2 py-1 fw-bold fs-7">
                                        <i class="fa-light <?= $iconoEstado ?> me-1"></i><?= strtoupper(e($estado)) ?>
                                    </span>
                                </td>

                                <!-- Equipo -->
                                <td class="px-3">
                                    <div class="d-flex align-items-center">
                                        <div class="fs-4 me-3 text-secondary text-center" style="width: 28px;" title="<?= e($soNombre ?: 'Linux') ?>">
                                            <i class="<?= $iconoSO ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold fs-6">
                                                <a href="servidor.php?id=<?= $srv['id'] ?>" class="text-decoration-none text-light">
                                                    <?= e($srv['nombre']) ?>
                                                </a>
                                            </div>
                                            <div class="small text-secondary font-monospace">
                                                <?= e($srv['host']) ?>:<?= e((string)$srv['puerto']) ?>
                                                <?php if (!empty($data['ip_public_address'])): ?>
                                                    <span class="ms-1 text-light-emphasis">• IP: <?= e($data['ip_public_address']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- SO / Distro & Uptime -->
                                <td class="px-3 d-none d-md-table-cell">
                                    <div class="fw-semibold text-light small">
                                        <?= e($soNombre ?: 'Linux / Desconocido') ?>
                                        <?php if (!empty($data['operating_system']['version'])): ?>
                                            <span class="text-secondary fw-normal">(<?= e($data['operating_system']['version']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-secondary">
                                        <i class="fa-light fa-clock-rotate-left me-1"></i>Uptime: <span class="text-light-emphasis fw-bold"><?= formatearUptime($srv['uptime'] ?? ($data['system_uptime'] ?? null)) ?></span>
                                    </div>
                                </td>

                                <!-- CPU -->
                                <td class="px-3">
                                    <div class="d-flex justify-content-between align-items-center small fw-bold mb-1">
                                        <span class="text-secondary"><i class="fa-light fa-microchip me-1"></i>CPU:</span>
                                        <span class="text-<?= colorPorcentaje($cpuNum) ?>"><?= e((string)$cpuVal) ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="<?= $cpuNum ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-<?= colorPorcentaje($cpuNum) ?>" style="width: <?= min(100, max(5, $cpuNum)) ?>%"></div>
                                    </div>
                                </td>

                                <!-- RAM -->
                                <td class="px-3">
                                    <div class="d-flex justify-content-between align-items-center small fw-bold mb-1">
                                        <span class="text-secondary"><i class="fa-light fa-memory me-1"></i>RAM:</span>
                                        <div>
                                            <span class="text-<?= colorPorcentaje($ramNum) ?>"><?= e((string)$ramVal) ?></span>
                                            <?php if (!empty($data['RAM'])): ?>
                                                <small class="text-secondary fw-normal ms-1">(<?= e($data['RAM']) ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="<?= $ramNum ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-<?= colorPorcentaje($ramNum) ?>" style="width: <?= min(100, max(5, $ramNum)) ?>%"></div>
                                    </div>
                                </td>

                                <!-- Último Check -->
                                <td class="px-3 d-none d-lg-table-cell">
                                    <div class="small text-secondary font-monospace">
                                        <i class="fa-light fa-clock me-1"></i><?= formatearFechaLocal($srv['ultimo_check']) ?>
                                    </div>
                                </td>

                                <!-- Acciones -->
                                <td class="px-3 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-dark border border-secondary text-light fw-bold" onclick="abrirTerminalSSH(<?= $srv['id'] ?>, '<?= e($srv['nombre']) ?>')" title="Terminal SSH" aria-label="Terminal SSH">
                                            <i class="fa-light fa-terminal"></i>
                                        </button>
                                        <button type="button" class="btn btn-info text-dark fw-bold" onclick="refrescarServidor(<?= $srv['id'] ?>, this)" title="Actualizar métricas" aria-label="Actualizar métricas">
                                            <i class="fa-light fa-arrows-rotate"></i>
                                        </button>
                                        <a href="servidor.php?id=<?= $srv['id'] ?>" class="btn btn-primary fw-bold" title="Ver detalle del servidor" aria-label="Ver detalle del servidor">
                                            <i class="fa-light fa-magnifying-glass"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mensaje de Sin Resultados por Filtro -->
        <div id="noResultadosFiltro" class="text-center py-5 d-none">
            <div class="card p-4 shadow-sm border-secondary mx-auto" style="max-width: 500px;">
                <div class="fs-1 mb-2 text-secondary"><i class="fa-light fa-filter-circle-xmark"></i></div>
                <h4 class="h5 text-light fw-bold">No hay servidores en esta categoría</h4>
                <p class="text-secondary mb-3">No se encontraron equipos que coincidan con el filtro seleccionado.</p>
                <div>
                    <button type="button" class="btn btn-outline-info btn-sm fw-bold px-3 py-2" onclick="limpiarTodosFiltros()">
                        <i class="fa-light fa-arrows-rotate me-1"></i>Mostrar todos los servidores
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script src="./js/bootstrap.bundle.min.js"></script>
<script src="./js/app.js"></script>
<script src="./js/xterm.js"></script>
<script src="./js/xterm-addon-fit.js"></script>
<script src="./js/terminal.js"></script>
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

// Control de Vista: Tarjetas o Tabla
function cambiarVista(vista) {
    document.documentElement.setAttribute('data-vista-equipos', vista);
    localStorage.setItem('gomanager_vista', vista);

    const btnTarjetas = document.getElementById('btnVistaTarjetas');
    const btnTabla = document.getElementById('btnVistaTabla');

    if (btnTarjetas && btnTabla) {
        if (vista === 'tabla') {
            btnTabla.setAttribute('aria-pressed', 'true');
            btnTarjetas.setAttribute('aria-pressed', 'false');
        } else {
            btnTarjetas.setAttribute('aria-pressed', 'true');
            btnTabla.setAttribute('aria-pressed', 'false');
        }
    }

    // Re-evaluar visibilidad si hay un filtro aplicado
    aplicarFiltro(filtroActivo);
}

// Filtrado interactivo por tarjetas de métricas y por nombre de equipo
let filtroActivo = 'todos';

function aplicarFiltro(tipo = filtroActivo) {
    filtroActivo = tipo;

    const inputTexto = document.getElementById('filtroNombreInput');
    const btnLimpiarTexto = document.getElementById('btnLimpiarTexto');
    const texto = (inputTexto?.value || '').trim().toLowerCase();

    if (btnLimpiarTexto) {
        btnLimpiarTexto.classList.toggle('d-none', texto.length === 0);
    }

    const cards = document.querySelectorAll('.metric-filter-card');
    cards.forEach(card => {
        const esActivo = card.dataset.filtro === tipo;
        card.classList.toggle('active-filter', esActivo);
        card.setAttribute('aria-pressed', esActivo ? 'true' : 'false');
    });

    const cardItems = document.querySelectorAll('.server-card-item');
    const rowItems = document.querySelectorAll('.server-row-item');
    let visibles = 0;

    const cumpleFiltro = (item) => {
        const estado = item.dataset.estado;
        const nombre = item.dataset.nombre || '';
        const host = item.dataset.host || '';

        let matchEstado = true;
        if (tipo === 'online') matchEstado = (estado === 'online');
        else if (tipo === 'alertas') matchEstado = (estado !== 'online');

        let matchTexto = true;
        if (texto.length > 0) {
            matchTexto = nombre.includes(texto) || host.includes(texto);
        }

        const mostrar = matchEstado && matchTexto;
        item.classList.toggle('d-none', !mostrar);
        return mostrar;
    };

    cardItems.forEach(item => {
        if (cumpleFiltro(item)) visibles++;
    });

    rowItems.forEach(item => {
        cumpleFiltro(item);
    });

    const noResultados = document.getElementById('noResultadosFiltro');
    const grid = document.getElementById('gridServidores');
    const tabla = document.getElementById('tablaServidoresContainer');

    if (noResultados) {
        if (visibles === 0 && cardItems.length > 0) {
            noResultados.classList.remove('d-none');
            if (grid) grid.style.setProperty('display', 'none', 'important');
            if (tabla) tabla.style.setProperty('display', 'none', 'important');
        } else {
            noResultados.classList.add('d-none');
            if (grid) grid.style.removeProperty('display');
            if (tabla) tabla.style.removeProperty('display');
        }
    }

    const badge = document.getElementById('filtroActualBadge');
    const btnLimpiar = document.getElementById('btnLimpiarFiltro');
    if (badge && btnLimpiar) {
        const hayFiltroEstado = (tipo !== 'todos');
        const hayFiltroTexto = (texto.length > 0);

        if (!hayFiltroEstado && !hayFiltroTexto) {
            badge.classList.add('d-none');
            btnLimpiar.classList.add('d-none');
        } else {
            badge.classList.remove('d-none');
            btnLimpiar.classList.remove('d-none');

            let etiqueta = '';
            if (hayFiltroEstado && hayFiltroTexto) {
                const nombreEstado = (tipo === 'online') ? 'Online' : 'Con Alertas';
                badge.className = (tipo === 'online') ? 'badge bg-success' : 'badge bg-danger';
                etiqueta = `${nombreEstado} + "${texto}" (${visibles})`;
            } else if (hayFiltroEstado) {
                badge.className = (tipo === 'online') ? 'badge bg-success' : 'badge bg-danger';
                etiqueta = `${tipo === 'online' ? 'Online' : 'Con Alertas'} (${visibles})`;
            } else {
                badge.className = 'badge bg-info text-dark';
                etiqueta = `"${texto}" (${visibles})`;
            }
            badge.textContent = `Mostrando: ${etiqueta}`;
        }
    }
}

function limpiarTodosFiltros() {
    const inputTexto = document.getElementById('filtroNombreInput');
    if (inputTexto) inputTexto.value = '';
    aplicarFiltro('todos');
    inputTexto?.focus();
}

// Ordenamiento interactivo de la tabla de equipos
let ordenColumna = localStorage.getItem('gomanager_orden_col') || 'nombre';
let ordenDireccion = localStorage.getItem('gomanager_orden_dir') || 'asc';

function ordenarTabla(columna, invertir = true) {
    if (invertir) {
        if (ordenColumna === columna) {
            ordenDireccion = (ordenDireccion === 'asc') ? 'desc' : 'asc';
        } else {
            ordenColumna = columna;
            ordenDireccion = (columna === 'cpu' || columna === 'ram' || columna === 'check') ? 'desc' : 'asc';
        }
    } else {
        ordenColumna = columna;
    }

    const tbody = document.querySelector('#tablaServidoresContainer tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('.server-row-item'));

    rows.sort((a, b) => {
        let valA = a.dataset[columna];
        let valB = b.dataset[columna];

        if (columna === 'cpu' || columna === 'ram' || columna === 'check') {
            const numA = parseFloat(valA) || 0;
            const numB = parseFloat(valB) || 0;
            return ordenDireccion === 'asc' ? numA - numB : numB - numA;
        }

        valA = (valA || '').toString();
        valB = (valB || '').toString();
        const comparacion = valA.localeCompare(valB, 'es', { numeric: true, sensitivity: 'base' });
        return ordenDireccion === 'asc' ? comparacion : -comparacion;
    });

    rows.forEach(row => tbody.appendChild(row));

    // Actualizar encabezados e indicadores visuales
    document.querySelectorAll('.sortable-th').forEach(th => {
        const thCol = th.dataset.col;
        const icon = th.querySelector('.sort-icon');
        if (thCol === columna) {
            th.classList.add('active-sort');
            th.setAttribute('aria-sort', ordenDireccion === 'asc' ? 'ascending' : 'descending');
            if (icon) {
                icon.className = `fa-light ${ordenDireccion === 'asc' ? 'fa-sort-up' : 'fa-sort-down'} ms-1 sort-icon text-info`;
            }
        } else {
            th.classList.remove('active-sort');
            th.setAttribute('aria-sort', 'none');
            if (icon) {
                icon.className = 'fa-light fa-sort ms-1 sort-icon text-secondary';
            }
        }
    });

    localStorage.setItem('gomanager_orden_col', ordenColumna);
    localStorage.setItem('gomanager_orden_dir', ordenDireccion);
}

document.addEventListener('DOMContentLoaded', () => {
    // Restaurar vista seleccionada
    const vistaGuardada = localStorage.getItem('gomanager_vista') || 'tarjetas';
    cambiarVista(vistaGuardada);

    // Event listeners para ordenamiento de tabla
    document.querySelectorAll('.sortable-th').forEach(th => {
        th.addEventListener('click', () => {
            ordenarTabla(th.dataset.col, true);
        });
        th.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                ordenarTabla(th.dataset.col, true);
            }
        });
    });

    // Aplicar orden inicial a la tabla
    ordenarTabla(ordenColumna, false);

    // Buscador por nombre de equipo
    const inputTexto = document.getElementById('filtroNombreInput');
    const btnLimpiarTexto = document.getElementById('btnLimpiarTexto');

    if (inputTexto) {
        inputTexto.addEventListener('input', () => {
            aplicarFiltro(filtroActivo);
        });
        inputTexto.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                inputTexto.value = '';
                aplicarFiltro(filtroActivo);
            }
        });
    }

    if (btnLimpiarTexto) {
        btnLimpiarTexto.addEventListener('click', () => {
            if (inputTexto) inputTexto.value = '';
            aplicarFiltro(filtroActivo);
            inputTexto?.focus();
        });
    }

    document.querySelectorAll('.metric-filter-card').forEach(card => {
        card.addEventListener('click', () => {
            aplicarFiltro(card.dataset.filtro);
        });
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                aplicarFiltro(card.dataset.filtro);
            }
        });
    });
});
</script>
</body>
</html>