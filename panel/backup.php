<?php
$pageTitle = 'Backup';
require_once __DIR__ . '/includes/header.php';

$backups = [];
if (is_dir(BACKUPS_PATH)) {
    foreach (scandir(BACKUPS_PATH) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp = BACKUPS_PATH . '/' . $f;
        if (is_file($fp)) {
            $backups[] = ['name' => $f, 'size' => filesize($fp), 'date' => date('d M Y H:i', filemtime($fp))];
        }
    }
    usort($backups, fn($a,$b) => strcmp($b['date'], $a['date']));
}
?>

<div style="display:flex;gap:10px;margin-bottom:18px;">
    <button class="btn btn-primary" onclick="doBackup('websites')"><i class="fas fa-globe"></i> Backup Websites</button>
    <button class="btn btn-success" onclick="doBackup('database')"><i class="fas fa-database"></i> Backup Database</button>
    <button class="btn btn-warning" onclick="doBackup('full')"><i class="fas fa-archive"></i> Full Backup</button>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-history"></i> Backup Files</h3></div>
    <div class="card-body">
        <?php if (empty($backups)): ?>
            <div class="empty-state"><div class="icon"><i class="fas fa-download"></i></div><h3>Belum ada backup</h3></div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>File</th><th>Ukuran</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><i class="fas fa-file-zipper" style="color:var(--primary);margin-right:6px;"><?= sanitize($b['name']) ?></i></td>
                            <td><?= formatSize($b['size']) ?></td>
                            <td><?= $b['date'] ?></td>
                            <td>
                                <a href="/panel/api/download-backup.php?file=<?= urlencode($b['name']) ?>" class="btn btn-sm btn-outline"><i class="fas fa-download"></i></a>
                                <button class="btn btn-sm btn-danger" onclick="deleteBackup('<?= sanitize($b['name']) ?>')"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
async function doBackup(type) {
    AHPL.toast('Membuat backup...', 'info');
    const res = await AHPL.api('/panel/api/backup.php', {
        method: 'POST',
        body: JSON.stringify({ type })
    });
    if (res.success) { AHPL.toast('Backup: ' + res.file); setTimeout(() => location.reload(), 500); }
    else AHPL.toast(res.error || 'Gagal', 'error');
}

async function deleteBackup(name) {
    if (!AHPL.confirm('Hapus "' + name + '"?')) return;
    const res = await AHPL.api('/panel/api/backup.php?file=' + encodeURIComponent(name), { method: 'DELETE' });
    if (res.success) { AHPL.toast('Dihapus'); setTimeout(() => location.reload(), 500); }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
