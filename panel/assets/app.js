const AHPL = {
    toast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    },

    async api(url, opts = {}) {
        const headers = { 'Content-Type': 'application/json', ...opts.headers };
        if (window.__CSRF_TOKEN__ && !headers['X-CSRF-TOKEN']) {
            headers['X-CSRF-TOKEN'] = window.__CSRF_TOKEN__;
        }
        const res = await fetch(url, { headers, ...opts });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            throw new Error('Server error (HTTP ' + res.status + ')');
        }
        if (!res.ok) throw new Error(data.error || 'Error (HTTP ' + res.status + ')');
        return data;
    },

    confirm(msg) {
        return confirm(msg);
    },

    formatSize(bytes) {
        if (bytes == 0) return '0 B';
        const u = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
    }
};

// Font Awesome fallback detection
(function() {
    var fa = document.createElement('span');
    fa.className = 'fa';
    fa.style.display = 'none';
    document.body.appendChild(fa);
    setTimeout(function() {
        var ok = getComputedStyle(fa).fontFamily.indexOf('FontAwesome') !== -1;
        document.body.removeChild(fa);
        if (!ok) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://use.fontawesome.com/releases/v6.5.1/css/all.css';
            link.onload = function() { document.querySelectorAll('.fa-fallback').forEach(function(e) { e.style.display = 'inline'; }); };
            document.head.appendChild(link);
        }
    }, 100);
})();

// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        let overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
});
