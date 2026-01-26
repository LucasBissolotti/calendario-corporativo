<?php
require_once 'config.php';

// Classe para gerenciar a conexão com o banco de dados SQLite
class Database {
    private static $instance = null;
    private static $suspendBackup = false;
    private $pdo;
    private $lastChangeAtStart = '0';

    private function __construct() {
        $this->inicializar_bd();
    }

    // Singleton para garantir apenas uma instância da conexão
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Inicializa o banco de dados se não existir
    private function inicializar_bd() {
        $db_existe = file_exists(DB_PATH);
        
        try {
            $this->pdo = new PDO("sqlite:" . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if (!$db_existe) {
                $this->criar_tabelas();
            } else {
                // Garantir tabelas auxiliares mesmo em bancos já existentes
                $this->aplicar_migracoes_sensiveis();
            }

            // Registrar marcador de alteração no início da conexão e o handler de shutdown
            $this->lastChangeAtStart = $this->obter_last_change_epoch();
            $this->registrar_shutdown();
        } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

    // Cria as tabelas necessárias se o banco de dados for novo
    private function criar_tabelas() {
        $sql = "
            -- Tabela de usuários
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                senha TEXT NOT NULL,
                cargo TEXT,
                is_admin INTEGER DEFAULT 0,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            -- Tabela de tarefas/eventos
            CREATE TABLE tarefas (
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
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            );

            -- Tabela de anexos
            CREATE TABLE anexos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tarefa_id INTEGER NOT NULL,
                nome_arquivo TEXT NOT NULL,
                tipo_arquivo TEXT NOT NULL,
                caminho TEXT NOT NULL,
                data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
            );

            -- Tabela de categorias de tarefas
            CREATE TABLE categorias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT UNIQUE NOT NULL,
                cor TEXT NOT NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            -- Tabela de itens de checklist
            CREATE TABLE checklists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT UNIQUE NOT NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ";

        try {
            $this->pdo->exec($sql);
            
            // Inserir usuário admin padrão
            $this->criar_admin_padrao();
            // Aplicar migrações auxiliares
            $this->aplicar_migracoes_sensiveis();
        } catch (PDOException $e) {
            die("Erro ao criar tabelas: " . $e->getMessage());
        }
    }

    // Aplica migrações leves/seguras idempotentes (CREATE IF NOT EXISTS, ALTERs triviais)
    private function aplicar_migracoes_sensiveis() {
        try {
            // Tabela de verificações de cadastro para 2FA por e-mail
            $this->pdo->exec("
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
            ");

            // Índices úteis
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_verificacoes_email ON verificacoes(email);");

            // Garantir coluna avatar (referenciada em código) se ainda não existir
            $cols = $this->pdo->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_ASSOC);
            $temAvatar = false;
            foreach ($cols as $c) {
                if ($c['name'] === 'avatar') { $temAvatar = true; break; }
            }
            if (!$temAvatar) {
                $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN avatar TEXT");
            }

            // Tabela meta e valor inicial para controle de alterações
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS meta (chave TEXT PRIMARY KEY, valor TEXT NOT NULL);");
            $this->pdo->exec("INSERT OR IGNORE INTO meta (chave, valor) VALUES ('last_change_epoch','0');");
            // Marcador de último envio semanal
            $this->pdo->exec("INSERT OR IGNORE INTO meta (chave, valor) VALUES ('envio_semanal_ultimo_envio','0');");

            // Destinatários do envio semanal (lista de usuarios)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS envio_semanal_destinatarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER UNIQUE NOT NULL,
                created_at INTEGER DEFAULT (strftime('%s','now')),
                FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            );");

            // Tabela de recuperação de senha (tokens)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS recuperacao_senha (
                token TEXT PRIMARY KEY,
                user_id INTEGER,
                email TEXT,
                expires_at INTEGER NOT NULL,
                used_at INTEGER,
                created_at INTEGER DEFAULT (strftime('%s','now')),
                request_ip TEXT,
                FOREIGN KEY(user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_rec_senha_email ON recuperacao_senha(email);
            CREATE INDEX IF NOT EXISTS idx_rec_senha_user ON recuperacao_senha(user_id);
            ");
                // Garantir coluna de nome do criador nas tarefas
                $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN criador_nome TEXT");

            // Triggers para marcar alterações no banco
            $tabelas = ['usuarios','tarefas','anexos','categorias','verificacoes','recuperacao_senha'];
            $eventos = [
                'INSERT' => 'ai',
                'UPDATE' => 'au',
                'DELETE' => 'ad'
            ];
            foreach ($tabelas as $tabela) {
                foreach ($eventos as $evento => $suf) {
                    $trg = "trg_{$tabela}_{$suf}_touch_meta";
                    $sqlTrg = "CREATE TRIGGER IF NOT EXISTS {$trg} AFTER {$evento} ON {$tabela} BEGIN UPDATE meta SET valor=strftime('%s','now') WHERE chave='last_change_epoch'; END;";
                    $this->pdo->exec($sqlTrg);
                }
            }
        } catch (PDOException $e) {
            // Evita quebrar a aplicação por erro em migração auxiliar
            error_log('Falha ao aplicar migracoes sensiveis: ' . $e->getMessage());
        }

        // Migrações específicas de colunas em 'tarefas'
        try {
            $colsT = $this->pdo->query("PRAGMA table_info(tarefas)")->fetchAll(PDO::FETCH_ASSOC);
            $temStatus = false; $temTipoServico = false; $temDiasUteis = false;
            foreach ($colsT as $c) {
                if ($c['name'] === 'status') $temStatus = true;
                if ($c['name'] === 'tipo_servico') $temTipoServico = true;
                if ($c['name'] === 'dias_uteis') $temDiasUteis = true;
            }
            if (!$temDiasUteis) {
                // garantir coluna dias_uteis para compatibilidade com UI
                $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN dias_uteis INTEGER DEFAULT 0");
            }
            if (!$temStatus) {
                $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN status TEXT DEFAULT 'Provisório'");
            }
            if (!$temTipoServico) {
                $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN tipo_servico TEXT");
            }
        } catch (Throwable $e) {
            error_log('Falha ao migrar colunas de tarefas: ' . $e->getMessage());
        }
    }
    
    // Criar o usuário admin padrão
    private function criar_admin_padrao() {
        $nome = 'admin';
        $email = 'admin@admin.com';
        $senha = password_hash('admin123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (?, ?, ?, 1)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$nome, $email, $senha]);
    }

    // Lê o valor do marcador de alteração
    private function obter_last_change_epoch() {
        try {
            $stmt = $this->pdo->query("SELECT valor FROM meta WHERE chave = 'last_change_epoch'");
            $val = $stmt ? $stmt->fetchColumn() : false;
            if ($val === false || $val === null) return '0';
            return (string)$val;
        } catch (Throwable $e) {
            error_log('Falha ao ler last_change_epoch: ' . $e->getMessage());
            return '0';
        }
    }

    // Registra função de shutdown para realizar backup se houver alterações
    private function registrar_shutdown() {
        try {
            if (function_exists('register_shutdown_function')) {
                register_shutdown_function([$this, 'shutdownHandler']);
            }
        } catch (Throwable $e) {
            error_log('Falha ao registrar shutdown handler: ' . $e->getMessage());
        }
    }

    // Handler de shutdown
    public function shutdownHandler() {
        try {
            if (self::$suspendBackup) {
                return;
            }
            $atual = $this->obter_last_change_epoch();
            if ($atual !== $this->lastChangeAtStart) {
                $this->criar_backup_com_rotacao();
            }
        } catch (Throwable $e) {
            error_log('Falha no shutdown handler: ' . $e->getMessage());
        }
    }

    // API pública: cria um backup imediatamente (com rotação)
    public function criar_backup_agora() {
        try {
            $this->criar_backup_com_rotacao();
            return true;
        } catch (Throwable $e) {
            error_log('Erro ao criar backup sob demanda: ' . $e->getMessage());
            return false;
        }
    }

    // Suspender/retomar rotina de backup no shutdown (útil durante restore)
    public static function suspender_backup($flag = true) {
        self::$suspendBackup = (bool)$flag;
    }

    // Fecha a conexão atual com o banco (libera arquivo para substituição)
    public function fechar_conexao() {
        try {
            $this->pdo = null;
        } catch (Throwable $e) {
            // ignore
        }
    }

    // Cria backup com rotação (mantém até DB_BACKUP_MAX)
    private function criar_backup_com_rotacao() {
        $dir = DB_BACKUP_DIR;
        try {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                error_log('Diretório de backup indisponível: ' . $dir);
                return;
            }
            $nome = 'calendario_backup_' . date('Ymd_His') . '.db';
            $dest = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nome;

            $ok = false;
            try {
                $quoted = str_replace("'", "''", $dest);
                $this->pdo->exec("VACUUM INTO '{$quoted}'");
                $ok = file_exists($dest);
            } catch (Throwable $e) {
                $ok = false;
            }
            if (!$ok) {
                try {
                    if (!@copy(DB_PATH, $dest)) {
                        error_log('Falha ao copiar arquivo de banco para backup.');
                        return;
                    }
                    $ok = true;
                } catch (Throwable $e) {
                    error_log('Erro no fallback de cópia de backup: ' . $e->getMessage());
                    return;
                }
            }

            $max = (int)DB_BACKUP_MAX;
            if ($max < 1) $max = 1;
            $this->rotacionar_backups($dir, $max);
        } catch (Throwable $e) {
            error_log('Erro ao criar backup: ' . $e->getMessage());
        }
    }

    // Remove backups antigos além do limite configurado
    private function rotacionar_backups($dir, $max) {
        try {
            $files = @glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'calendario_backup_*.db') ?: [];
            $valid = [];
            foreach ($files as $f) {
                if (preg_match('/calendario_backup_\d{8}_\d{6}\.db$/', $f)) {
                    $valid[] = $f;
                }
            }
            usort($valid, function($a, $b) {
                $ta = @filemtime($a) ?: 0;
                $tb = @filemtime($b) ?: 0;
                if ($ta === $tb) return strcmp($a, $b);
                return $ta <=> $tb;
            });
            $excesso = count($valid) - $max;
            for ($i = 0; $i < $excesso; $i++) {
                $old = $valid[$i];
                try {
                    @unlink($old);
                } catch (Throwable $e) {
                    error_log('Falha ao remover backup antigo: ' . $old);
                }
            }
        } catch (Throwable $e) {
            error_log('Erro na rotação de backups: ' . $e->getMessage());
        }
    }

    // Retorna a conexão PDO
    public function getConnection() {
        return $this->pdo;
    }
}