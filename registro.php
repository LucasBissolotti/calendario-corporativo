<?php
require_once 'includes/config.php';
require_once 'includes/usuario.php';
require_once 'includes/verification.php';
require_once 'includes/email_helper.php';

iniciar_sessao();

// Se já estiver logado, redireciona para o calendário
if (esta_logado()) {
    header("Location: index.php");
    exit;
}

// Processar o formulário de registro (2 etapas)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erro = "Erro de validação do formulário. Por favor, tente novamente.";
    } else {
        $etapa = isset($_POST['etapa']) ? $_POST['etapa'] : 'inicio';
        $usuario = new Usuario();
        $svcVerif = new VerificacaoCadastro();

        if ($etapa === 'verificar') {
            // Etapa 2: validar código e criar a conta
            $email = sanitizar($_POST['email']);
            $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';

            if (empty($email) || empty($codigo)) {
                $erro = "Informe o código recebido por e-mail.";
                $mostrar_verificacao = true;
            } else {
                $dados = $svcVerif->validar_codigo($email, $codigo);
                if ($dados) {
                    // Cria usuário com senha já hasheada
                    if ($usuario->registrar_com_hash($dados['nome'], $dados['email'], $dados['senha_hash'])) {
                        $svcVerif->remover($email);
                        $sucesso = "Conta criada com sucesso! Você já pode fazer login.";
                    } else {
                        // Pode ser que o e-mail tenha sido cadastrado em paralelo
                        $svcVerif->remover($email);
                        $sucesso = "Conta já existente para este e-mail. Você já pode fazer login.";
                    }
                } else {
                    $erro = "Código inválido ou expirado.";
                    $mostrar_verificacao = true;
                    // Preserva o e-mail para nova tentativa
                    $email_preservado = $email;
                }
            }
        } else {
            // Etapa 1: coleta de dados e envio do código
            $nome = sanitizar($_POST['nome']);
            $email = sanitizar($_POST['email']);
            $senha = $_POST['senha'];

            // Validações básicas
            if (empty($nome) || empty($email) || empty($senha)) {
                $erro = "Por favor, preencha todos os campos.";
            } elseif (strlen($senha) < 6) {
                $erro = "A senha deve ter pelo menos 6 caracteres.";
            } elseif ($usuario->email_existe($email)) {
                $erro = "Este e-mail já está cadastrado.";
            } else {
                // Gera etapa de verificação por e-mail
                $mostrar_verificacao = true;
                $email_preservado = $email;

                // Gera e persiste código (expira em 15 min)
                $codigo = (string)random_int(100000, 999999);
                $svcVerif->criar_pendente($nome, $email, $senha, $codigo, 900);
                // Tenta enviar o e-mail (independente do resultado, não detalhar para o usuário)
                enviar_codigo_verificacao($email, $nome, $codigo);

                // Mensagem neutra
                $info = "Enviamos um código de verificação para o e-mail informado. Digite-o abaixo para concluir o cadastro.";
            }
        }
    }
}

// Gerar novo token CSRF
$csrf_token = gerar_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Calendário Corporativo</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
            <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg" sizes="any">
            <link rel="apple-touch-icon" href="assets/img/logo-placeholder.svg">
            <link rel="alternate icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
            width: 100%;
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.9);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
        }
        .card-header {
            border-bottom: none;
            padding: 1.5rem 1.5rem;
            background: linear-gradient(45deg, #1a237e, #0d47a1);
            border-radius: 16px 16px 0 0 !important;
        }
        .card-header h3 {
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 0;
            text-transform: uppercase;
            font-size: 1.5rem;
        }
        .card-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
            border-color: #0d6efd;
            background-color: #fff;
        }
        .input-group-text {
            border-radius: 8px 0 0 8px;
            background-color: #e9ecef;
            border: 1px solid #ddd;
            color: #495057;
            padding: 0 15px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #1976d2, #2196f3);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #1565c0, #1976d2);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        .btn-show-password {
            cursor: pointer;
            border-radius: 0 8px 8px 0;
            border: 1px solid #ddd;
            background-color: #e9ecef;
            color: #495057;
            transition: all 0.3s ease;
        }
        .btn-show-password:hover {
            background-color: #dee2e6;
        }
        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background-color: #e9ecef;
            width: 0%;
        }
        hr {
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(0, 0, 0, 0.1), transparent);
            border: 0;
            margin: 1.5rem 0;
        }
        a {
            color: #1976d2;
            text-decoration: none;
            transition: color 0.3s ease;
            font-weight: 500;
        }
        a:hover {
            color: #1565c0;
            text-decoration: underline;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .text-muted {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: block;
        }
        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container register-container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header text-white d-flex align-items-center">
                        <div class="me-3">
                            <img src="assets/img/logo-placeholder.svg" alt="Logo" class="img-fluid" style="max-width: 50px; height: auto;">
                        </div>
                        <h3 class="mb-0 flex-grow-1 text-center" style="margin-right: 2.5rem;">Criar Nova Conta</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // O código PHP para inicialização da sessão e processamento do registro
                        ?>
                        
                        <?php if (isset($erro)): ?>
                            <div class="alert alert-danger"><?= $erro ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($sucesso)): ?>
                            <div class="alert alert-success"><?= $sucesso ?></div>
                            <div class="text-center mb-3">
                                <a href="login.php" class="btn btn-primary">Ir para o Login</a>
                            </div>
                        <?php elseif (!empty($mostrar_verificacao)): ?>
                            <?php if (!empty($info)): ?>
                                <div class="alert alert-info"><?= $info ?></div>
                            <?php endif; ?>
                            <?php if (isset($erro)): ?>
                                <div class="alert alert-danger"><?= $erro ?></div>
                            <?php endif; ?>
                            <form method="POST" action="registro.php">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="etapa" value="verificar">
                                <input type="hidden" name="email" value="<?= isset($email_preservado) ? htmlspecialchars($email_preservado, ENT_QUOTES, 'UTF-8') : '' ?>">

                                <div class="mb-3">
                                    <label for="codigo" class="form-label">Código de Verificação</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-shield-halved"></i></span>
                                        <input type="text" pattern="[0-9]{6}" maxlength="6" class="form-control" id="codigo" name="codigo" placeholder="000000" required>
                                    </div>
                                    <small class="text-muted">O código expira em 15 minutos.</small>
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check me-2"></i> Validar código
                                    </button>
                                </div>
                            </form>

                            <hr>
                            <div class="text-center">
                                <p>Já possui uma conta? <a href="login.php" class="fw-bold">Faça login</a></p>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="registro.php">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="etapa" value="inicio">
                                
                                <div class="mb-3">
                                    <label for="nome" class="form-label">Nome Completo</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="nome" name="nome" value="<?= isset($nome) ? $nome : '' ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-mail</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? $email : '' ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="senha" class="form-label">Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="senha" name="senha" required minlength="6">
                                        <button class="btn btn-outline-secondary btn-show-password" type="button" tabindex="-1">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength bg-secondary" id="passwordStrength"></div>
                                    <small class="text-muted">A senha deve ter pelo menos 6 caracteres.</small>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i> Registrar
                                    </button>
                                </div>
                            </form>
                            
                            <hr>
                            <div class="text-center">
                                <p>Já possui uma conta? <a href="login.php" class="fw-bold">Faça login</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS e dependências -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Funcionalidade para mostrar/ocultar senha
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar todos os botões de mostrar senha
            document.querySelectorAll('.btn-show-password').forEach(function(button) {
                button.addEventListener('click', function() {
                    const passwordField = this.parentElement.querySelector('input');
                    // Alternar tipo do campo entre 'password' e 'text'
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    
                    // Alternar ícone entre 'eye' e 'eye-slash'
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            });
            
            // Indicador de força da senha
            const passwordInput = document.getElementById('senha');
            const strengthIndicator = document.getElementById('passwordStrength');
            
            passwordInput.addEventListener('input', function() {
                const value = passwordInput.value;
                let strength = 0;
                
                if (value.length >= 6) strength += 20;
                if (value.length >= 8) strength += 20;
                if (/[A-Z]/.test(value)) strength += 20;
                if (/[0-9]/.test(value)) strength += 20;
                if (/[^A-Za-z0-9]/.test(value)) strength += 20;
                
                // Atualizar a barra de força
                strengthIndicator.style.width = strength + '%';
                
                // Mudar a cor com base na força
                if (strength <= 40) {
                    strengthIndicator.className = 'password-strength bg-danger';
                } else if (strength <= 80) {
                    strengthIndicator.className = 'password-strength bg-warning';
                } else {
                    strengthIndicator.className = 'password-strength bg-success';
                }
            });
        });
    </script>
</body>
</html>