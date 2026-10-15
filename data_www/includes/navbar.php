<?php
// includes/navbar.php - Menú lateral accesible compacto (Sidebar)
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
?>
<style>
    :root {
        --sidebar-width: 175px;
    }
    @media (min-width: 992px) {
        body {
            display: flex !important;
            min-height: 100vh;
        }
        .app-sidebar {
            width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
            height: 100vh !important;
            position: sticky !important;
            top: 0 !important;
            background-color: #161b22 !important;
            border-right: 1px solid #30363d !important;
            flex-shrink: 0;
            z-index: 1020;
        }
        .app-sidebar .offcanvas-body {
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            padding: 0.75rem 0.5rem !important;
        }
        main, .app-main {
            flex-grow: 1;
            min-width: 0;
            width: calc(100% - var(--sidebar-width));
        }
    }
    @media (max-width: 991.98px) {
        .app-sidebar {
            background-color: #161b22 !important;
            width: var(--sidebar-width) !important;
        }
        .app-sidebar .offcanvas-body {
            padding: 0.75rem 0.5rem !important;
        }
    }
    .sidebar-link {
        font-size: 0.85rem;
        font-weight: 600;
        color: #e6edf3;
        padding: 0.4rem 0.55rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        text-decoration: none;
        transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
        border-left: 3px solid transparent;
        white-space: nowrap;
    }
    .sidebar-link:hover, .sidebar-link:focus {
        background-color: #21262d;
        color: #58a6ff;
        border-left-color: #58a6ff;
    }
    .sidebar-link.active {
        background-color: #1f6feb;
        color: #ffffff !important;
        border-left-color: #ffffff;
    }
    .sidebar-link:focus-visible {
        outline: 2px solid #58a6ff;
        outline-offset: 2px;
    }
    .brand-title {
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .brand-badge {
        font-size: 0.65rem;
        padding: 0.2em 0.4em;
    }
    .sidebar-subtitle {
        font-size: 0.7rem;
    }
    .sidebar-section-header {
        font-size: 0.68rem;
        letter-spacing: 0.04em;
    }
    .btn-sync-compact {
        font-size: 0.78rem;
        padding: 0.35rem 0.5rem;
    }
</style>

<!-- Barra superior para móviles y tablets (< 992px) -->
<header class="navbar navbar-dark bg-dark border-bottom border-secondary d-lg-none px-3 py-2 sticky-top w-100" aria-label="Cabecera móvil">
    <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-1 m-0 brand-title" href="index.php">
        <i class="fa-light fa-bolt text-warning" aria-hidden="true"></i>GoManager <span class="badge bg-secondary brand-badge text-light">Svr</span>
    </a>
    <button class="navbar-toggler py-1 px-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Abrir menú de navegación lateral">
        <span class="navbar-toggler-icon" style="width: 1.2em; height: 1.2em;"></span>
    </button>
</header>

<!-- Menú lateral (Sidebar) accesible compacto -->
<aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header border-bottom border-secondary d-lg-none pb-2 mb-2">
        <h6 class="offcanvas-title fw-bold text-primary d-flex align-items-center gap-1 m-0 brand-title" id="sidebarMenuLabel">
            <i class="fa-light fa-bolt text-warning" aria-hidden="true"></i>GoManager <span class="badge bg-secondary brand-badge text-light">Svr</span>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Cerrar menú lateral"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column h-100">
        <!-- Logo en escritorio -->
        <div class="d-none d-lg-block pb-2 mb-2 border-bottom border-secondary">
            <a class="text-decoration-none fw-bold text-primary d-flex align-items-center gap-1 brand-title" href="index.php">
                <i class="fa-light fa-bolt text-warning" aria-hidden="true"></i>GoManager <span class="badge bg-secondary brand-badge text-light">Svr</span>
            </a>
            <div class="text-secondary sidebar-subtitle mt-1">Panel de Control</div>
        </div>

        <!-- Enlaces principales -->
        <nav class="flex-grow-1" aria-label="Navegación principal">
            <div class="text-secondary sidebar-section-header fw-bold text-uppercase px-1 mb-1">Navegación</div>
            <ul class="nav nav-pills flex-column gap-1 mb-3">
                <li class="nav-item">
                    <a class="sidebar-link <?= $paginaActual === 'index.php' ? 'active' : '' ?>" href="index.php" <?= $paginaActual === 'index.php' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-light fa-chart-line" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="sidebar-link <?= $paginaActual === 'config.php' ? 'active' : '' ?>" href="config.php" <?= $paginaActual === 'config.php' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-light fa-gear" aria-hidden="true"></i>
                        <span>Configuración</span>
                    </a>
                </li>
                <?php if ($paginaActual === 'servidor.php'): ?>
                <li class="nav-item">
                    <a class="sidebar-link active" href="#" aria-current="page">
                        <i class="fa-light fa-server" aria-hidden="true"></i>
                        <span>Servidor</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <hr class="border-secondary my-2">

            <!-- Acciones Rápidas -->
            <div class="px-1">
                <div class="text-secondary sidebar-section-header fw-bold text-uppercase mb-1">Acciones</div>
                <button type="button" class="btn btn-outline-info fw-bold w-100 btn-sync-compact d-flex align-items-center justify-content-center gap-1" id="btnSyncAll" onclick="sincronizarTodos()" aria-label="Sincronizar todos los servidores">
                    <i class="fa-light fa-arrows-rotate" aria-hidden="true"></i>
                    <span>Sincronizar</span>
                </button>
                <div class="text-center mt-1">
                    <span class="text-secondary" style="font-size: 0.7rem;" id="syncStatusGlobal">Listo</span>
                </div>
            </div>
        </nav>

        <!-- Pie del Sidebar -->
        <div class="pt-2 mt-auto border-top border-secondary text-secondary" style="font-size: 0.7rem;">
            <div class="d-flex align-items-center justify-content-between">
                <span>GoManager</span>
                <span class="badge bg-dark border border-secondary text-secondary" style="font-size: 0.65rem;">v0.3.0</span>
            </div>
        </div>
    </div>
</aside>
