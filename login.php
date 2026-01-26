<?php
require_once 'includes/config.php';
require_once 'includes/usuario.php';

iniciar_sessao();

// Proteção simples contra força bruta (rate limit por IP em janela rolante)
function tentativa_login_permitida(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agora = time();
    $janela = 5 * 60; // 5 minutos
    $limite = 10; // até 10 tentativas na janela
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    // Filtrar tentativas expiradas e contar as atuais
    $tentativas = $_SESSION['login_attempts'][$ip] ?? [];
    $tentativas = array_values(array_filter($tentativas, function($ts) use ($agora, $janela) {
        return ($agora - $ts) <= $janela;
    }));
    $_SESSION['login_attempts'][$ip] = $tentativas;
    return count($tentativas) < $limite;
}

function registrar_tentativa_login_falha(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    if (!isset($_SESSION['login_attempts'][$ip])) {
        $_SESSION['login_attempts'][$ip] = [];
    }
    $_SESSION['login_attempts'][$ip][] = time();
}

// Se já estiver logado, redireciona para o calendário
if (esta_logado()) {
    header("Location: index.php");
    exit;
}

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tentativa_login_permitida()) {
        $erro = "Muitas tentativas de login. Aguarde alguns minutos e tente novamente.";
    } else {
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erro = "Erro de validação do formulário. Por favor, tente novamente.";
    } else {
        $login = sanitizar($_POST['email']); // Mantendo o nome do campo como 'email' para compatibilidade
        $senha = $_POST['senha'];
        
        if (empty($login) || empty($senha)) {
            $erro = "Por favor, preencha todos os campos.";
        } else {
            $usuario = new Usuario();
            // Verificar se é o admin com nome de usuário "admin"
            if ($login === 'admin') {
                $resultado = $usuario->login_admin($senha);
            } else {
                $resultado = $usuario->login($login, $senha);
            }
            
            if ($resultado) {
                $_SESSION['usuario_id'] = $resultado['id'];
                $_SESSION['usuario_nome'] = $resultado['nome'];
                $_SESSION['usuario_email'] = $resultado['email'];
                $_SESSION['usuario_admin'] = isset($resultado['is_admin']) && $resultado['is_admin'] == 1;
                
                // Regenerar ID da sessão para segurança
                session_regenerate_id(true);
                
                header("Location: index.php");
                exit;
            } else {
                $erro = "Nome de usuário/E-mail ou senha incorretos.";
                registrar_tentativa_login_falha();
            }
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
    <title>Login - Calendário Corporativo</title>
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
        .login-container {
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
    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header text-white d-flex align-items-center">
                        <div class="me-3">
                            <img src="assets/img/logo-placeholder.svg" alt="Logo" class="img-fluid" style="max-width: 40px; height: auto;">
                        </div>
                        <h3 class="mb-0 flex-grow-1 text-center" style="margin-right: 2.5rem;">Acesso ao Sistema</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // O código PHP para inicializar a sessão e processar o login
                        // agora está no início do arquivo, antes do DOCTYPE
                        ?>
                        
                        <?php if (isset($erro)): ?>
                            <div class="alert alert-danger"><?= $erro ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Nome de Usuário/E-mail</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" required>
                                    <button class="btn btn-outline-secondary btn-show-password" type="button" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i> Entrar
                                </button>
                            </div>
                        </form>
                        
                        <hr>
                        <div class="text-center mb-2">
                            <a href="recuperar_senha.php">Esqueceu sua senha?</a>
                        </div>
                        <div class="text-center">
                            <p>Não tem uma conta? <a href="registro.php" class="fw-bold">Registre-se</a></p>
                        </div>
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
            const togglePassword = document.querySelector('.btn-show-password');
            const password = document.querySelector('#senha');
            
            togglePassword.addEventListener('click', function() {
                // Alternar tipo do campo entre 'password' e 'text'
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // Alternar ícone entre 'eye' e 'eye-slash'
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>