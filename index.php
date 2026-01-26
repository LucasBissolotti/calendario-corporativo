<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/checklist.php';
require_once __DIR__ . '/includes/categoria.php';

// Carregar checklists e categorias do banco
$checklist_obj = new Checklist();
$checklists_db = $checklist_obj->listar_todos();

$categoria_obj = new Categoria();
$categorias_db = $categoria_obj->listar_todas();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#0d6efd">
    <title>Calendário Corporativo</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
        <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg" sizes="any">
        <link rel="apple-touch-icon" href="assets/img/logo-placeholder.svg">
        <link rel="alternate icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- FullCalendar CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/participantes.css">
    <link rel="stylesheet" href="assets/css/avatar.css">
    <link rel="stylesheet" href="assets/css/fc-dom-spacing.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>
<body>
    <?php
    require_once 'includes/config.php';
    
    // Iniciar a sessão e verificar autenticação
    iniciar_sessao();
    requer_login();
    
    // Gerar token CSRF para formulários
    $csrf_token = gerar_csrf_token();

    // Buscar usuários
    require_once 'includes/usuario.php';
    $usuario_obj = new Usuario();
    $usuarios_todos = $usuario_obj->listar_todos();
    // Participantes: exceto admin
    $usuarios_participantes = array_filter($usuarios_todos, function($u) {
        return $u['nome'] !== 'admin';
    });

    // Usar categorias carregadas no início
    $categorias = $categorias_db;
    ?>
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/img/logo-placeholder.svg" alt="Logo" class="navbar-logo">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Administração
                            <?php if (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']): ?>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php
                            // Obtém o usuário atual para ter acesso ao avatar
                            $usuario_atual = $usuario_obj->obter_por_id($_SESSION['usuario_id']);
                            if (!empty($usuario_atual['avatar']) && file_exists($usuario_atual['avatar'])): ?>
                                <img src="<?= $usuario_atual['avatar'] ?>?v=<?= time() ?>" class="navbar-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="navbar-avatar-placeholder bg-light text-primary">
                                    <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <?= $_SESSION['usuario_nome'] ?>
                            <?php if (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']): ?>
                            <span class="badge bg-danger">Admin</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="admin/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Painel Admin
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Conteúdo principal -->
    <div class="container mt-4">
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-calendar-alt"></i> Calendário Corporativo</h1>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalTarefa">
                            <i class="fas fa-plus"></i> Adicionar nova tarefa
                        </button>
                        <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#modalFiltro">
                            <i class="fas fa-filter"></i> Filtro
                        </button>
                        <button id="btnBaixarRelatorio" class="btn btn-outline-secondary me-2" style="display:none;">
                            <i class="fas fa-file-download"></i> Baixar Relatório
                        </button>
    <!-- Modal de Filtro -->
    <div class="modal fade" id="modalFiltro" tabindex="-1" aria-labelledby="modalFiltroLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFiltroLabel"><i class="fas fa-filter"></i> Filtro de Tarefas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="formFiltro" class="form-filtro">
                        <div class="mb-3">
                            <label for="filtroParticipantes" class="form-label">Participantes</label>
                            <select class="form-select" id="filtroParticipantes" name="filtroParticipantes[]" multiple>
                                <?php foreach ($usuarios_participantes as $u): ?>
                                    <option value="<?= htmlspecialchars($u['nome']) ?>">
                                        <?= htmlspecialchars($u['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="filtroUsuarioCriador" class="form-label">Autor (criador da tarefa)</label>
                            <select class="form-select" id="filtroUsuarioCriador" name="filtroUsuarioCriador">
                                <option value="">Todos</option>
                                <?php foreach ($usuarios_todos as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>"><?php if ($u['nome'] === 'admin') echo 'ADMIN (padrão)'; else echo htmlspecialchars($u['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="filtroStatus" class="form-label">Status da Tarefa</label>
                            <select class="form-select" id="filtroStatus" name="filtroStatus">
                                <option value="">Todos</option>
                                <option value="Confirmado">Confirmado</option>
                                <option value="Provisório">Provisório</option>
                                <option value="Problema">Problema</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="filtroCategoria" class="form-label">Categoria</label>
                            <select class="form-select" id="filtroCategoria" name="filtroCategoria">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= htmlspecialchars($c['nome']) ?>">
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="filtroTipoServico" class="form-label">Tipo de serviço</label>
                            <input type="text" class="form-control" id="filtroTipoServico" name="filtroTipoServico" placeholder="Digite uma palavra ou frase">
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <button type="button" id="btnResetFiltros" class="btn btn-outline-secondary"><i class="fas fa-undo"></i> Resetar filtros</button>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-body">
                        <!-- Container do Calendário -->
                        <div id="calendario"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js" crossorigin="anonymous"></script>
    <script src="assets/js/alerts.js"></script>
    
    
    <!-- Modal de Tarefa -->
    <div class="modal fade" id="modalTarefa" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <h5 class="modal-title">Nova Tarefa</h5>
                    <div class="task-creator ms-auto" id="taskCreatorInfo">
                        <span class="task-creator-label">Criador da tarefa:</span>
                        <div class="task-creator-chip">
                            <div class="task-creator-avatar task-creator-placeholder" id="taskCreatorAvatar" aria-hidden="true"></div>
                            <span class="task-creator-name" id="taskCreatorName"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="formTarefa" class="form-tarefa" enctype="multipart/form-data">
                        <input type="hidden" id="csrf_token" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" id="tarefaId" name="tarefaId" value="">
                        <input type="hidden" id="cor" name="cor" value="#007bff">
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="titulo" class="form-label">Título</label>
                                <input type="text" class="form-control bg-light" id="titulo" name="titulo" readonly>
                                <div class="form-text">Gerado automaticamente a partir de Status, Localização, Tipo de serviço e Participantes.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="categoria" class="form-label">Categoria *</label>
                                <select class="form-select" id="categoria" name="categoria" required>
                                    <option value="" selected disabled hidden>Selecione uma categoria</option>
                                    <?php if (!empty($categorias)): ?>
                                        <?php foreach ($categorias as $c): ?>
                                            <option value="<?= htmlspecialchars($c['nome']) ?>" data-cor="<?= htmlspecialchars($c['cor']) ?>">
                                                <?= htmlspecialchars($c['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="localizacao" class="form-label">Localização</label>
                                <input type="text" class="form-control" id="localizacao" name="localizacao" placeholder="Local do evento">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="dataInicio" class="form-label">Data Início *</label>
                                <input type="date" class="form-control" id="dataInicio" name="data_inicio" required>
                            </div>
                            <div class="col-md-4">
                                <label for="dataFim" class="form-label">Data Fim *</label>
                                <input type="date" class="form-control" id="dataFim" name="data_fim" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Opções</label>
                                <div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="diaInteiro" name="dia_inteiro">
                                        <label class="form-check-label" for="diaInteiro">Dia Inteiro</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="diasUteis" name="dias_uteis">
                                        <label class="form-check-label" for="diasUteis">Dias úteis</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="horaInicio" class="form-label">Hora Início</label>
                                <input type="time" class="form-control" id="horaInicio" name="hora_inicio">
                            </div>
                            <div class="col-md-4">
                                <label for="horaFim" class="form-label">Hora Fim</label>
                                <input type="time" class="form-control" id="horaFim" name="hora_fim">
                            </div>
                        </div>

                        

                        <!-- Status e Tipo de Serviço -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Confirmado">Confirmado</option>
                                    <option value="Provisório" selected>Provisório</option>
                                    <option value="Problema">Problema</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="tipo_servico" class="form-label">Tipo de serviço</label>
                                <input type="text" class="form-control" id="tipo_servico" name="tipo_servico" placeholder="Descreva o serviço" />
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="participantes" class="form-label">
                                    <i class="fas fa-users me-1"></i> Participantes
                                </label>
                                <select class="form-select" id="participantes" name="participantes[]" multiple>
                                    <?php foreach ($usuarios_participantes as $u): ?>
                                        <option value="<?= htmlspecialchars($u['nome']) ?>" 
                                                data-email="<?= htmlspecialchars($u['email']) ?>"
                                                data-avatar="<?= !empty($u['avatar']) && file_exists($u['avatar']) ? htmlspecialchars($u['avatar']) : '' ?>">
                                            <?= htmlspecialchars($u['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted">
                                    <small>Selecione uma ou mais pessoas para participar da tarefa</small>
                                </div>
                            </div>
                        </div>
                                                
                        <div class="mb-3">
                            <label class="form-label">Checklist</label>
                            <div id="checklist-container">
                                <?php if (!empty($checklists_db)): ?>
                                    <?php foreach ($checklists_db as $index => $chk): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check_<?= $index ?>" name="checklist[]" value="<?= htmlspecialchars($chk['nome']) ?>">
                                            <label class="form-check-label" for="check_<?= $index ?>"><?= htmlspecialchars($chk['nome']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">Nenhum item de checklist cadastrado. Acesse o painel administrativo para criar.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="anexos" class="form-label">Anexos</label>
                            <input type="file" class="form-control" id="anexos" name="anexos[]" multiple>
                            <div class="form-text">Você pode selecionar múltiplos arquivos.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Anexos Existentes</label>
                            <div id="listaAnexos">
                                <p class="text-muted">Nenhum anexo disponível.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" id="btnExcluirTarefa" class="btn btn-danger" style="display:none;">
                                <i class="fas fa-trash"></i> Excluir Tarefa
                            </button>
                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success me-2" id="btnSalvarEnviar" name="enviar_aviso" value="1">
                                    <i class="fas fa-envelope"></i> Salvar e Enviar Aviso
                                </button>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Select2 Bootstrap 5 Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <!-- Scripts JavaScript -->
    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/pt-br.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Variáveis globais do usuário -->
    <script>
        // Passar informações do usuário para o JavaScript
        window.currentUserId = <?= (int)$_SESSION['usuario_id'] ?>;
        window.isUserAdmin = <?= isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'] ? 'true' : 'false' ?>;
        window.currentUserName = <?= json_encode($_SESSION['usuario_nome']) ?>;
        window.currentUserAvatar = <?= json_encode((!empty($usuario_atual['avatar']) && file_exists($usuario_atual['avatar'])) ? $usuario_atual['avatar'] : null) ?>;
    </script>

    <!-- Scripts personalizados -->
    <script src="assets/js/calendario.js"></script>

    <script>
    $(document).ready(function() {
        $('#participantes').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecione os participantes',
            width: '100%',
            allowClear: true,
            dropdownParent: $('#modalTarefa'),
            templateResult: formatUsuario,
            templateSelection: formatUsuarioSelecionado,
            escapeMarkup: function(m) { return m; },
            language: {
                noResults: function() {
                    return 'Nenhum usuário encontrado';
                },
                searching: function() {
                    return 'Pesquisando...';
                }
            }
        });

        const $participantes = $('#participantes');
        function reposicionarDropdown() {
            const container = $participantes.data('select2')?.$container?.[0];
            const dropdown = document.querySelector('.select2-container--open .select2-dropdown');
            if (!container || !dropdown) return;
            const rect = container.getBoundingClientRect();
            // Largura igual ao campo
            dropdown.style.width = rect.width + 'px';
            dropdown.style.position = 'fixed'; // ancorado ao viewport
            dropdown.style.left = rect.left + 'px';
            // Tentar abrir para baixo, se não couber abrir para cima
            const dropdownHeight = dropdown.offsetHeight || 300; // fallback aproximado
            const espaçoAbaixo = window.innerHeight - rect.bottom;
            if (espaçoAbaixo < dropdownHeight + 20 && rect.top > dropdownHeight) {
                // Abrir acima
                dropdown.style.top = (rect.top - dropdownHeight) + 'px';
            } else {
                // Abrir abaixo
                dropdown.style.top = rect.bottom + 'px';
            }
            // Ajustar classe para evitar conflitos de estilos relativos
            dropdown.classList.add('select2-dropdown-fixed');
        }
        $participantes.on('select2:open', function() {
            // Usar pequeno atraso para garantir renderização do dropdown
            setTimeout(reposicionarDropdown, 0);
        });
        // Reposicionar em eventos de scroll / resize enquanto aberto
        function monitorarEnquantoAberto() {
            if (document.querySelector('.select2-container--open .select2-dropdown')) {
                reposicionarDropdown();
            }
        }
        window.addEventListener('scroll', monitorarEnquantoAberto, true);
        window.addEventListener('resize', monitorarEnquantoAberto);
        // Opcional: quando fechar, remover estilos
        $participantes.on('select2:close', function() {
            const dropdown = document.querySelector('.select2-dropdown-fixed');
            if (dropdown) {
                dropdown.style.position = '';
                dropdown.style.top = '';
                dropdown.style.left = '';
                dropdown.style.width = '';
                dropdown.classList.remove('select2-dropdown-fixed');
            }
        });

        // Função para formatar usuários na lista dropdown
        function formatUsuario(usuario) {
            if (!usuario.id) return usuario.text;
            
            // Função para gerar uma cor aleatória baseada no nome
            const stringToColor = (str) => {
                let hash = 0;
                for (let i = 0; i < str.length; i++) {
                    hash = str.charCodeAt(i) + ((hash << 5) - hash);
                }
                let color = '#';
                for (let i = 0; i < 3; i++) {
                    const value = (hash >> (i * 8)) & 0xFF;
                    color += ('00' + value.toString(16)).substr(-2);
                }
                return color;
            };
            
            // Obter iniciais do nome
            const getInitials = (name) => {
                const parts = name.split(' ');
                if (parts.length >= 2) {
                    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                }
                return name.substring(0, 2).toUpperCase();
            };
            
            const name = usuario.text;
            const email = $(usuario.element).data('email');
            const avatarPath = $(usuario.element).data('avatar');
            const bgcolor = stringToColor(name);
            const initials = getInitials(name);
            
            let avatarHTML = '';
            
            // Verificar se o usuário tem avatar
            if (avatarPath) {
                avatarHTML = `<img src="${avatarPath}?v=${Date.now()}" class="select2-avatar me-2" alt="${name}">`;
            } else {
                avatarHTML = `<div class="select2-avatar-placeholder me-2" style="background-color: ${bgcolor};">
                    ${initials}
                </div>`;
            }
            
            return $(
                `<div class="select2-usuario-template d-flex align-items-center py-1">
                    ${avatarHTML}
                    <div class="usuario-info">
                        <div class="usuario-nome">${name}</div>
                        <div class="usuario-email text-muted small">${email}</div>
                    </div>
                </div>`
            );
        }
        
        // Função para formatar usuários selecionados como tags
        function formatUsuarioSelecionado(usuario) {
            if (!usuario.id) return usuario.text;
            
            const name = usuario.text;
            const avatarPath = $(usuario.element).data('avatar');
            
            // Exibir mini avatar ou ícone padrão
            if (avatarPath) {
                return $(`<span><img src="${avatarPath}?v=${Date.now()}" class="select2-avatar-mini me-1" style="width: 16px; height: 16px; border-radius: 50%;"> ${name}</span>`);
            } else {
                return $(`<span><i class="fas fa-user-circle me-1"></i> ${name}</span>`);
            }
        }
        
        // Manipular o botão de "Salvar e Enviar Aviso": apenas valida; o estado visual é controlado no salvarTarefa
        $('#btnSalvarEnviar').on('click', function(e) {
            const participantes = $('#participantes').val();
            if (!participantes || participantes.length === 0) {
                e.preventDefault();
                alert('Por favor, selecione pelo menos um participante para enviar avisos.');
                return false;
            }
            // Não altera o texto/estado aqui; salvarTarefa cuidará do spinner/disable
        });
    });
    </script>
</body>
</html>