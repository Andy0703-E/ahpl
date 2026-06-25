<?php
$pageTitle = 'Website Manager';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$websites = $db->query("SELECT * FROM websites ORDER BY id DESC");
$hasKey = $db->querySingle("SELECT COUNT(*) FROM settings WHERE key = '" . SQLite3::escapeString(SETTING_CEREBRAS_KEY) . "'");
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
    <p style="color:#666;font-size:13px;">Kelola semua website yang dihosting</p>
    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')">
        <i class="fas fa-plus"></i> Create Website
    </button>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr><th>Nama</th><th>Folder</th><th>Status</th><th>Size</th><th>Dibuat</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php while ($site = $websites->fetchArray(SQLITE3_ASSOC)): ?>
                    <?php $status = websiteStatus($site['folder']); ?>
                    <?php $siteSize = dirSize(WEBSITES_PATH . '/' . $site['folder']); ?>
                    <tr>
                        <td><strong><?= sanitize($site['name']) ?></strong></td>
                        <td><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px;"><?= sanitize($site['folder']) ?></code></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:<?= $status === 'online' ? 'var(--success)' : '#ccc' ?>;">
                                <i class="fas fa-circle" style="font-size:8px;"></i> <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td style="font-size:12px;color:#666;"><?= formatSize($siteSize) ?></td>
                        <td><?= date('d M Y', strtotime($site['created_at'])) ?></td>
                        <td>
                            <a href="/websites/<?= urlencode($site['folder']) ?>/" target="_blank" class="btn btn-sm btn-outline" title="Visit"><i class="fas fa-external-link-alt"></i></a>
                            <a href="/panel/files.php?dir=<?= urlencode($site['folder']) ?>" class="btn btn-sm btn-outline" title="Files"><i class="fas fa-folder"></i></a>
                            <button class="btn btn-sm btn-danger" onclick="deleteWebsite(<?= $site['id'] ?>, '<?= sanitize($site['name']) ?>', '<?= sanitize($site['folder']) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Website</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nama Website</label>
                <input type="text" class="form-control" id="siteName" placeholder="portfolio">
            </div>
            <div class="form-group">
                <label>Nama Folder</label>
                <input type="text" class="form-control" id="siteFolder" placeholder="portfolio">
            </div>
            <div class="form-group" style="border-top:1px solid #eee;padding-top:16px;margin-top:16px;">
                <label>Template</label>
                <select class="form-control" id="siteTemplate">
                    <option value="blank-html">Blank HTML</option>
                    <option value="portfolio">Portfolio</option>
                    <option value="landing">Landing Page</option>
                    <option value="blog">Blog</option>
                </select>
            </div>
            <div class="form-group" style="border-top:1px solid #eee;padding-top:16px;margin-top:16px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="useAI" onchange="toggleAI()">
                    <i class="fas fa-robot" style="color:var(--primary);"></i> Generate dengan AI (Z-AI GLM-4.7)
                </label>
            </div>
            <div id="aiSection" style="display:none;">
                <div class="form-group">
                    <label>Deskripsi website yang diinginkan</label>
                    <textarea class="form-control" id="aiPrompt" rows="4" placeholder="Contoh: Buat website portofolio pribadi dengan tema gelap" style="resize:vertical;"></textarea>
                </div>
                <div id="aiStatus" style="font-size:13px;color:#888;display:none;">
                    <i class="fas fa-spinner fa-spin"></i> AI sedang membuat website...
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="this.closest('.modal-overlay').classList.remove('active')">Batal</button>
            <button class="btn btn-primary" id="createBtn" onclick="createWebsite()">Create</button>
        </div>
    </div>
</div>

<script>
const TEMPLATES = {
    'blank-html': '<!DOCTYPE html>\n<html lang="id">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>{name}</title>\n</head>\n<body>\n    <h1>{name}</h1>\n    <p>Website ini berhasil dibuat!</p>\n</body>\n</html>',
    'portfolio': '<!DOCTYPE html>\n<html lang="id">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>{name} - Portfolio</title>\n    <style>\n        * { margin: 0; padding: 0; box-sizing: border-box; }\n        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #0f172a; color: #e2e8f0; line-height: 1.6; }\n        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }\n        h1 { font-size: 3em; margin-bottom: 10px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }\n        .sub { color: #94a3b8; font-size: 1.2em; margin-bottom: 40px; }\n        .card { background: #1e293b; border-radius: 12px; padding: 24px; margin-bottom: 20px; border: 1px solid #334155; }\n        .card h2 { color: #3b82f6; margin-bottom: 12px; }\n        .btn { display: inline-block; padding: 10px 24px; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }\n        .skills { display: flex; gap: 8px; flex-wrap: wrap; }\n        .skills span { background: #334155; padding: 4px 12px; border-radius: 16px; font-size: 13px; }\n    </style>\n</head>\n<body>\n    <div class="container">\n        <h1>{name}</h1>\n        <p class="sub">Web Developer & Designer</p>\n        <div class="card">\n            <h2>Tentang Saya</h2>\n            <p>Saya adalah web developer yang bersemangat menciptakan website modern dan responsif.</p>\n        </div>\n        <div class="card">\n            <h2>Skills</h2>\n            <div class="skills">\n                <span>HTML/CSS</span><span>JavaScript</span><span>PHP</span><span>React</span>\n            </div>\n        </div>\n        <div class="card">\n            <h2>Kontak</h2>\n            <p>Email: hello@{name}.com</p>\n            <a href="mailto:hello@email.com" class="btn">Hubungi Saya</a>\n        </div>\n    </div>\n</body>\n</html>',
    'landing': '<!DOCTYPE html>\n<html lang="id">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>{name} - Landing Page</title>\n    <style>\n        * { margin: 0; padding: 0; box-sizing: border-box; }\n        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #fff; color: #1e293b; }\n        .hero { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }\n        .hero h1 { font-size: 3.5em; margin-bottom: 16px; }\n        .hero p { font-size: 1.2em; opacity: 0.9; margin-bottom: 32px; max-width: 600px; }\n        .btn { display: inline-block; padding: 14px 36px; background: #fff; color: #667eea; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 1.1em; transition: transform 0.2s; }\n        .btn:hover { transform: translateY(-2px); }\n        .features { max-width: 1000px; margin: 60px auto; padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; }\n        .feature { text-align: center; padding: 30px; }\n        .feature h3 { margin-bottom: 10px; }\n        .feature p { color: #64748b; }\n    </style>\n</head>\n<body>\n    <section class="hero">\n        <h1>{name}</h1>\n        <p>Solusi terbaik untuk kebutuhan Anda. Kami hadir untuk membantu Anda sukses.</p>\n        <a href="#" class="btn">Mulai Sekarang</a>\n    </section>\n    <div class="features">\n        <div class="feature"><h3>Cepat</h3><p>Kinerja optimal dan response time yang cepat</p></div>\n        <div class="feature"><h3>Modern</h3><p>Teknologi terbaru untuk hasil terbaik</p></div>\n        <div class="feature"><h3>Responsif</h3><p>Tampil sempurna di semua perangkat</p></div>\n    </div>\n</body>\n</html>',
    'blog': '<!DOCTYPE html>\n<html lang="id">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>{name} - Blog</title>\n    <style>\n        * { margin: 0; padding: 0; box-sizing: border-box; }\n        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f8fafc; color: #334155; }\n        header { background: #1e293b; color: #fff; padding: 20px 40px; }\n        header h1 { font-size: 1.5em; }\n        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }\n        .post { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }\n        .post h2 { color: #1e293b; margin-bottom: 8px; }\n        .post .meta { color: #94a3b8; font-size: 13px; margin-bottom: 12px; }\n        .post p { line-height: 1.7; }\n        .post a { color: #3b82f6; text-decoration: none; font-weight: 600; }\n        footer { text-align: center; padding: 40px; color: #94a3b8; font-size: 13px; }\n    </style>\n</head>\n<body>\n    <header><h1>{name}</h1></header>\n    <div class="container">\n        <div class="post">\n            <h2>Selamat Datang!</h2>\n            <div class="meta">1 Januari 2026</div>\n            <p>Ini adalah postingan pertama di blog {name}. Nantikan artikel-artikel menarik lainnya!</p>\n            <a href="#">Baca selengkapnya</a>\n        </div>\n        <div class="post">\n            <h2>Tips & Trik Web Development</h2>\n            <div class="meta">25 Juni 2026</div>\n            <p>Pelajari berbagai tips dan trik dalam pengembangan website modern.</p>\n            <a href="#">Baca selengkapnya</a>\n        </div>\n    </div>\n    <footer>&copy; 2026 {name}. All rights reserved.</footer>\n</body>\n</html>'
};

function toggleAI() {
    const checked = document.getElementById('useAI').checked;
    document.getElementById('aiSection').style.display = checked ? 'block' : 'none';
}

async function createWebsite() {
    const name = document.getElementById('siteName').value.trim();
    const folder = document.getElementById('siteFolder').value.trim();
    const template = document.getElementById('siteTemplate').value;
    const useAI = document.getElementById('useAI').checked;
    const prompt = document.getElementById('aiPrompt').value.trim();

    if (!name || !folder) return AHPL.toast('Nama dan folder wajib diisi', 'error');
    if (useAI && !prompt) return AHPL.toast('Masukkan deskripsi untuk AI generate', 'error');

    document.getElementById('createBtn').disabled = true;

    let html = TEMPLATES[template] ? TEMPLATES[template].replace(/{name}/g, name) : '';
    if (useAI) {
        document.getElementById('aiStatus').style.display = 'block';
        try {
            const aiRes = await AHPL.api('/panel/api/cerebras.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
                body: JSON.stringify({ prompt })
            });
            html = aiRes.html;
        } catch (e) {
            document.getElementById('createBtn').disabled = false;
            document.getElementById('aiStatus').style.display = 'none';
            AHPL.toast(e.message || 'AI gagal generate', 'error');
            return;
        }
        document.getElementById('aiStatus').style.display = 'none';
    }

    try {
        const res = await AHPL.api('/panel/api/websites.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'create', name, folder, html })
        });

        if (res.success) {
            document.getElementById('createModal').classList.remove('active');
            AHPL.toast(template !== 'blank-html' ? 'Website dibuat dengan template!' : 'Website dibuat!');
            setTimeout(() => location.reload(), 500);
        }
    } catch (e) {
        AHPL.toast(e.message || 'Gagal membuat website', 'error');
    }

    document.getElementById('createBtn').disabled = false;
}

async function deleteWebsite(id, name, folder) {
    if (!AHPL.confirm('Hapus website "' + name + '"?\nWebsite akan di-backup otomatis sebelum dihapus.')) return;

    // Auto backup before delete
    try {
        await AHPL.api('/panel/api/backup.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'create', type: 'websites' })
        });
    } catch(e) { /* backup optional */ }

    const res = await AHPL.api('/panel/api/websites.php?id=' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }
    });
    if (res.success) { AHPL.toast('Website dihapus (backup tersimpan)'); setTimeout(() => location.reload(), 500); }
}

document.getElementById('siteName').addEventListener('input', function() {
    document.getElementById('siteFolder').value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
