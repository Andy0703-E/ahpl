<?php
$pageTitle = 'Code Editor';
require_once __DIR__ . '/includes/header.php';

$file = $_GET['file'] ?? '';
$content = '';
$fileName = '';
$lang = 'html';
$isFile = false;

if (!empty($file)) {
    $base = WEBSITES_PATH;
    $full = resolvePath($base, $file);
    if (isPathSafe($full, $base) && file_exists($full) && !is_dir($full)) {
        $content = file_get_contents($full);
        $fileName = basename($full);
        $isFile = true;
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $map = ['html'=>'html','htm'=>'html','css'=>'css','js'=>'javascript','php'=>'php','json'=>'application/json'];
        $lang = $map[$ext] ?? 'html';
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

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <strong><i class="fas fa-file-code"></i> <?= sanitize($fileName) ?></strong>
        <span class="badge badge-info"><?= strtoupper($lang) ?></span>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-sm btn-success" onclick="saveFile()"><i class="fas fa-save"></i> Save</button>
        <a href="/panel/files.php?dir=<?= urlencode(dirname($file)) ?>" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div id="editor" style="height:calc(100vh - 180px);border:1px solid #ddd;border-radius:8px;overflow:hidden;"></div>

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

async function saveFile() {
    const content = editor.getValue();
    const res = await fetch('/panel/api/file.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'save', path: <?= json_encode($file) ?>, content })
    });
    const data = await res.json();
    if (data.success) AHPL.toast('Tersimpan!');
    else AHPL.toast(data.error || 'Gagal', 'error');
}
</script>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
