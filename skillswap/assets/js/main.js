document.addEventListener('DOMContentLoaded', function() {
    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;

    function updateIcon(theme) {
        if (theme === 'dark') {
            themeIcon.className = 'bi bi-sun';
        } else {
            themeIcon.className = 'bi bi-moon-stars';
        }
    }

    updateIcon(html.getAttribute('data-theme') || 'light');

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = html.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('skillswap-theme', next);
            updateIcon(next);
        });
    }

    // Confirm actions (bind first so nothing else can disable them)
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            const msg = this.dataset.confirm;
            if (msg && msg !== 'false' && !confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // Auto-dismiss alerts after 5 seconds (bootstrap may be unavailable)
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
            setTimeout(function() {
                try {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                } catch (err) {}
            }, 5000);
        });
    }
});