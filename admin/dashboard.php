<?php
require_once '../includes/config.php';
require_once '../includes/usuario.php';
require_once '../includes/tarefa.php';
require_once '../includes/categoria.php';
require_once '../includes/checklist.php';
require_once '../includes/database.php';
require_once '../includes/envio_semanal.php';

// Verificar se o usuário está logado
iniciar_sessao();
requer_login();

// Verificar se o usuário é admin
$is_admin = isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'];
$view_only = !$is_admin;

// Instanciar classes
$usuario_obj = new Usuario();
$tarefa_obj = new Tarefa();
$categoria_obj = new Categoria();
$checklist_obj = new Checklist();
require_once '../includes/backlog.php';
$backlog_obj = new Backlog();

// Processar ações de categorias (permitido para todos os usuários logados)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && (isset($_POST['criar_categoria']) || isset($_POST['excluir_categoria']))) {
    verificar_csrf_token($_POST['csrf_token']);

    if (isset($_POST['criar_categoria'])) {
        $nome = trim($_POST['categoria_nome'] ?? '');
        $cor = trim($_POST['categoria_cor'] ?? '#007bff');
        if ($nome === '') {
            $erro = 'Informe o nome da categoria.';
        } else {
            try {
                if ($categoria_obj->criar($nome, $cor)) {
                    $sucesso = 'Categoria criada com sucesso!';
                } else {
                    $erro = 'Não foi possível criar a categoria. Verifique se já existe uma com este nome.';
                }
            } catch (Exception $e) {
                $erro = 'Erro ao criar categoria: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['excluir_categoria']) && isset($_POST['categoria_id'])) {
        $categoria_id = (int)$_POST['categoria_id'];
        if ($categoria_obj->excluir($categoria_id)) {
            $sucesso = 'Categoria excluída com sucesso!';
        } else {
            $erro = 'Não é possível excluir: categoria está em uso ou não existe.';
        }
    }
}

// Processar ações de checklists (permitido para todos os usuários logados)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && (isset($_POST['criar_checklist']) || isset($_POST['excluir_checklist']))) {
    verificar_csrf_token($_POST['csrf_token']);

    if (isset($_POST['criar_checklist'])) {
        $nome = trim($_POST['checklist_nome'] ?? '');
        if ($nome === '') {
            $erro = 'Informe o nome do item de checklist.';
        } else {
            try {
                if ($checklist_obj->criar($nome)) {
                    $sucesso = 'Item de checklist criado com sucesso!';
                } else {
                    $erro = 'Não foi possível criar o item. Verifique se já existe um com este nome.';
                }
            } catch (Exception $e) {
                $erro = 'Erro ao criar item de checklist: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['excluir_checklist']) && isset($_POST['checklist_id'])) {
        $checklist_id = (int)$_POST['checklist_id'];
        if ($checklist_obj->excluir($checklist_id)) {
            $sucesso = 'Item de checklist excluído com sucesso!';
        } else {
            $erro = 'Não foi possível excluir o item de checklist.';
        }
    }
}

// Processar ações (apenas para administradores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $is_admin) {
    verificar_csrf_token($_POST['csrf_token']);
    
    // Ação para promover usuário a admin
    if (isset($_POST['promover_admin']) && isset($_POST['usuario_id'])) {
        $usuario_id = (int)$_POST['usuario_id'];
        $usuario_obj->promover_admin($usuario_id);
        $sucesso = "Usuário promovido a administrador com sucesso!";
    }
    
    // Ação para rebaixar usuário de admin
    if (isset($_POST['rebaixar_admin']) && isset($_POST['usuario_id'])) {
        $usuario_id = (int)$_POST['usuario_id'];
        // Evitar rebaixar o próprio usuário
        if ($usuario_id == $_SESSION['usuario_id']) {
            $erro = "Você não pode remover seus próprios privilégios de administrador.";
        } else {
            $usuario_obj->rebaixar_admin($usuario_id);
            $sucesso = "Privilégios de administrador removidos com sucesso!";
        }
    }
    
    // Ação para excluir usuário
    if (isset($_POST['excluir_usuario']) && isset($_POST['usuario_id'])) {
        $usuario_id = (int)$_POST['usuario_id'];
        // Evitar excluir o próprio usuário
        if ($usuario_id == $_SESSION['usuario_id']) {
            $erro = "Você não pode excluir sua própria conta.";
        } else {
            $usuario_obj->excluir($usuario_id);
            $sucesso = "Usuário excluído com sucesso!";
        }
    }
    
    // Ação para excluir tarefa
    if (isset($_POST['excluir_tarefa']) && isset($_POST['tarefa_id'])) {
        $tarefa_id = (int)$_POST['tarefa_id'];
        $tarefa_obj->excluir($tarefa_id, 0, true); // true para indicar que é admin
        $sucesso = "Tarefa excluída com sucesso!";
    }
    
    // Ação para excluir entrada de backlog (apenas para administradores)
    if (isset($_POST['excluir_backlog']) && isset($_POST['backlog_id'])) {
        $backlog_id = (int)$_POST['backlog_id'];
        if ($backlog_obj->excluir($backlog_id)) {
            $sucesso = "Registro de atividade excluído com sucesso!";
        } else {
            $erro = "Erro ao excluir o registro de atividade.";
        }
    }
    // Ação para excluir TODOS os registros do backlog
    if (isset($_POST['excluir_backlog_todos'])) {
        if ($backlog_obj->excluir_todos()) {
            $sucesso = "Todos os registros do backlog foram excluídos.";
        } else {
            $erro = "Erro ao excluir todos os registros do backlog.";
        }
    }
    // Ação para excluir apenas registros da CONTA ADMIN PADRÃO no backlog
    if (isset($_POST['excluir_backlog_admins'])) {
        if ($backlog_obj->excluir_da_conta_admin_padrao()) {
            $sucesso = "Registros do backlog da conta ADMIN padrão foram excluídos.";
        } else {
            $erro = "Erro ao excluir registros do backlog da conta ADMIN padrão.";
        }
    }

    // Configuração de envio semanal
    if (isset($_POST['adicionar_destinatario_envio']) && isset($_POST['usuario_id_envio'])) {
        $uid = (int)$_POST['usuario_id_envio'];
        $resp = adicionar_destinatario_envio_semanal($uid);
        if ($resp['status'] === 'ok') {
            $sucesso = 'Destinatário adicionado à lista semanal.';
        } elseif ($resp['status'] === 'exists') {
            $erro = 'Este usuário já está na lista de envio semanal.';
        } else {
            $erro = 'Não foi possível adicionar o destinatário.';
        }
    }

    if (isset($_POST['remover_destinatario_envio']) && isset($_POST['usuario_id_envio'])) {
        $uid = (int)$_POST['usuario_id_envio'];
        if (remover_destinatario_envio_semanal($uid)) {
            $sucesso = 'Destinatário removido da lista semanal.';
        } else {
            $erro = 'Não foi possível remover o destinatário.';
        }
    }

    if (isset($_POST['enviar_teste_envio_semanal'])) {
        $resultadoEnvio = enviar_resumo_semanal(true, true);
        if ($resultadoEnvio['status'] === 'ok') {
            $sucesso = 'Envio de teste concluído. Emails enviados: ' . ($resultadoEnvio['enviados'] ?? 0) . ' de ' . ($resultadoEnvio['total'] ?? 0) . '.';
        } else {
            $erro = 'Envio de teste falhou: ' . ($resultadoEnvio['mensagem'] ?? 'Erro desconhecido');
        }
    }

    // Ações de Backup (somente admin)
    if (isset($_POST['criar_backup'])) {
        $db = Database::getInstance();
        if ($db->criar_backup_agora()) {
            $sucesso = 'Backup criado com sucesso.';
        } else {
            $erro = 'Falha ao criar backup. Verifique os logs.';
        }
    }

    if (isset($_POST['excluir_backup']) && isset($_POST['backup_file'])) {
        $file = basename($_POST['backup_file']);
        if (preg_match('/^calendario_backup_\d{8}_\d{6}\.db$/', $file)) {
            $path = rtrim(DB_BACKUP_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                if (@unlink($path)) {
                    $sucesso = 'Backup excluído: ' . htmlspecialchars($file);
                } else {
                    $erro = 'Não foi possível excluir o backup.';
                }
            } else {
                $erro = 'Arquivo de backup não encontrado.';
            }
        } else {
            $erro = 'Nome de arquivo inválido.';
        }
    }

    // Removido: ação de purga manual de backups (a rotação automática já cuida disso ao criar backups)

    // Restaurar um backup selecionado (somente admin)
    if (isset($_POST['restaurar_backup']) && isset($_POST['backup_file'])) {
        $file = basename($_POST['backup_file']);
        if (preg_match('/^calendario_backup_\d{8}_\d{6}\.db$/', $file)) {
            $src = rtrim(DB_BACKUP_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            $dst = DB_PATH;
            if (is_file($src)) {
                // Suspender backups no shutdown e fechar conexão
                Database::suspender_backup(true);
                $db = Database::getInstance();
                $db->fechar_conexao();
                // Tentar substituir o banco atual
                $ok = @copy($src, $dst);
                if ($ok) {
                    // Redirecionar para evitar reenvio e indicar sucesso
                    header('Location: dashboard.php?restore_ok=1');
                    exit;
                } else {
                    $erro = 'Falha ao restaurar. O arquivo de banco pode estar em uso. Tente parar o serviço e repetir.';
                }
            } else {
                $erro = 'Backup selecionado não encontrado.';
            }
        } else {
            $erro = 'Nome de arquivo inválido para restauração.';
        }
    }

    // Criar novo usuário (somente admin)
    if (isset($_POST['criar_usuario'])) {
        $nome = trim($_POST['novo_nome'] ?? '');
        $email = trim($_POST['novo_email'] ?? '');
        $senha = $_POST['novo_senha'] ?? '';
        $senha2 = $_POST['novo_senha2'] ?? '';
        $novo_is_admin = isset($_POST['novo_is_admin']) ? 1 : 0;

        if ($nome === '' || $email === '' || $senha === '' || $senha2 === '') {
            $erro = 'Preencha todos os campos para criar o usuário.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($senha !== $senha2) {
            $erro = 'As senhas não conferem.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        } else {
            if ($usuario_obj->email_existe($email)) {
                $erro = 'Este e-mail já está cadastrado.';
            } else {
                $novo_id = $usuario_obj->registrar($nome, $email, $senha, $novo_is_admin);
                if ($novo_id) {
                    $sucesso = 'Usuário criado com sucesso (ID ' . (int)$novo_id . ').';
                } else {
                    $erro = 'Não foi possível criar o usuário. Tente novamente.';
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_admin) {
    // Usuários não-admin: bloquear apenas ações administrativas (não categorias)
    if (!(isset($_POST['criar_categoria']) || isset($_POST['excluir_categoria']))) {
        $erro = "Você está no modo de visualização. Somente administradores podem realizar ações administrativas.";
    }
}

// Download de backup (somente admin, via GET e com CSRF)
if ($is_admin && isset($_GET['download_backup']) && isset($_GET['csrf_token'])) {
    verificar_csrf_token($_GET['csrf_token']);
    $file = basename($_GET['download_backup']);
    if (preg_match('/^calendario_backup_\d{8}_\d{6}\.db$/', $file)) {
        $path = rtrim(DB_BACKUP_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
        if (is_file($path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        }
    }
}

// Obter dados para o dashboard
$usuarios = $usuario_obj->listar_todos();
$tarefas = $tarefa_obj->listar_todas();
$backlog = $backlog_obj->listar(100); // Últimas 100 entradas
$estatisticas_backlog = $backlog_obj->obter_estatisticas();
$categorias = $categoria_obj->listar_todas();
$checklists = $checklist_obj->listar_todos();
$destinatarios_envio = listar_destinatarios_envio_semanal();
$periodo_proxima_semana = periodo_semana_seguinte();

// Listagem de backups para a aba (somente admin)
$lista_backups = [];
if ($is_admin) {
    $dir = rtrim(DB_BACKUP_DIR, DIRECTORY_SEPARATOR);
    if (is_dir($dir)) {
        $files = @glob($dir . DIRECTORY_SEPARATOR . 'calendario_backup_*.db') ?: [];
        foreach ($files as $f) {
            if (preg_match('/calendario_backup_\d{8}_\d{6}\.db$/', $f)) {
                $lista_backups[] = [
                    'nome' => basename($f),
                    'tam' => @filesize($f) ?: 0,
                    'mtime' => @filemtime($f) ?: 0
                ];
            }
        }
        usort($lista_backups, function($a, $b){ return $b['mtime'] <=> $a['mtime']; });
    }
}

// Gerar token CSRF
$csrf_token = gerar_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração - Calendário Corporativo</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo-placeholder.svg">
    <link rel="apple-touch-icon" href="../assets/img/logo-placeholder.svg">
    <link rel="alternate icon" type="image/svg+xml" href="../assets/img/logo-placeholder.svg">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-avatar.css">
    
    <style>
        .table-responsive {
            overflow-x: auto;
        }
        .admin-badge {
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        .navbar-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }
        .navbar-avatar-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 8px;
            font-size: 12px;
            font-weight: bold;
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/img/logo-placeholder.svg" alt="Logo" class="navbar-logo">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Calendário</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Administração</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php
                            // Obtém o usuário atual para ter acesso ao avatar
                            $usuario_atual = $usuario_obj->obter_por_id($_SESSION['usuario_id']);
                            if (!empty($usuario_atual['avatar']) && file_exists('../' . $usuario_atual['avatar'])) {
                                echo '<img src="../' . $usuario_atual['avatar'] . '?v=' . time() . '" class="navbar-avatar" alt="Avatar">';
                            } else {
                                $iniciais = strtoupper(mb_substr($_SESSION['usuario_nome'], 0, 2));
                                echo '<div class="navbar-avatar-placeholder bg-light text-primary">' . $iniciais . '</div>';
                            }
                            ?>
                            <?= $_SESSION['usuario_nome'] ?>
                            <?php if ($is_admin): ?>
                            <span class="badge bg-danger admin-badge">Admin</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-id-card"></i> Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Conteúdo principal -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <h1>
                    <i class="fas fa-tachometer-alt"></i> Painel de Administração
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Calendário</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Administração</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <?php if ($view_only): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Você está no modo de visualização para recursos administrativos.
            </div>
        <?php endif; ?>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['restore_ok'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Banco restaurado com sucesso a partir do backup selecionado. Se algo parecer inconsistente, reinicie o serviço e limpe o cache do navegador.</div>
        <?php endif; ?>

        <?php if (isset($sucesso)): ?>
            <div class="alert alert-success"><?= $sucesso ?></div>
        <?php endif; ?>
        
        <!-- Cards de Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users"></i> Total de Usuários</h5>
                        <p class="card-text display-4"><?= count($usuarios) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-shield"></i> Administradores</h5>
                        <p class="card-text display-4">
                            <?= count(array_filter($usuarios, function($u) { return $u['is_admin'] == 1; })) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tasks"></i> Total de Tarefas</h5>
                        <p class="card-text display-4"><?= count($tarefas) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Guias para as diferentes seções -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab" aria-controls="usuarios" aria-selected="true">
                    <i class="fas fa-users"></i> Gerenciar Usuários
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tarefas-tab" data-bs-toggle="tab" data-bs-target="#tarefas" type="button" role="tab" aria-controls="tarefas" aria-selected="false">
                    <i class="fas fa-tasks"></i> Gerenciar Tarefas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="backlog-tab" data-bs-toggle="tab" data-bs-target="#backlog" type="button" role="tab" aria-controls="backlog" aria-selected="false">
                    <i class="fas fa-history"></i> Log de Atividades
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="categorias-tab" data-bs-toggle="tab" data-bs-target="#categorias" type="button" role="tab" aria-controls="categorias" aria-selected="false">
                    <i class="fas fa-tags"></i> Categorias
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="checklists-tab" data-bs-toggle="tab" data-bs-target="#checklists" type="button" role="tab" aria-controls="checklists" aria-selected="false">
                    <i class="fas fa-check-square"></i> Checklists
                </button>
            </li>
            <?php if ($is_admin): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="backups-tab" data-bs-toggle="tab" data-bs-target="#backups" type="button" role="tab" aria-controls="backups" aria-selected="false">
                    <i class="fas fa-database"></i> Backups
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="envio-semanal-tab" data-bs-toggle="tab" data-bs-target="#envio-semanal" type="button" role="tab" aria-controls="envio-semanal" aria-selected="false">
                    <i class="fas fa-envelope-open-text"></i> Envio semanal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dbadmin-tab" data-bs-toggle="tab" data-bs-target="#dbadmin" type="button" role="tab" aria-controls="dbadmin" aria-selected="false">
                    <i class="fas fa-table"></i> Banco de Dados
                </button>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="tab-content" id="adminTabsContent">
            <!-- Gerenciar Usuários -->
            <div class="tab-pane fade show active" id="usuarios" role="tabpanel" aria-labelledby="usuarios-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0"><i class="fas fa-users"></i> Lista de Usuários</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($is_admin): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <strong><i class="fas fa-user-plus"></i> Criar novo usuário</strong>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <div class="col-md-4">
                                        <label class="form-label">Nome</label>
                                        <input type="text" name="novo_nome" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="novo_email" class="form-control" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Senha</label>
                                        <input type="password" name="novo_senha" class="form-control" minlength="6" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Confirmar senha</label>
                                        <input type="password" name="novo_senha2" class="form-control" minlength="6" required>
                                    </div>
                                    <div class="col-12 d-flex align-items-center gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="novo_is_admin" name="novo_is_admin">
                                            <label class="form-check-label" for="novo_is_admin">Conceder acesso de Administrador</label>
                                        </div>
                                        <button type="submit" name="criar_usuario" class="btn btn-success">
                                            <i class="fas fa-save"></i> Criar usuário
                                        </button>
                                    </div>
                                </form>
                                <div class="form-text mt-2">Dica: use uma senha segura para novos usuários. Contas criadas por admin não passam por 2FA de cadastro.</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tabelaUsuarios">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Tipo</th>
                                        <th>Registro</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= $usuario['id'] ?></td>
                                        <td>
                                            <?php
                                            // Avatar do usuário
                                            if (!empty($usuario['avatar']) && file_exists('../' . $usuario['avatar'])) {
                                                echo '<img src="../' . htmlspecialchars($usuario['avatar']) . '?v=' . time() . '" class="admin-avatar" alt="Avatar">';
                                            } else {
                                                $iniciais = strtoupper(mb_substr($usuario['nome'], 0, 2));
                                                echo '<span class="admin-avatar-placeholder">' . $iniciais . '</span>';
                                            }
                                            ?>
                                            <?= $usuario['nome'] ?>
                                            <?php if ($usuario['id'] == $_SESSION['usuario_id']): ?>
                                                <span class="badge bg-info">Você</span>
                                            <?php endif; ?>
                                            <?php if ($usuario['nome'] == 'admin'): ?>
                                                <span class="badge bg-warning">Conta Fixa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $usuario['email'] ?></td>
                                        <td>
                                            <?php if ($usuario['is_admin'] == 1): ?>
                                                <span class="badge bg-danger">Administrador</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Usuário</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php
                                            $dt = new DateTime($usuario['data_criacao'], new DateTimeZone('UTC'));
                                            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                            echo $dt->format('d/m/Y H:i');
                                        ?></td>
                                        <td>
                                            <?php if ($is_admin): ?>
                                            <div class="btn-group">
                                                <?php if ($usuario['nome'] != 'admin'): ?>
                                                    <?php if ($usuario['is_admin'] == 0): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                            <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                            <button type="submit" name="promover_admin" class="btn btn-sm btn-outline-primary" title="Promover a Admin">
                                                                <i class="fas fa-user-shield"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                            <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                            <button type="submit" name="rebaixar_admin" class="btn btn-sm btn-outline-warning" title="Remover Admin">
                                                                <i class="fas fa-user"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <!-- Acessar perfil de usuário para alterar avatar (admin) -->
                                                <a href="../perfil.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-outline-info" title="Abrir Perfil">
                                                    <i class="fas fa-id-card"></i>
                                                </a>
                                                
                                                <?php if ($usuario['id'] != $_SESSION['usuario_id'] && $usuario['nome'] != 'admin'): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                        <button type="submit" name="excluir_usuario" class="btn btn-sm btn-outline-danger" title="Excluir Usuário">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                                <i class="fas fa-eye text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gerenciar Tarefas -->
            <div class="tab-pane fade" id="tarefas" role="tabpanel" aria-labelledby="tarefas-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0"><i class="fas fa-tasks"></i> Lista de Tarefas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tabelaTarefas">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Usuário</th>
                                        <th>Categoria</th>
                                        <th>Data Início</th>
                                        <th>Data Fim</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tarefas as $tarefa): ?>
                                    <tr>
                                        <td><?= $tarefa['id'] ?></td>
                                        <td><?= $tarefa['titulo'] ?></td>
                                        <td><?= $tarefa['nome_usuario'] ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= $tarefa['cor'] ?>">
                                                <?= $tarefa['categoria'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $dt_inicio = new DateTime($tarefa['data_inicio'] . ($tarefa['hora_inicio'] ? ' ' . $tarefa['hora_inicio'] : ''), new DateTimeZone('UTC'));
                                            $dt_inicio->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                            echo $dt_inicio->format('d/m/Y');
                                            if (!$tarefa['dia_inteiro'] && $tarefa['hora_inicio']) {
                                                echo ' ' . $dt_inicio->format('H:i');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $dt_fim = new DateTime($tarefa['data_fim'] . ($tarefa['hora_fim'] ? ' ' . $tarefa['hora_fim'] : ''), new DateTimeZone('UTC'));
                                            $dt_fim->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                            echo $dt_fim->format('d/m/Y');
                                            if (!$tarefa['dia_inteiro'] && $tarefa['hora_fim']) {
                                                echo ' ' . $dt_fim->format('H:i');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="../index.php?tarefa_id=<?= $tarefa['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver Tarefa">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($is_admin): ?>
                                                <a href="../index.php?editar_tarefa=<?= $tarefa['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar Tarefa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta tarefa? Esta ação não pode ser desfeita.');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="tarefa_id" value="<?= $tarefa['id'] ?>">
                                                    <button type="submit" name="excluir_tarefa" class="btn btn-sm btn-outline-danger" title="Excluir Tarefa">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Backlog de Atividades -->
            <div class="tab-pane fade" id="backlog" role="tabpanel" aria-labelledby="backlog-tab">
                <div class="card shadow mb-4">
                                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                                <h5 class="m-0"><i class="fas fa-history"></i> Log de Atividades do Sistema</h5>
                                                <?php if ($is_admin): ?>
                                                <div class="d-flex gap-2 align-items-center m-0">
                                                    <form method="POST" class="m-0" onsubmit="return confirm('Tem certeza que deseja limpar apenas os registros da conta ADMIN padrão? Esta ação não pode ser desfeita.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <button type="submit" name="excluir_backlog_admins" class="btn btn-sm btn-warning text-dark" title="Limpa somente registros feitos pela conta ADMIN padrão">
                                                                <i class="fas fa-user-shield"></i> Limpar logs do ADMIN padrão
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="m-0" onsubmit="return confirm('Tem certeza que deseja excluir TODOS os registros do backlog? Esta ação não pode ser desfeita.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <button type="submit" name="excluir_backlog_todos" class="btn btn-sm btn-light">
                                                                <i class="fas fa-trash-alt"></i> Limpar logs
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php endif; ?>
                                        </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Ações por Tipo</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($estatisticas_backlog) || empty($estatisticas_backlog['por_tipo'])): ?>
                                            <div class="mb-2 small text-muted">Sem dados de ações registrados ainda. Gere alguma atividade (criar, atualizar, excluir tarefa ou usuário) para popular.</div>
                                        <?php endif; ?>
                                        <canvas id="graficoAcoes" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Filtrar Registros</h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="filtroBacklog" class="row g-2">
                                            <div class="col-md-12">
                                                <label for="filtroAcao" class="form-label">Tipo de Ação</label>
                                                <select class="form-select form-select-sm" id="filtroAcao">
                                                    <option value="">Todas</option>
                                                    <option value="criar">Criar</option>
                                                    <option value="atualizar">Atualizar</option>
                                                    <option value="excluir">Excluir</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12">
                                                <label for="filtroEntidade" class="form-label">Entidade</label>
                                                <select class="form-select form-select-sm" id="filtroEntidade">
                                                    <option value="">Todas</option>
                                                    <option value="tarefa">Tarefas</option>
                                                    <option value="usuario">Usuários</option>
                                                </select>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tabelaBacklog">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Data/Hora</th>
                                        <th>Usuário</th>
                                        <th>Ação</th>
                                        <th>Entidade</th>
                                        <th>Detalhes</th>
                                        <?php if ($is_admin): ?>
                                        <th>Ações</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backlog as $log): ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td><?php
                                            $dt = new DateTime($log['data_hora'], new DateTimeZone('UTC'));
                                            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                            echo $dt->format('d/m/Y H:i:s');
                                        ?></td>
                                        <td><?= htmlspecialchars($log['nome_usuario']) ?></td>
                                        <td>
                                            <?php 
                                            $acao = $log['acao'];
                                            $badge_class = '';
                                            
                                            switch ($acao) {
                                                case 'criar':
                                                    $badge_class = 'bg-success';
                                                    $acao_texto = 'Criação';
                                                    break;
                                                case 'atualizar':
                                                    $badge_class = 'bg-primary';
                                                    $acao_texto = 'Atualização';
                                                    break;
                                                case 'excluir':
                                                    $badge_class = 'bg-danger';
                                                    $acao_texto = 'Exclusão';
                                                    break;
                                                case 'registrar':
                                                    $badge_class = 'bg-info';
                                                    $acao_texto = 'Registro';
                                                    break;
                                                case 'promover_admin':
                                                    $badge_class = 'bg-warning text-dark';
                                                    $acao_texto = 'Promover Admin';
                                                    break;
                                                case 'rebaixar_admin':
                                                    $badge_class = 'bg-secondary';
                                                    $acao_texto = 'Rebaixar Admin';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-light text-dark';
                                                    $acao_texto = ucfirst($acao);
                                            }
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= $acao_texto ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $entidade = $log['entidade'];
                                            $entidade_id = $log['entidade_id'];
                                            
                                            if ($entidade === 'tarefa') {
                                                echo '<span class="badge bg-info">Tarefa #' . $entidade_id . '</span>';
                                            } elseif ($entidade === 'usuario') {
                                                echo '<span class="badge bg-secondary">Usuário #' . $entidade_id . '</span>';
                                            } else {
                                                echo htmlspecialchars($entidade) . ' #' . $entidade_id;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['detalhes'])): ?>
                                                <button class="btn btn-sm btn-outline-secondary view-details" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#detalhesModal" 
                                                        data-acao="<?= htmlspecialchars($log['acao']) ?>"
                                                        data-entidade="<?= htmlspecialchars($log['entidade']) ?>"
                                                        data-detalhes='<?= htmlspecialchars($log['detalhes']) ?>'>
                                                    <i class="fas fa-info-circle"></i> Ver
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($is_admin): ?>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este registro de atividade? Esta ação não pode ser desfeita.');">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="backlog_id" value="<?= $log['id'] ?>">
                                                <button type="submit" name="excluir_backlog" class="btn btn-sm btn-outline-danger" title="Excluir Registro">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Modal de Detalhes -->
                <div class="modal fade" id="detalhesModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-light">
                                <h5 class="modal-title">Detalhes da Atividade</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="detalhesJson" class="bg-light p-3 rounded"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gerenciar Categorias -->
            <div class="tab-pane fade" id="categorias" role="tabpanel" aria-labelledby="categorias-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><i class="fas fa-tags"></i> Gerenciar Categorias</h5>
                        <?php if ($is_admin): ?>
                        <form method="POST" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="text" name="categoria_nome" class="form-control form-control-sm" placeholder="Nome da categoria" required>
                            <input type="color" name="categoria_cor" class="form-control form-control-color form-control-sm" value="#007bff" title="Escolha a cor">
                            <button type="submit" name="criar_categoria" class="btn btn-sm btn-light">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Cor</th>
                                        <th>Criado em</th>
                                        <?php if ($is_admin): ?><th>Ações</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($categorias)): ?>
                                        <?php foreach ($categorias as $cat): ?>
                                            <tr>
                                                <td><?= (int)$cat['id'] ?></td>
                                                <td><?= htmlspecialchars($cat['nome']) ?></td>
                                                <td>
                                                    <span class="badge" style="background-color: <?= htmlspecialchars($cat['cor']) ?>;">&nbsp;&nbsp;&nbsp;</span>
                                                    <code class="ms-2"><?= htmlspecialchars($cat['cor']) ?></code>
                                                </td>
                                                <td><?= isset($cat['data_criacao']) ? htmlspecialchars($cat['data_criacao']) : '-' ?></td>
                                                <?php if ($is_admin): ?>
                                                <td>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir esta categoria? Tarefas existentes não serão alteradas.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="categoria_id" value="<?= (int)$cat['id'] ?>">
                                                        <button type="submit" name="excluir_categoria" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Nenhuma categoria cadastrada.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gerenciar Checklists -->
            <div class="tab-pane fade" id="checklists" role="tabpanel" aria-labelledby="checklists-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><i class="fas fa-check-square"></i> Gerenciar Itens de Checklist</h5>
                        <?php if ($is_admin): ?>
                        <form method="POST" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="text" name="checklist_nome" class="form-control form-control-sm" placeholder="Nome do item" required>
                            <button type="submit" name="criar_checklist" class="btn btn-sm btn-light">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Criado em</th>
                                        <?php if ($is_admin): ?><th>Ações</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($checklists)): ?>
                                        <?php foreach ($checklists as $chk): ?>
                                            <tr>
                                                <td><?= (int)$chk['id'] ?></td>
                                                <td><?= htmlspecialchars($chk['nome']) ?></td>
                                                <td><?= isset($chk['data_criacao']) ? htmlspecialchars($chk['data_criacao']) : '-' ?></td>
                                                <?php if ($is_admin): ?>
                                                <td>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este item de checklist?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="checklist_id" value="<?= (int)$chk['id'] ?>">
                                                        <button type="submit" name="excluir_checklist" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Nenhum item de checklist cadastrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_admin): ?>
            <!-- Gerenciar Backups -->
            <div class="tab-pane fade" id="backups" role="tabpanel" aria-labelledby="backups-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><i class="fas fa-database"></i> Backups do Banco</h5>
                        <div class="d-flex gap-2">
                            <form method="POST" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <button type="submit" name="criar_backup" class="btn btn-sm btn-light">
                                    <i class="fas fa-plus"></i> Criar backup agora
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-2 text-muted small">
                            Diretório: <code><?= htmlspecialchars(DB_BACKUP_DIR) ?></code> · Política: manter até <strong><?= (int)DB_BACKUP_MAX ?></strong> arquivos
                        </div>
                        <?php if (!is_dir(DB_BACKUP_DIR)): ?>
                            <div class="alert alert-warning">Diretório de backups não existe. Um backup criado irá tentar criar automaticamente a pasta.</div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Arquivo</th>
                                        <th>Tamanho</th>
                                        <th>Modificado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($lista_backups)): ?>
                                        <?php foreach ($lista_backups as $b): ?>
                                            <tr>
                                                <td><code><?= htmlspecialchars($b['nome']) ?></code></td>
                                                <td><?= number_format($b['tam']/1024, 2, ',', '.') ?> KB</td>
                                                <td><?= $b['mtime'] ? date('d/m/Y H:i:s', $b['mtime']) : '-' ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a class="btn btn-sm btn-outline-primary" href="dashboard.php?download_backup=<?= urlencode($b['nome']) ?>&csrf_token=<?= $csrf_token ?>" title="Baixar">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Restaurar este backup? O arquivo atual do banco será substituído. Em Windows, pare o serviço se falhar.');">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($b['nome']) ?>">
                                                            <button type="submit" name="restaurar_backup" class="btn btn-sm btn-outline-success" title="Restaurar">
                                                                <i class="fas fa-rotate"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este backup? Esta ação não pode ser desfeita.');">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($b['nome']) ?>">
                                                            <button type="submit" name="excluir_backup" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">Nenhum backup encontrado.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Envio semanal (admin) -->
            <div class="tab-pane fade" id="envio-semanal" role="tabpanel" aria-labelledby="envio-semanal-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="m-0"><i class="fas fa-envelope-open-text"></i> Configurações de envio semanal</h5>
                            <small>Próxima semana: <?= $periodo_proxima_semana['inicio_br'] ?> até <?= $periodo_proxima_semana['fim_br'] ?></small>
                        </div>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <button type="submit" name="enviar_teste_envio_semanal" class="btn btn-sm btn-light">
                                <i class="fas fa-paper-plane"></i> Enviar e-mail de teste
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h6>Destinatários atuais</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>E-mail</th>
                                                <th style="width:80px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($destinatarios_envio)): ?>
                                                <?php foreach ($destinatarios_envio as $dest): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($dest['nome']) ?></td>
                                                        <td><?= htmlspecialchars($dest['email']) ?></td>
                                                        <td>
                                                            <form method="POST" onsubmit="return confirm('Remover este destinatário da lista semanal?');">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                <input type="hidden" name="usuario_id_envio" value="<?= (int)$dest['usuario_id'] ?>">
                                                                <button type="submit" name="remover_destinatario_envio" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-muted text-center">Nenhum destinatário configurado.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="small text-muted">O sistema evita envios duplicados no mesmo dia e envia automaticamente às sextas.</div>
                            </div>
                            <div class="col-lg-6">
                                <h6>Adicionar destinatário</h6>
                                <form method="POST" class="row g-2 align-items-end">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <div class="col-12">
                                        <label class="form-label">Usuário</label>
                                        <select name="usuario_id_envio" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <?php
                                                $destIds = array_column($destinatarios_envio, 'usuario_id');
                                                foreach ($usuarios as $u):
                                                    if (in_array($u['id'], $destIds)) continue;
                                            ?>
                                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="adicionar_destinatario_envio" class="btn btn-success">
                                            <i class="fas fa-plus"></i> Incluir na lista semanal
                                        </button>
                                    </div>
                                </form>
                                <div class="alert alert-info mt-3 mb-0 small">
                                    O e-mail semanal lista todas as tarefas da semana seguinte. Se não houver tarefas, informaremos que a agenda está vazia.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Banco de Dados (admin) -->
            <div class="tab-pane fade" id="dbadmin" role="tabpanel" aria-labelledby="dbadmin-tab">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><i class="fas fa-table"></i> Administração do Banco de Dados</h5>
                    </div>
                    <div class="card-body p-0">
                        <iframe src="db_admin.php" title="Banco de Dados" style="width:100%; height:70vh; border:0;" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Rodapé -->
    <footer class="bg-light text-center text-muted py-3 mt-5">
        <div class="container">
            <p class="mb-0">© <?= date('Y') ?> Calendário Corporativo - Painel de Administração</p>
        </div>
    </footer>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS e dependências -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTables
            $('#tabelaUsuarios').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                }
            });
            
            $('#tabelaTarefas').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                }
            });
            
            // Inicializar DataTable com variável para referência
            const tabelaBacklog = $('#tabelaBacklog').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                order: [[0, 'desc']] // Ordenar por ID de forma decrescente (mais recentes primeiro)
            });
            
            // Adicionar filtro personalizado para ação e entidade
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    // Verifica se estamos filtrando a tabela correta
                    if (settings.nTable.id !== 'tabelaBacklog') {
                        return true;
                    }
                    
                    const filtroAcao = $('#filtroAcao').val().toLowerCase();
                    const filtroEntidade = $('#filtroEntidade').val().toLowerCase();
                    
                    // Se não houver filtros, mostrar todas as linhas
                    if (!filtroAcao && !filtroEntidade) {
                        return true;
                    }
                    
                    // Dados da linha (colunas)
                    const acaoTexto = data[3].toLowerCase(); // Coluna de Ação (0-indexed)
                    const entidade = data[4].toLowerCase(); // Coluna de Entidade
                    
                    // Mapear o texto do filtro para o texto exibido na tabela
                    let acaoMatch = true;
                    if (filtroAcao) {
                        // Mapear os valores do filtro para os textos exibidos na interface
                        if (filtroAcao === 'criar' && acaoTexto.includes('criação')) {
                            acaoMatch = true;
                        } else if (filtroAcao === 'atualizar' && acaoTexto.includes('atualização')) {
                            acaoMatch = true;
                        } else if (filtroAcao === 'excluir' && acaoTexto.includes('exclusão')) {
                            acaoMatch = true;
                        } else {
                            acaoMatch = false;
                        }
                    }
                    
                    // Aplicar filtro por entidade
                    const entidadeMatch = !filtroEntidade || entidade.includes(filtroEntidade);
                    
                    // Mostrar apenas linhas que correspondam aos filtros
                    return acaoMatch && entidadeMatch;
                }
            );
            
            // Aplicar filtros automaticamente quando os selects são alterados
            $('#filtroAcao, #filtroEntidade').on('change', function() {
                tabelaBacklog.draw();
            });
            
            // Garantir que o gráfico seja renderizado corretamente ao mudar para a aba
            $('button[data-bs-target="#backlog"]').on('shown.bs.tab', function (e) {
                // Disparar um resize para forçar o Chart.js a recalcular dimensões
                if (window.myPieChart) {
                    setTimeout(function() {
                        window.myPieChart.resize();
                        window.myPieChart.update();
                    }, 50);
                }
            });
            
            // Manipular cliques no botão "Ver Detalhes"
            // Delegar evento para suportar redraws do DataTables
            $(document).on('click', '.view-details', function() {
                // Recuperar atributo bruto para decodificar entidades HTML antes de parsear
                const rawAttr = $(this).attr('data-detalhes') || '';
                // Decodificar entidades HTML (&quot; etc.) usando elemento temporário
                const decoded = $('<textarea/>').html(rawAttr).text();
                let detalhesObj;
                try {
                    detalhesObj = JSON.parse(decoded);
                } catch (e) {
                    // Tentar segunda estratégia: substituir entidades de aspas manualmente
                    try {
                        const fallback = decoded.replace(/&quot;/g,'"').replace(/&#039;/g,"'");
                        detalhesObj = JSON.parse(fallback);
                    } catch (e2) {
                        console.error('Falha ao decodificar detalhes:', e2);
                        $('#detalhesJson').html('<div class="alert alert-warning">Não foi possível processar os detalhes desta atividade.</div>');
                        return;
                    }
                }
                    // Gerar uma visualização amigável dos detalhes em HTML
                    let detalhesHTML = '<div class="details-container">';
                    const entidade = $(this).data('entidade');
                    detalhesHTML += '<div class="details-header mb-3 pb-2 border-bottom">';
                    detalhesHTML += `<h6>Detalhes da ${entidade === 'usuario' ? 'Conta' : 'Tarefa'}</h6>`;
                    detalhesHTML += '</div>';
                    for (const [key, value] of Object.entries(detalhesObj)) {
                        let fieldName;
                        switch(key) {
                            case 'nome': fieldName = 'Nome'; break;
                            case 'email': fieldName = 'Email'; break;
                            case 'is_admin': fieldName = 'Administrador'; break;
                            case 'titulo': fieldName = 'Título'; break;
                            case 'descricao': fieldName = 'Descrição'; break;
                            case 'data_inicio': fieldName = 'Data de Início'; break;
                            case 'data_fim': fieldName = 'Data de Término'; break;
                            case 'hora_inicio': fieldName = 'Hora de Início'; break;
                            case 'hora_fim': fieldName = 'Hora de Término'; break;
                            case 'categoria': fieldName = 'Categoria'; break;
                            case 'cor': fieldName = 'Cor'; break;
                            case 'dia_inteiro': fieldName = 'Dia Inteiro'; break;
                            case 'usuario_id': fieldName = 'ID do Usuário'; break;
                            default: fieldName = key.charAt(0).toUpperCase() + key.slice(1).replace('_', ' ');
                        }
                        let displayValue;
                        if (key === 'is_admin') {
                            displayValue = value == 1 ? '<span class="badge bg-danger">Sim</span>' : '<span class="badge bg-secondary">Não</span>';
                        } else if (key === 'dia_inteiro') {
                            displayValue = value == 1 ? '<span class="badge bg-info">Sim</span>' : '<span class="badge bg-secondary">Não</span>';
                        } else if (key === 'cor' && value) {
                            displayValue = `<span class="badge" style="background-color: ${value}">${value}</span>`;
                        } else if (value === null || value === undefined) {
                            displayValue = '<em class="text-muted">Não definido</em>';
                        } else if (typeof value === 'object') {
                            displayValue = JSON.stringify(value);
                        } else {
                            displayValue = value.toString();
                        }
                        detalhesHTML += `<div class="mb-2"><strong>${fieldName}:</strong> ${displayValue}</div>`;
                    }
                    detalhesHTML += '</div>';
                    $('#detalhesJson').html(detalhesHTML);
            });
            
            // Renderizar gráfico de ações por tipo
            setTimeout(function() {
                const canvasElement = document.getElementById('graficoAcoes');
                if (!canvasElement) {
                    console.error('Canvas element not found');
                    return;
                }
                
                const ctx = canvasElement.getContext('2d');
                if (!ctx) {
                    console.error('Could not get 2d context from canvas');
                    return;
                }
                
                const backlogStats = <?= json_encode($estatisticas_backlog) ?>;
                function renderChart() {
                    if (!(backlogStats && backlogStats.por_tipo && backlogStats.por_tipo.length)) {
                        const canvasEl = document.getElementById('graficoAcoes');
                        if (canvasEl) {
                            canvasEl.outerHTML = '<div class="p-3 text-muted">Sem dados de atividades para exibir no gráfico.</div>';
                        }
                        return;
                    }
                    try {
                        const acoes = backlogStats.por_tipo.map(item => {
                            switch(item.acao) {
                                case 'criar': return 'Criação';
                                case 'atualizar': return 'Atualização';
                                case 'excluir': return 'Exclusão';
                                case 'registrar': return 'Registro';
                                case 'promover_admin': return 'Promover Admin';
                                case 'rebaixar_admin': return 'Rebaixar Admin';
                                default: return item.acao.charAt(0).toUpperCase() + item.acao.slice(1);
                            }
                        });
                        const totais = backlogStats.por_tipo.map(item => item.total);
                        const cores = [
                            '#4e73df', '#1cc88a', '#e74a3b', '#f6c23e', '#36b9cc', '#6f42c1',
                            '#5a5c69', '#858796', '#2e59d9', '#17a673'
                        ];
                        if (window.myPieChart) {
                            window.myPieChart.destroy();
                        }
                        window.myPieChart = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: acoes,
                                datasets: [{
                                    data: totais,
                                    backgroundColor: cores.slice(0, acoes.length)
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'right' } }
                            }
                        });
                    } catch (e) {
                        console.error('Erro ao renderizar gráfico:', e);
                        document.getElementById('graficoAcoes').outerHTML = '<div class="p-3 text-danger">Erro ao renderizar gráfico de atividades.</div>';
                    }
                }
                // Render inicial somente quando a aba backlog for mostrada pela primeira vez
                let backlogChartInitialized = false;
                $('button[data-bs-target="#backlog"]').on('shown.bs.tab', function() {
                    if (!backlogChartInitialized) {
                        renderChart();
                        backlogChartInitialized = true;
                    } else if (window.myPieChart) {
                        setTimeout(function(){ window.myPieChart.resize(); window.myPieChart.update(); }, 50);
                    }
                });
                // Caso a aba já esteja ativa por algum motivo (ex: carregada via âncora), tentar renderizar
                if ($('#backlog').hasClass('active show')) {
                    renderChart(); backlogChartInitialized = true;
                }
            }, 100); // Pequeno delay para garantir que o DOM esteja totalmente carregado
        });
    </script>
</body>
</html>