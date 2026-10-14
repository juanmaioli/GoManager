<?php
// includes/navbar.php - Barra de navegación accesible
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary mb-4 shadow-sm" aria-label="Navegación principal">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold fs-4 text-primary" href="index.php">
            <span class="me-1" aria-hidden="true">⚡</span>GoManager <span class="badge bg-secondary fs-6 text-light">SvrInfo</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Abrir menú de navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link fs-5 px-3 <?= $paginaActual === 'index.php' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-light' ?>" href="index.php">
                        📊 Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fs-5 px-3 <?= $paginaActual === 'config.php' ? 'active fw-bold text-primary border-bottom border-primary border-2' : 'text-light' ?>" href="config.php">
                        ⚙️ Configuración
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-info fw-bold btn-sm px-3 py-2" id="btnSyncAll" onclick="sincronizarTodos()">
                    🔄 Sincronizar Todo
                </button>
                <span class="text-secondary small d-none d-md-inline" id="syncStatusGlobal">Listo</span>
            </div>
        </div>
    </div>
</nav>
