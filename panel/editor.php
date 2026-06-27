<?php
$pageTitle = 'Code Editor';
require_once __DIR__ . '/includes/header.php';

$file = $_GET['file'] ?? '';
$content = '';
$fileName = '';
$lang = 'html';
$isFile = false;
$treeRoot = '';
$treeHtml = '';
$folderName = '';

if (!empty($file)) {
    $base = WEBSITES_PATH;
    $full = resolvePath($base, $file);
    if (isPathSafe($full, $base) && file_exists($full) && !is_dir($full)) {
        $content = file_get_contents($full);
        $fileName = basename($full);
        $isFile = true;
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $map = ['html'=>'htmlmixed','htm'=>'htmlmixed','css'=>'css','js'=>'javascript','php'=>'php','json'=>'application/json'];
        $lang = $map[$ext] ?? 'htmlmixed';
        $treeRoot = dirname($file);
        $folderName = basename($treeRoot ?: $file);
        $fullRoot = resolvePath(WEBSITES_PATH, $treeRoot);
        $treeHtml = buildTreeHtml($fullRoot, $treeRoot, $file);
    }
}
?>

<?php if (!$isFile): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <div class="icon"><i class="fas fa-code"></i></div>
            <h3>Pilih File dari File Manager</h3>
            <a href="/panel/files.php" class="btn btn-primary" style="margin-top:12px;"><i class="fas fa-folder"></i> Buka File Manager</a>
        </div>
    </div>
</div>
<?php else: ?>

<style>
body .sidebar, body .topbar { display:none; }
body .main-content { margin-left:0; }
body .content { padding:0; }
#editorLayout { margin:0; height:100vh; }
#editorMain { height:100vh; }
#editorToolbar { padding:8px 16px; }
#editor .CodeMirror { min-height:calc(100vh - 42px) !important; }
.tree-actions { display:none; margin-left:auto; flex-shrink:0; }
.tree-file:hover .tree-actions, .tree-folder:hover .tree-actions { display:flex; gap:2px; }
.tree-actions .btn-icon { width:22px; height:22px; font-size:10px; }
</style>

<div id="editorLayout">
    <div id="editorSidebar">
        <div class="sidebar-head">
            <i class="fas fa-folder"></i> <span id="explorerTitle"><?= sanitize($folderName ?: 'Explorer') ?></span>
            <div style="margin-left:auto;display:flex;gap:2px;">
                <button class="btn-icon" onclick="showNewFile()" title="New File" style="color:var(--primary);font-size:12px;"><i class="fas fa-file-circle-plus"></i></button>
                <button class="btn-icon" onclick="renameFile()" title="Rename" style="color:var(--text-muted);font-size:11px;"><i class="fas fa-i-cursor"></i></button>
                <button class="btn-icon del" onclick="deleteFile()" title="Delete" style="font-size:11px;"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <div id="fileTree"><?= $treeHtml ?></div>
    </div>
    <div id="editorMain">
        <div id="editorToolbar">
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="btn-icon" onclick="document.getElementById('editorSidebar').classList.toggle('hide')" title="Toggle Sidebar" style="color:var(--text-muted);"><i class="fas fa-bars"></i></button>
                <strong id="editorFileName"><i class="fas fa-file-code"></i> <?= sanitize($fileName) ?></strong>
                <span class="badge badge-info" id="editorLang"><?= strtoupper($lang) ?></span>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-sm btn-success" onclick="saveFile()"><i class="fas fa-save"></i> Save</button>
                <a href="/panel/files.php?dir=<?= urlencode(dirname($file)) ?>" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div id="editor"></div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/jump-to-line.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">

<div class="modal-overlay" id="newFileModal">
    <div class="modal">
        <div class="modal-header"><h3>Buat File Baru</h3><button class="modal-close" onclick="closeNewFile()">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label>Nama File</label><input type="text" class="form-control" id="newFileName" placeholder="contoh: style.css"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeNewFile()">Batal</button>
            <button class="btn btn-primary" onclick="createFile()">Buat</button>
        </div>
    </div>
</div>

<script>
let currentFile = <?= json_encode($file) ?>;
let currentPath = <?= json_encode($treeRoot) ?>;

const editor = CodeMirror(document.getElementById('editor'), {
    value: <?= json_encode($content) ?>,
    mode: '<?= $lang ?>',
    theme: 'monokai',
    lineNumbers: true,
    matchBrackets: true,
    autoCloseBrackets: true,
    indentUnit: 4,
    tabSize: 4,
    lineWrapping: true,
});

editor.setOption('extraKeys', {
    'Ctrl-S': () => saveFile(),
    'Cmd-S': () => saveFile(),
    'Ctrl-F': () => editor.execCommand('find'),
});

function treeToggle(el) {
    var li = el.parentElement;
    li.classList.toggle('expanded');
    el.innerHTML = li.classList.contains('expanded') ? '&#9662;' : '&#9656;';
}

async function refreshTree() {
    var res = await fetch('/panel/api/file.php?action=tree_html&dir=' + encodeURIComponent(currentPath) + '&current=' + encodeURIComponent(currentFile));
    var data = await res.json();
    if (data.success) {
        document.getElementById('fileTree').innerHTML = data.html;
    }
}

async function loadFile(path) {
    try {
        var res = await fetch('/panel/api/file.php?action=read&path=' + encodeURIComponent(path));
        var data = await res.json();
        if (!data.success) { AHPL.toast('Gagal buka file', 'error'); return; }
        editor.setValue(data.content);
        editor.setOption('mode', data.lang);
        editor.clearHistory();
        document.getElementById('editorFileName').innerHTML = '<i class="fas fa-file-code"></i> ' + data.name;
        document.getElementById('editorLang').textContent = data.lang.toUpperCase();
        currentFile = path;
        refreshTree();
    } catch(e) {
        AHPL.toast('Error: ' + e.message, 'error');
    }
}

function showNewFile() { document.getElementById('newFileName').value = ''; document.getElementById('newFileModal').classList.add('active'); setTimeout(function() { document.getElementById('newFileName').focus(); }, 150); }
function closeNewFile() { document.getElementById('newFileModal').classList.remove('active'); }

async function createFile() {
    const name = document.getElementById('newFileName').value.trim();
    if (!name) return AHPL.toast('Masukkan nama file', 'error');
    const res = await AHPL.api('/panel/api/file.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'create_file', dir: currentPath, name })
    });
    if (res.success) {
        AHPL.toast('File dibuat!');
        closeNewFile();
        loadFile(res.path);
    } else {
        AHPL.toast(res.error || 'Gagal', 'error');
    }
}

function renameFileByPath(path) {
    var name = prompt('Nama file baru:', path.split('/').pop());
    if (!name || name === path.split('/').pop()) return;
    (async function() {
        var res = await AHPL.api('/panel/api/file.php', {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'rename', path: path, newName: name })
        });
        if (res.success) {
            AHPL.toast('File direname');
            var newPath = path.substring(0, path.lastIndexOf('/') + 1) + name;
            if (path === currentFile) { loadFile(newPath); }
            else { refreshTree(); }
        } else {
            AHPL.toast(res.error || 'Gagal rename', 'error');
        }
    })();
}

function renameFile() {
    renameFileByPath(currentFile);
}

function deleteFileByPath(path) {
    if (!confirm('Hapus "' + path.split('/').pop() + '"?')) return;
    (async function() {
        var res = await AHPL.api('/panel/api/file.php?path=' + encodeURIComponent(path), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }
        });
        if (res.success) {
            AHPL.toast('Dihapus');
            if (path === currentFile) {
                window.location = '/panel/files.php?dir=' + encodeURIComponent(currentPath);
            } else {
                refreshTree();
            }
        } else {
            AHPL.toast(res.error || 'Gagal hapus', 'error');
        }
    })();
}

function deleteFile() {
    deleteFileByPath(currentFile);
}

async function saveFile() {
    const content = editor.getValue();
    const res = await fetch('/panel/api/file.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'save', path: currentFile, content })
    });
    const data = await res.json();
    if (data.success) AHPL.toast('Tersimpan!');
    else AHPL.toast(data.error || 'Gagal', 'error');
}
</script>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
