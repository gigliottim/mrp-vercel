// Sidebar active state handler
document.addEventListener('DOMContentLoaded', () => {
  // 1. Highlight active link based on current URL
  const currentPath = window.location.pathname;
  const links = document.querySelectorAll('.app-sidebar__link');
  let activeLink = null;

  links.forEach(link => {
    // Remove trailing slash for comparison if needed
    const href = new URL(link.href).pathname;
    if (currentPath === href || (href !== '/' && currentPath.startsWith(href))) {
      // If multiple matches (e.g. /mrp/ and /mrp/users), prefer the longer one
      if (!activeLink || href.length > new URL(activeLink.href).pathname.length) {
        activeLink = link;
      }
    }
  });

  if (activeLink) {
    // Remove active class from all first (server-side might have added it but we refine here)
    links.forEach(l => l.classList.remove('is-active'));
    activeLink.classList.add('is-active');

    // Scroll to the active group
    const section = activeLink.closest('.app-sidebar__section');
    if (section) {
      // Scroll sidebar to show this section at top
      const sidebar = document.querySelector('.app-sidebar');
      if (sidebar) {
        // Calculate position to scroll
        const sectionTop = section.offsetTop;
        const sidebarTop = sidebar.getBoundingClientRect().top;
        // We want sectionTop relative to sidebar container
        // But offsetTop is relative to parent. Sidebar inner structure?

        // Simpler: scrollIntoView on the section
        // Use block: 'start' to align to top
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }
});
