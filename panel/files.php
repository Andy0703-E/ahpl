<?php
$pageTitle = 'File Manager';
require_once __DIR__ . '/includes/header.php';

$currentDir = $_GET['dir'] ?? '/';
$baseDir = WEBSITES_PATH;

$fullDir = resolvePath($baseDir, $currentDir);
if (!is_dir($fullDir)) {
    $currentDir = '/';
    $fullDir = $baseDir;
}
if (!isPathSafe($fullDir, $baseDir)) {
    $currentDir = '/';
    $fullDir = $baseDir;
}
?>

<div class="card" style="padding:0;">
    <div class="fm-toolbar">
        <div class="fm-breadcrumb">
            <a href="/panel/files.php?dir=/"><i class="fas fa-home"></i></a>
            <?php
            $parts = array_filter(explode('/', $currentDir));
            $acc = '';
            foreach ($parts as $part):
                $acc .= '/' . $part;
            ?>
                <span>/</span>
                <a href="/panel/files.php?dir=<?= urlencode($acc) ?>"><?= sanitize($part) ?></a>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-primary" onclick="showUpload()"><i class="fas fa-upload"></i> Upload</button>
        <button class="btn btn-sm btn-success" onclick="showNewFolder()"><i class="fas fa-folder-plus"></i> Folder</button>
    </div>

    <ul class="fm-list">
        <?php if ($currentDir !== '/'): ?>
            <?php $parent = dirname($currentDir); if ($parent === '.') $parent = '/'; ?>
            <li class="fm-item" ondblclick="window.location='/panel/files.php?dir=<?= urlencode($parent) ?>'">
                <div class="fm-icon folder"><i class="fas fa-arrow-up"></i></div>
                <span class="fm-name">..</span>
                <span class="fm-meta">Parent</span>
            </li>
        <?php endif; ?>

        <?php
        $items = [];
        if (is_dir($fullDir)) {
            foreach (scandir($fullDir) as $f) {
                if ($f === '.' || $f === '..') continue;
                $fp = $fullDir . '/' . $f;
                $isDir = is_dir($fp);
                $ext = $isDir ? '' : strtolower(pathinfo($f, PATHINFO_EXTENSION));
                $items[] = [
                    'name' => $f, 'is_dir' => $isDir,
                    'size' => $isDir ? 0 : filesize($fp),
                    'modified' => date('Y-m-d H:i', filemtime($fp)),
                    'ext' => $ext,
                    'path' => rtrim($currentDir, '/') . '/' . $f,
                ];
            }
        }
        usort($items, function($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
            return strcasecmp($a['name'], $b['name']);
        });
        ?>

        <?php if (empty($items)): ?>
            <div class="empty-state"><div class="icon"><i class="fas fa-folder-open"></i></div><h3>Kosong</h3></div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <li class="fm-item" <?= $item['is_dir'] ? 'ondblclick="window.location=\'/panel/files.php?dir=' . urlencode($item['path']) . '\'"' : '' ?>>
                    <?php
                    $icon = 'file'; $fa = 'fa-file';
                    if ($item['is_dir']) { $icon = 'folder'; $fa = 'fa-folder'; }
                    elseif (in_array($item['ext'], ['jpg','jpeg','png','gif','svg','webp'])) { $icon = 'img'; $fa = 'fa-image'; }
                    elseif ($item['ext'] === 'zip') { $icon = 'zip'; $fa = 'fa-file-zipper'; }
                    elseif (in_array($item['ext'], ['html','css','js','php'])) { $icon = 'code'; $fa = 'fa-code'; }
                    ?>
                    <div class="fm-icon <?= $icon ?>"><i class="fas <?= $fa ?>"></i></div>
                    <span class="fm-name"><?= sanitize($item['name']) ?></span>
                    <span class="fm-meta"><?= $item['is_dir'] ? 'Folder' : formatSize($item['size']) ?></span>
                    <span class="fm-meta"><?= $item['modified'] ?></span>
                    <div class="fm-actions">
                        <?php if (!$item['is_dir']): ?>
                            <a href="/panel/api/download.php?path=<?= urlencode($item['path']) ?>" class="btn-icon" title="Download"><i class="fas fa-download"></i></a>
                            <?php if (in_array($item['ext'], ['html','htm','css','js','php','json','txt'])): ?>
                                <a href="/panel/editor.php?file=<?= urlencode($item['path']) ?>" class="btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn-icon" onclick="renameItem('<?= sanitize($item['path']) ?>','<?= sanitize($item['name']) ?>')" title="Rename"><i class="fas fa-i-cursor"></i></button>
                        <button class="btn-icon del" onclick="deleteItem('<?= sanitize($item['path']) ?>','<?= sanitize($item['name']) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header"><h3>Upload File</h3><button class="modal-close" onclick="closeModal('uploadModal')">&times;</button></div>
        <div class="modal-body">
            <div class="upload-zone" id="uploadZone">
                <div class="icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <p>Klik atau seret file ke sini</p>
                <p style="font-size:11px;color:#aaa;margin-top:6px;">Max: <?= formatSize(MAX_UPLOAD_SIZE) ?></p>
            </div>
            <div id="uploadProgress" style="margin-top:14px;"></div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="folderModal">
    <div class="modal">
        <div class="modal-header"><h3>Buat Folder</h3><button class="modal-close" onclick="closeModal('folderModal')">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label>Nama Folder</label><input type="text" class="form-control" id="folderName"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('folderModal')">Batal</button>
            <button class="btn btn-primary" onclick="createFolder()">Buat</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="renameModal">
    <div class="modal">
        <div class="modal-header"><h3>Rename</h3><button class="modal-close" onclick="closeModal('renameModal')">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label>Nama Baru</label><input type="text" class="form-control" id="newName"></div>
            <input type="hidden" id="renamePath">
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('renameModal')">Batal</button>
            <button class="btn btn-primary" onclick="doRename()">Rename</button>
        </div>
    </div>
</div>

<script>
const currentDir = '<?= addslashes($currentDir) ?>';
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function showUpload() { document.getElementById('uploadModal').classList.add('active'); }
function showNewFolder() { document.getElementById('folderName').value = ''; document.getElementById('folderModal').classList.add('active'); }

async function deleteItem(path, name) {
    if (!AHPL.confirm('Hapus "' + name + '"?')) return;
    const res = await AHPL.api('/panel/api/file.php?path=' + encodeURIComponent(path), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }
    });
    if (res.success) { AHPL.toast('Dihapus'); setTimeout(() => location.reload(), 400); }
}

function renameItem(path, name) {
    document.getElementById('renamePath').value = path;
    document.getElementById('newName').value = name;
    document.getElementById('renameModal').classList.add('active');
}

async function doRename() {
    const path = document.getElementById('renamePath').value;
    const newName = document.getElementById('newName').value.trim();
    if (!newName) return AHPL.toast('Masukkan nama', 'error');
    const res = await AHPL.api('/panel/api/file.php', {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'rename', path, newName })
    });
    if (res.success) { AHPL.toast('Direname'); closeModal('renameModal'); setTimeout(() => location.reload(), 400); }
}

async function createFolder() {
    const name = document.getElementById('folderName').value.trim();
    if (!name) return AHPL.toast('Masukkan nama', 'error');
    const res = await AHPL.api('/panel/api/file.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'mkdir', dir: currentDir, name })
    });
    if (res.success) { AHPL.toast('Folder dibuat'); closeModal('folderModal'); setTimeout(() => location.reload(), 400); }
}

// Upload
document.getElementById('uploadZone').addEventListener('click', function() {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.multiple = true;
    inp.onchange = () => doUpload(inp.files);
    inp.click();
});

['dragenter','dragover'].forEach(e => document.getElementById('uploadZone').addEventListener(e, ev => { ev.preventDefault(); document.getElementById('uploadZone').classList.add('dragover'); }));
['dragleave','drop'].forEach(e => document.getElementById('uploadZone').addEventListener(e, ev => { ev.preventDefault(); document.getElementById('uploadZone').classList.remove('dragover'); }));
document.getElementById('uploadZone').addEventListener('drop', ev => doUpload(ev.dataTransfer.files));

async function doUpload(files) {
    const prog = document.getElementById('uploadProgress');
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('dir', currentDir);
        prog.innerHTML += '<div style="margin-bottom:8px;"><strong>' + file.name + '</strong><div class="progress-bar"><div class="progress-fill" style="width:0%" id="p-' + file.name.replace(/\./g,'_') + '"></div></div></div>';
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = e => { if (e.lengthComputable) { const el = document.getElementById('p-' + file.name.replace(/\./g,'_')); if(el) el.style.width = Math.round(e.loaded/e.total*100)+'%'; } };
        xhr.onload = () => AHPL.toast(file.name + ' diupload');
        xhr.onerror = () => AHPL.toast(file.name + ' gagal diupload', 'error');
        xhr.open('POST', '/panel/api/upload.php');
        xhr.send(fd);
    }
    setTimeout(() => location.reload(), 2000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
