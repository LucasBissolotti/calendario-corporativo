# 📅 Sistema de Calendário Corporativo

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)]()
[![Build](https://img.shields.io/badge/build-passing-brightgreen.svg)]()
[![Versão](https://img.shields.io/badge/version-1.0.0-yellow.svg)]()

Um sistema completo de calendário corporativo que permite o gerenciamento de usuários, criação e controle de tarefas, filtros avançados e envio automático de e-mails com programação semanal.

---

##  **Instalação**

### Pré-requisitos
- PHP 7.4 ou superior
- Composer
- Servidor web (Apache/Nginx)
- SQLite3

### Passos

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/LucasBissolotti/calendario-corporativo.git
   cd calendario-corporativo
   ```

2. **Instale as dependências:**
   ```bash
   composer install
   ```

3. **Configure as variáveis de ambiente:**
   ```bash
   cp .env.example .env
   ```
   Edite o arquivo `.env` com suas credenciais:
   - Configurações de SMTP para e-mails

4. **Acesse o sistema:**
   - O banco de dados será criado automaticamente na primeira execução
   - Acesse: `http://localhost/calendario-corporativo`
   - Login padrão: `admin@admin.com` / `admin123`
   - **⚠️ Altere a senha do admin imediatamente após o primeiro acesso!**

---

## 🚀 **Recursos Principais**

### 👤 **Gestão de Usuários**
- Criação e administração de usuários.
- Suporte a permissões diferentes (administrador e usuário padrão).
- Acesso a funções administrativas restrito apenas a administradores.

### 🗓️ **Calendário Inteligente**
- Visualizações completas: **Mês**, **Semana**, **Dia** e **Lista**.
- Criação, edição e exclusão de tarefas.
- Responsividade completa para desktop e dispositivos móveis.
- Título da tarefa inclui automaticamente a *Localização* (quando informada).
- Botão **"Baixar Relatório"** presente em todas as visualizações.
  
### 🔍 **Filtros Avançados**
- Filtro por **usuário criador** da tarefa.
- Filtro por **status**: Confirmado, Provisório e Problema.
- Tratamento corrigido para exibir tarefas criadas por administradores usando o nome real do usuário.

### 📨 **Envio Automático de E-mails**
- Envio automático toda **sexta-feira** com as tarefas programadas para a semana seguinte.
- Aba administrativa para configurar os destinatários.
- Campo adicional para adicionar participantes específicos.
- Botão de **envio de e-mail de teste**.
- Integração com estrutura de e-mails já existente no sistema.

---

## 📝 **Relatórios**
- Exportação estruturada e **totalmente compatível com Power BI**.
- Tratamento especial para a coluna *Participantes* (normalização adequada).
- Organização ideal de colunas/linhas para análise corporativa.

---

## 📁 **Tecnologias Utilizadas**

- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP
- **Banco de Dados:** SQLite  
- **E-mails:** SMTP (PHPMailer)
- **Agendamento:** Cron / Task Scheduler  

---

## 📱 **Responsividade**
- Layout totalmente adaptado para mobile.
- Calendário com navegação, botões e conteúdos otimizados.
- Todas as visualizações liberadas para dispositivos móveis.

---

## 🔒 **Segurança**

- Todas as credenciais sensíveis são armazenadas em variáveis de ambiente (`.env`)
- Proteção CSRF em todos os formulários
- Hashing seguro de senhas com `password_hash()`
- Cabeçalhos de segurança HTTP configurados

---

## 📄 **Licença**

Este projeto está licenciado sob a licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.
