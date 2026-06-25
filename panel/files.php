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
        <button class="btn btn-sm btn-info" onclick="showDeploy()" title="Deploy ZIP ke folder ini"><i class="fas fa-rocket"></i> Deploy</button>
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
                            <?php if ($item['ext'] === 'zip'): ?>
                                <button class="btn-icon" onclick="deployZipHere('<?= sanitize($item['path']) ?>')" title="Deploy ZIP ke sini" style="color:var(--success);"><i class="fas fa-rocket"></i></button>
                            <?php endif; ?>
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

<div class="modal-overlay" id="deployModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header"><h3><i class="fas fa-rocket"></i> Deploy ZIP</h3><button class="modal-close" onclick="closeModal('deployModal')">&times;</button></div>
        <div class="modal-body">
            <p style="font-size:13px;color:#666;margin-bottom:16px;">Upload file ZIP dan extract isinya langsung ke folder ini. Semua file akan di-overwrite.</p>
            <div class="upload-zone" id="deployZone" style="padding:20px;">
                <div class="icon" style="font-size:32px;"><i class="fas fa-file-archive"></i></div>
                <p>Klik atau seret file ZIP ke sini</p>
            </div>
            <div id="deployProgress" style="margin-top:12px;"></div>
            <div id="deployResult" style="margin-top:12px;display:none;">
                <div style="padding:10px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;">
                    <i class="fas fa-check-circle"></i> <span id="deployResultText"></span>
                </div>
            </div>
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
function showDeploy() { document.getElementById('deployModal').classList.add('active'); }

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
        xhr.onload = () => { AHPL.toast(AHPL.escapeHtml(file.name) + ' diupload'); };
        xhr.onerror = () => { AHPL.toast(AHPL.escapeHtml(file.name) + ' gagal', 'error'); };
        xhr.open('POST', '/panel/api/upload.php');
        xhr.send(fd);
    }
    setTimeout(() => location.reload(), 2000);
}

// Deploy ZIP
document.getElementById('deployZone').addEventListener('click', function() {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = '.zip';
    inp.onchange = () => deployZipUpload(inp.files[0]);
    inp.click();
});
['dragenter','dragover'].forEach(e => document.getElementById('deployZone').addEventListener(e, ev => { ev.preventDefault(); ev.currentTarget.classList.add('dragover'); }));
['dragleave','drop'].forEach(e => document.getElementById('deployZone').addEventListener(e, ev => { ev.preventDefault(); ev.currentTarget.classList.remove('dragover'); if(e.type === 'drop') deployZipUpload(ev.dataTransfer.files[0]); }));

async function deployZipUpload(file) {
    if (!file || !file.name.endsWith('.zip')) return AHPL.toast('Pilih file ZIP', 'error');
    const prog = document.getElementById('deployProgress');
    const result = document.getElementById('deployResult');
    result.style.display = 'none';

    const fd = new FormData();
    fd.append('file', file);
    fd.append('dir', currentDir);

    prog.innerHTML = '<div style="margin-bottom:8px;"><strong>' + AHPL.escapeHtml(file.name) + '</strong><div class="progress-bar"><div class="progress-fill" style="width:0%" id="deployPbar"></div></div><div style="font-size:11px;color:#888;margin-top:2px;" id="deployPct">0%</div></div>';

    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const pct = Math.round(e.loaded/e.total*100);
            document.getElementById('deployPbar').style.width = pct + '%';
            document.getElementById('deployPct').textContent = pct + '%';
        }
    };

    xhr.onload = function() {
        let data;
        try { data = JSON.parse(xhr.responseText); } catch(e) { AHPL.toast('Upload gagal', 'error'); return; }
        if (!data.success) { AHPL.toast(data.error || 'Upload gagal', 'error'); return; }

        prog.innerHTML = '<div style="margin-bottom:8px;"><i class="fas fa-spinner fa-spin"></i> Deploying...</div>';

        const zipPath = '<?= addslashes(WEBSITES_PATH) ?>/' + currentDir.replace(/^\//,'') + '/' + file.name;
        fetch('/panel/api/deploy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'website', folder: currentDir.replace(/^\//,''), zipPath: zipPath })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('deployModal');
                AHPL.toast('Deploy berhasil! ' + d.deploy.extracted + ' file diextract.');
                setTimeout(() => location.reload(), 1500);
            } else {
                AHPL.toast(d.error || 'Deploy gagal', 'error');
            }
        })
        .catch(e => AHPL.toast('Deploy gagal: ' + (e.message || 'Unknown'), 'error'));
    };
    xhr.onerror = () => AHPL.toast('Upload gagal', 'error');
    xhr.open('POST', '/panel/api/upload.php');
    xhr.send(fd);
}

async function deployZipHere(path) {
    if (!AHPL.confirm('Deploy ZIP ' + path.split('/').pop() + ' ke folder ini?')) return;
    const folder = currentDir.replace(/^\//, '') || '';
    try {
        const res = await AHPL.api('/panel/api/deploy.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'website', folder: folder, zipPath: '<?= addslashes(WEBSITES_PATH) ?>/' + folder + '/' + path.split('/').pop() })
        });
        if (res.success) {
            AHPL.toast(res.deploy.extracted + ' file dideploy!');
            setTimeout(() => location.reload(), 1000);
        }
    } catch(e) { AHPL.toast(e.message || 'Deploy gagal', 'error'); }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
