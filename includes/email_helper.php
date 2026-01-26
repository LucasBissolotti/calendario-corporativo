<?php
/**
 * Funções auxiliares para envio de e-mails
 */

/**
 * Envia um e-mail de aviso sobre uma tarefa para os participantes
 * 
 * @param array $tarefa Dados da tarefa
 * @param array $participantes_emails Array associativo com nome => email dos participantes
 * @return bool True se enviou com sucesso, False caso contrário
 */
function enviar_aviso_tarefa($tarefa, $participantes_emails) {
    // Verificar se deve usar PHPMailer (precisa ter o arquivo de configuração)
    $usar_phpmailer = file_exists(__DIR__ . '/phpmailer_config.php');
    if ($usar_phpmailer) {
        require_once __DIR__ . '/phpmailer_config.php';
    }
    
    // Configurações de e-mail (carregadas do .env via config.php)
    require_once __DIR__ . '/config.php';
    $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : "Calendário Corporativo";
    $from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : "noreply@seudominio.com.br";
    $site_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    
    if (empty($participantes_emails)) {
        error_log("Nenhum participante com e-mail para enviar aviso da tarefa #{$tarefa['id']}");
        return false;
    }
    
    // Preparar data formatada
    $data_inicio = date('d/m/Y', strtotime($tarefa['data_inicio']));
    $data_fim = date('d/m/Y', strtotime($tarefa['data_fim']));
    
    // Preparar horários (se não for dia inteiro)
    $horario = "";
    if ($tarefa['dia_inteiro'] != 1 && !empty($tarefa['hora_inicio'])) {
        $horario = " às {$tarefa['hora_inicio']}";
        if (!empty($tarefa['hora_fim'])) {
            $horario .= " até {$tarefa['hora_fim']}";
        }
    }
    
    // Preparar local
    $local = !empty($tarefa['localizacao']) ? " em {$tarefa['localizacao']}" : "";
    
    // Recalcular título garantindo ordem: Status, Localização, Tipo, Participantes
    $componentesTitulo = [];
    if (!empty($tarefa['status'])) {
        $componentesTitulo[] = $tarefa['status'];
    }
    if (!empty($tarefa['localizacao'])) {
        $componentesTitulo[] = $tarefa['localizacao'];
    }
    if (!empty($tarefa['tipo_servico'])) {
        $componentesTitulo[] = $tarefa['tipo_servico'];
    }
    if (!empty($tarefa['participantes'])) {
        $componentesTitulo[] = $tarefa['participantes'];
    }
    $tituloExibicao = $componentesTitulo ? implode(' - ', $componentesTitulo) : $tarefa['titulo'];

    // Construir o assunto do e-mail (mantido como antes, somente título da tarefa)
    $assunto = "Nova tarefa: {$tarefa['titulo']} - {$data_inicio}";
    
    // Contador de e-mails enviados com sucesso
    $enviados = 0;
    $total = count($participantes_emails);
    
foreach ($participantes_emails as $nome => $email) {
    // Construir o corpo do e-mail
    $mensagem = "
    <!DOCTYPE html>
    <html lang=\"pt-BR\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Nova Tarefa: {$tituloExibicao}</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333333; 
                background-color: #ffffff; 
                margin: 0; 
                padding: 20px;
            }
            
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 8px; 
                overflow: hidden; 
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .email-header { 
                background: #ffffff; 
                padding: 25px; 
                text-align: center; 
                border-bottom: 1px solid #e9ecef;
            }
            .email-header h1 { 
                font-size: 22px; 
                font-weight: bold; 
                margin: 0;
                color: #2c3e50;
            }
            
            .email-content { 
                padding: 30px; 
            }
            .greeting { 
                font-size: 16px; 
                color: #2c3e50; 
                margin-bottom: 20px; 
            }
            
            .task-card { 
                background: #ffffff; 
                border-radius: 6px; 
                padding: 20px; 
                margin: 20px 0; 
                border: 1px solid #e9ecef;
                border-left: 4px solid {$tarefa['cor']};
            }
            .task-info { 
                margin-bottom: 12px; 
                display: flex;
            }
            .task-info:last-child { 
                margin-bottom: 0; 
            }
            .info-label { 
                font-weight: bold; 
                color: #495057; 
                width: 120px;
                flex-shrink: 0;
            }
            .info-value { 
                color: #2c3e50; 
                flex: 1;
            }
            
            .category-badge { 
                display: inline-block; 
                padding: 4px 8px; 
                background-color: {$tarefa['cor']}; 
                color: white; 
                border-radius: 4px; 
                font-size: 12px; 
                font-weight: bold; 
            }
            
            .action-section { 
                text-align: center; 
                margin: 30px 0;
                padding: 20px;
                background: #ffffff;
                border-radius: 6px;
                border: 1px solid #e9ecef;
            }
            
            .link-calendario { 
                color: #007bff; 
                text-decoration: underline;
                font-size: 16px;
                font-weight: bold;
            }
            
            .email-footer { 
                background: #ffffff; 
                padding: 20px; 
                text-align: center; 
                border-top: 1px solid #e9ecef;
            }
            .footer-text { 
                font-size: 12px; 
                color: #6c757d; 
                line-height: 1.4;
            }
            
            @media (max-width: 480px) {
                body { padding: 10px; }
                .email-content { padding: 20px; }
                .email-header { padding: 20px; }
                .task-info {
                    flex-direction: column;
                }
                .info-label {
                    width: 100%;
                    margin-bottom: 5px;
                }
            }
        </style>
    </head>
    <body>
        <div class=\"email-container\">
            <div class=\"email-header\">
                <h1>Nova Tarefa: {$tituloExibicao}</h1>
            </div>
            
            <div class=\"email-content\">
                <div class=\"greeting\">
                    Olá <strong>{$nome}</strong>,
                </div>
                
                <p>Você foi adicionado(a) como participante da tarefa <strong>{$tituloExibicao}</strong>.</p>
                
                <div class=\"task-card\">
                    <div class=\"task-info\">
                        <div class=\"info-label\">Categoria:</div>
                        <div class=\"info-value\">
                            <span class=\"category-badge\">{$tarefa['categoria']}</span>
                        </div>
                    </div>
                    
                    <div class=\"task-info\">
                        <div class=\"info-label\">Data:</div>
                        <div class=\"info-value\">
                            {$data_inicio}" . ($data_inicio != $data_fim ? " até {$data_fim}" : "") . "{$horario}
                        </div>
                    </div>
                    " . (!empty($local) ? "
                    <div class=\"task-info\">
                        <div class=\"info-label\">Local:</div>
                        <div class=\"info-value\">{$local}</div>
                    </div>" : "") . "
                    " . (!empty($tarefa['descricao']) ? "
                    <div class=\"task-info\">
                        <div class=\"info-label\">Descrição:</div>
                        <div class=\"info-value\">{$tarefa['descricao']}</div>
                    </div>" : "") . "
                </div>
                
                <div class=\"action-section\">
                    <p><strong>Acesse o calendário para mais detalhes:</strong></p>
                    <a href=\"{$site_url}\" class=\"link-calendario\">Acessar Calendário</a>
                </div>
            </div>
            
            <div class=\"email-footer\">
                <div class=\"footer-text\">
                    <p>Calendário Corporativo</p>
                    <p>Esta é uma mensagem automática. Por favor, não responda a este e-mail.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Resto do código permanece igual...
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: {$from_name} <{$from_email}>" . "\r\n";
    
    $enviado = false;
    
    if ($usar_phpmailer && function_exists('enviar_email_phpmailer')) {
        try {
            if (enviar_email_phpmailer($email, $nome, $assunto, $mensagem)) {
                $enviados++;
                $enviado = true;
                error_log("E-mail enviado via PHPMailer para {$email} sobre a tarefa #{$tarefa['id']}");
            } else {
                error_log("Falha ao enviar e-mail via PHPMailer para {$email} sobre a tarefa #{$tarefa['id']}");
            }
        } catch (Exception $e) {
            error_log("Exceção ao enviar e-mail via PHPMailer: " . $e->getMessage());
        }
    }
    
    if (!$enviado) {
        $additional_headers = '-f' . $from_email;
        $additional_params = "-oi -f {$from_email}";
        
        try {
            if (mail($email, $assunto, $mensagem, $headers, $additional_params)) {
                $enviados++;
                error_log("E-mail enviado via mail() para {$email} sobre a tarefa #{$tarefa['id']}");
            } else {
                error_log("Falha ao enviar e-mail via mail() para {$email} sobre a tarefa #{$tarefa['id']}");
            }
        } catch (Exception $e) {
            error_log("Exceção ao enviar e-mail via mail(): " . $e->getMessage());
        }
    }
}
    
    return $enviados > 0;
}

/**
 * Extrai e-mails dos participantes a partir de uma string ou array de participantes
 * 
 * @param string|array $participantes_str String com nomes de participantes separados por vírgula ou array
 * @param array $usuarios Array de usuários do sistema com e-mails
 * @return array Array associativo com nome => email dos participantes
 */
function extrair_emails_participantes($participantes_str, $usuarios) {
    $participantes_emails = [];
    
    // Converter para array se for string
    if (is_string($participantes_str)) {
        $participantes = explode(',', $participantes_str);
    } else {
        $participantes = $participantes_str;
    }
    
    // Remover espaços em branco
    $participantes = array_map('trim', $participantes);
    
    // Associar nomes aos e-mails
    foreach ($participantes as $nome) {
        if (!empty($nome)) {
            // Procurar o e-mail correspondente ao nome
            foreach ($usuarios as $usuario) {
                if ($usuario['nome'] == $nome && !empty($usuario['email'])) {
                    $participantes_emails[$nome] = $usuario['email'];
                    break;
                }
            }
        }
    }
    
    return $participantes_emails;
}

/**
 * Envia e-mail com código de verificação para cadastro (2FA)
 *
 * @param string $email
 * @param string $nome
 * @param string $codigo 6 dígitos
 * @return bool
 */
function enviar_codigo_verificacao($email, $nome, $codigo) {
    $usar_phpmailer = file_exists(__DIR__ . '/phpmailer_config.php');
    if ($usar_phpmailer) {
        require_once __DIR__ . '/phpmailer_config.php';
    }

    $from_name = "Calendário Corporativo";
    $from_email = defined('SMTP_USERNAME') ? SMTP_USERNAME : "noreply@exemplo.com.br";

    $assunto = "Código de Verificação";
    $mensagem = "<!DOCTYPE html><html lang=\"pt-BR\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Código de Verificação</title></head><body style=\"font-family:Arial,sans-serif;color:#333;background:#fff;padding:20px\"><div style=\"max-width:600px;margin:0 auto;border:1px solid #e9ecef;border-radius:8px\"><div style=\"padding:20px;border-bottom:1px solid #e9ecef\"><h2 style=\"margin:0;color:#2c3e50\">Confirme seu cadastro</h2></div><div style=\"padding:24px\"><p>Olá <strong>" . htmlspecialchars($nome) . "</strong>,</p><p>Use o código abaixo para confirmar a criação da sua conta:</p><div style=\"font-size:28px;font-weight:bold;letter-spacing:6px;background:#f8f9fa;border:1px dashed #ccd5e0;padding:16px;text-align:center;border-radius:8px;color:#1a237e\">" . htmlspecialchars($codigo) . "</div><p style=\"margin-top:16px;color:#555\">Este código expira em 15 minutos. Se você não solicitou este cadastro, ignore este e-mail.</p></div><div style=\"padding:16px;border-top:1px solid #e9ecef;text-align:center;color:#6c757d;font-size:12px\">Calendário Corporativo</div></div></body></html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from_email}>\r\n";

    if ($usar_phpmailer && function_exists('enviar_email_phpmailer')) {
        try {
            return enviar_email_phpmailer($email, $nome, $assunto, $mensagem);
        } catch (Exception $e) {
            error_log('PHPMailer falhou em enviar código de verificação: ' . $e->getMessage());
        }
    }

    try {
        $additional_params = "-oi -f {$from_email}";
        return mail($email, $assunto, $mensagem, $headers, $additional_params);
    } catch (Exception $e) {
        error_log('mail() falhou em enviar código de verificação: ' . $e->getMessage());
        return false;
    }
}

/**
 * Envia e-mail de recuperação de senha com link único e temporário
 * @param string $email
 * @param string $nome
 * @param string $linkRecuperacao
 * @return bool
 */
function enviar_link_recuperacao_senha($email, $nome, $linkRecuperacao) {
    $usar_phpmailer = file_exists(__DIR__ . '/phpmailer_config.php');
    if ($usar_phpmailer) {
        require_once __DIR__ . '/phpmailer_config.php';
    }

    $from_name = "Calendário Corporativo";
    $from_email = defined('SMTP_USERNAME') ? SMTP_USERNAME : "noreply@exemplo.com.br";

    $assunto = "Recuperação de Senha";
    $mensagem = "<!DOCTYPE html><html lang=\"pt-BR\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Recuperação de Senha</title></head><body style=\"font-family:Arial,sans-serif;color:#333;background:#fff;padding:20px\"><div style=\"max-width:600px;margin:0 auto;border:1px solid #e9ecef;border-radius:8px\"><div style=\"padding:20px;border-bottom:1px solid #e9ecef\"><h2 style=\"margin:0;color:#2c3e50\">Redefinir sua senha</h2></div><div style=\"padding:24px\"><p>Olá <strong>" . htmlspecialchars($nome) . "</strong>,</p><p>Recebemos uma solicitação para redefinir a sua senha.</p><p>Para continuar, clique no botão abaixo (o link expira em 1 hora):</p><p style=\"margin:20px 0\"><a href=\"" . htmlspecialchars($linkRecuperacao) . "\" style=\"background:#0d6efd;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;display:inline-block\">Redefinir senha</a></p><p>Se você não solicitou esta alteração, ignore este e-mail.</p></div><div style=\"padding:16px;border-top:1px solid #e9ecef;text-align:center;color:#6c757d;font-size:12px\">Calendário Corporativo</div></div></body></html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from_email}>\r\n";

    if ($usar_phpmailer && function_exists('enviar_email_phpmailer')) {
        try {
            return enviar_email_phpmailer($email, $nome, $assunto, $mensagem);
        } catch (Exception $e) {
            error_log('PHPMailer falhou em enviar link de recuperação: ' . $e->getMessage());
        }
    }

    try {
        $additional_params = "-oi -f {$from_email}";
        return mail($email, $assunto, $mensagem, $headers, $additional_params);
    } catch (Exception $e) {
        error_log('mail() falhou em enviar link de recuperação: ' . $e->getMessage());
        return false;
    }
}