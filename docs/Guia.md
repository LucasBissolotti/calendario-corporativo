# Guia completo do sistema Calendário Corporativo

Este documento consolida arquitetura, módulos, fluxos principais, políticas de segurança, rotinas de manutenção e procedimentos de atualização para garantir continuidade e suporte técnico ao longo do tempo.

## 1. Visão geral

- Finalidade: gestão de tarefas corporativas com calendário (FullCalendar), controle de disponibilidade, anexos e recursos administrativos.
- Stack:
  - Backend: PHP 8.x (XAMPP), SQLite
  - Frontend: FullCalendar + Bootstrap 5 + Vanilla JS
  - Email: PHPMailer (opcional), mail() como fallback

## 2. Estrutura de diretórios (resumo)

- `api.php`: gateway HTTP (REST simples) para CRUD de tarefas
- `index.php`, `login.php`, `logout.php`, `perfil.php`, `registro.php`: páginas principais
- `includes/`
  - `config.php`: configuração global (sessão, cabeçalhos de segurança, constantes)
  - `database.php`: conexão SQLite, criação de schema inicial e admin padrão
  - `usuario.php`: autenticação, CRUD de usuários, privilégios
  - `tarefa.php`: CRUD de tarefas e anexos; listagem por período
  - `categoria.php`: categorias e cores
  - `email_helper.php`: envio de e-mails com HTML
  - `backlog.php`: registro de atividades (auditoria funcional)
- `assets/js/calendario.js`: lógica do calendário, modais, drag/drop
- `assets/css/`: estilos
- `admin/`: dashboard e ferramentas administrativas
- `uploads/`: armazenamento de anexos e `uploads/avatares/` (imagens públicas de perfil)
- `database/`: banco SQLite (recomendado mover para fora do webroot em produção)
- `docs/`: documentação

## 3. Autenticação e autorização

- Sessão: iniciada via `includes/config.php::iniciar_sessao()` com reforços de segurança (HttpOnly, SameSite=Lax, Secure quando HTTPS, modo estrito de sessão).
- Login: `login.php` (aceita email ou nome de usuário); rate limit simples (10 tentativas/15min por IP via sessão).
- Permissões:
  - Usuário autenticado: acesso ao calendário e suas funcionalidades
  - Admin: privilégios adicionais (dashboard, limpeza de backlog, etc.)

### 3.1 Cadastro com verificação (2FA por e-mail) e domínio interno

- Domínio interno: durante o cadastro, apenas e-mails do domínio configurado em `ALLOWED_EMAIL_DOMAIN` são aceitos. A validação é silenciosa: usuários com domínios externos verão o fluxo natural de solicitação de código, porém nenhuma conta será criada sem um e-mail interno válido.
- Fluxo de 2 etapas (registro):
  1) Usuário informa nome, e-mail e senha em `registro.php`.
  2) O sistema envia um código de 6 dígitos por e-mail (PHPMailer ou `mail()`), válido por 15 minutos. O código é persistido em `verificacoes` com hash e limite de tentativas.
  3) Usuário digita o código no site; somente após a validação correta a conta é criada em `usuarios`.
- Tabelas envolvidas: `verificacoes` (pendências) e `usuarios` (definitivo).
- Reenvio de código não está exposto na UI por padrão; pode ser adicionado no futuro.

## 4. Configuração e variáveis de ambiente

- `.env`: arquivo de variáveis de ambiente (não versionado)
  - `DB_PATH`: caminho para o SQLite
  - `DB_BACKUP_DIR`: diretório para backups
  - `SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_PORT`, `SMTP_SECURE`: configurações de e-mail
  - `SITE_TITLE`: título do site
  - `ALLOWED_EMAIL_DOMAIN`: domínio permitido para cadastro
- `includes/config.php`:
  - `UPLOAD_DIR`: pasta de uploads (já protegida contra acesso direto).
  - `ALLOW_FILE_DOWNLOADS`: `false` por padrão. Com `false`, o sistema não entrega anexos via HTTP e remove links de visualização.
- Fuso horário: `America/Sao_Paulo`.

## 5. Banco de dados

- SQLite: schema criado automaticamente em `includes/database.php` se o arquivo não existir.
- Tables principais:
  - `usuarios` (id, nome, email, senha hash, is_admin, avatar)
  - `tarefas` (campos de data/hora, dia_inteiro, dias_uteis, participantes, etc.)
  - `anexos` (tarefa_id, nome_arquivo, tipo_arquivo, caminho)
  - `categorias`
  - `verificacoes` (email, nome, senha_hash, codigo_hash, expires_at, attempts, created_at) — pendências de cadastro para 2FA.

## 6. API (gateway `api.php`)

Ações (querystring `acao=`):
- `listar`: retorna eventos para FullCalendar (respeita filtro, `dias_uteis`, e fim exclusivo para allDay).
- `obter`: retorna dados completos de uma tarefa + anexos.
- `criar`: cria tarefa; pode enviar e-mails para participantes após salvar.
- `atualizar`: atualiza tarefa (drag/drop e redimensionamento também usam este caminho).
- `excluir`: remove tarefa (e anexos físicos associados).
- `excluir_anexo`: remove um anexo.
- `enviar_aviso`: reenvia e-mails aos participantes.

Observações importantes:
- CSRF obrigatório em POST; token em `$_SESSION['csrf_token']`.
- Respostas JSON com `status` e mensagens em caso de erro.

## 7. Frontend (calendário e UI)

- `assets/js/calendario.js`:
  - Carrega eventos via `acao=listar` com filtros de participantes/categoria.
  - Modal de criação/edição com suporte a `dia_inteiro` e `dias_uteis`.
  - "Dias úteis": eventos multi-dia são segmentados, ocultando finais de semana, e mantendo a data final visualmente inclusiva.
  - Drag/drop e resize: ajustam datas respeitando fuso local (evita drift UTC); para eventos allDay subtrai 1 dia do fim na gravação.
  - Anexos: visualização/descarregamento foi desabilitada; interface exibe rótulo "Bloqueado".

## 8. E-mails

- `includes/email_helper.php`:
  - Corpo em HTML responsivo; inclui categoria, datas, local (quando presente) e descrição.
  - PHPMailer opcional via `includes/phpmailer_config.php`; caso contrário, utiliza `mail()`.
  - `enviar_codigo_verificacao($email, $nome, $codigo)`: envia o código de 6 dígitos para confirmação de cadastro.
  - Manual único de configuração: ver `docs/Guia Configuração de E-mail.md`.

## 9. Segurança (controles em vigor)

- Sessão endurecida (HttpOnly, SameSite, Secure quando HTTPS, strict mode) e regeneração de ID no login.
- Cabeçalhos de segurança: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CSP (com suporte aos CDNs usados).
- HSTS automático quando HTTPS.
- Rate limit de login (simples) por IP/sessão.
- Downloads de anexos desabilitados (flag `ALLOW_FILE_DOWNLOADS=false`) e sem endpoints ativos; UI não disponibiliza links de download.
- `uploads/.htaccess`: bloqueia qualquer acesso direto, com exceção de `uploads/avatares/`.
- `uploads/avatares/.htaccess`: libera apenas a pasta de avatares para exibição pública (necessária para carregar imagens de perfil).
- `database/.htaccess`: acesso negado à pasta do banco por HTTP.

## 10. Manutenção e operações

- Categorias: ajustar cores/nomes em `includes/categoria.php` e/ou `CATEGORIAS` em `includes/config.php` se usado.
- E-mail: configurar `phpmailer_config.php` (SMTP, credenciais) quando necessário, ou via variáveis de ambiente no `.env`.
- Limpeza de backlog: via `admin/dashboard.php` (apenas admin), com CSRF e confirmação.
- Avatares: armazenados em `uploads/avatares/` e servidos ao navegador; outras pastas de uploads continuam bloqueadas.

## 11. Backup e Restore do Banco de Dados (Automático)

- Como funciona:
  - O sistema monitora alterações via triggers nas tabelas `usuarios`, `tarefas`, `anexos`, `categorias` e `verificacoes`, atualizando `meta('last_change_epoch')` a cada INSERT/UPDATE/DELETE.
  - Ao final de cada requisição (shutdown do PHP), se houveram alterações desde o início da conexão, é criado um backup automaticamente.
  - Método preferencial: `VACUUM INTO` (snapshot consistente do SQLite). Fallback automático: cópia do arquivo `.db` via `copy()`.

- Local e nomenclatura:
  - Diretório: `DB_BACKUP_DIR` (padrão: `database/backups`).
  - Nome dos arquivos: `calendario_backup_YYYYMMDD_HHMMSS.db` (ordenável por data/hora).

- Retenção e rotação:
  - Quantidade máxima de arquivos: `DB_BACKUP_MAX` (padrão: 20). Sempre que um novo backup é criado, os mais antigos além do limite são removidos.

- Gerenciamento via painel (Admin > Backups):
  - Listar: exibe todos os arquivos em `DB_BACKUP_DIR` com tamanho e data.
  - Criar: "Criar backup agora" dispara um snapshot imediato com rotação.
  - Baixar: realiza o download seguro do arquivo (admin + CSRF, valida nome).
  - Restaurar: substitui `DB_PATH` pelo backup selecionado. Durante a restauração, o sistema suspende o backup em shutdown e fecha a conexão do SQLite para liberar o arquivo. Em Windows, se houver bloqueio de arquivo, pare o Apache/XAMPP e repita.
  - Excluir: remove o arquivo selecionado.
  - Observação: o botão de "Purgar excedentes" foi removido — a rotação automática já mantém o limite. Para aplicar um novo limite imediatamente, basta criar um backup para acionar a rotação, ou remover arquivos antigos manualmente.

- Restauração (passo a passo):
  1) Coloque o site em manutenção ou pare o Apache (XAMPP) para evitar escritas concorrentes.
  2) Identifique o arquivo principal do banco em `DB_PATH` (padrão: `database/calendario.db`).
  3) Escolha o arquivo de backup desejado em `DB_BACKUP_DIR` pelo timestamp do nome.
  4) Faça uma cópia de segurança do `.db` atual (contingência).
  5) Substitua o arquivo de `DB_PATH` pelo backup escolhido.
  6) Inicie o serviço e valide o funcionamento (login, listagem de tarefas).
