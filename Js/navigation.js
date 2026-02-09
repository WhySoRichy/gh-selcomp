// Navegación unificada para admin y usuarios
// Controla submenús, dropdown de usuario, cierre al hacer clic fuera/Escape y estados activos
(function() {
  'use strict';

  function closeAllSubmenus(submenus, menuButtons, exceptId) {
    submenus.forEach(sub => {
      if (exceptId && sub.id === exceptId) return;
      sub.style.display = 'none';
      sub.classList.remove('open');
    });
    menuButtons.forEach(btn => {
      if (exceptId && btn.getAttribute('data-toggle') === exceptId) return;
      btn.classList.remove('open');
    });
  }

  function setActiveStates() {
    const currentPath = window.location.pathname.replace(/\/+$/, '');
    const buttons = Array.from(document.querySelectorAll('.inicio-btn, .menu-btn, .submenu-btn'));

    buttons.forEach(btn => {
      const onClick = btn.getAttribute('onclick') || '';
      const match = onClick.match(/window\.location\.href='([^']+)'/);
      if (!match) return;
      const target = match[1].replace(/\/+$/, '');
      if (currentPath.endsWith(target)) {
        btn.classList.add('active');
        const parentMenu = btn.closest('.submenu');
        if (parentMenu) {
          parentMenu.style.display = 'flex';
          const parentButton = document.querySelector(`.menu-btn[data-toggle='${parentMenu.id}']`);
          if (parentButton) parentButton.classList.add('open');
        }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const submenus = Array.from(document.querySelectorAll('.submenu'));
    const menuButtons = Array.from(document.querySelectorAll('.menu-btn[data-toggle]'));
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    const dropdownWrapper = dropdownToggle ? dropdownToggle.closest('.topbar-user') : null;

    // Inicialmente cerrar todos los submenús
    closeAllSubmenus(submenus, menuButtons);

    // Toggle de submenús (solo botones con data-toggle)
    menuButtons.forEach(btn => {
      const targetId = btn.getAttribute('data-toggle');
      const target = targetId ? document.getElementById(targetId) : null;
      if (!target) return;

      btn.addEventListener('click', evt => {
        evt.preventDefault();
        const willOpen = target.style.display !== 'flex';
        closeAllSubmenus(submenus, menuButtons, willOpen ? targetId : null);
        if (willOpen) {
          target.style.display = 'flex';
          btn.classList.add('open');
        } else {
          target.style.display = 'none';
          btn.classList.remove('open');
        }
      });
    });

    // Dropdown de usuario
    if (dropdownToggle && dropdownMenu) {
      dropdownToggle.addEventListener('click', evt => {
        evt.preventDefault();
        dropdownMenu.classList.toggle('show');
        if (dropdownWrapper) dropdownWrapper.classList.toggle('open');
      });
    }

    // Cerrar al hacer clic fuera
    document.addEventListener('click', evt => {
      const sidebar = document.querySelector('.sidebar-navbar');
      if (dropdownMenu && dropdownToggle && !dropdownMenu.contains(evt.target) && !dropdownToggle.contains(evt.target)) {
        dropdownMenu.classList.remove('show');
        if (dropdownWrapper) dropdownWrapper.classList.remove('open');
      }
      if (sidebar && !sidebar.contains(evt.target)) {
        closeAllSubmenus(submenus, menuButtons);
      }
    });

    // Cerrar con Escape
    document.addEventListener('keydown', evt => {
      if (evt.key === 'Escape') {
        if (dropdownMenu) dropdownMenu.classList.remove('show');
        if (dropdownWrapper) dropdownWrapper.classList.remove('open');
        closeAllSubmenus(submenus, menuButtons);
      }
    });

    // Marcar estados activos según la URL
    setActiveStates();
  });
})();
