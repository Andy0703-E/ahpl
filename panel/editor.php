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
    }
}

function buildTreeHtml($dir, $basePath, $currentFile) {
    $html = '<ul>';
    $items = scandir($dir);
    sort($items);
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $full = $dir . '/' . $item;
        $rel = ltrim($basePath . '/' . $item, '/');
        if (is_dir($full)) {
            $html .= '<li class="tree-folder"><span class="tree-toggle" onclick="treeToggle(this)">&#9656;</span> <span class="tree-name">' . htmlspecialchars($item) . '</span>';
            $html .= buildTreeHtml($full, $rel, $currentFile);
            $html .= '</li>';
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $editable = in_array($ext, ['html','htm','css','js','php','json','txt','xml','md','svg']);
            if (!$editable) continue;
            $active = ($rel === $currentFile) ? ' active' : '';
            $iconMap = ['html'=>'<i class="fas fa-file-code" style="color:#e44d26"></i>','htm'=>'<i class="fas fa-file-code" style="color:#e44d26"></i>','css'=>'<i class="fas fa-file-code" style="color:#264de4"></i>','js'=>'<i class="fas fa-file-code" style="color:#f7df1e"></i>','php'=>'<i class="fas fa-file-code" style="color:#8892bf"></i>','json'=>'<i class="fas fa-file-code" style="color:#28a745"></i>'];
            $icon = $iconMap[$ext] ?? '<i class="fas fa-file"></i>';
            $html .= '<li class="tree-file' . $active . '" data-path="' . htmlspecialchars($rel) . '" onclick="loadFile(\'' . htmlspecialchars($rel) . '\')">' . $icon . ' ' . htmlspecialchars($item) . '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

if ($isFile) {
    $fullRoot = resolvePath(WEBSITES_PATH, $treeRoot);
    $treeHtml = buildTreeHtml($fullRoot, $treeRoot, $file);
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
</style>

<div id="editorLayout">
    <div id="editorSidebar">
        <div class="sidebar-head">
            <i class="fas fa-folder-open"></i> Explorer
            <button class="btn-icon" onclick="treeToggleAll()" title="Expand All" style="margin-left:auto;color:var(--text-muted);font-size:11px;"><i class="fas fa-expand"></i></button>
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

function treeToggleAll() {
    var all = document.querySelectorAll('.tree-folder');
    var anyClosed = false;
    all.forEach(function(f) { if (!f.classList.contains('expanded')) anyClosed = true; });
    all.forEach(function(f) {
        if (anyClosed) { f.classList.add('expanded'); f.querySelector('.tree-toggle').innerHTML = '&#9662;'; }
        else { f.classList.remove('expanded'); f.querySelector('.tree-toggle').innerHTML = '&#9656;'; }
    });
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
        document.querySelectorAll('.tree-file.active').forEach(function(e) { e.classList.remove('active'); });
        var el = document.querySelector('.tree-file[data-path="' + path.replace(/"/g, '\\"') + '"]');
        if (el) el.classList.add('active');
    } catch(e) {
        AHPL.toast('Error: ' + e.message, 'error');
    }
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
