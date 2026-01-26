<?php
require_once 'includes/config.php';
require_once 'includes/usuario.php';

// Iniciar a sessão e verificar autenticação
iniciar_sessao();
requer_login();

// Instanciar a classe Usuario
$usuario_obj = new Usuario();
// Determinar alvo (usuário sendo editado). Admin pode ver outro perfil via ?id=.
$alvo_id = $_SESSION['usuario_id'];
if (function_exists('is_admin') && is_admin() && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $tmp = (int)$_GET['id'];
    if ($tmp > 0) { $alvo_id = $tmp; }
}
$usuario = $usuario_obj->obter_por_id($alvo_id);
$editando_outro = ($alvo_id !== $_SESSION['usuario_id']);

// Gerar token CSRF para formulários
$csrf_token = gerar_csrf_token();

// Processar o formulário de atualização de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erro = "Erro de validação do formulário. Por favor, tente novamente.";
    } else {
        // Determinar qual formulário foi enviado
        if (isset($_POST['atualizar_perfil'])) {
            // Atualizar dados do perfil (limitar a avatar se for outro usuário)
            if ($editando_outro && is_admin()) {
                $nome = $usuario['nome'];
                $email = $usuario['email'];
            } else {
                $nome = sanitizar($_POST['nome']);
                $email = sanitizar($_POST['email']);
            }
            
            if (empty($nome) || empty($email)) {
                $erro = "Por favor, preencha todos os campos.";
            } else {
                // Inicializar variável para o avatar
                $avatar = null;
                $caminho_avatar = null;
                
                // Processar o upload do avatar se houver um arquivo
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $arquivo_tmp = $_FILES['avatar']['tmp_name'];
                    $nome_arquivo = $_FILES['avatar']['name'];
                    $tamanho_arquivo = $_FILES['avatar']['size'];
                    $tipo_arquivo = $_FILES['avatar']['type'];
                    
                    // Verificar tipo de arquivo (apenas imagens)
                    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
                    $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
                    
                    if (!in_array($extensao, $extensoes_permitidas)) {
                        $erro = "Tipo de arquivo não permitido. Apenas JPG, JPEG, PNG e GIF são aceitos.";
                    }
                    // Verificar tamanho (máximo 2MB)
                    elseif ($tamanho_arquivo > 2 * 1024 * 1024) {
                        $erro = "O arquivo é muito grande. Tamanho máximo permitido: 2MB.";
                    }
                    else {
                        // Diretório para os avatares
                        $diretorio_upload = 'uploads/avatares';
                        if (!file_exists($diretorio_upload)) {
                            mkdir($diretorio_upload, 0755, true);
                        }
                        
                        // Gerar nome único para o arquivo
                        $nome_unico = md5($alvo_id . time()) . '.' . $extensao;
                        $caminho_avatar = $diretorio_upload . '/' . $nome_unico;
                        
                        // Mover o arquivo
                        if (move_uploaded_file($arquivo_tmp, $caminho_avatar)) {
                            // Atualizar o caminho no banco de dados
                            $avatar = $caminho_avatar;
                            
                            // Remover avatar antigo, se existir
                            if (!empty($usuario['avatar']) && file_exists($usuario['avatar']) && $usuario['avatar'] != $caminho_avatar) {
                                unlink($usuario['avatar']);
                            }
                        } else {
                            $erro = "Falha ao fazer upload do arquivo. Por favor, tente novamente.";
                        }
                    }
                }
                
                if (!isset($erro)) {
                    $okAtualizacao = false;
                    if ($editando_outro && is_admin()) {
                        // Atualiza apenas avatar se fornecido
                        if ($avatar !== null) {
                            $okAtualizacao = $usuario_obj->atualizar_avatar($alvo_id, $avatar);
                        } else {
                            $okAtualizacao = true; // nada a alterar
                        }
                    } else {
                        $okAtualizacao = $usuario_obj->atualizar($alvo_id, $nome, $email, $avatar);
                    }
                    if ($okAtualizacao) {
                        $sucesso = "Perfil atualizado com sucesso!";
                        if (!$editando_outro) {
                            $_SESSION['usuario_nome'] = $nome;
                            $_SESSION['usuario_email'] = $email;
                            if ($avatar) { $_SESSION['usuario_avatar'] = $avatar; }
                        }
                        $usuario = $usuario_obj->obter_por_id($alvo_id);
                    } else {
                        $erro = "Erro ao atualizar perfil.";
                    }
                }
            }
        } elseif (isset($_POST['alterar_senha']) && !$editando_outro) {
            // Alterar senha
            $senha_atual = $_POST['senha_atual'];
            $senha_nova = $_POST['senha_nova'];
            $confirmar_senha = $_POST['confirmar_senha'];
            
            if (empty($senha_atual) || empty($senha_nova) || empty($confirmar_senha)) {
                $erro_senha = "Por favor, preencha todos os campos.";
            } elseif ($senha_nova !== $confirmar_senha) {
                $erro_senha = "As senhas não conferem.";
            } elseif (strlen($senha_nova) < 6) {
                $erro_senha = "A nova senha deve ter pelo menos 6 caracteres.";
            } else {
                // Verificar senha atual
                $login = $usuario_obj->login($_SESSION['usuario_email'], $senha_atual);
                
                if ($login) {
                    if ($usuario_obj->alterar_senha($_SESSION['usuario_id'], $senha_nova)) {
                        $sucesso_senha = "Senha alterada com sucesso!";
                    } else {
                        $erro_senha = "Erro ao alterar senha.";
                    }
                } else {
                    $erro_senha = "Senha atual incorreta.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editando_outro ? 'Perfil do Usuário' : 'Meu Perfil' ?> - Calendário Corporativo</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/avatar.css">

        <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
        <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg" sizes="any">
        <link rel="apple-touch-icon" href="assets/img/logo-placeholder.svg">
        <link rel="alternate icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
    
</head>
<body>
    
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
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (!empty($usuario['avatar']) && file_exists($usuario['avatar'])): ?>
                                <img src="<?= $usuario['avatar'] ?>?v=<?= time() ?>" class="navbar-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="navbar-avatar-placeholder bg-light text-primary">
                                    <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <?= $_SESSION['usuario_nome'] ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card"></i> Meu Perfil</a></li>
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
        <div class="row">
            <div class="col-md-12 mb-4">
                <h1><i class="fas fa-user"></i> <?= $editando_outro ? 'Perfil do Usuário' : 'Meu Perfil' ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Calendário</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= $editando_outro ? 'Perfil' : 'Meu Perfil' ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0"><i class="fas fa-user-edit"></i> Informações Pessoais</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($erro)): ?>
                            <div class="alert alert-danger"><?= $erro ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($sucesso)): ?>
                            <div class="alert alert-success"><?= $sucesso ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="perfil.php?id=<?= $alvo_id ?>" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="atualizar_perfil" value="1">
                            <input type="hidden" name="alvo_id" value="<?= $alvo_id ?>">
                            
                            <div class="mb-4 text-center">
                                <div class="avatar-container mb-3">
                                    <?php if (!empty($usuario['avatar']) && file_exists($usuario['avatar'])): ?>
                                        <img src="<?= $usuario['avatar'] ?>?v=<?= time() ?>" class="avatar-img rounded-circle" alt="Avatar">
                                    <?php else: ?>
                                        <div class="avatar-placeholder rounded-circle bg-primary text-white">
                                            <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="avatar" class="form-label">Foto de Perfil <?= $editando_outro ? '(Edição por Admin)' : '' ?></label>
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                    <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Tamanho máximo: 2MB.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nome" name="nome" value="<?= $usuario['nome'] ?>" <?= $editando_outro ? 'readonly' : 'required' ?>>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= $usuario['email'] ?>" <?= $editando_outro ? 'readonly' : 'required' ?>>
                                </div>
                            </div>
                            
                            
                            <div class="mb-3">
                                <label class="form-label">Data de Registro</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($usuario['data_criacao'])) ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= $editando_outro ? 'Atualizar Avatar' : 'Atualizar Perfil' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0"><i class="fas fa-lock"></i> Alterar Senha</h5>
                    </div>
                    <div class="card-body" <?= $editando_outro ? 'style="opacity:0.5;pointer-events:none"' : '' ?>>
                        <?php if (isset($erro_senha)): ?>
                            <div class="alert alert-danger"><?= $erro_senha ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($sucesso_senha)): ?>
                            <div class="alert alert-success"><?= $sucesso_senha ?></div>
                        <?php endif; ?>
                        
                        <?php if (!$editando_outro): ?>
                        <form method="POST" action="perfil.php?id=<?= $alvo_id ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="alterar_senha" value="1">
                            
                            <div class="mb-3">
                                <label for="senha_atual" class="form-label">Senha Atual</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="senha_nova" class="form-label">Nova Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" class="form-control" id="senha_nova" name="senha_nova" required minlength="6">
                                </div>
                                <small class="text-muted">A senha deve ter pelo menos 6 caracteres.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-check"></i></span>
                                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> Apenas o próprio usuário pode alterar sua senha aqui.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar para o Calendário
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para preview de imagem -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const avatarInput = document.getElementById('avatar');
        
        if (avatarInput) {
            // Adicionar container para preview depois do input
            const previewContainer = document.createElement('div');
            previewContainer.id = 'avatar-preview-container';
            previewContainer.className = 'mt-3 text-center';
            previewContainer.innerHTML = '<img id="avatar-preview" class="img-thumbnail" />';
            avatarInput.parentNode.appendChild(previewContainer);
            
            // Evento para mostrar preview
            avatarInput.addEventListener('change', function() {
                const previewContainer = document.getElementById('avatar-preview-container');
                const previewImg = document.getElementById('avatar-preview');
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        previewContainer.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                } else {
                    previewContainer.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>