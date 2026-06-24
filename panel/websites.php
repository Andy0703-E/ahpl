<?php
$pageTitle = 'Website Manager';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$websites = $db->query("SELECT * FROM websites ORDER BY id DESC");
$hasKey = $db->querySingle("SELECT COUNT(*) FROM settings WHERE key = 'cerebras_key'");
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
                <tr><th>Nama</th><th>Folder</th><th>Dibuat</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php while ($site = $websites->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><strong><?= sanitize($site['name']) ?></strong></td>
                        <td><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px;"><?= sanitize($site['folder']) ?></code></td>
                        <td><?= date('d M Y', strtotime($site['created_at'])) ?></td>
                        <td>
                            <a href="/panel/files.php?dir=<?= urlencode($site['folder']) ?>" class="btn btn-sm btn-outline"><i class="fas fa-folder"></i></a>
                            <button class="btn btn-sm btn-danger" onclick="deleteWebsite(<?= $site['id'] ?>, '<?= sanitize($site['name']) ?>')"><i class="fas fa-trash"></i></button>
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
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="useAI" onchange="toggleAI()">
                    <i class="fas fa-robot" style="color:var(--primary);"></i> Generate dengan AI (Z-AI GLM-4.7)
                </label>
            </div>
            <div id="aiSection" style="display:none;">
                <div class="form-group">
                    <label>Deskripsi website yang diinginkan</label>
                    <textarea class="form-control" id="aiPrompt" rows="4" placeholder="Contoh: Buat website portofolio pribadi dengan tema gelap, ada navbar, hero section, tentang saya, dan kontak. Gunakan warna biru dan putih." style="resize:vertical;"></textarea>
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
function toggleAI() {
    const checked = document.getElementById('useAI').checked;
    document.getElementById('aiSection').style.display = checked ? 'block' : 'none';
}

async function createWebsite() {
    const name = document.getElementById('siteName').value.trim();
    const folder = document.getElementById('siteFolder').value.trim();
    const useAI = document.getElementById('useAI').checked;
    const prompt = document.getElementById('aiPrompt').value.trim();
    
    if (!name || !folder) return AHPL.toast('Nama dan folder wajib diisi', 'error');
    if (useAI && !prompt) return AHPL.toast('Masukkan deskripsi untuk AI generate', 'error');
    
    document.getElementById('createBtn').disabled = true;
    
    let aiHtml = '';
    
    if (useAI) {
        document.getElementById('aiStatus').style.display = 'block';
        try {
            const aiRes = await AHPL.api('/panel/api/cerebras.php', {
                method: 'POST',
                body: JSON.stringify({ prompt })
            });
            aiHtml = aiRes.html;
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
            body: JSON.stringify({ action: 'create', name, folder, html: aiHtml })
        });
        
        if (res.success) {
            document.getElementById('createModal').classList.remove('active');
            AHPL.toast(useAI ? 'Website dibuat dengan AI!' : 'Website dibuat!');
            setTimeout(() => location.reload(), 500);
        }
    } catch (e) {
        AHPL.toast(e.message || 'Gagal membuat website', 'error');
    }
    
    document.getElementById('createBtn').disabled = false;
}

async function deleteWebsite(id, name) {
    if (!AHPL.confirm('Hapus website "' + name + '"?')) return;
    const res = await AHPL.api('/panel/api/websites.php?id=' + id, { method: 'DELETE' });
    if (res.success) { AHPL.toast('Website dihapus'); setTimeout(() => location.reload(), 500); }
}

document.getElementById('siteName').addEventListener('input', function() {
    document.getElementById('siteFolder').value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
