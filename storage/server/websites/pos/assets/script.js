function api(url, data) {
    var opts = { headers: {} };
    if (data) {
        opts.method = 'POST';
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
    }
    return fetch(url, opts).then(function(r){ return r.json(); });
}

function confirmDelete(msg) {
    return confirm(msg || 'Yakin ingin menghapus?');
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type) {
    var el = document.createElement('div');
    el.className = 'toast toast-' + (type || 'success');
    el.textContent = msg;
    el.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:12px 20px;border-radius:8px;z-index:9999;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,0.15);transition:all .3s;';
    el.style.background = type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#10b981';
    el.style.color = '#fff';
    document.body.appendChild(el);
    setTimeout(function(){ el.style.opacity = '0'; setTimeout(function(){ el.remove(); }, 300); }, 3000);
}

function formatMoney(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}
