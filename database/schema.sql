-- ===========================================
-- SCHEMA DO BANCO DE DADOS - CALENDÁRIO CORPORATIVO
-- ===========================================
-- Este arquivo contém apenas a estrutura (DDL) do banco de dados.
-- O banco de dados real será criado automaticamente pela aplicação.
-- SQLite 3.x

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    senha TEXT NOT NULL,
    cargo TEXT,
    avatar TEXT,
    is_admin INTEGER DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de tarefas/eventos
CREATE TABLE IF NOT EXISTS tarefas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    titulo TEXT NOT NULL,
    categoria TEXT NOT NULL,
    cor TEXT NOT NULL,
    data_inicio TEXT NOT NULL,
    data_fim TEXT NOT NULL,
    hora_inicio TEXT,
    hora_fim TEXT,
    dia_inteiro INTEGER DEFAULT 0,
    participantes TEXT,
    localizacao TEXT,
    descricao TEXT,
    checklist TEXT,
    criador_nome TEXT,
    status TEXT DEFAULT 'Provisório',
    tipo_servico TEXT,
    dias_uteis INTEGER DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de anexos
CREATE TABLE IF NOT EXISTS anexos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tarefa_id INTEGER NOT NULL,
    nome_arquivo TEXT NOT NULL,
    tipo_arquivo TEXT NOT NULL,
    caminho TEXT NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
);

-- Tabela de categorias de tarefas
CREATE TABLE IF NOT EXISTS categorias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT UNIQUE NOT NULL,
    cor TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de verificações de cadastro para 2FA por e-mail
CREATE TABLE IF NOT EXISTS verificacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    nome TEXT NOT NULL,
    senha_hash TEXT NOT NULL,
    codigo_hash TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    attempts INTEGER DEFAULT 0,
    created_at INTEGER DEFAULT (strftime('%s','now'))
);

-- Tabela de metadados do sistema
CREATE TABLE IF NOT EXISTS meta (
    chave TEXT PRIMARY KEY,
    valor TEXT NOT NULL
);

-- Tabela de destinatários do envio semanal
CREATE TABLE IF NOT EXISTS envio_semanal_destinatarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER UNIQUE NOT NULL,
    created_at INTEGER DEFAULT (strftime('%s','now')),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de recuperação de senha (tokens)
CREATE TABLE IF NOT EXISTS recuperacao_senha (
    token TEXT PRIMARY KEY,
    user_id INTEGER,
    email TEXT,
    expires_at INTEGER NOT NULL,
    used_at INTEGER,
    created_at INTEGER DEFAULT (strftime('%s','now')),
    request_ip TEXT,
    FOREIGN KEY(user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Índices para otimização de consultas
CREATE INDEX IF NOT EXISTS idx_verificacoes_email ON verificacoes(email);
CREATE INDEX IF NOT EXISTS idx_rec_senha_email ON recuperacao_senha(email);
CREATE INDEX IF NOT EXISTS idx_rec_senha_user ON recuperacao_senha(user_id);

-- Inserir valores padrão na tabela meta
INSERT OR IGNORE INTO meta (chave, valor) VALUES ('last_change_epoch', '0');
INSERT OR IGNORE INTO meta (chave, valor) VALUES ('envio_semanal_ultimo_envio', '0');
