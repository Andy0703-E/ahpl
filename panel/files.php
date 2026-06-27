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
        <button class="btn btn-sm btn-info" onclick="showNewFile()"><i class="fas fa-file-circle-plus"></i> New File</button>
        <button class="btn btn-sm btn-success" onclick="showNewFolder()"><i class="fas fa-folder-plus"></i> Folder</button>
        <button class="btn btn-sm btn-warning" onclick="showZipUpload()"><i class="fas fa-file-zipper"></i> Upload ZIP</button>
    </div>

    <ul class="fm-list">
        <?php if ($currentDir !== '/'): ?>
            <?php $parent = dirname($currentDir); if ($parent === '.') $parent = '/'; ?>
            <li class="fm-item" onclick="window.location='/panel/files.php?dir=<?= urlencode($parent) ?>'">
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
                <li class="fm-item" <?= $item['is_dir'] ? 'onclick="window.location=\'/panel/files.php?dir=' . urlencode($item['path']) . '\'"' : '' ?>>
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
                    <div class="fm-actions" onclick="event.stopPropagation()">
                        <?php if (!$item['is_dir']): ?>
                            <a href="/panel/api/download.php?path=<?= urlencode($item['path']) ?>" class="btn-icon" title="Download"><i class="fas fa-download"></i></a>
                            <?php if (in_array($item['ext'], ['html','htm','css','js','php','json','txt'])): ?>
                                <a href="/panel/editor.php?file=<?= urlencode($item['path']) ?>" class="btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                            <?php if ($item['ext'] === 'zip'): ?>
                                <button class="btn-icon" onclick="extractZip('<?= sanitize($item['path']) ?>')" title="Extract ZIP"><i class="fas fa-file-zipper"></i></button>
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

<div class="modal-overlay" id="zipModal">
    <div class="modal">
        <div class="modal-header"><h3><i class="fas fa-file-zipper"></i> Upload & Extract ZIP</h3><button class="modal-close" onclick="closeModal('zipModal')">&times;</button></div>
        <div class="modal-body">
            <div class="upload-zone" id="zipUploadZone">
                <div class="icon"><i class="fas fa-file-archive"></i></div>
                <p>Klik atau seret file ZIP ke sini</p>
                <p style="font-size:11px;color:#aaa;margin-top:6px;">Max: <?= formatSize(MAX_ZIP_EXTRACT_SIZE) ?></p>
            </div>
            <div id="zipProgress" style="margin-top:14px;"></div>
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

<div class="modal-overlay" id="newFileModal">
    <div class="modal">
        <div class="modal-header"><h3>Buat File Baru</h3><button class="modal-close" onclick="closeModal('newFileModal')">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label>Nama File</label><input type="text" class="form-control" id="newFileName" placeholder="contoh: index.html"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('newFileModal')">Batal</button>
            <button class="btn btn-primary" onclick="createFile()">Buat</button>
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
function showNewFile() { document.getElementById('newFileName').value = ''; document.getElementById('newFileModal').classList.add('active'); }

async function createFile() {
    const name = document.getElementById('newFileName').value.trim();
    if (!name) return AHPL.toast('Masukkan nama file', 'error');
    const res = await AHPL.api('/panel/api/file.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'create_file', dir: currentDir, name })
    });
    if (res.success) {
        AHPL.toast('File dibuat!');
        closeModal('newFileModal');
        const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
        if (['html','htm','css','js','php','json','txt'].includes(ext)) {
            window.location = '/panel/editor.php?file=' + encodeURIComponent(res.path);
        } else {
            setTimeout(() => location.reload(), 400);
        }
    } else {
        AHPL.toast(res.error || 'Gagal', 'error');
    }
}

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
    let pending = 0, done = 0;
    for (const file of files) {
        pending++;
        const fd = new FormData();
        fd.append('file', file);
        fd.append('dir', currentDir);
        const fileId = 'p-' + file.name.replace(/[^a-zA-Z0-9]/g,'_');
        prog.innerHTML += '<div style="margin-bottom:8px;"><strong>' + AHPL.escapeHtml(file.name) + '</strong><div class="progress-bar"><div class="progress-fill" style="width:0%" id="' + fileId + '"></div></div><div style="font-size:11px;color:#888;margin-top:2px;" id="' + fileId + '-pct">0%</div></div>';
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = e => {
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded/e.total*100);
                const el = document.getElementById(fileId);
                const pctEl = document.getElementById(fileId + '-pct');
                if(el) el.style.width = pct + '%';
                if(pctEl) pctEl.textContent = pct + '%';
            }
        };
        xhr.onload = () => {
            AHPL.toast(AHPL.escapeHtml(file.name) + ' diupload');
            done++;
            if (done >= pending) setTimeout(function() { location.reload(); }, 600);
        };
        xhr.onerror = () => {
            AHPL.toast(AHPL.escapeHtml(file.name) + ' gagal', 'error');
            done++;
            if (done >= pending) setTimeout(function() { location.reload(); }, 600);
        };
        xhr.open('POST', '/panel/api/upload.php');
        xhr.send(fd);
    }
    if (pending === 0) AHPL.toast('Tidak ada file dipilih', 'error');
}

// ZIP Upload
function showZipUpload() { document.getElementById('zipModal').classList.add('active'); }

document.getElementById('zipUploadZone').addEventListener('click', function() {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = '.zip';
    inp.onchange = () => doZipUpload(inp.files);
    inp.click();
});

['dragenter','dragover'].forEach(function(e) {
    document.getElementById('zipUploadZone').addEventListener(e, function(ev) { ev.preventDefault(); document.getElementById('zipUploadZone').classList.add('dragover'); });
});
['dragleave','drop'].forEach(function(e) {
    document.getElementById('zipUploadZone').addEventListener(e, function(ev) { ev.preventDefault(); document.getElementById('zipUploadZone').classList.remove('dragover'); });
});
document.getElementById('zipUploadZone').addEventListener('drop', function(ev) { doZipUpload(ev.dataTransfer.files); });

async function doZipUpload(files) {
    const prog = document.getElementById('zipProgress');
    let pending = 0, done = 0;
    for (const file of files) {
        if (!file.name.toLowerCase().endsWith('.zip')) {
            AHPL.toast(AHPL.escapeHtml(file.name) + ' bukan file ZIP', 'error');
            continue;
        }
        pending++;
        const fd = new FormData();
        fd.append('file', file);
        fd.append('dir', currentDir);
        const fileId = 'zp-' + file.name.replace(/[^a-zA-Z0-9]/g, '_');
        prog.innerHTML += '<div style="margin-bottom:8px;padding:10px;background:var(--bg);border-radius:8px;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">'
            + '<strong>' + AHPL.escapeHtml(file.name) + '</strong>'
            + '<span style="font-size:11px;color:#888;" id="' + fileId + '-status">Uploading...</span>'
            + '</div>'
            + '<div class="progress-bar"><div class="progress-fill" style="width:0%" id="' + fileId + '"></div></div>'
            + '<div style="font-size:11px;color:#888;margin-top:2px;" id="' + fileId + '-pct">0%</div>'
            + '<div style="font-size:12px;margin-top:6px;display:none;" id="' + fileId + '-result"></div>'
            + '</div>';
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded / e.total * 100);
                const el = document.getElementById(fileId);
                const pctEl = document.getElementById(fileId + '-pct');
                const statusEl = document.getElementById(fileId + '-status');
                if (el) el.style.width = pct + '%';
                if (pctEl) pctEl.textContent = pct + '%';
                if (statusEl && pct === 100) statusEl.textContent = 'Mengekstrak...';
            }
        };
        xhr.onload = function() {
            const resultEl = document.getElementById(fileId + '-result');
            const statusEl = document.getElementById(fileId + '-status');
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    if (statusEl) statusEl.textContent = 'Selesai';
                    var extra = '';
                    if (data.registered && data.registered.length) {
                        extra = '<br><span style="color:var(--info);font-size:11px;"><i class="fas fa-globe"></i> Website terdaftar: ' + data.registered.join(', ') + '</span>';
                    }
                    if (resultEl) { resultEl.style.display = 'block'; resultEl.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> ' + data.extracted + ' file diekstrak</span>' + extra; }
                } else {
                    if (statusEl) statusEl.textContent = 'Gagal';
                    if (resultEl) { resultEl.style.display = 'block'; resultEl.innerHTML = '<span style="color:var(--danger);"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(data.error || 'Gagal') + '</span>'; }
                }
            } catch(e) {
                if (statusEl) statusEl.textContent = 'Error';
                if (resultEl) { resultEl.style.display = 'block'; resultEl.innerHTML = '<span style="color:var(--danger);"><i class="fas fa-exclamation-circle"></i> Server error: ' + AHPL.escapeHtml(xhr.responseText.substring(0,200)) + '</span>'; }
            }
            done++;
            if (done >= pending) {
                var allOk = document.querySelectorAll('#zipProgress [id$="-result"] .fa-check-circle').length === pending;
                setTimeout(function() { closeModal('zipModal'); if (allOk) location.reload(); }, allOk ? 1200 : 3000);
            }
        };
        xhr.onerror = function() {
            const resultEl = document.getElementById(fileId + '-result');
            const statusEl = document.getElementById(fileId + '-status');
            if (statusEl) statusEl.textContent = 'Gagal';
            if (resultEl) { resultEl.style.display = 'block'; resultEl.innerHTML = '<span style="color:var(--danger);"><i class="fas fa-exclamation-circle"></i> Network error</span>'; }
            AHPL.toast(AHPL.escapeHtml(file.name) + ' gagal', 'error');
            done++;
            if (done >= pending) setTimeout(function() { closeModal('zipModal'); location.reload(); }, 3000);
        };
        xhr.open('POST', '/panel/api/zip.php');
        xhr.send(fd);
    }
    if (pending === 0) AHPL.toast('Tidak ada file ZIP valid', 'error');
}

// Extract existing ZIP
async function extractZip(path) {
    if (!AHPL.confirm('Extract file ZIP ini?')) return;
    try {
        const res = await AHPL.api('/panel/api/zip.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'extract', path: path })
        });
        if (res.success) {
            var msg = res.extracted + ' file diekstrak';
            if (res.registered && res.registered.length) msg += '. Website: ' + res.registered.join(', ');
            AHPL.toast(msg);
            setTimeout(function() { location.reload(); }, 500);
        }
    } catch(e) {
        AHPL.toast(e.message || 'Gagal extract', 'error');
    }
}

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
