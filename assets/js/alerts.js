(function(){
  function ensureModal(){
    if (document.getElementById('appAlertModal')) return;
    const html = `
<style id="appAlertStyles">
  #appAlertModal .modal-content { border-radius: 14px; overflow: hidden; }
  #appAlertModal .modal-header { padding: 1rem 1.25rem; }
  #appAlertModal .modal-body { padding: 1rem 1.25rem; }
  #appAlertModal .modal-footer { padding: .75rem 1.25rem; }
  #appAlertBadge { display:inline-flex; align-items:center; gap:.5rem; font-weight:600; }
  #appAlertAccent { height:4px; width:100%; }
  .alert-accent-success { background: linear-gradient(90deg,#28a745,#7cd992); }
  .alert-accent-error { background: linear-gradient(90deg,#dc3545,#ff7b88); }
  .alert-accent-warning { background: linear-gradient(90deg,#ffc107,#ffe08a); }
  .alert-accent-info { background: linear-gradient(90deg,#0d6efd,#7ab1ff); }
  #appAlertModal .btn-primary { border-radius: 999px; padding: .5rem 1rem; }
  #appAlertMessage p { margin: 0; }
  #appAlertModal .modal-dialog { transition: transform .2s ease; }
  #appAlertModal.show .modal-dialog { transform: translateY(-4px); }
</style>
<div class="modal fade" id="appAlertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div id="appAlertAccent" class="alert-accent-info"></div>
      <div class="modal-header border-0">
        <h5 class="modal-title d-flex align-items-center gap-2" id="appAlertTitle">
          <span id="appAlertBadge"></span>
        </h5>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" aria-label="Fechar">&times;</button>
      </div>
      <div class="modal-body">
        <div id="appAlertMessage" class="fs-6"></div>
        <div id="appAlertDetails" class="mt-2 text-muted small" style="display:none"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" id="appAlertPrimaryBtn" style="display:none"></button>
      </div>
    </div>
  </div>
</div>`;
    const div = document.createElement('div');
    div.innerHTML = html; document.body.appendChild(div);
  }
  function configFor(type){
    switch(type){
      case 'success': return { badge:'<i class="fas fa-check-circle text-success"></i> Sucesso', accent:'alert-accent-success' };
      case 'error': return { badge:'<i class="fas fa-times-circle text-danger"></i> Erro', accent:'alert-accent-error' };
      case 'warning': return { badge:'<i class="fas fa-exclamation-triangle text-warning"></i> Atenção', accent:'alert-accent-warning' };
      default: return { badge:'<i class="fas fa-info-circle text-primary"></i> Informação', accent:'alert-accent-info' };
    }
  }
  window.showAlert = function(message, type='info', options={}){
    ensureModal();
    const cfg = configFor(type);
    const badgeEl = document.getElementById('appAlertBadge');
    const msgEl = document.getElementById('appAlertMessage');
    const accentEl = document.getElementById('appAlertAccent');
    const detailsEl = document.getElementById('appAlertDetails');
    const primaryBtn = document.getElementById('appAlertPrimaryBtn');
    badgeEl.innerHTML = cfg.badge;
    accentEl.className = cfg.accent;
    // Permitir HTML opcional
    if (options && options.html === true) { msgEl.innerHTML = message; }
    else { msgEl.textContent = message; }
    // Detalhes adicionais
    if (options && options.details){ detailsEl.style.display=''; detailsEl.textContent = options.details; }
    else { detailsEl.style.display='none'; detailsEl.textContent=''; }
    // Botão primário opcional
    if (options && options.primary){
      primaryBtn.style.display='';
      primaryBtn.textContent = options.primary.text || 'OK';
      primaryBtn.className = 'btn btn-primary';
      primaryBtn.onclick = function(){
        if (typeof options.primary.onClick === 'function') options.primary.onClick();
        const modalEl = document.getElementById('appAlertModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      };
    } else {
      primaryBtn.style.display='none';
      primaryBtn.onclick = null;
    }
    const modalEl = document.getElementById('appAlertModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
    modal.show();
    if (options && typeof options.onClose === 'function'){
      const handler = function(){ modalEl.removeEventListener('hidden.bs.modal', handler); options.onClose(); };
      modalEl.addEventListener('hidden.bs.modal', handler);
    }
    // Auto-dismiss opcional (em ms)
    if (options && typeof options.autohide === 'number' && options.autohide > 0){
      setTimeout(()=>{ bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }, options.autohide);
    }
  };
})();
