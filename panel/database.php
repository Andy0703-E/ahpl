<?php
$pageTitle = 'Database Manager';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table"></i> Tables</h3>
        <div><button class="btn btn-sm btn-info" onclick="openQuery()"><i class="fas fa-terminal"></i> SQL Query</button></div>
    </div>
    <div class="card-body">
        <div id="tablesContainer"><p style="color:#888;font-size:13px;">Memuat tabel...</p></div>
    </div>
</div>

<div id="tableViewer" style="display:none;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-database"></i> <span id="currentTableName"></span></h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="addRow()"><i class="fas fa-plus"></i> Tambah</button>
                <button class="btn btn-sm btn-outline" onclick="showSchema()"><i class="fas fa-info-circle"></i> Schema</button>
                <button class="btn btn-sm btn-outline" onclick="exportCSV()"><i class="fas fa-file-csv"></i> CSV</button>
                <button class="btn btn-sm btn-outline" onclick="closeTable()"><i class="fas fa-times"></i> Tutup</button>
            </div>
        </div>
        <div class="card-body" style="overflow-x:auto;">
            <div id="tableContent"><p style="color:#888;font-size:13px;">Memuat data...</p></div>
            <div id="tablePagination" style="margin-top:12px;display:flex;justify-content:center;gap:6px;flex-wrap:wrap;"></div>
        </div>
    </div>
</div>

<div id="schemaModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Schema: <span id="schemaTableName"></span></h3>
            <button class="modal-close" onclick="closeSchema()">&times;</button>
        </div>
        <div class="modal-body" style="overflow-x:auto;"><div id="schemaContent"></div></div>
    </div>
</div>

<div id="rowModal" class="modal-overlay">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="rowModalTitle">Edit Row</span></h3>
            <button class="modal-close" onclick="closeRowModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="rowFormContainer"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeRowModal()">Batal</button>
            <button class="btn btn-primary" id="rowSaveBtn" onclick="saveRow()"><i class="fas fa-save"></i> Simpan</button>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3><i class="fas fa-trash" style="color:var(--danger);"></i> Hapus Row</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;margin-bottom:6px;">Yakin ingin menghapus row ini?</p>
            <p style="font-size:12px;color:#888;" id="deletePreview"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeDeleteModal()">Batal</button>
            <button class="btn btn-danger" onclick="confirmDelete()"><i class="fas fa-trash"></i> Hapus</button>
        </div>
    </div>
</div>

<div id="queryModal" class="modal-overlay">
    <div class="modal" style="max-width:750px;">
        <div class="modal-header">
            <h3><i class="fas fa-terminal"></i> SQL Query</h3>
            <button class="modal-close" onclick="closeQuery()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#888;margin-bottom:10px;">Semua query didukung. Query <code>INSERT</code>/<code>UPDATE</code>/<code>DELETE</code> memerlukan konfirmasi.</p>
            <div class="form-group">
                <label>Query</label>
                <textarea class="form-control" id="sqlQuery" rows="4" style="font-family:monospace;font-size:13px;" placeholder="SELECT * FROM websites LIMIT 10"></textarea>
            </div>
            <button class="btn btn-primary" onclick="executeQuery()"><i class="fas fa-play"></i> Run</button>
            <div id="queryResult" style="margin-top:12px;overflow-x:auto;"></div>
        </div>
    </div>
</div>

<script>
let currentTable = null;
let currentSchema = null;
let currentPage = 1;
let editingRow = null;
let deletingRow = null;
const PER_PAGE = 50;

// --- Tables ---
async function loadTables() {
    try {
        const res = await AHPL.api('/panel/api/database.php?action=list_tables');
        const el = document.getElementById('tablesContainer');
        if (!res.tables || !res.tables.length) {
            el.innerHTML = '<p style="color:#888;font-size:13px;">Tidak ada tabel</p>';
            return;
        }
        el.innerHTML = '<table class="table"><thead><tr><th>Tabel</th><th>Rows</th><th>Aksi</th></tr></thead><tbody>' +
            res.tables.map(t => '<tr><td style="font-weight:600;"><i class="fas fa-table" style="color:var(--primary);margin-right:8px;"></i>' + AHPL.escapeHtml(t.name) + '</td><td>' + t.row_count + '</td><td><button class="btn btn-sm btn-info" onclick="viewTable(\'' + t.name + '\')"><i class="fas fa-eye"></i></button></td></tr>').join('') +
            '</tbody></table>';
    } catch (e) {
        document.getElementById('tablesContainer').innerHTML = '<p style="color:var(--danger);font-size:13px;">Gagal memuat tabel: ' + AHPL.escapeHtml(e.message) + '</p>';
    }
}

// --- View Table ---
async function viewTable(table, page) {
    page = page || 1;
    currentTable = table;
    currentPage = page;
    document.getElementById('currentTableName').textContent = table;
    document.getElementById('tableViewer').style.display = 'block';
    document.getElementById('tableContent').innerHTML = '<p style="color:#888;font-size:13px;">Memuat data...</p>';

    try {
        const res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + encodeURIComponent(table) + '&page=' + page + '&per_page=' + PER_PAGE);
        currentSchema = res.schema;

        // Find PK column
        var pkCol = null;
        for (var i = 0; i < res.schema.length; i++) { if (res.schema[i].pk) { pkCol = res.schema[i]; break; } }

        var html = '<table class="table"><thead><tr>';
        if (pkCol) html += '<th style="width:80px;">Aksi</th>';
        res.columns.forEach(function(c) { html += '<th>' + AHPL.escapeHtml(c) + '</th>'; });
        html += '</tr></thead><tbody>';

        if (!res.rows.length) {
            html += '<tr><td colspan="' + (res.columns.length + (pkCol ? 1 : 0)) + '" style="text-align:center;color:#888;">Kosong</td></tr>';
        } else {
            res.rows.forEach(function(row) {
                html += '<tr>';
                if (pkCol) {
                    var idx = res.columns.indexOf(pkCol.name);
                    var val = idx >= 0 ? row[idx] : '';
                    var encVal = encodeURIComponent(String(val));
                    var encTable = encodeURIComponent(table);
                    html += '<td style="white-space:nowrap;"><button class="btn-icon" onclick="editRow(\'' + encTable + '\',\'' + AHPL.escapeHtml(pkCol.name) + '\',\'' + encVal + '\')" title="Edit"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="showDelete(\'' + encTable + '\',\'' + AHPL.escapeHtml(pkCol.name) + '\',\'' + encVal + '\')" title="Hapus"><i class="fas fa-trash"></i></button></td>';
                }
                row.forEach(function(val) {
                    if (val === null) {
                        html += '<td style="font-size:12px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><span style="color:#ccc;font-style:italic;">NULL</span></td>';
                    } else {
                        html += '<td style="font-size:12px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + AHPL.escapeHtml(String(val)) + '">' + AHPL.escapeHtml(String(val)) + '</td>';
                    }
                });
                html += '</tr>';
            });
        }
        html += '</tbody></table>';
        document.getElementById('tableContent').innerHTML = html;

        var totalPages = Math.ceil(res.total / PER_PAGE);
        if (totalPages <= 1) { document.getElementById('tablePagination').innerHTML = ''; return; }
        var pagHtml = '<span style="font-size:12px;color:#888;margin-right:6px;">Halaman:</span>';
        for (var i = 1; i <= totalPages; i++) {
            pagHtml += '<button class="btn btn-sm ' + (i === page ? 'btn-primary' : 'btn-outline') + '" onclick="viewTable(\'' + table + '\', ' + i + ')">' + i + '</button>';
        }
        document.getElementById('tablePagination').innerHTML = pagHtml;
    } catch (e) {
        document.getElementById('tableContent').innerHTML = '<p style="color:var(--danger);font-size:13px;">' + AHPL.escapeHtml(e.message) + '</p>';
    }
}

function closeTable() { document.getElementById('tableViewer').style.display = 'none'; currentTable = null; currentSchema = null; }

// --- Schema ---
async function showSchema() {
    if (!currentTable) return;
    try {
        var res = await AHPL.api('/panel/api/database.php?action=get_schema&table=' + encodeURIComponent(currentTable));
        document.getElementById('schemaTableName').textContent = currentTable;
        document.getElementById('schemaContent').innerHTML = '<table class="table"><thead><tr><th>#</th><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th><th>PK</th></tr></thead><tbody>' +
            res.schema.map(function(s) { return '<tr><td>' + s.cid + '</td><td style="font-weight:600;">' + AHPL.escapeHtml(s.name) + '</td><td><code>' + AHPL.escapeHtml(s.type || 'N/A') + '</code></td><td>' + (s.notnull ? '<span class="badge badge-danger">NO</span>' : '<span class="badge badge-success">YES</span>') + '</td><td style="font-style:italic;color:#888;">' + (s.dflt_value !== null ? AHPL.escapeHtml(s.dflt_value) : '\u2014') + '</td><td>' + (s.pk ? '<span class="badge badge-info">PK</span>' : '') + '</td></tr>'; }).join('') +
            '</tbody></table>';
        document.getElementById('schemaModal').classList.add('active');
    } catch (e) { AHPL.toast(e.message, 'error'); }
}
function closeSchema() { document.getElementById('schemaModal').classList.remove('active'); }

// --- Row Form ---
function buildRowForm(schema, data, isEdit) {
    var pkCol = null;
    for (var i = 0; i < schema.length; i++) { if (schema[i].pk) { pkCol = schema[i]; break; } }

    var html = '';
    for (var i = 0; i < schema.length; i++) {
        var col = schema[i];
        if (col.pk) {
            var val = data && data[col.name] !== undefined ? data[col.name] : '';
            html += '<div class="form-group"><label>' + AHPL.escapeHtml(col.name) + ' <span style="color:#888;font-size:11px;">' + (col.type || '') + ' [PK]</span></label>';
            html += '<input type="text" class="form-control" id="rf-' + col.name + '" value="' + AHPL.escapeHtml(String(val)) + '" ' + (isEdit ? '' : 'readonly style="background:#f5f5f5;color:#999;"') + '></div>';
            continue;
        }
        var val = data && data[col.name] !== undefined ? data[col.name] : col.dflt_value || '';
        var required = col.notnull ? 'required' : '';
        html += '<div class="form-group"><label>' + AHPL.escapeHtml(col.name) + ' <span style="color:#888;font-size:11px;">' + (col.type || '') + (col.notnull ? ' *' : '') + '</span></label>';
        var isTextarea = col.type && (col.type.toUpperCase().includes('TEXT') || col.type.toUpperCase().includes('CHAR'));
        if (isTextarea && (!val || val.length > 100)) {
            html += '<textarea class="form-control" id="rf-' + col.name + '" rows="3" style="font-family:monospace;font-size:13px;" ' + required + '>' + AHPL.escapeHtml(String(val)) + '</textarea>';
        } else {
            html += '<input type="text" class="form-control" id="rf-' + col.name + '" value="' + AHPL.escapeHtml(String(val)) + '" ' + required + '>';
        }
        html += '</div>';
    }
    return html;
}

function getFormData(schema) {
    var data = {};
    for (var i = 0; i < schema.length; i++) {
        var el = document.getElementById('rf-' + schema[i].name);
        if (el) data[schema[i].name] = el.value;
    }
    return data;
}

// --- Add Row ---
function addRow() {
    editingRow = null;
    document.getElementById('rowModalTitle').textContent = 'Tambah Row — ' + currentTable;
    document.getElementById('rowSaveBtn').innerHTML = '<i class="fas fa-plus"></i> Tambah';
    document.getElementById('rowFormContainer').innerHTML = buildRowForm(currentSchema, null, false);
    document.getElementById('rowModal').classList.add('active');
}

// --- Edit Row ---
async function editRow(table, idCol, idVal) {
    try {
        var res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + table + '&page=1&per_page=10000000');
        var idx = res.columns.indexOf(idCol);
        if (idx < 0) { AHPL.toast('Kolom ID tidak ditemukan', 'error'); return; }
        var rowData = null;
        for (var i = 0; i < res.rows.length; i++) {
            if (String(res.rows[i][idx]) === decodeURIComponent(idVal)) { rowData = res.rows[i]; break; }
        }
        if (!rowData) { AHPL.toast('Row tidak ditemukan', 'error'); return; }
        var data = {};
        for (var j = 0; j < res.columns.length; j++) { data[res.columns[j]] = rowData[j]; }

        editingRow = { table: table, idCol: idCol, idVal: decodeURIComponent(idVal), schema: res.schema };
        document.getElementById('rowModalTitle').textContent = 'Edit Row — ' + table;
        document.getElementById('rowSaveBtn').innerHTML = '<i class="fas fa-save"></i> Simpan';
        document.getElementById('rowFormContainer').innerHTML = buildRowForm(res.schema, data, true);
        document.getElementById('rowModal').classList.add('active');
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

function closeRowModal() { document.getElementById('rowModal').classList.remove('active'); }

async function saveRow() {
    var data = getFormData(editingRow ? editingRow.schema : currentSchema);
    var table = editingRow ? editingRow.table : currentTable;
    try {
        if (editingRow) {
            var res = await AHPL.api('/panel/api/database.php', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
                body: JSON.stringify({ action: 'update_row', table: table, id_column: editingRow.idCol, id_value: editingRow.idVal, data: data })
            });
            if (res.success) { AHPL.toast('Row diupdate'); closeRowModal(); viewTable(table, currentPage); }
        } else {
            var res = await AHPL.api('/panel/api/database.php', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
                body: JSON.stringify({ action: 'insert_row', table: table, data: data })
            });
            if (res.success) { AHPL.toast('Row ditambahkan'); closeRowModal(); viewTable(table, currentPage); }
        }
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

// --- Delete ---
function showDelete(table, idCol, idVal) {
    deletingRow = { table: table, idCol: idCol, idVal: decodeURIComponent(idVal) };
    document.getElementById('deletePreview').textContent = idCol + ' = ' + decodeURIComponent(idVal);
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); deletingRow = null; }

async function confirmDelete() {
    if (!deletingRow) return;
    var tbl = deletingRow.table;
    try {
        var res = await AHPL.api('/panel/api/database.php', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'delete_row', table: tbl, id_column: deletingRow.idCol, id_value: deletingRow.idVal })
        });
        if (res.success) { AHPL.toast('Row dihapus'); closeDeleteModal(); viewTable(tbl, currentPage); }
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

// --- CSV Export ---
function exportCSV() {
    if (!currentTable) return;
    window.location = '/panel/api/database.php?action=export_csv&table=' + encodeURIComponent(currentTable) + '&t=' + Date.now();
}

// --- SQL Query ---
function openQuery() {
    document.getElementById('sqlQuery').value = '';
    document.getElementById('queryResult').innerHTML = '';
    document.getElementById('queryModal').classList.add('active');
    setTimeout(function() { document.getElementById('sqlQuery').focus(); }, 150);
}
function closeQuery() { document.getElementById('queryModal').classList.remove('active'); }

async function executeQuery() {
    var query = document.getElementById('sqlQuery').value.trim();
    if (!query) return AHPL.toast('Masukkan query', 'error');

    var resultDiv = document.getElementById('queryResult');
    resultDiv.innerHTML = '<p style="color:#888;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Running...</p>';

    try {
        var res = await AHPL.api('/panel/api/database.php', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'query', query: query })
        });

        if (res.columns && res.columns.length) {
            var html = '<div style="margin-bottom:8px;font-size:12px;color:#666;">' + res.rows.length + ' row(s) in ' + res.elapsed + 's</div>';
            html += '<div style="overflow-x:auto;"><table class="table"><thead><tr>';
            res.columns.forEach(function(c) { html += '<th>' + AHPL.escapeHtml(c) + '</th>'; });
            html += '</tr></thead><tbody>';
            if (!res.rows.length) {
                html += '<tr><td colspan="' + res.columns.length + '" style="text-align:center;color:#888;">Kosong</td></tr>';
            } else {
                res.rows.forEach(function(row) {
                    html += '<tr>';
                    row.forEach(function(val) {
                        if (val === null) { html += '<td style="font-size:12px;max-width:300px;"><span style="color:#ccc;font-style:italic;">NULL</span></td>'; }
                        else { html += '<td style="font-size:12px;max-width:300px;">' + AHPL.escapeHtml(String(val)) + '</td>'; }
                    });
                    html += '</tr>';
                });
            }
            html += '</tbody></table></div>';
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = '<div style="padding:12px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle"></i> Query executed. Affected rows: ' + res.affected + ' (' + res.elapsed + 's)</div>';
        }
    } catch (e) {
        if (e.message && e.message.indexOf('require_confirm') >= 0) {
            resultDiv.innerHTML = '<div style="padding:12px;background:#fff3cd;color:#856404;border-radius:8px;font-size:13px;margin-bottom:10px;"><i class="fas fa-exclamation-triangle"></i> Query ini akan memodifikasi data. Lanjutkan?</div>' +
                '<button class="btn btn-warning" onclick="executeWriteQuery()"><i class="fas fa-play"></i> Ya, jalankan</button>';
            window._pendingWriteQuery = query;
        } else {
            resultDiv.innerHTML = '<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(e.message) + '</div>';
        }
    }
}

async function executeWriteQuery() {
    var query = window._pendingWriteQuery;
    if (!query) return;
    var resultDiv = document.getElementById('queryResult');
    resultDiv.innerHTML = '<p style="color:#888;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Running...</p>';
    try {
        var res = await AHPL.api('/panel/api/database.php', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'query', query: query, confirm_write: true })
        });
        resultDiv.innerHTML = '<div style="padding:12px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle"></i> Query executed. Affected rows: ' + res.affected + ' (' + res.elapsed + 's)</div>';
    } catch (e) {
        resultDiv.innerHTML = '<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(e.message) + '</div>';
    }
}

document.addEventListener('DOMContentLoaded', loadTables);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
