/**
 * app.js - Lógica de persistencia y conmutación de tema claro/oscuro (switch-tema)
 */
document.addEventListener('DOMContentLoaded', () => {
  const themeSwitch = document.getElementById('theme-switch');
  const themeSwitchMobile = document.getElementById('theme-switch-mobile');
  const switches = [themeSwitch, themeSwitchMobile].filter(Boolean);

  // 1. Cargar preferencia guardada o usar oscuro por defecto
  const savedTheme = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-bs-theme', savedTheme);

  switches.forEach(sw => {
    sw.checked = savedTheme === 'dark';
  });

  // 2. Escuchar el cambio en el switch
  switches.forEach(sw => {
    sw.addEventListener('change', (e) => {
      const newTheme = e.target.checked ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);

      // Sincronizar todos los switches en pantalla
      switches.forEach(other => {
        other.checked = e.target.checked;
      });

      console.log(`[Tema] Cambiado a modo ${newTheme}.`);
    });
  });
});
