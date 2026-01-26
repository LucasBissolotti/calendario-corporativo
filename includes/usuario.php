<?php
require_once 'config.php';
require_once 'database.php';
require_once 'backlog.php';

class Usuario {
    private $db;
    private $backlog;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->backlog = new Backlog();
    }

    // Obter usuário por e-mail
    public function obter_por_email($email) {
        $sql = "SELECT id, nome, email, is_admin, data_criacao, avatar FROM usuarios WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }
    
    // Registrar um novo usuário
    public function registrar($nome, $email, $senha, $is_admin = 0, $cargo = null) {
        // Verificar se o email já existe
        if ($this->email_existe($email)) {
            return false;
        }
        
        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (:nome, :email, :senha, :is_admin)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':senha', $senha_hash);
        $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $usuario_id = $this->db->lastInsertId();
            
            // Registrar no backlog (usando o ID do autor logado, se houver)
            $autor_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 1;
            $detalhes = json_encode([
                'nome' => $nome,
                'email' => $email,
                'is_admin' => $is_admin
            ]);
            
            $this->backlog->registrar(
                $autor_id,
                'registrar',
                'usuario',
                $usuario_id,
                $detalhes
            );
            
            return $usuario_id;
        }
        
        return false;
    }

    // Registrar usuário com senha já hasheada (fluxo de verificação por código)
    public function registrar_com_hash($nome, $email, $senha_hash, $is_admin = 0) {
        // Verificar se o email já existe
        if ($this->email_existe($email)) {
            return false;
        }

        $sql = "INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (:nome, :email, :senha, :is_admin)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':senha', $senha_hash);
        $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $usuario_id = $this->db->lastInsertId();

            // Registrar no backlog (usando ID 1 como sistema)
            $detalhes = json_encode([
                'nome' => $nome,
                'email' => $email,
                'is_admin' => $is_admin,
                'via' => 'registro_verificado_codigo'
            ]);

            $this->backlog->registrar(
                1,
                'registrar',
                'usuario',
                $usuario_id,
                $detalhes
            );

            return $usuario_id;
        }

        return false;
    }
    
    // Verificar se um email já está cadastrado
    public function email_existe($email) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    // Fazer login de usuário (aceita email ou nome de usuário)
    public function login($login, $senha) {
        try {
            // Primeiro, verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            // Consulta SQL adaptada com base na existência da coluna is_admin e permite login com email ou nome
            if ($tem_coluna_admin) {
                $sql = "SELECT id, nome, email, senha, is_admin FROM usuarios WHERE email = :login OR nome = :login";
            } else {
                $sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = :login OR nome = :login";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':login', $login);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Remove a senha do array antes de retornar
                unset($usuario['senha']);
                
                // Se não tiver a coluna is_admin, adiciona manualmente
                if (!$tem_coluna_admin) {
                    $usuario['is_admin'] = ($usuario['nome'] === 'admin') ? 1 : 0;
                }
                
                return $usuario;
            }
        } catch (PDOException $e) {
            // Caso ocorra algum erro, tenta uma consulta mais simples
            $sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = :login OR nome = :login";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':login', $login);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Remove a senha do array antes de retornar
                unset($usuario['senha']);
                
                // Adiciona informação de admin manualmente
                $usuario['is_admin'] = ($usuario['nome'] === 'admin') ? 1 : 0;
                
                return $usuario;
            }
        }
        
        return false;
    }
    
    // Método especial para login do admin usando nome de usuário "admin"
    public function login_admin($senha) {
        try {
            // Primeiro, verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            // Consulta SQL adaptada com base na existência da coluna is_admin
            if ($tem_coluna_admin) {
                $sql = "SELECT id, nome, email, senha FROM usuarios WHERE nome = 'admin'";
            } else {
                $sql = "SELECT id, nome, email, senha FROM usuarios WHERE nome = 'admin'";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Remove a senha do array antes de retornar
                unset($usuario['senha']);
                // Adicionar a informação de admin manualmente
                $usuario['is_admin'] = 1;
                return $usuario;
            }
        } catch (PDOException $e) {
            // Caso ocorra algum erro, tenta uma consulta mais simples
            $sql = "SELECT id, nome, email, senha FROM usuarios WHERE nome = 'admin'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Remove a senha do array antes de retornar
                unset($usuario['senha']);
                // Adicionar a informação de admin manualmente
                $usuario['is_admin'] = 1;
                return $usuario;
            }
        }
        
        return false;
    }
    
    // Obter dados de um usuário pelo ID
    public function obter_por_id($id) {
        try {
            // Verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            if ($tem_coluna_admin) {
                $sql = "SELECT id, nome, email, is_admin, data_criacao, avatar FROM usuarios WHERE id = :id";
            } else {
                $sql = "SELECT id, nome, email, data_criacao, avatar FROM usuarios WHERE id = :id";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Adicionar campo is_admin se não existir
            if ($usuario && !$tem_coluna_admin) {
                $usuario['is_admin'] = ($usuario['nome'] === 'admin') ? 1 : 0;
            }
            
            return $usuario;
        } catch (PDOException $e) {
            // Em caso de erro, tenta uma abordagem mais simples
            $sql = "SELECT id, nome, email, data_criacao, avatar FROM usuarios WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Adicionar campo is_admin
            if ($usuario) {
                $usuario['is_admin'] = ($usuario['nome'] === 'admin') ? 1 : 0;
            }
            
            return $usuario;
        }
    }
    
    // Listar todos os usuários
    public function listar_todos() {
        try {
            // Verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            if ($tem_coluna_admin) {
                $sql = "SELECT id, nome, email, is_admin, data_criacao, avatar FROM usuarios ORDER BY nome";
            } else {
                $sql = "SELECT id, nome, email, data_criacao, avatar FROM usuarios ORDER BY nome";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar campo is_admin se não existir
            if (!$tem_coluna_admin) {
                foreach ($usuarios as &$usuario) {
                    $usuario['is_admin'] = ($usuario['nome'] === 'admin') ? 1 : 0;
                }
            }
            
            return $usuarios;
        } catch (PDOException $e) {
            // Em caso de erro, tenta uma abordagem mais simples
            $sql = "SELECT id, nome, email, data_criacao FROM usuarios ORDER BY nome";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar campo is_admin
            foreach ($usuarios as &$usuario) {
                $usuario['is_admin'] = ($usuario['nome'] === 'admin') ? 1 : 0;
            }
            
            return $usuarios;
        }
    }
    
    // Atualizar dados do usuário
    public function atualizar($id, $nome, $email, $avatar = null) {
        $sql = "UPDATE usuarios SET nome = :nome, email = :email";
        
        // Se um avatar for fornecido, incluí-lo na atualização
        if ($avatar !== null) {
            $sql .= ", avatar = :avatar";
        }
        
        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($avatar !== null) {
            $stmt->bindParam(':avatar', $avatar);
        }
        
        return $stmt->execute();
    }
    
    // Atualizar apenas o avatar do usuário
    public function atualizar_avatar($id, $avatar) {
        $sql = "UPDATE usuarios SET avatar = :avatar WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':avatar', $avatar);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    // Alterar a senha do usuário
    public function alterar_senha($id, $senha_nova) {
        $senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
        
        $sql = "UPDATE usuarios SET senha = :senha WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':senha', $senha_hash);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    // Verificar se o usuário é administrador
    public function is_admin($id) {
        try {
            // Primeiro verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            if ($tem_coluna_admin) {
                $sql = "SELECT is_admin, nome FROM usuarios WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return ($result && $result['is_admin'] == 1);
            } else {
                // Se não tiver a coluna, verifica se é o usuário 'admin'
                $sql = "SELECT nome FROM usuarios WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return ($result && $result['nome'] === 'admin');
            }
        } catch (PDOException $e) {
            // Em caso de erro, tenta uma abordagem mais simples
            $sql = "SELECT nome FROM usuarios WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result && $result['nome'] === 'admin');
        }
    }
    
    // Promover usuário para administrador
    public function promover_admin($id) {
        try {
            // Verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            // Se a coluna não existir, adicioná-la
            if (!$tem_coluna_admin) {
                $sql = "ALTER TABLE usuarios ADD COLUMN is_admin INTEGER DEFAULT 0";
                $this->db->exec($sql);
            }
            
            // Obter dados do usuário para o log
            $usuario = $this->obter_por_id($id);
            
            $sql = "UPDATE usuarios SET is_admin = 1 WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Registrar no backlog
                $detalhes = json_encode([
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email']
                ]);
                
                // Usar o ID do usuário logado como autor da ação
                $autor_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;
                
                $this->backlog->registrar(
                    $autor_id,
                    'promover_admin',
                    'usuario',
                    $id,
                    $detalhes
                );
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Remover privilégios de administrador
    public function rebaixar_admin($id) {
        try {
            // Primeiro verificar se não é o usuário admin
            $sql = "SELECT nome FROM usuarios WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se for o usuário admin, não permitir rebaixamento
            if ($usuario && $usuario['nome'] === 'admin') {
                return false;
            }
            
            // Verificar se a coluna is_admin existe
            $sql = "PRAGMA table_info(usuarios)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tem_coluna_admin = false;
            foreach ($colunas as $coluna) {
                if ($coluna['name'] === 'is_admin') {
                    $tem_coluna_admin = true;
                    break;
                }
            }
            
            // Se a coluna não existir, adicioná-la
            if (!$tem_coluna_admin) {
                $sql = "ALTER TABLE usuarios ADD COLUMN is_admin INTEGER DEFAULT 0";
                $this->db->exec($sql);
            }
            
            // Obter dados completos do usuário para o log
            $usuario = $this->obter_por_id($id);
            
            $sql = "UPDATE usuarios SET is_admin = 0 WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Registrar no backlog
                $detalhes = json_encode([
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email']
                ]);
                
                // Usar o ID do usuário logado como autor da ação
                $autor_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;
                
                $this->backlog->registrar(
                    $autor_id,
                    'rebaixar_admin',
                    'usuario',
                    $id,
                    $detalhes
                );
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Excluir usuário (apenas administradores podem fazer isso)
    public function excluir($id) {
        // Primeiro verificar se não é o usuário admin
        $sql = "SELECT nome FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se for o usuário admin, não permitir exclusão
        if ($usuario && $usuario['nome'] === 'admin') {
            return false;
        }
        
        // Obter dados completos do usuário para o log
        $usuario = $this->obter_por_id($id);
        
        $sql = "DELETE FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // Registrar no backlog
            $detalhes = json_encode([
                'nome' => $usuario['nome'],
                'email' => $usuario['email']
            ]);
            
            // Usar o ID do usuário logado como autor da ação
            $autor_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;
            
            $this->backlog->registrar(
                $autor_id,
                'excluir',
                'usuario',
                $id,
                $detalhes
            );
            
            return true;
        }
        
        return false;
    }
}