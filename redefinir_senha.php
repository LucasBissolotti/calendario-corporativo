<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/usuario.php';

iniciar_sessao();

$pdo = Database::getInstance()->getConnection();
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($token) ? trim($token) : '';

$token_valido = false;
$registro = null;
$agora = time();
$erro = null;
$sucesso = null;

if ($token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM recuperacao_senha WHERE token = :t LIMIT 1");
    $stmt->bindValue(':t', $token, PDO::PARAM_STR);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($registro && empty($registro['used_at']) && (int)$registro['expires_at'] >= $agora) {
        $token_valido = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erro = "Erro de validação do formulário. Por favor, tente novamente.";
    } elseif (!$token_valido) {
        $erro = "Link inválido ou expirado.";
    } else {
        $senha = (string)($_POST['senha'] ?? '');
        $confirmar = (string)($_POST['confirmar'] ?? '');
        if ($senha === '' || $confirmar === '') {
            $erro = "Por favor, preencha todos os campos.";
        } elseif ($senha !== $confirmar) {
            $erro = "As senhas não conferem.";
        } elseif (strlen($senha) < 6) {
            $erro = "A senha deve ter pelo menos 6 caracteres.";
        } else {
            $usuarioSvc = new Usuario();
            $userId = $registro['user_id'] ?? null;
            $email = $registro['email'] ?? null;

            if (!$userId && $email) {
                // Tentar localizar pelo e-mail
                try {
                    $u = $usuarioSvc->obter_por_email($email);
                    if ($u) { $userId = $u['id']; }
                } catch (Throwable $e) { /* ignore */ }
            }

            if ($userId) {
                try {
                    if ($usuarioSvc->alterar_senha($userId, $senha)) {
                        $up = $pdo->prepare("UPDATE recuperacao_senha SET used_at = :u WHERE token = :t");
                        $up->execute([':u' => time(), ':t' => $token]);
                        $sucesso = "Senha redefinida com sucesso. Você já pode entrar.";
                        $token_valido = false; // evita reenvio do formulário
                    } else {
                        $erro = "Não foi possível redefinir a senha.";
                    }
                } catch (Throwable $e) {
                    $erro = "Ocorreu um erro ao redefinir a senha.";
                }
            } else {
                $erro = "Conta não encontrada para este token.";
            }
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
    <title>Redefinir Senha - Calendário Corporativo</title>
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
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i> Redefinir Senha</h5>
                </div>
                <div class="card-body">
                    <?php if ($erro): ?>
                        <div class="alert alert-danger"><?= $erro ?></div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success"><?= $sucesso ?></div>
                        <div class="text-center">
                            <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i> Ir para Login</a>
                        </div>
                    <?php elseif (!$token_valido): ?>
                        <div class="alert alert-warning">Link inválido ou expirado. Solicite um novo link de recuperação.</div>
                        <div class="text-center">
                            <a href="recuperar_senha.php" class="btn btn-secondary"><i class="fas fa-undo me-2"></i> Solicitar novamente</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="redefinir_senha.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <div class="mb-3">
                                <label for="senha" class="form-label">Nova Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" required minlength="6">
                                </div>
                                <small class="text-muted">Pelo menos 6 caracteres.</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar" class="form-label">Confirmar Nova Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirmar" name="confirmar" required minlength="6">
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Redefinir Senha</button>
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
