<?php
$pageTitle = 'phpMyAdmin';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.phpmyadmin { --pmabg: #f3f3f3; }
.pma-header { background: linear-gradient(135deg,#007bff,#0056b3); color:#fff; border-radius:10px; padding:18px 22px; margin-bottom:20px; display:flex; align-items:center; gap:24px; flex-wrap:wrap; }
.pma-header .brand { font-size:18px; font-weight:700; display:flex; align-items:center; gap:8px; }
.pma-header .brand i { font-size:22px; }
.pma-header .info { display:flex; gap:18px; font-size:12px; opacity:.9; flex-wrap:wrap; }
.pma-header .info span { display:flex; align-items:center; gap:4px; }
.pma-header .info strong { font-weight:600; }
#dbStatus { display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:4px 12px; border-radius:20px; }
#dbStatus.loading { background:rgba(255,255,255,.2); }
#dbStatus.connected { background:rgba(40,167,69,.3); }
#dbStatus.error { background:rgba(220,53,69,.3); }
.db-card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:18px; overflow:hidden; }
.db-card-head { padding:14px 18px; border-bottom:1px solid #e9ecef; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.db-card-head h3 { font-size:15px; font-weight:600; margin-right:auto; }
.db-card-head .actions { display:flex; gap:6px; }
.db-card-body { padding:14px 18px; overflow-x:auto; }
.table-list td { vertical-align:middle; }
.table-list .tname { font-weight:600; }
.table-list .tname i { color:var(--primary); margin-right:8px; }
.tab-bar { display:flex; gap:2px; background:#f0f0f0; padding:3px; border-radius:8px; margin-bottom:14px; }
.tab-btn { padding:6px 16px; border:none; border-radius:6px; font-size:12px; cursor:pointer; background:transparent; color:#666; font-weight:500; }
.tab-btn.active { background:#fff; color:#333; box-shadow:0 1px 3px rgba(0,0,0,.1); }
.tab-btn:hover:not(.active) { background:rgba(0,0,0,.05); }
.pma-table td, .pma-table th { font-size:12px; }
.pma-table .null { color:#aaa; font-style:italic; }
.pma-table .act-cell { white-space:nowrap; width:70px; }
.filter-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.filter-bar input { padding:6px 10px; border:1px solid #ccc; border-radius:6px; font-size:12px; }
</style>

<div id="app">
  <!-- Server Info Header -->
  <div class="pma-header">
    <div class="brand"><i class="fas fa-server"></i> MariaDB</div>
    <div class="info">
      <span><i class="fas fa-tag"></i> <strong id="hdrVersion">-</strong></span>
      <span><i class="fas fa-table"></i> Tables: <strong id="hdrTables">0</strong></span>
      <span><i class="fas fa-database"></i>
        <select id="dbSelector" style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:4px;padding:3px 8px;font-size:13px;font-weight:600;cursor:pointer;" onchange="switchDB(this.value)"></select>
      </span>
      <span><i class="fas fa-hdd"></i> Size: <strong id="hdrSize">0 KB</strong></span>
      <span id="hdrUptimeRow"><i class="fas fa-clock"></i> Uptime: <strong id="hdrUptime">-</strong></span>
    </div>
    <span id="dbStatus" class="loading"><i class="fas fa-spinner fa-spin"></i> Connecting...</span>
  </div>

  <!-- Table List -->
  <div class="db-card" id="tableListCard">
    <div class="db-card-head">
      <h3><i class="fas fa-list"></i> Tables</h3>
      <div class="actions">
        <button class="btn btn-sm btn-primary" onclick="openNewDB()"><i class="fas fa-plus-circle"></i> New DB</button>
        <button class="btn btn-sm btn-success" onclick="openCreateTable()"><i class="fas fa-plus"></i> New Table</button>
        <button class="btn btn-sm btn-info" onclick="openQuery()"><i class="fas fa-terminal"></i> SQL</button>
        <button class="btn btn-sm btn-outline" onclick="init()"><i class="fas fa-sync"></i></button>
      </div>
    </div>
    <div class="db-card-body" id="tablesContainer">
      <p style="color:#888;font-size:13px;">Memuat...</p>
    </div>
  </div>

  <!-- Table Viewer -->
  <div id="tableViewer" style="display:none;">
    <div class="db-card">
      <div class="db-card-head">
        <h3><i class="fas fa-database"></i> <span id="currentTableName"></span></h3>
        <div class="actions">
          <button class="btn btn-sm btn-success" onclick="addRow()"><i class="fas fa-plus"></i> Insert</button>
          <button class="btn btn-sm btn-warning" onclick="truncateTable()"><i class="fas fa-eraser"></i> Empty</button>
          <button class="btn btn-sm btn-outline" onclick="exportCSV()"><i class="fas fa-file-csv"></i> Export</button>
          <button class="btn btn-sm btn-danger" onclick="closeTable()"><i class="fas fa-times"></i></button>
        </div>
      </div>
      <div class="db-card-body">
        <div class="tab-bar">
          <button class="tab-btn active" id="tabBrowse" onclick="switchTab('browse')"><i class="fas fa-table"></i> Browse</button>
          <button class="tab-btn" id="tabStructure" onclick="switchTab('structure')"><i class="fas fa-info-circle"></i> Structure</button>
        </div>
        <div id="browseTab">
          <div class="filter-bar" style="margin-bottom:10px;">
            <input type="text" id="searchInput" placeholder="Cari..." style="flex:1;">
            <button class="btn btn-sm btn-outline" onclick="viewTable(currentTable, 1)"><i class="fas fa-search"></i></button>
          </div>
          <div id="tableContent">Memuat...</div>
          <div id="tablePagination" style="margin-top:12px;display:flex;justify-content:center;gap:6px;flex-wrap:wrap;"></div>
        </div>
        <div id="structureTab" style="display:none;">
          <div id="schemaContent">Memuat...</div>
          <div style="margin-top:12px;">
            <button class="btn btn-sm btn-outline" onclick="addColumn()"><i class="fas fa-plus"></i> Add Column</button>
            <button class="btn btn-sm btn-danger" onclick="dropTable(currentTable)" style="float:right;"><i class="fas fa-trash"></i> Drop Table</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modals -->
<div id="newDBModal" class="modal-overlay">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header"><h3><i class="fas fa-plus-circle"></i> New Database</h3><button class="modal-close" onclick="closeNewDB()">&times;</button></div>
    <div class="modal-body">
      <div class="form-group"><label>Database name</label><input type="text" class="form-control" id="newDBName" placeholder="contoh: db_toko" style="text-transform:lowercase;"></div>
      <p style="font-size:12px;color:#888;">Hanya huruf, angka, dan underscore.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeNewDB()">Cancel</button>
      <button class="btn btn-primary" onclick="confirmNewDB()">Create</button>
    </div>
  </div>
</div>

<div id="createTableModal" class="modal-overlay">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Create Table</h3><button class="modal-close" onclick="closeCreateTable()">&times;</button></div>
    <div class="modal-body">
      <div class="form-group"><label>Table name</label><input type="text" class="form-control" id="newTableName" placeholder="contoh: users"></div>
      <div style="font-size:12px;color:#888;margin-bottom:8px;">Columns:</div>
      <div id="newTableColumns"></div>
      <button class="btn btn-sm btn-outline" onclick="addColumnDef()"><i class="fas fa-plus"></i> Add column</button>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeCreateTable()">Cancel</button>
      <button class="btn btn-primary" onclick="confirmCreateTable()">Create</button>
    </div>
  </div>
</div>

<div id="addColumnModal" class="modal-overlay">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header"><h3><i class="fas fa-plus"></i> Add Column</h3><button class="modal-close" onclick="closeAddColumn()">&times;</button></div>
    <div class="modal-body">
      <div class="form-group"><label>Name</label><input type="text" class="form-control" id="acName" placeholder="column_name"></div>
      <div class="form-group"><label>Type</label>
        <select class="form-control" id="acType">
          <option>INT</option><option selected>VARCHAR(255)</option><option>TEXT</option><option>DATETIME</option>
          <option>BOOLEAN</option><option>FLOAT</option><option>BIGINT</option><option>LONGTEXT</option>
        </select>
      </div>
      <div style="display:flex;gap:16px;margin-top:8px;">
        <label style="font-size:13px;"><input type="checkbox" id="acPk"> PRIMARY KEY</label>
        <label style="font-size:13px;"><input type="checkbox" id="acAi"> AUTO_INCREMENT</label>
        <label style="font-size:13px;"><input type="checkbox" id="acNn"> NOT NULL</label>
      </div>
      <div class="form-group" style="margin-top:8px;"><label>Default (optional)</label><input type="text" class="form-control" id="acDefault" placeholder="NULL"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeAddColumn()">Cancel</button>
      <button class="btn btn-primary" onclick="confirmAddColumn()">Add</button>
    </div>
  </div>
</div>

<div id="rowModal" class="modal-overlay">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header"><h3><i class="fas fa-edit"></i> <span id="rowModalTitle">Edit Row</span></h3><button class="modal-close" onclick="closeRowModal()">&times;</button></div>
    <div class="modal-body" id="rowFormContainer"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeRowModal()">Cancel</button>
      <button class="btn btn-primary" id="rowSaveBtn" onclick="saveRow()">Save</button>
    </div>
  </div>
</div>

<div id="deleteModal" class="modal-overlay">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3 style="color:var(--danger);"><i class="fas fa-trash"></i> Delete Row</h3><button class="modal-close" onclick="closeDeleteModal()">&times;</button></div>
    <div class="modal-body">
      <p style="font-size:14px;margin-bottom:6px;">Yakin ingin menghapus row ini?</p>
      <p style="font-size:12px;color:#888;" id="deletePreview"></p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()">Delete</button>
    </div>
  </div>
</div>

<div id="dropTableModal" class="modal-overlay">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3 style="color:var(--danger);"><i class="fas fa-trash"></i> Drop Table</h3><button class="modal-close" onclick="closeDropTable()">&times;</button></div>
    <div class="modal-body">
      <p style="font-size:14px;margin-bottom:6px;">Yakin ingin menghapus table <strong id="dropTableName"></strong>?</p>
      <p style="font-size:12px;color:var(--danger);">Semua data akan hilang permanen!</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeDropTable()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDropTable()">Drop</button>
    </div>
  </div>
</div>

<div id="truncateTableModal" class="modal-overlay">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3 style="color:var(--warning);"><i class="fas fa-eraser"></i> Empty Table</h3><button class="modal-close" onclick="closeTruncateTable()">&times;</button></div>
    <div class="modal-body">
      <p style="font-size:14px;margin-bottom:6px;">Yakin ingin mengosongkan <strong id="truncateTableName"></strong>?</p>
      <p style="font-size:12px;color:var(--warning);">Semua baris dihapus, struktur tetap.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeTruncateTable()">Cancel</button>
      <button class="btn btn-warning" onclick="confirmTruncateTable()">Empty</button>
    </div>
  </div>
</div>

<div id="queryModal" class="modal-overlay">
  <div class="modal" style="max-width:800px;">
    <div class="modal-header"><h3><i class="fas fa-terminal"></i> SQL Query</h3><button class="modal-close" onclick="closeQuery()">&times;</button></div>
    <div class="modal-body">
      <div class="form-group">
        <textarea class="form-control" id="sqlQuery" rows="5" style="font-family:monospace;font-size:13px;" placeholder="SELECT * FROM ..."></textarea>
      </div>
      <button class="btn btn-primary" onclick="executeQuery()"><i class="fas fa-play"></i> Go</button>
      <div id="queryResult" style="margin-top:12px;overflow-x:auto;"></div>
    </div>
  </div>
</div>

<script>
let currentTable = null, currentSchema = null, currentPage = 1;
let editingRow = null, deletingRow = null;
let _dropTable = null, _truncateTable = null;
let currentDB = '<?= MARIADB_NAME ?>';
const PER_PAGE = 50;

function dbp() { return 'db_type=mariadb&db_name=' + encodeURIComponent(currentDB); }

async function loadDatabases() {
  try {
    var res = await AHPL.api('/panel/api/database.php?action=list_databases&db_type=mariadb');
    var sel = document.getElementById('dbSelector');
    sel.innerHTML = '';
    res.databases.forEach(function(d) {
      var o = document.createElement('option');
      o.value = d; o.textContent = d;
      if (d === currentDB) o.selected = true;
      sel.appendChild(o);
    });
  } catch(e) {}
}

function switchDB(name) {
  currentDB = name;
  document.getElementById('tableViewer').style.display = 'none';
  currentTable = null;
  var el = document.getElementById('dbStatus');
  el.className = 'loading';
  el.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Switching...';
  loadDbInfo();
  loadTables();
}

function init() {
  var el = document.getElementById('dbStatus');
  el.className = 'loading';
  el.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
  document.getElementById('tablesContainer').innerHTML = '<p style="color:#888;font-size:13px;">Memuat...</p>';
  document.getElementById('tableViewer').style.display = 'none';
  currentTable = null;

  loadDatabases();
  loadDbInfo();
  loadTables();
}

// --- DB Info ---
async function loadDbInfo() {
  try {
    var res = await AHPL.api('/panel/api/database.php?action=db_info&' + dbp());
    if (!res.info) return;
    var i = res.info;
    document.getElementById('hdrVersion').textContent = i.version || '-';
    document.getElementById('hdrTables').textContent = i.table_count || '0';
    document.getElementById('hdrSize').textContent = (i.size_kb || '0') + ' KB';
    if (i.uptime && i.uptime > 0) {
      var d = Math.floor(i.uptime / 86400), h = Math.floor((i.uptime % 86400) / 3600), m = Math.floor((i.uptime % 3600) / 60);
      document.getElementById('hdrUptime').textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
      document.getElementById('hdrUptimeRow').style.display = '';
    }
    var el = document.getElementById('dbStatus');
    el.className = 'connected';
    el.innerHTML = '<i class="fas fa-check-circle"></i> Connected';
  } catch(e) {
    document.getElementById('dbStatus').className = 'error';
    document.getElementById('dbStatus').innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + e.message;
  }
}

// --- Tables ---
async function loadTables() {
  try {
    var res = await AHPL.api('/panel/api/database.php?action=list_tables&' + dbp());
    var el = document.getElementById('tablesContainer');
    if (!res.tables || !res.tables.length) {
      el.innerHTML = '<p style="color:#888;font-size:13px;text-align:center;padding:20px;">Tidak ada tabel</p>';
      return;
    }
    el.innerHTML = '<table class="table table-list"><thead><tr><th>Table</th><th>Rows</th><th>Action</th></tr></thead><tbody>' +
      res.tables.map(function(t) {
        return '<tr><td class="tname"><i class="fas fa-table"></i>' + AHPL.escapeHtml(t.name) + '</td><td>' + t.row_count + '</td><td style="white-space:nowrap;">' +
          '<button class="btn btn-sm btn-info" onclick="viewTable(\'' + t.name + '\')" title="Browse"><i class="fas fa-search"></i></button> ' +
          '<button class="btn btn-sm btn-outline" onclick="showStructure(\'' + t.name + '\')" title="Structure"><i class="fas fa-list"></i></button> ' +
          '<button class="btn btn-sm btn-danger" onclick="dropTable(\'' + t.name + '\')" title="Drop"><i class="fas fa-trash"></i></button></td></tr>';
      }).join('') +
      '</tbody></table>';
  } catch(e) {
    document.getElementById('tablesContainer').innerHTML = '<p style="color:var(--danger);font-size:13px;">' + AHPL.escapeHtml(e.message) + '</p>';
  }
}

// --- Show Structure directly ---
async function showStructure(table) {
  currentTable = table;
  document.getElementById('currentTableName').textContent = table;
  document.getElementById('tableViewer').style.display = 'block';
  switchTab('structure');
  try {
    var res = await AHPL.api('/panel/api/database.php?action=get_schema&table=' + encodeURIComponent(table) + '&' + dbp());
    currentSchema = res.schema;
    document.getElementById('schemaContent').innerHTML = '<table class="table pma-table"><thead><tr><th>#</th><th>Column</th><th>Type</th><th>Collation</th><th>Null</th><th>Default</th><th>Extra</th></tr></thead><tbody>' +
      res.schema.map(function(s, i) {
        return '<tr><td>' + (i + 1) + '</td><td style="font-weight:600;">' + AHPL.escapeHtml(s.name) + '</td><td><code>' + AHPL.escapeHtml(s.type || '') + '</code></td><td>-</td><td>' + (s.notnull ? 'No' : 'Yes') + '</td><td style="font-style:italic;color:#888;">' + (s.dflt_value !== null ? AHPL.escapeHtml(s.dflt_value) : 'NULL') + '</td><td>' + (s.pk ? 'PRI' : '') + '</td></tr>';
      }).join('') +
      '</tbody></table>';
  } catch(e) { AHPL.toast(e.message, 'error'); }
}

// --- View Table ---
async function viewTable(table, page) {
  page = page || 1;
  currentTable = table;
  currentPage = page;
  document.getElementById('currentTableName').textContent = table;
  document.getElementById('tableViewer').style.display = 'block';
  switchTab('browse');
  document.getElementById('tableContent').innerHTML = '<p style="color:#888;font-size:13px;">Loading...</p>';

  try {
    var res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + encodeURIComponent(table) + '&page=' + page + '&per_page=' + PER_PAGE + '&' + dbp());
    currentSchema = res.schema;
    var pkCol = null;
    for (var i = 0; i < res.schema.length; i++) { if (res.schema[i].pk) { pkCol = res.schema[i]; break; } }

    var html = '<table class="table pma-table"><thead><tr>';
    if (pkCol) html += '<th class="act-cell">Action</th>';
    res.columns.forEach(function(c) { html += '<th>' + AHPL.escapeHtml(c) + '</th>'; });
    html += '</tr></thead><tbody>';

    if (!res.rows.length) {
      html += '<tr><td colspan="' + (res.columns.length + (pkCol ? 1 : 0)) + '" style="text-align:center;color:#888;padding:30px;">Empty</td></tr>';
    } else {
      res.rows.forEach(function(row) {
        html += '<tr>';
        if (pkCol) {
          var idx = res.columns.indexOf(pkCol.name), val = idx >= 0 ? row[idx] : '';
          var encVal = encodeURIComponent(String(val)), encT = encodeURIComponent(table);
          html += '<td class="act-cell"><button class="btn-icon" onclick="editRow(\'' + encT + '\',\'' + AHPL.escapeHtml(pkCol.name) + '\',\'' + encVal + '\')" title="Edit"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="showDelete(\'' + encT + '\',\'' + AHPL.escapeHtml(pkCol.name) + '\',\'' + encVal + '\')" title="Delete"><i class="fas fa-trash"></i></button></td>';
        }
        row.forEach(function(val) {
          if (val === null) { html += '<td class="null">NULL</td>'; }
          else { html += '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + AHPL.escapeHtml(String(val)) + '">' + AHPL.escapeHtml(String(val)) + '</td>'; }
        });
        html += '</tr>';
      });
    }
    html += '</tbody></table>';
    document.getElementById('tableContent').innerHTML = html;

    var totalPages = Math.ceil(res.total / PER_PAGE);
    if (totalPages <= 1) { document.getElementById('tablePagination').innerHTML = ''; return; }
    var pg = '<span style="font-size:12px;color:#888;margin-right:6px;">Page:</span>';
    for (var i = 1; i <= totalPages; i++) {
      pg += '<button class="btn btn-sm ' + (i === page ? 'btn-primary' : 'btn-outline') + '" onclick="viewTable(\'' + table + '\', ' + i + ')">' + i + '</button>';
    }
    document.getElementById('tablePagination').innerHTML = pg;
  } catch(e) {
    document.getElementById('tableContent').innerHTML = '<p style="color:var(--danger);font-size:13px;">' + AHPL.escapeHtml(e.message) + '</p>';
  }
}

function switchTab(tab) {
  document.getElementById('tabBrowse').className = 'tab-btn' + (tab === 'browse' ? ' active' : '');
  document.getElementById('tabStructure').className = 'tab-btn' + (tab === 'structure' ? ' active' : '');
  document.getElementById('browseTab').style.display = tab === 'browse' ? '' : 'none';
  document.getElementById('structureTab').style.display = tab === 'structure' ? '' : 'none';
  if (tab === 'structure' && currentTable) showStructure(currentTable);
}

function closeTable() { document.getElementById('tableViewer').style.display = 'none'; currentTable = null; currentSchema = null; }

// --- Row CRUD ---
function buildRowForm(schema, data, isEdit) {
  var pkCol = null;
  for (var i = 0; i < schema.length; i++) { if (schema[i].pk) { pkCol = schema[i]; break; } }
  var html = '';
  for (var i = 0; i < schema.length; i++) {
    var col = schema[i];
    if (col.pk) {
      var val = data && data[col.name] !== undefined ? data[col.name] : '';
      html += '<div class="form-group"><label>' + AHPL.escapeHtml(col.name) + ' <span style="color:#888;font-size:11px;">[' + (col.type || '') + ' PK]</span></label>';
      html += '<input type="text" class="form-control" id="rf-' + col.name + '" value="' + AHPL.escapeHtml(String(val)) + '" ' + (isEdit ? '' : 'readonly style="background:#f5f5f5;color:#999;"') + '></div>';
      continue;
    }
    var val = data && data[col.name] !== undefined ? data[col.name] : col.dflt_value || '';
    var required = col.notnull ? 'required' : '';
    html += '<div class="form-group"><label>' + AHPL.escapeHtml(col.name) + ' <span style="color:#888;font-size:11px;">' + (col.type || '') + (col.notnull ? ' *' : '') + '</span></label>';
    var isText = col.type && (col.type.toUpperCase().includes('TEXT') || col.type.toUpperCase().includes('CHAR'));
    if (isText && (!val || val.length > 100)) {
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
  for (var i = 0; i < schema.length; i++) { var e = document.getElementById('rf-' + schema[i].name); if (e) data[schema[i].name] = e.value; }
  return data;
}
function addRow() {
  editingRow = null;
  document.getElementById('rowModalTitle').textContent = 'Insert Row - ' + currentTable;
  document.getElementById('rowSaveBtn').innerHTML = '<i class="fas fa-plus"></i> Insert';
  document.getElementById('rowFormContainer').innerHTML = buildRowForm(currentSchema, null, false);
  document.getElementById('rowModal').classList.add('active');
}
async function editRow(table, idCol, idVal) {
  try {
    var res = await AHPL.api('/panel/api/database.php?action=get_table&table=' + table + '&page=1&per_page=10000000&' + dbp());
    var idx = res.columns.indexOf(idCol);
    if (idx < 0) { AHPL.toast('ID column not found', 'error'); return; }
    var rowData = null;
    for (var i = 0; i < res.rows.length; i++) { if (String(res.rows[i][idx]) === decodeURIComponent(idVal)) { rowData = res.rows[i]; break; } }
    if (!rowData) { AHPL.toast('Row not found', 'error'); return; }
    var data = {};
    for (var j = 0; j < res.columns.length; j++) { data[res.columns[j]] = rowData[j]; }
    editingRow = { table: table, idCol: idCol, idVal: decodeURIComponent(idVal), schema: res.schema };
    document.getElementById('rowModalTitle').textContent = 'Edit Row - ' + table;
    document.getElementById('rowSaveBtn').innerHTML = '<i class="fas fa-save"></i> Save';
    document.getElementById('rowFormContainer').innerHTML = buildRowForm(res.schema, data, true);
    document.getElementById('rowModal').classList.add('active');
  } catch(e) { AHPL.toast(e.message, 'error'); }
}
function closeRowModal() { document.getElementById('rowModal').classList.remove('active'); }
async function saveRow() {
  var data = getFormData(editingRow ? editingRow.schema : currentSchema);
  var table = editingRow ? editingRow.table : currentTable;
  var body = { db_type: 'mariadb', db_name: currentDB };
  try {
    if (editingRow) {
      body.action = 'update_row'; body.table = table; body.id_column = editingRow.idCol; body.id_value = editingRow.idVal; body.data = data;
      var res = await AHPL.api('/panel/api/database.php', { method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }, body: JSON.stringify(body) });
      if (res.success) { AHPL.toast('Row updated'); closeRowModal(); viewTable(table, currentPage); }
    } else {
      body.action = 'insert_row'; body.table = table; body.data = data;
      var res = await AHPL.api('/panel/api/database.php', { method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }, body: JSON.stringify(body) });
      if (res.success) { AHPL.toast('Row inserted'); closeRowModal(); viewTable(table, currentPage); }
    }
  } catch(e) { AHPL.toast(e.message, 'error'); }
}
// --- Delete Row ---
function showDelete(table, idCol, idVal) { deletingRow = { table: table, idCol: idCol, idVal: decodeURIComponent(idVal) }; document.getElementById('deletePreview').textContent = idCol + ' = ' + decodeURIComponent(idVal); document.getElementById('deleteModal').classList.add('active'); }
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); deletingRow = null; }
async function confirmDelete() {
  if (!deletingRow) return;
  try {
    var res = await AHPL.api('/panel/api/database.php', { method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }, body: JSON.stringify({ action: 'delete_row', table: deletingRow.table, id_column: deletingRow.idCol, id_value: deletingRow.idVal, db_type: 'mariadb', db_name: currentDB }) });
    if (res.success) { AHPL.toast('Row deleted'); closeDeleteModal(); viewTable(deletingRow.table, currentPage); }
  } catch(e) { AHPL.toast(e.message, 'error'); }
}
// --- CSV ---
function exportCSV() { if (!currentTable) return; window.location = '/panel/api/database.php?action=export_csv&table=' + encodeURIComponent(currentTable) + '&' + dbp() + '&t=' + Date.now(); }

// --- SQL Query ---
function openQuery() { document.getElementById('sqlQuery').value = ''; document.getElementById('queryResult').innerHTML = ''; document.getElementById('queryModal').classList.add('active'); setTimeout(function() { document.getElementById('sqlQuery').focus(); }, 150); }
function closeQuery() { document.getElementById('queryModal').classList.remove('active'); }
async function executeQuery() {
  var q = document.getElementById('sqlQuery').value.trim();
  if (!q) return AHPL.toast('Enter query', 'error');
  var rd = document.getElementById('queryResult');
  rd.innerHTML = '<p style="color:#888;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Running...</p>';
  try {
    var res = await AHPL.api('/panel/api/database.php', { method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }, body: JSON.stringify({ action: 'query', query: q, db_type: 'mariadb', db_name: currentDB }) });
    if (res.columns && res.columns.length) {
      var h = '<div style="margin-bottom:8px;font-size:12px;color:#666;">' + res.rows.length + ' row(s) in ' + res.elapsed + 's</div><div style="overflow-x:auto;"><table class="table pma-table"><thead><tr>';
      res.columns.forEach(function(c) { h += '<th>' + AHPL.escapeHtml(c) + '</th>'; });
      h += '</tr></thead><tbody>';
      if (!res.rows.length) { h += '<tr><td colspan="' + res.columns.length + '" style="text-align:center;color:#888;padding:20px;">Empty</td></tr>'; }
      else { res.rows.forEach(function(row) { h += '<tr>'; row.forEach(function(v) { if (v === null) h += '<td class="null">NULL</td>'; else h += '<td style="max-width:300px;">' + AHPL.escapeHtml(String(v)) + '</td>'; }); h += '</tr>'; }); }
      h += '</tbody></table></div>';
      rd.innerHTML = h;
    } else {
      rd.innerHTML = '<div style="padding:12px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle"></i> Query executed. Affected: ' + res.affected + ' (' + res.elapsed + 's)</div>';
    }
  } catch(e) {
    if (e.message && e.message.indexOf('require_confirm') >= 0) {
      rd.innerHTML = '<div style="padding:12px;background:#fff3cd;color:#856404;border-radius:8px;font-size:13px;margin-bottom:10px;"><i class="fas fa-exclamation-triangle"></i> Query will modify data. Continue?</div><button class="btn btn-warning" onclick="executeWriteQuery()"><i class="fas fa-play"></i> Yes, execute</button>';
      window._pendingWriteQuery = q;
    } else {
      rd.innerHTML = '<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(e.message) + '</div>';
    }
  }
}
async function executeWriteQuery() {
  var q = window._pendingWriteQuery; if (!q) return;
  var rd = document.getElementById('queryResult');
  rd.innerHTML = '<p style="color:#888;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Running...</p>';
  try {
    var res = await AHPL.api('/panel/api/database.php', { method: 'POST', headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ }, body: JSON.stringify({ action: 'query', query: q, confirm_write: true, db_type: 'mariadb', db_name: currentDB }) });
    rd.innerHTML = '<div style="padding:12px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle"></i> Query executed. Affected: ' + res.affected + ' (' + res.elapsed + 's)</div>';
  } catch(e) { rd.innerHTML = '<div style="padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle"></i> ' + AHPL.escapeHtml(e.message) + '</div>'; }
}

// --- Drop Table ---
function dropTable(n) { _dropTable = n; document.getElementById('dropTableName').textContent = n; document.getElementById('dropTableModal').classList.add('active'); }
function closeDropTable() { document.getElementById('dropTableModal').classList.remove('active'); _dropTable = null; }
async function confirmDropTable() {
  if (!_dropTable) return;
  try { var r = await AHPL.api('/panel/api/database.php', { method:'POST', headers:{'X-CSRF-TOKEN':window.__CSRF_TOKEN__}, body:JSON.stringify({action:'drop_table', table:_dropTable, db_type:'mariadb', db_name: currentDB}) }); if (r.success) { AHPL.toast('Table dropped'); closeDropTable(); document.getElementById('tableViewer').style.display='none'; loadTables(); loadDbInfo(); } }
  catch(e) { AHPL.toast(e.message, 'error'); }
}
// --- Truncate ---
function truncateTable() { if (!currentTable) return; _truncateTable = currentTable; document.getElementById('truncateTableName').textContent = currentTable; document.getElementById('truncateTableModal').classList.add('active'); }
function closeTruncateTable() { document.getElementById('truncateTableModal').classList.remove('active'); _truncateTable = null; }
async function confirmTruncateTable() {
  if (!_truncateTable) return;
  try { var r = await AHPL.api('/panel/api/database.php', { method:'POST', headers:{'X-CSRF-TOKEN':window.__CSRF_TOKEN__}, body:JSON.stringify({action:'truncate_table', table:_truncateTable, db_type:'mariadb', db_name: currentDB}) }); if (r.success) { AHPL.toast('Table emptied'); closeTruncateTable(); viewTable(currentTable, 1); loadTables(); } }
  catch(e) { AHPL.toast(e.message, 'error'); }
}
// --- Create Table ---
function openCreateTable() { document.getElementById('newTableName').value = ''; document.getElementById('newTableColumns').innerHTML = ''; addColumnDef(); document.getElementById('createTableModal').classList.add('active'); }
function closeCreateTable() { document.getElementById('createTableModal').classList.remove('active'); }
function addColumnDef() {
  var c = document.getElementById('newTableColumns'), idx = c.children.length, d = document.createElement('div');
  d.className = 'ct-col'; d.style.cssText = 'display:flex;gap:6px;margin-bottom:6px;align-items:center;';
  d.innerHTML = '<input type="text" class="form-control" style="width:130px;font-size:12px;" placeholder="name" value="column' + (idx + 1) + '"><select class="form-control" style="width:100px;font-size:12px;"><option>TEXT</option><option>INT</option><option>VARCHAR(255)</option><option>DATETIME</option><option>BOOLEAN</option><option>FLOAT</option></select><label style="font-size:11px;"><input type="checkbox"> PK</label><label style="font-size:11px;"><input type="checkbox"> AI</label><label style="font-size:11px;"><input type="checkbox"> NN</label><button class="btn-icon del" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
  c.appendChild(d);
}
async function confirmCreateTable() {
  var name = document.getElementById('newTableName').value.trim();
  if (!name) { AHPL.toast('Table name required', 'error'); return; }
  var divs = document.querySelectorAll('#newTableColumns .ct-col'), cols = [];
  divs.forEach(function(d) {
    var inps = d.querySelectorAll('input[type=text], select'); if (inps.length < 2) return;
    var cn = inps[0].value.trim(), ct = inps[1].value; if (!cn) return;
    var chk = d.querySelectorAll('input[type=checkbox]');
    cols.push({ name: cn, type: ct, pk: chk[0] ? chk[0].checked : false, auto: chk[1] ? chk[1].checked : false, notnull: chk[2] ? chk[2].checked : false });
  });
  if (cols.length === 0) { AHPL.toast('At least 1 column', 'error'); return; }
  try { var r = await AHPL.api('/panel/api/database.php', { method:'POST', headers:{'X-CSRF-TOKEN':window.__CSRF_TOKEN__}, body:JSON.stringify({action:'create_table', table:name, columns:cols, db_type:'mariadb', db_name: currentDB}) }); if (r.success) { AHPL.toast('Table created!'); closeCreateTable(); loadTables(); loadDbInfo(); } }
  catch(e) { AHPL.toast(e.message, 'error'); }
}
// --- Add Column ---
function openAddColumn() { document.getElementById('addColumnModal').classList.add('active'); }
function closeAddColumn() { document.getElementById('addColumnModal').classList.remove('active'); }
async function confirmAddColumn() {
  if (!currentTable) return;
  var name = document.getElementById('acName').value.trim();
  if (!name) { AHPL.toast('Column name required', 'error'); return; }
  var type = document.getElementById('acType').value;
  var cols = [{ name: name, type: type, pk: document.getElementById('acPk').checked, auto: document.getElementById('acAi').checked, notnull: document.getElementById('acNn').checked }];
  var dv = document.getElementById('acDefault').value.trim();
  if (dv) cols[0].default = dv;
  try { var r = await AHPL.api('/panel/api/database.php', { method:'POST', headers:{'X-CSRF-TOKEN':window.__CSRF_TOKEN__}, body:JSON.stringify({action:'create_table', table: '_ignore_' }), }); } catch(e) {}
  // Use ALTER TABLE via SQL query
  var parts = [name + ' ' + type];
  if (cols[0].pk) parts.push('PRIMARY KEY'); if (cols[0].auto) parts.push('AUTO_INCREMENT'); if (cols[0].notnull) parts.push('NOT NULL');
  if (dv) parts.push("DEFAULT " + (isNaN(dv) ? "'" + dv.replace(/'/g, "''") + "'" : dv));
  var sql = "ALTER TABLE `" + currentTable + "` ADD COLUMN " + parts.join(' ');
  try {
    var res = await AHPL.api('/panel/api/database.php', { method:'POST', headers:{'X-CSRF-TOKEN':window.__CSRF_TOKEN__}, body:JSON.stringify({action:'query', query: sql, confirm_write: true, db_type:'mariadb', db_name: currentDB}) });
    if (res.success) { AHPL.toast('Column added!'); closeAddColumn(); showStructure(currentTable); }
  } catch(e) { AHPL.toast(e.message, 'error'); }
}

function addColumn() {
  document.getElementById('acName').value = ''; document.getElementById('acPk').checked = false; document.getElementById('acAi').checked = false; document.getElementById('acNn').checked = false; document.getElementById('acDefault').value = '';
  document.getElementById('addColumnModal').classList.add('active');
}

// --- New Database ---
function openNewDB() { document.getElementById('newDBName').value = ''; document.getElementById('newDBModal').classList.add('active'); setTimeout(function() { document.getElementById('newDBName').focus(); }, 150); }
function closeNewDB() { document.getElementById('newDBModal').classList.remove('active'); }
async function confirmNewDB() {
  var name = document.getElementById('newDBName').value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
  if (!name) { AHPL.toast('Database name required', 'error'); return; }
  try {
    var r = await AHPL.api('/panel/api/database.php', { method:'POST', headers:{'X-CSRF-TOKEN':window.__CSRF_TOKEN__}, body:JSON.stringify({action:'create_database', name: name}) });
    if (r.success) { AHPL.toast('Database "' + name + '" created!'); closeNewDB(); currentDB = name; loadDatabases(); loadDbInfo(); loadTables(); document.getElementById('dbSelector').value = name; }
  } catch(e) { AHPL.toast(e.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', init);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
