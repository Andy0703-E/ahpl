const AHPL = {
    toast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    },

    async api(url, opts = {}) {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...opts.headers },
            ...opts
        });
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
