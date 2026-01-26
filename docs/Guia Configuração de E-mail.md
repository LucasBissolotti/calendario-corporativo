# Configuração de E-mail

Este documento consolida a configuração de e-mails do Calendário Corporativo. O sistema envia:
- Notificações de tarefas para participantes; e
- Códigos de verificação (2FA) no cadastro.

O envio usa PHPMailer (recomendado) e cai para `mail()` como fallback quando necessário.

## Arquivos envolvidos
- `includes/phpmailer_config.php`: configuração do PHPMailer e função de envio.
- `includes/email_helper.php`: funções auxiliares (tarefas e `enviar_codigo_verificacao`).

## Opções de configuração
Você pode usar:
1) PHPMailer via SMTP (recomendado); ou
2) `mail()` nativa do PHP (apenas fallback/legado).

---

## 1) PHPMailer via SMTP (recomendado)

### Passo 1 — Instalar dependência

```
composer require phpmailer/phpmailer
```

### Passo 2 — Configurar variáveis de ambiente

Crie um arquivo `.env` na raiz do projeto (não versionado) com:

```env
SMTP_HOST=smtp.exemplo.com.br
SMTP_PORT=587
SMTP_USERNAME=seu_usuario@exemplo.com.br
SMTP_PASSWORD=sua_senha_ou_app_password
SMTP_SECURE=tls
```

### Passo 3 — Remetente padrão
O sistema usa automaticamente o `SMTP_USERNAME` como remetente. Você pode customizar em `includes/email_helper.php`:

```php
$from_name  = 'Calendário Corporativo';
$from_email = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'noreply@exemplo.com.br';
```

---

## 2) Fallback com `mail()`

Se o SMTP falhar ou não estiver disponível, o sistema tenta `mail()`.
Para XAMPP (Windows), ajuste:

`C:\xampp\php\php.ini`
```ini
[mail function]
SMTP = smtp.seuprovedor.com
smtp_port = 587
sendmail_from = seu_remetente@dominio.com
sendmail_path = "\"C:\\xampp\\sendmail\\sendmail.exe\" -t"
```

`C:\xampp\sendmail\sendmail.ini`
```ini
[sendmail]
smtp_server=smtp.seuprovedor.com
smtp_port=587
auth_username=seu_remetente@dominio.com
auth_password=sua_senha_ou_app_password
force_sender=seu_remetente@dominio.com
```

Para Gmail, use "Senha de aplicativo" (2FA habilitado) em vez da senha normal.

---

## Testes rápidos
1. Crie uma tarefa com participantes e use "Salvar e Enviar Aviso".
2. Cadastre um usuário com e-mail do domínio configurado em `ALLOWED_EMAIL_DOMAIN` para receber o código 2FA.
3. Verifique o log do PHP (`C:\xampp\php\logs\php_error_log`) e eventuais logs SMTP.

## Segurança e boas práticas
- Prefira SMTP com TLS/STARTTLS; evite SMTP sem criptografia.
- Armazene credenciais em variáveis de ambiente e restrinja permissões do arquivo.
- Configure SPF, DKIM e DMARC no domínio remetente para melhor entregabilidade.
- Não exponha páginas de diagnóstico em produção.

## Solução de problemas
- Falha no envio via SMTP: valide host/porta/credenciais e teste STARTTLS.
- E-mails caem no spam: revise SPF/DKIM/DMARC e conteúdo.
- `mail()` sem entrega: verifique configuração do sendmail, firewall e reputação do IP.
- Verificação (2FA) não recebida: confirme o domínio de e-mail permitido, caixa de spam e relógio do servidor.
