# 📅 Sistema de Calendário Corporativo

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)]()
[![SQLite](https://img.shields.io/badge/SQLite-3-003B57.svg)]()

Um sistema completo de calendário corporativo que permite o gerenciamento de usuários, criação e controle de tarefas, filtros avançados e envio automático de e-mails com programação semanal.

---

## 🖼️ Screenshots

> *Adicione screenshots do sistema aqui*

---

## ⚙️ **Instalação**

### Pré-requisitos
- PHP 7.4 ou superior
- Composer
- Servidor web (Apache/Nginx) - Recomendado: **XAMPP** ou **WAMP**
- SQLite3 (já incluso no PHP)

### Instalação com XAMPP (Recomendado para Windows)

1. **Baixe e instale o XAMPP:**
   - Acesse: https://www.apachefriends.org/
   - Instale com as opções padrão

2. **Clone o repositório na pasta htdocs:**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/LucasBissolotti/calendario-corporativo.git
   cd calendario-corporativo
   ```

3. **Instale as dependências:**
   ```bash
   composer install
   ```

4. **Configure as variáveis de ambiente:**
   ```bash
   copy .env.example .env
   ```
   Edite o arquivo `.env` conforme necessário (configurações de SMTP para e-mails)

5. **Inicie o Apache no XAMPP Control Panel**

6. **Acesse o sistema:**
   - URL: `http://localhost/calendario-corporativo`
   - **Login padrão:** `admin@admin.com` / `admin123`
   - ⚠️ **Altere a senha do admin imediatamente após o primeiro acesso!**

### Instalação em Linux/Mac

```bash
git clone https://github.com/LucasBissolotti/calendario-corporativo.git
cd calendario-corporativo
composer install
cp .env.example .env
# Configure o .env e aponte seu servidor web para a pasta
```

---

## 🚀 **Primeiros Passos (Após Instalação)**

1. **Faça login** com `admin@admin.com` / `admin123`
2. **Acesse "Administração"** no menu
3. **Crie as Categorias** (aba "Categorias") - Ex: Reunião, Férias, Viagem
4. **Crie os itens de Checklist** (aba "Checklists") - Ex: Confirmado com cliente, Logística definida
5. **Crie novos usuários** se necessário (aba "Usuários")
6. **Altere a senha do admin** em "Perfil"

---

## 📋 **Recursos Principais**

### 👤 **Gestão de Usuários**
- Criação e administração de usuários
- Permissões: Administrador e Usuário padrão
- Upload de avatar de perfil
- Cadastro com verificação por e-mail (2FA)

### 🗓️ **Calendário Inteligente**
- Visualizações: **Mês**, **Semana**, **Dia** e **Lista**
- Criação, edição e exclusão de tarefas com drag & drop
- Título automático baseado em: Status, Categoria, Localização, Tipo de Serviço e Participantes
- Suporte a "Dia inteiro" e "Apenas dias úteis"
- Responsivo para desktop e mobile

### 🔍 **Filtros Avançados**
- Por participantes
- Por categoria
- Por usuário criador
- Por status (Confirmado, Provisório, Problema)
- Por tipo de serviço

### 📨 **E-mails**
- Envio de avisos aos participantes das tarefas
- Envio automático semanal (sextas-feiras) com resumo da próxima semana
- Templates HTML responsivos
- Configurável via painel admin

### 📝 **Relatórios**
- Exportação em formato compatível com **Power BI** e **Excel**
- Dados normalizados para análise

### 🔧 **Painel Administrativo**
- Gerenciar usuários (criar, promover admin, excluir)
- Gerenciar tarefas
- Gerenciar categorias e checklists
- Log de atividades (backlog)
- Backup e restauração do banco de dados
- Configurar destinatários do envio semanal

---

## 📁 **Estrutura do Projeto**

```
calendario-corporativo/
├── admin/              # Painel administrativo
├── assets/
│   ├── css/           # Estilos
│   ├── img/           # Imagens estáticas
│   └── js/            # JavaScript (calendario.js)
├── database/          # Banco SQLite (criado automaticamente)
│   └── backups/       # Backups automáticos
├── docs/              # Documentação adicional
├── includes/          # Classes PHP (config, database, usuario, tarefa, etc.)
├── uploads/           # Uploads de usuários
│   └── avatares/      # Fotos de perfil
├── vendor/            # Dependências (Composer)
├── .env.example       # Modelo de configuração
├── api.php            # API REST para o calendário
├── index.php          # Página principal (calendário)
└── README.md
```

---

## 🔒 **Segurança**

- Credenciais em variáveis de ambiente (`.env`)
- Proteção CSRF em todos os formulários
- Hashing de senhas com `password_hash()` (bcrypt)
- Rate limiting no login
- Cabeçalhos de segurança HTTP (X-Frame-Options, CSP, etc.)
- Proteção de diretórios sensíveis via `.htaccess`

---

## 📧 **Configuração de E-mail (Opcional)**

Para habilitar o envio de e-mails, edite o arquivo `.env`:

```env
SMTP_HOST=smtp.seuservidor.com
SMTP_PORT=587
SMTP_USER=seu@email.com
SMTP_PASSWORD=sua_senha
SMTP_FROM_EMAIL=noreply@seudominio.com
SMTP_FROM_NAME="Calendário Corporativo"
```

Consulte `docs/Guia Configuração de E-mail.md` para instruções detalhadas.

---

## 📱 **Responsividade**

- Layout adaptado para mobile
- Navegação otimizada em telas pequenas
- Todas as visualizações do calendário funcionam em dispositivos móveis

---

## 🤝 **Contribuindo**

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

---

## 📄 **Licença**

Este projeto está licenciado sob a licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.
