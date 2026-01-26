<?php
/**
 * Configuração do PHPMailer para o sistema de calendário
 * 
 * Este arquivo contém funções para envio de e-mail usando a biblioteca PHPMailer.
 * As credenciais são carregadas do arquivo .env via config.php.
 */

require_once __DIR__ . '/config.php';

// Carregamento das dependências do PHPMailer via autoload
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    throw new RuntimeException('Autoload do Composer não encontrado em ' . $autoloadPath . '. Rode "composer install" na raiz do projeto.');
}
require $autoloadPath;

/**
 * Função para enviar e-mails usando PHPMailer
 * 
 * @param string $destinatario E-mail do destinatário
 * @param string $nome_destinatario Nome do destinatário
 * @param string $assunto Assunto do e-mail
 * @param string $corpo Corpo do e-mail (HTML)
 * @param string $corpo_texto Corpo do e-mail (texto plano)
 * @param bool $debug Habilita o modo de debug (padrão: false)
 * @return bool Retorna true se o e-mail foi enviado com sucesso, false caso contrário
 */
function enviar_email_phpmailer($destinatario, $nome_destinatario, $assunto, $corpo, $corpo_texto = '', $debug = false) {
    // Criação de uma nova instância do PHPMailer
    $mail = new PHPMailer(true);
    
    // Configuração do nível de debug
    $mail->SMTPDebug = $debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
    if ($debug) {
        // Função para capturar as mensagens de debug
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
            if (defined('DEBUG_OUTPUT')) {
                echo "Debug: $str<br>";
            }
        };
    }

    try {
        // Configurações do servidor (carregadas do .env via config.php)
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        
        // Configurações de segurança - ajustadas para maior compatibilidade
        $mail->SMTPSecure = ''; // Sem criptografia explícita
        $mail->SMTPAutoTLS = false; // Desabilitar negociação automática de TLS
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Aumentar timeout para servidores lentos
        $mail->Timeout    = 60; // 60 segundos
        
        // Configurar Keep-Alive
        $mail->SMTPKeepAlive = true; // Manter conexão aberta

        // Configurar origem do e-mail
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Adicionar destinatário
        $mail->addAddress($destinatario, $nome_destinatario);
        
        // Configuração do e-mail de resposta
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Configuração do conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo;
        $mail->AltBody = !empty($corpo_texto) ? $corpo_texto : strip_tags(str_replace('<br>', "\n", $corpo));

        // Envio do e-mail
        $mail->send();
        
        // Log de sucesso
        error_log("E-mail enviado com sucesso para: $destinatario");
        
        return true;
    } catch (Exception $e) {
        // Log de erro
        error_log("Erro ao enviar e-mail para $destinatario: " . $mail->ErrorInfo);
        
        // Em modo de debug, mostra o erro na tela
        if ($debug && defined('DEBUG_OUTPUT')) {
            echo "Erro ao enviar e-mail: {$mail->ErrorInfo}";
        }
        
        return false;
    }
}

/**
 * Função alternativa para enviar e-mails usando o mail() nativo do PHP
 * Útil como fallback quando o SMTP não está funcionando
 * 
 * @param string $destinatario E-mail do destinatário
 * @param string $assunto Assunto do e-mail
 * @param string $corpo_texto Corpo do e-mail (texto plano)
 * @return bool Retorna true se o e-mail foi enviado com sucesso, false caso contrário
 */
function enviar_email_nativo($destinatario, $assunto, $corpo_texto) {
    $headers = "From: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
    
    $resultado = mail($destinatario, $assunto, $corpo_texto, $headers);
    
    if ($resultado) {
        error_log("E-mail nativo enviado com sucesso para: $destinatario");
    } else {
        error_log("Erro ao enviar e-mail nativo para: $destinatario");
    }
    
    return $resultado;
}
?>