// Keeps active item state without forcing sidebar reposition on every navigation.
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.app-sidebar');
  if (!sidebar) {
    return;
  }

  const currentPath = window.location.pathname;
  const links = sidebar.querySelectorAll('.app-sidebar__link');
  const scrollKey = 'mrp.sidebar.scrollTop';
  let activeLink = null;

  const restoreScroll = () => {
    const stored = window.sessionStorage.getItem(scrollKey);
    if (stored === null) {
      return;
    }

    const scrollTop = Number.parseInt(stored, 10);
    if (!Number.isNaN(scrollTop) && scrollTop >= 0) {
      sidebar.scrollTop = scrollTop;
    }
  };

  const persistScroll = () => {
    window.sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
  };

  restoreScroll();
  sidebar.addEventListener('scroll', persistScroll, { passive: true });
  window.addEventListener('beforeunload', persistScroll);

  links.forEach((link) => {
    const href = new URL(link.href).pathname;

    if (currentPath === href || (href !== '/' && currentPath.startsWith(href))) {
      if (!activeLink || href.length > new URL(activeLink.href).pathname.length) {
        activeLink = link;
      }
    }

    // Ensure current position is saved right before navigation.
    link.addEventListener('click', persistScroll);
  });

  if (!activeLink) {
    return;
  }

  links.forEach((link) => link.classList.remove('is-active'));
  activeLink.classList.add('is-active');
});
