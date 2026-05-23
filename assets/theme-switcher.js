// Apply theme based on system preference for Bootstrap pages
(function() {
    const htmlElement = document.documentElement;
    const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

    // Set data-bs-theme immediately (before DOM is ready) to avoid flash of wrong theme
    htmlElement.setAttribute('data-bs-theme', darkModeQuery.matches ? 'dark' : 'light');

    // Handle admin navbar — deferred until DOM is ready so #admin-navbar exists
    function applyNavbarTheme(isDark) {
        const adminNavbar = document.getElementById('admin-navbar');
        if (!adminNavbar) return;
        if (isDark) {
            adminNavbar.classList.remove('navbar-light', 'bg-light');
            adminNavbar.classList.add('navbar-dark', 'bg-dark');
        } else {
            adminNavbar.classList.remove('navbar-dark', 'bg-dark');
            adminNavbar.classList.add('navbar-light', 'bg-light');
        }
    }

    function applyTheme(isDark) {
        htmlElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
        applyNavbarTheme(isDark);
    }

    // Apply navbar classes once the DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyNavbarTheme(darkModeQuery.matches);
        });
    } else {
        applyNavbarTheme(darkModeQuery.matches);
    }

    // Listen for changes in system theme preference
    darkModeQuery.addEventListener('change', function (e) {
        applyTheme(e.matches);
    });
})();
