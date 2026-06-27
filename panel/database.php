<?php
$pageTitle = 'Database Manager';
require_once __DIR__ . '/includes/header.php';
?>

<div id="dbInfoCard" class="card" style="margin-bottom:18px;display:none;">
    <div class="card-body" style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;">
        <span><strong>Type:</strong> <span id="dbInfoType">-</span></span>
        <span><strong>Version:</strong> <span id="dbInfoVersion">-</span></span>
        <span><strong>Tables:</strong> <span id="dbInfoTables">-</span></span>
        <span><strong>Size:</strong> <span id="dbInfoSize">-</span></span>
        <span id="dbInfoUptimeRow" style="display:none;"><strong>Uptime:</strong> <span id="dbInfoUptime">-</span></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table"></i> Tables</h3>
        <div style="display:flex;gap:6px;align-items:center;">
            <div class="db-switcher" style="display:flex;gap:3px;background:var(--bg);padding:3px;border-radius:8px;margin-right:8px;">
                <button class="db-switch-btn active" data-type="sqlite" onclick="switchDB('sqlite')"><i class="fas fa-database"></i> SQLite</button>
                <button class="db-switch-btn" data-type="mariadb" onclick="switchDB('mariadb')"><i class="fas fa-server"></i> MariaDB</button>
            </div>
            <span id="dbStatus" style="font-size:11px;color:#888;display:none;"></span>
            <button class="btn btn-sm btn-success" onclick="openCreateTable()" title="Create Table"><i class="fas fa-plus"></i> Table</button>
            <button class="btn btn-sm btn-info" onclick="openQuery()"><i class="fas fa-terminal"></i> SQL</button>
        </div>
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
                <button class="btn btn-sm btn-warning" onclick="truncateTable()"><i class="fas fa-eraser"></i> Truncate</button>
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

<div id="createTableModal" class="modal-overlay">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Buat Table Baru</h3>
            <button class="modal-close" onclick="closeCreateTable()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nama Table</label>
                <input type="text" class="form-control" id="newTableName" placeholder="contoh: users">
            </div>
            <div style="font-size:12px;color:#888;margin-bottom:8px;">Columns:</div>
            <div id="newTableColumns">
                <div class="ct-col" style="display:flex;gap:6px;margin-bottom:6px;align-items:center;">
                    <input type="text" class="form-control" style="width:140px;font-size:12px;" placeholder="nama" value="id">
                    <select class="form-control" style="width:110px;font-size:12px;">
                        <option>INT</option>
                        <option selected>TEXT</option>
                        <option>VARCHAR(255)</option>
                        <option>DATETIME</option>
                        <option>BOOLEAN</option>
                        <option>FLOAT</option>
                    </select>
                    <label style="font-size:11px;white-space:nowrap;"><input type="checkbox" checked> PK</label>
                    <label style="font-size:11px;white-space:nowrap;"><input type="checkbox" checked> AI</label>
                    <label style="font-size:11px;white-space:nowrap;"><input type="checkbox"> NN</label>
                    <button class="btn-icon del" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <button class="btn btn-sm btn-outline" onclick="addColumnDef()"><i class="fas fa-plus"></i> Column</button>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeCreateTable()">Batal</button>
            <button class="btn btn-primary" onclick="confirmCreateTable()"><i class="fas fa-check"></i> Buat</button>
        </div>
    </div>
</div>

<div id="dropTableModal" class="modal-overlay">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3><i class="fas fa-trash" style="color:var(--danger);"></i> Hapus Table</h3>
            <button class="modal-close" onclick="closeDropTable()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;margin-bottom:6px;">Yakin ingin menghapus table <strong id="dropTableName"></strong>?</p>
            <p style="font-size:12px;color:var(--danger);">Semua data akan hilang permanen!</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeDropTable()">Batal</button>
            <button class="btn btn-danger" onclick="confirmDropTable()"><i class="fas fa-trash"></i> Hapus</button>
        </div>
    </div>
</div>

<div id="truncateTableModal" class="modal-overlay">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3><i class="fas fa-eraser" style="color:var(--warning);"></i> Truncate Table</h3>
            <button class="modal-close" onclick="closeTruncateTable()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;margin-bottom:6px;">Yakin ingin mengosongkan table <strong id="truncateTableName"></strong>?</p>
            <p style="font-size:12px;color:var(--warning);">Semua baris akan dihapus, tapi struktur table tetap ada.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeTruncateTable()">Batal</button>
            <button class="btn btn-warning" onclick="confirmTruncateTable()"><i class="fas fa-eraser"></i> Truncate</button>
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

<style>
#dbStatus { font-size:11px; animation: fadeIn 0.3s; }
#dbStatus.connected { color:var(--success); }
#dbStatus.error { color:var(--danger); }
</style>

<script>
let currentTable = null;
let currentSchema = null;
let currentPage = 1;
let editingRow = null;
let deletingRow = null;
let dbType = 'sqlite';
const PER_PAGE = 50;

function getDBParam() { return 'db_type=' + dbType; }

// --- DB Switcher ---
async function switchDB(type) {
    dbType = type;
    document.querySelectorAll('.db-switch-btn').forEach(function(b) { b.classList.remove('active'); });
    document.querySelector('.db-switch-btn[data-type="' + type + '"]').classList.add('active');

    var statusEl = document.getElementById('dbStatus');
    statusEl.style.display = 'inline-flex';
    statusEl.className = '';
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghubungi...';

    document.getElementById('tablesContainer').innerHTML = '<p style="color:#888;font-size:13px;">Memuat tabel...</p>';
    document.getElementById('tableViewer').style.display = 'none';
    currentTable = null;

    if (type === 'mariadb') {
        try {
            await AHPL.api('/panel/api/database.php?action=list_tables&' + getDBParam());
            statusEl.className = 'connected';
            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> MariaDB terhubung';
            loadTables();
            loadDbInfo();
        } catch (e) {
            statusEl.className = 'error';
            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> MariaDB: ' + AHPL.escapeHtml(e.message);
            document.getElementById('tablesContainer').innerHTML = '<p style="color:var(--danger);font-size:13px;">Gagal terhubung ke MariaDB: ' + AHPL.escapeHtml(e.message) + '</p>';
            document.getElementById('dbInfoCard').style.display = 'none';
        }
    } else {
        statusEl.style.display = 'none';
        loadTables();
        loadDbInfo();
    }
}

// --- Tables ---
async function loadTables() {
    try {
        const res = await AHPL.api('/panel/api/database.php?action=list_tables&' + getDBParam());
        const el = document.getElementById('tablesContainer');
        if (!res.tables || !res.tables.length) {
            el.innerHTML = '<p style="color:#888;font-size:13px;">Tidak ada tabel</p>';
            return;
        }
        el.innerHTML = '<table class="table"><thead><tr><th>Tabel</th><th>Rows</th><th>Aksi</th></tr></thead><tbody>' +
            res.tables.map(t => '<tr><td style="font-weight:600;"><i class="fas fa-table" style="color:var(--primary);margin-right:8px;"></i>' + AHPL.escapeHtml(t.name) + '</td><td>' + t.row_count + '</td><td style="white-space:nowrap;"><button class="btn btn-sm btn-info" onclick="viewTable(\'' + t.name + '\')" title="View"><i class="fas fa-eye"></i></button> <button class="btn btn-sm btn-danger" onclick="dropTable(\'' + t.name + '\')" title="Drop"><i class="fas fa-trash"></i></button></td></tr>').join('') +
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
        const res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + encodeURIComponent(table) + '&page=' + page + '&per_page=' + PER_PAGE + '&' + getDBParam());
        currentSchema = res.schema;

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
        var res = await AHPL.api('/panel/api/database.php?action=get_schema&table=' + encodeURIComponent(currentTable) + '&' + getDBParam());
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
        var res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + table + '&page=1&per_page=10000000&' + getDBParam());
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
    var bodyData = { db_type: dbType };

    try {
        if (editingRow) {
            bodyData.action = 'update_row';
            bodyData.table = table;
            bodyData.id_column = editingRow.idCol;
            bodyData.id_value = editingRow.idVal;
            bodyData.data = data;
            var res = await AHPL.api('/panel/api/database.php', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
                body: JSON.stringify(bodyData)
            });
            if (res.success) { AHPL.toast('Row diupdate'); closeRowModal(); viewTable(table, currentPage); }
        } else {
            bodyData.action = 'insert_row';
            bodyData.table = table;
            bodyData.data = data;
            var res = await AHPL.api('/panel/api/database.php', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
                body: JSON.stringify(bodyData)
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
            body: JSON.stringify({ action: 'delete_row', table: tbl, id_column: deletingRow.idCol, id_value: deletingRow.idVal, db_type: dbType })
        });
        if (res.success) { AHPL.toast('Row dihapus'); closeDeleteModal(); viewTable(tbl, currentPage); }
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

// --- CSV Export ---
function exportCSV() {
    if (!currentTable) return;
    window.location = '/panel/api/database.php?action=export_csv&table=' + encodeURIComponent(currentTable) + '&' + getDBParam() + '&t=' + Date.now();
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
            body: JSON.stringify({ action: 'query', query: query, db_type: dbType })
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
            body: JSON.stringify({ action: 'query', query: query, confirm_write: true, db_type: dbType })
        });
        resultDiv.innerHTML = '<div style="padding:12px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle"></i> Query executed. Affected rows: ' + res.affected + ' (' + res.elapsed + 's)</div>';
    } catch (e) {
        resultDiv.innerHTML = '<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(e.message) + '</div>';
    }
}

// --- DB Info ---
async function loadDbInfo() {
    try {
        var res = await AHPL.api('/panel/api/database.php?action=db_info&' + getDBParam());
        if (!res.info) return;
        var info = res.info;
        document.getElementById('dbInfoCard').style.display = 'block';
        document.getElementById('dbInfoType').textContent = info.type.toUpperCase();
        document.getElementById('dbInfoVersion').textContent = info.version || '-';
        document.getElementById('dbInfoTables').textContent = info.table_count || '0';
        document.getElementById('dbInfoSize').textContent = (info.size_kb || '0') + ' KB';
        if (info.uptime && info.uptime > 0) {
            document.getElementById('dbInfoUptimeRow').style.display = 'inline';
            var days = Math.floor(info.uptime / 86400);
            var hours = Math.floor((info.uptime % 86400) / 3600);
            var mins = Math.floor((info.uptime % 3600) / 60);
            document.getElementById('dbInfoUptime').textContent = (days > 0 ? days + 'd ' : '') + hours + 'h ' + mins + 'm';
        } else {
            document.getElementById('dbInfoUptimeRow').style.display = 'none';
        }
    } catch (e) {}
}

// --- Drop Table ---
var _dropTable = null;
function dropTable(name) {
    _dropTable = name;
    document.getElementById('dropTableName').textContent = name;
    document.getElementById('dropTableModal').classList.add('active');
}
function closeDropTable() { document.getElementById('dropTableModal').classList.remove('active'); _dropTable = null; }
async function confirmDropTable() {
    if (!_dropTable) return;
    try {
        var res = await AHPL.api('/panel/api/database.php', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'drop_table', table: _dropTable, db_type: dbType })
        });
        if (res.success) { AHPL.toast('Table ' + _dropTable + ' dihapus'); closeDropTable(); loadTables(); loadDbInfo(); }
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

// --- Truncate Table ---
var _truncateTable = null;
function truncateTable() {
    if (!currentTable) return;
    _truncateTable = currentTable;
    document.getElementById('truncateTableName').textContent = currentTable;
    document.getElementById('truncateTableModal').classList.add('active');
}
function closeTruncateTable() { document.getElementById('truncateTableModal').classList.remove('active'); _truncateTable = null; }
async function confirmTruncateTable() {
    if (!_truncateTable) return;
    try {
        var res = await AHPL.api('/panel/api/database.php', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'truncate_table', table: _truncateTable, db_type: dbType })
        });
        if (res.success) { AHPL.toast('Table ' + _truncateTable + ' dikosongkan'); closeTruncateTable(); viewTable(currentTable, currentPage); loadTables(); }
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

// --- Create Table ---
function openCreateTable() {
    document.getElementById('newTableName').value = '';
    document.getElementById('newTableColumns').innerHTML = '';
    addColumnDef();
    document.getElementById('createTableModal').classList.add('active');
}
function closeCreateTable() { document.getElementById('createTableModal').classList.remove('active'); }
function addColumnDef() {
    var cont = document.getElementById('newTableColumns');
    var idx = cont.children.length;
    var div = document.createElement('div');
    div.className = 'ct-col';
    div.style.cssText = 'display:flex;gap:6px;margin-bottom:6px;align-items:center;';
    div.innerHTML =
        '<input type="text" class="form-control" style="width:140px;font-size:12px;" placeholder="nama" value="column' + (idx + 1) + '">' +
        '<select class="form-control" style="width:110px;font-size:12px;">' +
            '<option>TEXT</option><option>INT</option><option>VARCHAR(255)</option><option>DATETIME</option><option>BOOLEAN</option><option>FLOAT</option>' +
        '</select>' +
        '<label style="font-size:11px;white-space:nowrap;"><input type="checkbox"> PK</label>' +
        '<label style="font-size:11px;white-space:nowrap;"><input type="checkbox"> AI</label>' +
        '<label style="font-size:11px;white-space:nowrap;"><input type="checkbox"> NN</label>' +
        '<button class="btn-icon del" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
    cont.appendChild(div);
}
async function confirmCreateTable() {
    var name = document.getElementById('newTableName').value.trim();
    if (!name) { AHPL.toast('Nama table wajib diisi', 'error'); return; }
    var colDivs = document.querySelectorAll('#newTableColumns .ct-col');
    var columns = [];
    colDivs.forEach(function(d) {
        var inputs = d.querySelectorAll('input[type=text], select');
        if (inputs.length < 2) return;
        var colName = inputs[0].value.trim();
        var colType = inputs[1].value;
        if (!colName) return;
        var chks = d.querySelectorAll('input[type=checkbox]');
        columns.push({
            name: colName,
            type: colType,
            pk: chks[0] ? chks[0].checked : false,
            auto: chks[1] ? chks[1].checked : false,
            notnull: chks[2] ? chks[2].checked : false,
        });
    });
    if (columns.length === 0) { AHPL.toast('Minimal 1 kolom', 'error'); return; }
    try {
        var res = await AHPL.api('/panel/api/database.php', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'create_table', table: name, columns: columns, db_type: dbType })
        });
        if (res.success) { AHPL.toast('Table ' + name + ' dibuat!'); closeCreateTable(); loadTables(); loadDbInfo(); }
    } catch (e) { AHPL.toast(e.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', loadTables);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
