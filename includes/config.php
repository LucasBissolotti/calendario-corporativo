<?php
// Carrega variáveis de ambiente do arquivo .env (se existir)
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }
}

// Função auxiliar para obter variáveis de ambiente
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return ($value !== false && $value !== null && $value !== '') ? $value : $default;
}

// Configurações do sistema
define('DB_PATH', __DIR__ . '/../' . env('DB_PATH', 'database/calendario.db'));
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('SITE_TITLE', env('SITE_TITLE', 'Calendário Corporativo'));
// Backup do SQLite
define('DB_BACKUP_DIR', env('DB_BACKUP_DIR', __DIR__ . '/../database/backups'));
define('DB_BACKUP_MAX', (int)env('DB_BACKUP_MAX', 20));
// Política de download de arquivos (false = ninguém pode baixar)
define('ALLOW_FILE_DOWNLOADS', filter_var(env('ALLOW_FILE_DOWNLOADS', 'false'), FILTER_VALIDATE_BOOLEAN));
// Domínio permitido para registro de usuários
define('ALLOWED_EMAIL_DOMAIN', env('ALLOWED_EMAIL_DOMAIN', '@seudominio\\.com\\.br$'));

// Configurações de E-mail (SMTP)
define('SMTP_HOST', env('SMTP_HOST', 'smtp.seudominio.com.br'));
define('SMTP_PORT', (int)env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', 'noreply@seudominio.com.br'));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'noreply@seudominio.com.br'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Calendário Corporativo'));

// Configurações de fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Aplicar cabeçalhos de segurança e reforçar configurações de sessão/cookies
function aplicar_cabecalhos_seguranca() {
    // Evitar reenvio de cabeçalhos depois de enviados
    if (headers_sent()) return;

    // Detecta HTTPS inclusive atrás de proxy
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Cabeçalhos de segurança
    $allow_iframe = defined('ALLOW_IFRAME') && ALLOW_IFRAME === true;
    header('X-Frame-Options: ' . ($allow_iframe ? 'SAMEORIGIN' : 'DENY'));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()");
            $csp = "default-src 'self'; " .
                // Scripts: permitir jQuery e DataTables além dos já usados
                "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net 'unsafe-inline'; " .
                // Styles: permitir DataTables CSS
                "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net 'unsafe-inline'; " .
                "img-src 'self' data:; " .
                // Fonts: manter já existentes
                "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:; " .
                // Conexões: permitir CDNs para mapas de fontes e recursos dinâmicos
                "connect-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net; " .
            "frame-ancestors " . ($allow_iframe ? "'self'" : "'none'") . "; " .
            "base-uri 'self'";
        header("Content-Security-Policy: $csp");
    if ($is_https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Fortalecer sessão
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    // Samesite e Secure via session_set_cookie_params
    $params = session_get_cookie_params();
    $cookie_lifetime = $params['lifetime'] ?? 0;
    $cookie_path = $params['path'] ?? '/';
    $cookie_domain = $params['domain'] ?? '';
    $cookie_secure = $is_https ? true : false;
    $cookie_httponly = true;
    // PHP 7.3+: array de opções
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $cookie_lifetime,
            'path' => $cookie_path,
            'domain' => $cookie_domain,
            'secure' => $cookie_secure,
            'httponly' => $cookie_httponly,
            'samesite' => 'Lax'
        ]);
    } else {
        // Fallback para versões antigas
        session_set_cookie_params($cookie_lifetime, $cookie_path . '; samesite=Lax', $cookie_domain, $cookie_secure, $cookie_httponly);
    }
}

// Função para iniciar a sessão de forma segura
function iniciar_sessao() {
    if (session_status() == PHP_SESSION_NONE) {
        aplicar_cabecalhos_seguranca();
        session_start();
    }
}

// Função para verificar se o usuário está logado
function esta_logado() {
    iniciar_sessao();
    return isset($_SESSION['usuario_id']);
}

// Função para redirecionar para a página de login se não estiver autenticado
function requer_login() {
    if (!esta_logado()) {
        header("Location: login.php");
        exit;
    }
}

// Função para sanitizar entradas
function sanitizar($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Função para gerar um token CSRF
function gerar_csrf_token() {
    iniciar_sessao();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para verificar token CSRF
function verificar_csrf_token($token_enviado) {
    iniciar_sessao();
    if (!isset($_SESSION['csrf_token']) || $token_enviado !== $_SESSION['csrf_token']) {
        die("Erro de validação CSRF. Por favor, tente novamente.");
    }
}

// Verificar se o usuário atual é administrador
function is_admin() {
    iniciar_sessao();
    return isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'] === true;
}

// Função para redirecionar se não for administrador
function requer_admin() {
    if (!esta_logado() || !is_admin()) {
        header("Location: index.php");
        exit;
    }
}