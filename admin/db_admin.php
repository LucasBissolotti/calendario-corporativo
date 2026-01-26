<?php
// Permite que esta página seja exibida em iframe no mesmo domínio (dashboard)
define('ALLOW_IFRAME', true);
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/usuario.php';

// AuthN + AuthZ
iniciar_sessao();
requer_login();
if (!is_admin()) {
    header('Location: ../index.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$csrf = gerar_csrf_token();

function json_out($arr) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($arr);
    exit;
}

function list_tables(PDO $pdo) {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_columns(PDO $pdo, $table) {
  $stmt = $pdo->query("PRAGMA table_info(\"" . str_replace("\"", "\"\"", $table) . "\")");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function table_exists(PDO $pdo, $table) {
    $q = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

function whitelist_ident($pdo, $table, $col) {
    $cols = get_columns($pdo, $table);
    foreach ($cols as $c) {
        if ($c['name'] === $col) return true;
    }
    return false;
}

// AJAX API
if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];
    if ($acao === 'listar_tabelas') {
        json_out(['status'=>'success','tables'=>list_tables($pdo)]);
    }
    if ($acao === 'info_tabela' && isset($_GET['tabela'])) {
        $tabela = trim($_GET['tabela']);
        if (!table_exists($pdo,$tabela)) json_out(['status'=>'error','message'=>'Tabela inválida']);
        $cols = get_columns($pdo,$tabela);
        $count = (int)$pdo->query("SELECT COUNT(*) FROM \"".str_replace("\"","\"\"",$tabela)."\"")->fetchColumn();
        json_out(['status'=>'success','columns'=>$cols,'rowCount'=>$count]);
    }
    if ($acao === 'listar_linhas' && isset($_GET['tabela'])) {
        $tabela = trim($_GET['tabela']);
        if (!table_exists($pdo,$tabela)) json_out(['status'=>'error','message'=>'Tabela inválida']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(200, max(1,(int)($_GET['pageSize'] ?? 50)));
        $offset = ($page-1)*$pageSize;
        $sql = "SELECT rowid, * FROM \"".str_replace("\"","\"\"",$tabela)."\" ORDER BY rowid DESC LIMIT :lim OFFSET :off";
        $st = $pdo->prepare($sql);
        $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['status'=>'success','rows'=>$rows]);
    }
    // Ações de edição desativadas por solicitação: manter apenas exclusões
    if ($acao === 'excluir_linha' && $_SERVER['REQUEST_METHOD']==='POST') {
      verificar_csrf_token($_POST['csrf_token'] ?? '');
      $tabela = trim($_POST['tabela'] ?? '');
      $rowid = (int)($_POST['rowid'] ?? 0); // pode vir 0 se não existir
      if (!$tabela) json_out(['status'=>'error','message'=>'Tabela não informada']);
      if (!table_exists($pdo,$tabela)) json_out(['status'=>'error','message'=>'Tabela inválida']);
      if ($rowid > 0) { // caminho tradicional via rowid
        $st = $pdo->prepare("DELETE FROM \"".str_replace("\"","\"\"",$tabela)."\" WHERE rowid = :id");
        $ok = $st->execute([':id'=>$rowid]);
        json_out(['status'=>$ok?'success':'error']);
      }
      // Fallback via PK
      $pk_col = $_POST['pk_col'] ?? '';
      $pk_val = $_POST['pk_val'] ?? '';
      if (!$pk_col || $pk_val==='') json_out(['status'=>'error','message'=>'Parâmetros inválidos para PK']);
      $cols = get_columns($pdo,$tabela);
      $is_pk = false;
      foreach ($cols as $c){ if ($c['name'] === $pk_col && (int)$c['pk'] === 1){ $is_pk = true; break; } }
      if (!$is_pk) json_out(['status'=>'error','message'=>'Coluna PK inválida']);
      $sql = "DELETE FROM \"".str_replace("\"","\"\"",$tabela)."\" WHERE \"".str_replace("\"","\"\"",$pk_col)."\" = :val";
      $st = $pdo->prepare($sql);
      if (is_numeric($pk_val)) { $st->bindValue(':val',(int)$pk_val,PDO::PARAM_INT); } else { $st->bindValue(':val',$pk_val,PDO::PARAM_STR); }
      $ok = $st->execute();
      json_out(['status'=>$ok?'success':'error']);
    }
    if ($acao === 'dropar_coluna' && $_SERVER['REQUEST_METHOD']==='POST') {
        verificar_csrf_token($_POST['csrf_token'] ?? '');
        $tabela = trim($_POST['tabela'] ?? '');
        $coluna = trim($_POST['coluna'] ?? '');
        if (!$tabela || !$coluna) json_out(['status'=>'error','message'=>'Parâmetros inválidos']);
        if (!table_exists($pdo,$tabela) || !whitelist_ident($pdo,$tabela,$coluna)) json_out(['status'=>'error','message'=>'Tabela/coluna inválida']);
        try {
            $pdo->exec("ALTER TABLE \"".str_replace("\"","\"\"",$tabela)."\" DROP COLUMN \"".str_replace("\"","\"\"",$coluna)."\"");
            json_out(['status'=>'success']);
        } catch (Throwable $e) {
            json_out(['status'=>'error','message'=>'Falha ao dropar coluna. Versão do SQLite pode não suportar.']);
        }
    }
    // Unknown
    json_out(['status'=>'error','message'=>'Ação inválida']);
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin DB | <?= htmlspecialchars(SITE_TITLE) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
      body { background-color:#f8f9fa; }
      .sidebar { max-height: calc(100vh - 140px); overflow:auto; }
      .table-fixed { table-layout: fixed; }
      .schema-col .btn-sm { padding: .15rem .35rem; }
      .navbar-logo { height: 28px; margin-right: 8px; }

      /* Tipografia e espaços */
      h1,h2,h3,h4,h5,h6 { font-weight:600; }
      .card-header { background:#fff; border-bottom: 1px solid rgba(0,0,0,.075); }
      .card { border-radius: .5rem; }
      .list-group-item { border: none; border-bottom: 1px solid rgba(0,0,0,.05); }
      .list-group-item-action:hover { background: rgba(13,110,253,.06); }

      /* Tabela refinada */
      #grid thead th { position: sticky; top: 0; background: #fff; z-index: 2; box-shadow: 0 1px 0 rgba(0,0,0,.06); }
      #grid td, #grid th { vertical-align: middle; }

      /* Estados e ícones */
      .btn-outline-danger:hover { color: #fff; }
      .loading-inline { display:inline-flex; align-items:center; gap:.5rem; color:#6c757d; }
      .loading-block { padding: 1rem; text-align:center; color:#6c757d; }

      /* Esquema */
      .schema-col { background:#fff; }
      .schema-col + .schema-col { margin-top:.25rem; }
      .schema-col code { color:#0d6efd; }
    </style>
</head>
<body>
<div class="container-fluid mt-3">
  <div class="row">
    <div class="col-md-3">
      <div class="card">
        <div class="card-header"><i class="fa fa-database me-1"></i> Tabelas</div>
        <div class="card-body sidebar p-2">
          <ul id="listaTabelas" class="list-group list-group-flush"></ul>
        </div>
      </div>
    </div>
    <div class="col-md-9">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span id="tituloTabela"><i class="fa fa-table me-1"></i> Selecione uma tabela</span>
          <span class="text-muted" id="rowCount"></span>
        </div>
        <div class="card-body">
          <h6 class="mb-2">Esquema</h6>
          <div id="schema" class="mb-3"></div>
          <h6 class="mb-2">Registros</h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-fixed align-middle" id="grid">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <button class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
              <button class="btn btn-outline-secondary btn-sm" id="btnNext">Próximo</button>
            </div>
            <small class="text-muted" id="pagerInfo"></small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = <?= json_encode($csrf) ?>;
let tabelaAtual = null, page=1, pageSize=50, colunas=[], pkCol=null;

function fmtHtml(s){ return String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'); }

async function fetchJSON(url, opts={}){ const r = await fetch(url, opts); return r.json(); }

async function carregarTabelas(){
  const ul = document.getElementById('listaTabelas');
  ul.innerHTML = '<div class="loading-block"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando...</div>';
  const data = await fetchJSON('db_admin.php?acao=listar_tabelas');
  ul.innerHTML = '';
  if (data.status==='success'){
    data.tables.forEach(t=>{
      const li = document.createElement('li');
      li.className='list-group-item list-group-item-action';
      li.textContent=t; li.style.cursor='pointer';
      li.onclick=()=>{ selecionarTabela(t); };
      ul.appendChild(li);
    });
    if (!data.tables.length){ ul.innerHTML = '<div class="text-muted small">Nenhuma tabela encontrada.</div>'; }
  }
}

async function selecionarTabela(nome){
  tabelaAtual = nome; page=1; document.getElementById('tituloTabela').innerHTML = `<i class="fa fa-table me-1"></i> ${fmtHtml(nome)}`;
  const schemaDiv = document.getElementById('schema');
  schemaDiv.innerHTML = '<div class="loading-inline"><div class="spinner-border spinner-border-sm text-primary"></div><span>Carregando esquema...</span></div>';
  const info = await fetchJSON('db_admin.php?acao=info_tabela&tabela='+encodeURIComponent(nome));
  if (info.status!=='success'){ alert(info.message||'Falha ao carregar info'); return; }
  colunas = info.columns.map(c=>c.name);
  pkCol = null; for (const c of info.columns){ if (parseInt(c.pk)===1){ pkCol = c.name; break; } }
  document.getElementById('rowCount').textContent = info.rowCount + ' registros';
  // Schema render
  schemaDiv.innerHTML = '';
  info.columns.forEach(c=>{
    const wrap=document.createElement('div'); wrap.className='d-flex justify-content-between align-items-center schema-col border rounded p-1 mb-1';
    // Usar data-attributes para evitar problemas de escape em nomes de colunas
    const pkBadge = c.pk?'<span class="badge bg-secondary ms-1">PK</span>':'';
    wrap.innerHTML = `<div><code>${fmtHtml(c.name)}</code> <small class="text-muted">${fmtHtml(c.type||'')}</small> ${pkBadge}</div>`+
      `<div><button class="btn btn-sm btn-outline-danger btn-drop-col" data-tabela="${fmtHtml(nome)}" data-coluna="${fmtHtml(c.name)}" title="Excluir coluna" ${c.pk?'disabled':''}><i class='fa fa-trash'></i></button></div>`;
    schemaDiv.appendChild(wrap);
  });
  await carregarLinhas();
}

async function carregarLinhas(){
  const thead=document.querySelector('#grid thead');
  const tbody=document.querySelector('#grid tbody');
  thead.innerHTML='';
  const colspan = (colunas.length||0) + 1; // colunas + ações
  tbody.innerHTML = `<tr><td colspan='${colspan}' class='loading-block'><div class=\"spinner-border spinner-border-sm text-primary me-2\"></div>Carregando...</td></tr>`;
  const data = await fetchJSON('db_admin.php?acao=listar_linhas&tabela='+encodeURIComponent(tabelaAtual)+'&page='+page+'&pageSize='+pageSize);
  tbody.innerHTML='';
  // Header (sem rowid)
  const trh=document.createElement('tr');
  trh.innerHTML = colunas.map(c=>`<th>${fmtHtml(c)}</th>`).join('') + '<th>Ações</th>';
  thead.appendChild(trh);
  // Rows
  (data.rows||[]).forEach(row=>{
    const tr=document.createElement('tr');
    const pkVal = pkCol ? row[pkCol] : row[colunas[0]];
    const canDelete = pkVal !== undefined && pkVal !== null && pkVal !== '';
    const deleteBtn = canDelete ?
      `<button class='btn btn-sm btn-outline-danger btn-del-row' data-pk='${fmtHtml(pkVal)}' aria-label='Excluir registro'><i class=\"fa fa-trash\"></i></button>` :
      `<button class='btn btn-sm btn-outline-secondary' disabled title='PK indisponível'><i class=\"fa fa-ban\"></i></button>`;
    tr.innerHTML = colunas.map(c=>`<td>${fmtHtml(row[c])}</td>`).join('') + `<td>${deleteBtn}</td>`;
    tbody.appendChild(tr);
  });
  document.getElementById('pagerInfo').textContent = `Página ${page}`;
}

async function excluirLinha(pkVal, btn){
  if (!tabelaAtual){ alert('Selecione a tabela antes.'); return; }
  if (pkVal===undefined || pkVal===null || pkVal===''){ alert('Valor de PK inválido.'); return; }
  if (!confirm(`Excluir registro (PK=${pkVal})? Esta ação não pode ser desfeita.`)) return;
  const original = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  try {
    const form = new FormData(); form.append('csrf_token', CSRF); form.append('tabela', tabelaAtual);
    // Enviar como PK
    if (pkCol) form.append('pk_col', pkCol); else form.append('pk_col', colunas[0]);
    form.append('pk_val', pkVal);
    const data = await fetchJSON('db_admin.php?acao=excluir_linha', { method:'POST', body: form });
    if (data.status==='success'){ await selecionarTabela(tabelaAtual); }
    else { alert(data.message||'Erro ao excluir'); btn.disabled=false; btn.innerHTML=original; }
  } catch(e){ console.error(e); alert('Falha na requisição.'); btn.disabled=false; btn.innerHTML=original; }
}

async function dropCol(tabela, coluna){
  if (!tabela || !coluna){ alert('Tabela/coluna inválida.'); return; }
  if (!confirm(`Dropar coluna "${coluna}" da tabela "${tabela}"?`)) return;
  const form = new FormData(); form.append('csrf_token', CSRF); form.append('tabela', tabela); form.append('coluna', coluna);
  const data = await fetchJSON('db_admin.php?acao=dropar_coluna', { method:'POST', body: form });
  if (data.status==='success'){ alert('Coluna removida.'); await selecionarTabela(tabela); }
  else { alert(data.message||'Falha ao remover coluna.'); }
}

// Delegação para botões de drop coluna
document.addEventListener('click', e=>{
  const btn = e.target.closest('.btn-drop-col');
  if (!btn) return;
  const tabela = btn.getAttribute('data-tabela');
  const coluna = btn.getAttribute('data-coluna');
  dropCol(tabela, coluna);
});

// Delegação para botões de excluir linha
document.addEventListener('click', e=>{
  const btn = e.target.closest('.btn-del-row');
  if (!btn) return;
  const pkVal = btn.getAttribute('data-pk');
  excluirLinha(pkVal, btn);
});

document.getElementById('btnPrev').onclick = ()=>{ if (page>1){ page--; carregarLinhas(); } };

document.getElementById('btnNext').onclick = ()=>{ page++; carregarLinhas(); };

carregarTabelas();
</script>
</body>
</html>
