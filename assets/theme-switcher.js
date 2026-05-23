// Apply theme based on system preference for Bootstrap pages
(function() {
    const htmlElement = document.documentElement;
    const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

    // Set data-bs-theme immediately (before DOM is ready) to avoid flash of wrong theme
    htmlElement.setAttribute('data-bs-theme', darkModeQuery.matches ? 'dark' : 'light');

    // Handle admin navbar class switching
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

    // Exposed for inline <script> placed right after the navbar HTML — runs
    // synchronously before paint, so there is no flash of wrong navbar theme.
    window.__applyNavbarTheme = function () {
        applyNavbarTheme(darkModeQuery.matches);
    };

    // DOMContentLoaded fallback (e.g. if the inline call is missing or navbar
    // is added dynamically after page load)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyNavbarTheme(darkModeQuery.matches);
        });
    }

    function applyTheme(isDark) {
        htmlElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
        applyNavbarTheme(isDark);
    }

    // Listen for changes in system theme preference (live OS toggle)
    darkModeQuery.addEventListener('change', function (e) {
        applyTheme(e.matches);
    });
})();
