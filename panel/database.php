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
            <div style="display:flex;gap:6px;">
                <button class="btn btn-sm btn-outline" onclick="showSchema()"><i class="fas fa-info-circle"></i> Schema</button>
                <button class="btn btn-sm btn-outline" onclick="exportCSV()"><i class="fas fa-file-csv"></i> Export CSV</button>
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

<div id="queryModal" class="modal-overlay">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-terminal"></i> SQL Query</h3>
            <button class="modal-close" onclick="closeQuery()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#888;margin-bottom:10px;">Hanya query <code>SELECT</code>, <code>PRAGMA</code>, dan <code>EXPLAIN</code> yang diizinkan.</p>
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
let currentPage = 1;
const PER_PAGE = 50;

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

async function viewTable(table, page) {
    page = page || 1;
    currentTable = table;
    currentPage = page;
    document.getElementById('currentTableName').textContent = table;
    document.getElementById('tableViewer').style.display = 'block';
    document.getElementById('tableContent').innerHTML = '<p style="color:#888;font-size:13px;">Memuat data...</p>';

    try {
        const res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + encodeURIComponent(table) + '&page=' + page + '&per_page=' + PER_PAGE);
        let html = '<table class="table"><thead><tr>';
        res.columns.forEach(c => { html += '<th>' + AHPL.escapeHtml(c) + '</th>'; });
        html += '</tr></thead><tbody>';
        if (!res.rows.length) {
            html += '<tr><td colspan="' + res.columns.length + '" style="text-align:center;color:#888;">Kosong</td></tr>';
        } else {
            res.rows.forEach(row => {
                html += '<tr>';
                row.forEach(val => {
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

        const totalPages = Math.ceil(res.total / PER_PAGE);
        if (totalPages <= 1) {
            document.getElementById('tablePagination').innerHTML = '';
            return;
        }
        let pagHtml = '<span style="font-size:12px;color:#888;margin-right:6px;">Halaman:</span>';
        for (let i = 1; i <= totalPages; i++) {
            pagHtml += '<button class="btn btn-sm ' + (i === page ? 'btn-primary' : 'btn-outline') + '" onclick="viewTable(\'' + table + '\', ' + i + ')">' + i + '</button>';
        }
        document.getElementById('tablePagination').innerHTML = pagHtml;
    } catch (e) {
        document.getElementById('tableContent').innerHTML = '<p style="color:var(--danger);font-size:13px;">' + AHPL.escapeHtml(e.message) + '</p>';
    }
}

function closeTable() {
    document.getElementById('tableViewer').style.display = 'none';
    currentTable = null;
}

async function showSchema() {
    if (!currentTable) return;
    try {
        const res = await AHPL.api('/panel/api/database.php?action=get_schema&table=' + encodeURIComponent(currentTable));
        document.getElementById('schemaTableName').textContent = currentTable;
        document.getElementById('schemaContent').innerHTML = '<table class="table"><thead><tr><th>#</th><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th><th>PK</th></tr></thead><tbody>' +
            res.schema.map(s => '<tr><td>' + s.cid + '</td><td style="font-weight:600;">' + AHPL.escapeHtml(s.name) + '</td><td><code>' + AHPL.escapeHtml(s.type || 'N/A') + '</code></td><td>' + (s.notnull ? '<span class="badge badge-danger">NO</span>' : '<span class="badge badge-success">YES</span>') + '</td><td style="font-style:italic;color:#888;">' + (s.dflt_value !== null ? AHPL.escapeHtml(s.dflt_value) : '\u2014') + '</td><td>' + (s.pk ? '<span class="badge badge-info">PK</span>' : '') + '</td></tr>').join('') +
            '</tbody></table>';
        document.getElementById('schemaModal').classList.add('active');
    } catch (e) {
        AHPL.toast(e.message, 'error');
    }
}

function closeSchema() {
    document.getElementById('schemaModal').classList.remove('active');
}

function openQuery() {
    document.getElementById('sqlQuery').value = '';
    document.getElementById('queryResult').innerHTML = '';
    document.getElementById('queryModal').classList.add('active');
    setTimeout(function () { document.getElementById('sqlQuery').focus(); }, 150);
}

function closeQuery() {
    document.getElementById('queryModal').classList.remove('active');
}

async function executeQuery() {
    const query = document.getElementById('sqlQuery').value.trim();
    if (!query) return AHPL.toast('Masukkan query', 'error');

    const resultDiv = document.getElementById('queryResult');
    resultDiv.innerHTML = '<p style="color:#888;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Running...</p>';

    try {
        const res = await AHPL.api('/panel/api/database.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'query', query: query })
        });

        if (res.columns && res.columns.length) {
            let html = '<div style="margin-bottom:8px;font-size:12px;color:#666;">' + res.rows.length + ' row(s) in ' + res.elapsed + 's</div>';
            html += '<div style="overflow-x:auto;"><table class="table"><thead><tr>';
            res.columns.forEach(function (c) { html += '<th>' + AHPL.escapeHtml(c) + '</th>'; });
            html += '</tr></thead><tbody>';
            if (!res.rows.length) {
                html += '<tr><td colspan="' + res.columns.length + '" style="text-align:center;color:#888;">Kosong</td></tr>';
            } else {
                res.rows.forEach(function (row) {
                    html += '<tr>';
                    row.forEach(function (val) {
                        if (val === null) {
                            html += '<td style="font-size:12px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><span style="color:#ccc;font-style:italic;">NULL</span></td>';
                        } else {
                            html += '<td style="font-size:12px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + AHPL.escapeHtml(String(val)) + '</td>';
                        }
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
        resultDiv.innerHTML = '<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(e.message) + '</div>';
    }
}

function exportCSV() {
    if (!currentTable) return;
    window.location = '/panel/api/database.php?action=export_csv&table=' + encodeURIComponent(currentTable) + '&t=' + Date.now();
}

document.addEventListener('DOMContentLoaded', loadTables);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
