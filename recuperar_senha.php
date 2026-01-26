<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/usuario.php';
require_once 'includes/email_helper.php';

iniciar_sessao();

function reset_tentativa_permitida(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agora = time();
    $janela = 10 * 60; // 10 minutos
    $limite = 5; // até 5 solicitações por janela
    if (!isset($_SESSION['reset_attempts'])) {
        $_SESSION['reset_attempts'] = [];
    }
    $tentativas = $_SESSION['reset_attempts'][$ip] ?? [];
    $tentativas = array_values(array_filter($tentativas, function($ts) use ($agora, $janela) {
        return ($agora - $ts) <= $janela;
    }));
    $_SESSION['reset_attempts'][$ip] = $tentativas;
    return count($tentativas) < $limite;
}

function registrar_reset_tentativa(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!isset($_SESSION['reset_attempts'])) {
        $_SESSION['reset_attempts'] = [];
    }
    if (!isset($_SESSION['reset_attempts'][$ip])) {
        $_SESSION['reset_attempts'][$ip] = [];
    }
    $_SESSION['reset_attempts'][$ip][] = time();
}

$mensagem_enviada = false;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!reset_tentativa_permitida()) {
        $erro = "Muitas solicitações. Aguarde alguns minutos e tente novamente.";
    } else if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erro = "Erro de validação do formulário. Por favor, tente novamente.";
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Trata como enviado, mantendo a resposta genérica
            $mensagem_enviada = true;
        } else {
            $usuarioSvc = new Usuario();
            $usuario = $usuarioSvc->obter_por_email($email);

            // Gerar token e gravar registro (mesmo se usuário não existir, apenas com e-mail)
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $expires = time() + 3600; // 1 hora
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("INSERT INTO recuperacao_senha (token, user_id, email, expires_at, request_ip) VALUES (:token, :uid, :email, :exp, :ip)");
            $uid = $usuario ? $usuario['id'] : null;
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->bindValue(':uid', $uid, is_null($uid) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':exp', $expires, PDO::PARAM_INT);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            try { $stmt->execute(); } catch (Throwable $e) { /* não vaza erro para o usuário */ }

            // Montar link absoluto
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $scheme = $is_https ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            if ($basePath === '' || $basePath === '/') { $basePath = ''; }
            $link = $scheme . '://' . $host . $basePath . '/redefinir_senha.php?token=' . urlencode($token);

            if ($usuario) {
                try {
                    enviar_link_recuperacao_senha($email, $usuario['nome'], $link);
                } catch (Throwable $e) {
                    // Não revela falha de envio ao usuário
                }
            }
            registrar_reset_tentativa();
            $mensagem_enviada = true;
        }
    }
}

$csrf_token = gerar_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Calendário Corporativo</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo-placeholder.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-unlock-alt me-2"></i> Recuperar Senha</h5>
                </div>
                <div class="card-body">
                    <?php if ($erro): ?>
                        <div class="alert alert-danger"><?= $erro ?></div>
                    <?php endif; ?>

                    <?php if ($mensagem_enviada): ?>
                        <div class="alert alert-success">
                            Se o e-mail existir na nossa base, enviaremos instruções para redefinição de senha.
                        </div>
                        <div class="text-center">
                            <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i> Voltar ao Login</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="recuperar_senha.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Enviar link de redefinição
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
