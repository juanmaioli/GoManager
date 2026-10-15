<?php
// config.php - Administración de Servidores (ABM)

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$mensaje = '';
$tipoMensaje = 'info';

// Procesar Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
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
            if ($id > 0) {
                // Actualizar
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

                $mensaje = "Servidor '{$nombre}' actualizado correctamente.";
                $tipoMensaje = 'success';
            } else {
                // Crear nuevo
                $stmt = $pdo->prepare("
                    INSERT INTO servidores (nombre, host, puerto, usuario, auth_tipo, password, clave_privada, ruta_binario)
                    VALUES (:nombre, :host, :puerto, :usuario, :auth_tipo, :password, :clave_privada, :ruta_binario)
                ");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':host' => $host,
                    ':puerto' => $puerto,
                    ':usuario' => $usuario,
                    ':auth_tipo' => $authTipo,
                    ':password' => $password,
                    ':clave_privada' => $clavePrivada,
                    ':ruta_binario' => $rutaBinario
                ]);

                $mensaje = "Servidor '{$nombre}' registrado con éxito.";
                $tipoMensaje = 'success';
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM servidores WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensaje = 'Servidor eliminado correctamente.';
            $tipoMensaje = 'warning';
        }
    }
}

// Obtener lista de servidores con su última métrica para conocer el S.O.
$query = "
    SELECT s.*, m.raw_json
    FROM servidores s
    LEFT JOIN metricas m ON m.id = (
        SELECT id FROM metricas WHERE servidor_id = s.id ORDER BY id DESC LIMIT 1
    )
    ORDER BY s.id DESC
";
$servidores = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Servidores - GoManager SvrInfo</title>
    <link rel="icon" href='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚙️</text></svg>'>
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
    <style>
        .btn-action { font-size: 0.95rem; font-weight: 600; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="container-fluid px-3 px-md-4 py-4 app-main">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h2 fw-bold text-light mb-1"><i class="fa-light fa-gear text-primary me-2"></i>Configuración de Equipos</h1>
            <p class="text-secondary mb-0">Administrá los servidores y contenedores a monitorear con <code>gosvrinfo</code>.</p>
        </div>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2" onclick="abrirModalNuevo()">
            <i class="fa-light fa-plus me-1"></i>Nuevo Servidor
        </button>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show fw-bold" role="alert">
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- Tabla de Servidores -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark border-bottom border-secondary">
                        <tr>
                            <th class="ps-4">Nombre</th>
                            <th>Host / IP</th>
                            <th>Puerto</th>
                            <th>Usuario</th>
                            <th>Tipo Auth</th>
                            <th>Ruta Binario</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($servidores)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-secondary">
                                    <div class="fs-4 mb-2"><i class="fa-light fa-tower-broadcast me-2"></i>No hay servidores registrados aún.</div>
                                    <p class="mb-3">Hacé clic en "Nuevo Servidor" para conectar tu primer equipo.</p>
                                    <button class="btn btn-outline-primary fw-bold" onclick="abrirModalNuevo()"><i class="fa-light fa-plus me-1"></i>Agregar Servidor</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($servidores as $srv): ?>
                                <?php 
                                    $dataSrv = !empty($srv['raw_json']) ? json_decode($srv['raw_json'], true) : [];
                                    $soNombre = $dataSrv['operating_system']['name'] ?? '';
                                    $iconoSO = iconoDistroSO($soNombre);
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-light">
                                        <a href="servidor.php?id=<?= $srv['id'] ?>" class="text-decoration-none text-light hover-primary">
                                            <i class="<?= $iconoSO ?> me-2"></i><?= e($srv['nombre']) ?>
                                        </a>
                                    </td>
                                    <td><code><?= e($srv['host']) ?></code></td>
                                    <td><?= e((string)$srv['puerto']) ?></td>
                                    <td><code><?= e($srv['usuario']) ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $srv['auth_tipo'] === 'key' ? 'info' : 'secondary' ?>">
                                            <?= $srv['auth_tipo'] === 'key' ? '<i class="fa-light fa-key me-1"></i>Clave SSH' : '<i class="fa-light fa-lock me-1"></i>Contraseña' ?>
                                        </span>
                                    </td>
                                    <td><code><?= e($srv['ruta_binario']) ?></code></td>
                                    <td>
                                        <?php if ($srv['ultimo_estado'] === 'online'): ?>
                                            <span class="badge bg-success">Online</span>
                                        <?php elseif ($srv['ultimo_estado'] === 'error'): ?>
                                            <span class="badge bg-danger">Error</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-info me-1 btn-action" 
                                            onclick='probarConexionServidor(<?= json_encode($srv) ?>)'>
                                            <i class="fa-light fa-network-wired me-1"></i>Probar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning me-1 btn-action" 
                                            onclick='editarServidor(<?= json_encode($srv) ?>)'>
                                            <i class="fa-light fa-pen-to-square me-1"></i>Editar
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este servidor?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= $srv['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Eliminar servidor">
                                                <i class="fa-light fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal Agregar / Editar Servidor -->
<div class="modal fade" id="modalServidor" tabindex="-1" aria-labelledby="modalServidorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-light" id="modalServidorLabel"><i class="fa-light fa-server me-2"></i>Nuevo Servidor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" id="formServidor">
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="srv_id" value="0">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="srv_nombre" class="form-label fw-bold">Nombre del Equipo *</label>
                            <input type="text" class="form-control" id="srv_nombre" name="nombre" placeholder="Ej: VPS Producción / Docker Web" required>
                        </div>
                        <div class="col-md-4">
                            <label for="srv_puerto" class="form-label fw-bold">Puerto SSH *</label>
                            <input type="number" class="form-control" id="srv_puerto" name="puerto" value="22" required>
                        </div>

                        <div class="col-md-7">
                            <label for="srv_host" class="form-label fw-bold">Host o Dirección IP *</label>
                            <input type="text" class="form-control" id="srv_host" name="host" placeholder="Ej: 192.168.1.50 o vps.midominio.com" required>
                        </div>
                        <div class="col-md-5">
                            <label for="srv_usuario" class="form-label fw-bold">Usuario SSH *</label>
                            <input type="text" class="form-control" id="srv_usuario" name="usuario" placeholder="Ej: root, juan, debian" required>
                        </div>

                        <div class="col-md-6">
                            <label for="srv_auth_tipo" class="form-label fw-bold">Método de Autenticación *</label>
                            <select class="form-select" id="srv_auth_tipo" name="auth_tipo" onchange="toggleAuthFields()">
                                <option value="password">Contraseña (password)</option>
                                <option value="key">Clave Privada SSH (key)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="srv_ruta_binario" class="form-label fw-bold">Ruta del comando 'gosvrinfo'</label>
                            <input type="text" class="form-control" id="srv_ruta_binario" name="ruta_binario" value="gosvrinfo" placeholder="gosvrinfo o /usr/local/bin/gosvrinfo">
                        </div>

                        <!-- Campo Contraseña -->
                        <div class="col-12" id="divPassword">
                            <label for="srv_password" class="form-label fw-bold">Contraseña SSH</label>
                            <input type="password" class="form-control" id="srv_password" name="password" placeholder="Ingresá la contraseña">
                            <div class="form-text text-secondary">Si estás editando y no deseás cambiarla, dejala en blanco.</div>
                        </div>

                        <!-- Campo Clave Privada -->
                        <div class="col-12 d-none" id="divKey">
                            <label for="srv_clave_privada" class="form-label fw-bold">Clave Privada SSH (OpenSSH / RSA / Ed25519)</label>
                            <textarea class="form-control font-monospace" id="srv_clave_privada" name="clave_privada" rows="5" placeholder="-----BEGIN OPENSSH PRIVATE KEY----- ..."></textarea>
                            <div class="form-text text-secondary">Pegá el contenido de tu clave privada pem/id_rsa. Si ya existe y no la cambiás, dejala en blanco.</div>
                        </div>
                    </div>

                    <!-- Resultado de Test SSH -->
                    <div id="resultadoTest" class="mt-3 d-none alert alert-info mb-0" role="alert"></div>
                </div>

                <div class="modal-footer border-secondary justify-content-between">
                    <button type="button" class="btn btn-outline-info fw-bold" id="btnTestConexion" onclick="probarConexionForm()">
                        <i class="fa-light fa-network-wired me-1"></i>Probar Conexión Ahora
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Guardar Servidor</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="./js/bootstrap.bundle.min.js"></script>
<script src="./js/app.js"></script>
<script>
const modalEl = document.getElementById('modalServidor');
const bsModal = new bootstrap.Modal(modalEl);

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

function abrirModalNuevo() {
    document.getElementById('modalServidorLabel').innerHTML = '<i class="fa-light fa-server me-2"></i>Nuevo Servidor';
    document.getElementById('srv_id').value = '0';
    document.getElementById('srv_nombre').value = '';
    document.getElementById('srv_host').value = '';
    document.getElementById('srv_puerto').value = '22';
    document.getElementById('srv_usuario').value = '';
    document.getElementById('srv_auth_tipo').value = 'password';
    document.getElementById('srv_password').value = '';
    document.getElementById('srv_clave_privada').value = '';
    document.getElementById('srv_ruta_binario').value = 'gosvrinfo';
    document.getElementById('resultadoTest').classList.add('d-none');
    toggleAuthFields();
    bsModal.show();
}

function editarServidor(srv) {
    document.getElementById('modalServidorLabel').innerHTML = '<i class="fa-light fa-pen-to-square me-2"></i>Editar Servidor: ' + srv.nombre;
    document.getElementById('srv_id').value = srv.id;
    document.getElementById('srv_nombre').value = srv.nombre;
    document.getElementById('srv_host').value = srv.host;
    document.getElementById('srv_puerto').value = srv.puerto;
    document.getElementById('srv_usuario').value = srv.usuario;
    document.getElementById('srv_auth_tipo').value = srv.auth_tipo;
    document.getElementById('srv_password').value = '';
    document.getElementById('srv_clave_privada').value = '';
    document.getElementById('srv_ruta_binario').value = srv.ruta_binario || 'gosvrinfo';
    document.getElementById('resultadoTest').classList.add('d-none');
    toggleAuthFields();
    bsModal.show();
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

async function probarConexionServidor(srv) {
    const btn = event.target;
    const txtOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fa-light fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const resp = await fetch(`api/sync.php?id=${srv.id}`);
        const data = await resp.json();
        if (data.success) {
            alert(`Conexión exitosa con ${srv.nombre}.\nUptime: ${data.data.system_uptime}s\nCPU: ${data.data.cpu_usage}`);
            location.reload();
        } else {
            alert(`Error al conectar con ${srv.nombre}:\n${data.error}`);
            location.reload();
        }
    } catch (e) {
        alert('Error de comunicación: ' + e.message);
    } finally {
        btn.innerHTML = txtOriginal;
        btn.disabled = false;
    }
}

function sincronizarTodos() {
    window.location.href = 'index.php?sync_all=1';
}
</script>
</body>
</html>
