/**
 * app.js - Persistencia de tema claro/oscuro y sistema de Auto-chequeo en vivo
 */

let autoRefreshIntervalSec = 0;
let autoRefreshRemainingSec = 0;
let autoRefreshTimer = null;
let autoRefreshEnProgreso = false;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Selector de Tema Claro/Oscuro
  const themeSwitch = document.getElementById('theme-switch');
  const themeSwitchMobile = document.getElementById('theme-switch-mobile');
  const switches = [themeSwitch, themeSwitchMobile].filter(Boolean);

  const savedTheme = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-bs-theme', savedTheme);

  switches.forEach(sw => {
    sw.checked = savedTheme === 'dark';
  });

  switches.forEach(sw => {
    sw.addEventListener('change', (e) => {
      const newTheme = e.target.checked ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);

      switches.forEach(other => {
        other.checked = e.target.checked;
      });
    });
  });

  // 2. Inicializar Auto-chequeo de Actividad
  inicializarAutoRefresh();
});

function inicializarAutoRefresh() {
  const select = document.getElementById('selectAutoRefresh');
  if (!select) return;

  const savedInterval = parseInt(localStorage.getItem('auto_refresh_interval')) || 0;
  select.value = savedInterval.toString();
  autoRefreshIntervalSec = savedInterval;
  autoRefreshRemainingSec = savedInterval;

  actualizarUIAutoRefresh();

  // Iniciar temporizador de 1 segundo
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
  autoRefreshTimer = setInterval(tickAutoRefresh, 1000);
}

function cambiarIntervaloAutoRefresh(nuevoValor) {
  const sec = parseInt(nuevoValor) || 0;
  autoRefreshIntervalSec = sec;
  autoRefreshRemainingSec = sec;
  localStorage.setItem('auto_refresh_interval', sec.toString());
  actualizarUIAutoRefresh();
}

function tickAutoRefresh() {
  if (autoRefreshIntervalSec <= 0 || autoRefreshEnProgreso) {
    actualizarUIAutoRefresh();
    return;
  }

  // Si la terminal SSH está abierta, pausar la cuenta atrás para no interrumpir
  const terminalModal = document.getElementById('modalTerminalSSH');
  if (terminalModal && terminalModal.classList.contains('show')) {
    const txtCount = document.getElementById('txtAutoRefreshCountdown');
    if (txtCount) txtCount.textContent = 'En pausa (SSH)';
    return;
  }

  autoRefreshRemainingSec--;

  if (autoRefreshRemainingSec <= 0) {
    autoRefreshRemainingSec = autoRefreshIntervalSec;
    ejecutarAutoChequeo();
  } else {
    actualizarUIAutoRefresh();
  }
}

function actualizarUIAutoRefresh() {
  const badge = document.getElementById('badgeAutoRefreshStatus');
  const txtCount = document.getElementById('txtAutoRefreshCountdown');

  if (!badge || !txtCount) return;

  if (autoRefreshIntervalSec <= 0) {
    badge.className = 'badge bg-secondary font-monospace';
    badge.textContent = 'Off';
    txtCount.textContent = 'Inactivo';
    return;
  }

  const mins = Math.floor(autoRefreshIntervalSec / 60);
  badge.className = 'badge bg-success font-monospace';
  badge.textContent = `${mins}m`;

  const m = Math.floor(autoRefreshRemainingSec / 60);
  const s = autoRefreshRemainingSec % 60;
  txtCount.textContent = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

async function ejecutarAutoChequeo() {
  if (autoRefreshEnProgreso) return;
  autoRefreshEnProgreso = true;

  const txtCount = document.getElementById('txtAutoRefreshCountdown');
  const syncStatus = document.getElementById('syncStatusGlobal');
  const btnSync = document.getElementById('btnSyncAll');

  if (txtCount) txtCount.innerHTML = '<i class="fa-light fa-spinner fa-spin me-1"></i>Chequeando...';
  if (syncStatus) syncStatus.textContent = 'Chequeando...';
  if (btnSync) {
    const icon = btnSync.querySelector('i');
    if (icon) icon.classList.add('fa-spin');
  }

  try {
    const resp = await fetch('api/sync_all.php');
    const data = await resp.json();

    if (data.success) {
      if (syncStatus) syncStatus.textContent = `Actualizado ${data.timestamp}`;
      
      // Si estamos en dashboard o servidor y no hay ningún modal abierto, recargar
      const modalAbierto = document.querySelector('.modal.show');
      if (!modalAbierto) {
        const pag = window.location.pathname;
        if (pag.endsWith('index.php') || pag.endsWith('/') || pag.endsWith('servidor.php')) {
          location.reload();
          return;
        }
      }
    } else {
      if (syncStatus) syncStatus.textContent = 'Error al chequear';
    }
  } catch (err) {
    console.warn('[Auto-chequeo] Error de conexión:', err);
    if (syncStatus) syncStatus.textContent = 'Error de red';
  } finally {
    autoRefreshEnProgreso = false;
    if (btnSync) {
      const icon = btnSync.querySelector('i');
      if (icon) icon.classList.remove('fa-spin');
    }
    actualizarUIAutoRefresh();
  }
}

function ejecutarAutoRefreshInmediato() {
  autoRefreshRemainingSec = autoRefreshIntervalSec > 0 ? autoRefreshIntervalSec : 0;
  ejecutarAutoChequeo();
}
