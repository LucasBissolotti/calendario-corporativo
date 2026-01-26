<?php
require_once 'config.php';
require_once 'database.php';

class Backlog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        
        // Verificar se a tabela backlog existe, caso contrário, criar
        $this->verificar_tabela();
    }
    
    // Verificar e criar tabela se não existir
    private function verificar_tabela() {
        try {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='backlog'";
            $stmt = $this->db->query($sql);
            
            if ($stmt->fetchColumn() === false) {
                // Tabela não existe, criar
                $sql = "CREATE TABLE backlog (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    usuario_id INTEGER NOT NULL,
                    acao TEXT NOT NULL,
                    entidade TEXT NOT NULL,
                    entidade_id INTEGER,
                    detalhes TEXT,
                    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                )";
                
                $this->db->exec($sql);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log('Erro ao verificar/criar tabela backlog: ' . $e->getMessage());
            return false;
        }
    }
    
    // Registrar uma ação no backlog
    public function registrar($usuario_id, $acao, $entidade, $entidade_id = null, $detalhes = null) {
        try {
            $sql = "INSERT INTO backlog (usuario_id, acao, entidade, entidade_id, detalhes) 
                   VALUES (:usuario_id, :acao, :entidade, :entidade_id, :detalhes)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':acao', $acao);
            $stmt->bindParam(':entidade', $entidade);
            $stmt->bindParam(':entidade_id', $entidade_id, PDO::PARAM_INT);
            $stmt->bindParam(':detalhes', $detalhes);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao registrar ação no backlog: ' . $e->getMessage());
            return false;
        }
    }
    
    // Listar todas as entradas do backlog, com opção de filtro
    public function listar($limite = 100, $usuario_id = null, $acao = null, $entidade = null) {
        try {
            $params = [];
            $sql = "SELECT b.*, u.nome as nome_usuario 
                   FROM backlog b
                   LEFT JOIN usuarios u ON b.usuario_id = u.id
                   WHERE 1=1";
            
            if ($usuario_id !== null) {
                $sql .= " AND b.usuario_id = :usuario_id";
                $params[':usuario_id'] = $usuario_id;
            }
            
            if ($acao !== null) {
                $sql .= " AND b.acao = :acao";
                $params[':acao'] = $acao;
            }
            
            if ($entidade !== null) {
                $sql .= " AND b.entidade = :entidade";
                $params[':entidade'] = $entidade;
            }
            
            $sql .= " ORDER BY b.data_hora DESC LIMIT :limite";
            $params[':limite'] = $limite;
            
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $param => $value) {
                if ($param == ':limite') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Erro ao listar backlog: ' . $e->getMessage());
            return [];
        }
    }
    
    // Obter estatísticas de ações
    public function obter_estatisticas() {
        try {
            // Total de ações por tipo
            $sql = "SELECT acao, COUNT(*) as total FROM backlog GROUP BY acao ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            $acoes_por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Total de ações por usuário (top 5)
            $sql = "SELECT u.nome, COUNT(*) as total 
                   FROM backlog b 
                   JOIN usuarios u ON b.usuario_id = u.id 
                   GROUP BY b.usuario_id 
                   ORDER BY total DESC LIMIT 5";
            $stmt = $this->db->query($sql);
            $acoes_por_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ações recentes (últimas 24 horas)
            $sql = "SELECT COUNT(*) as total FROM backlog 
                   WHERE data_hora >= datetime('now', '-1 day')";
            $stmt = $this->db->query($sql);
            $acoes_recentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'por_tipo' => $acoes_por_tipo,
                'por_usuario' => $acoes_por_usuario,
                'recentes' => $acoes_recentes
            ];
            
        } catch (PDOException $e) {
            error_log('Erro ao obter estatísticas do backlog: ' . $e->getMessage());
            return [];
        }
    }
    
    // Excluir entradas antigas do backlog (manutenção)
    public function limpar_antigos($dias = 90) {
        try {
            $sql = "DELETE FROM backlog WHERE data_hora < datetime('now', :dias_atras)";
            $stmt = $this->db->prepare($sql);
            $dias_string = "-$dias days";
            $stmt->bindParam(':dias_atras', $dias_string);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao limpar entradas antigas do backlog: ' . $e->getMessage());
            return false;
        }
    }
    
    // Excluir uma entrada específica do backlog (apenas para administradores)
    public function excluir($id) {
        try {
            $sql = "DELETE FROM backlog WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erro ao excluir entrada do backlog: ' . $e->getMessage());
            return false;
        }
    }

    // Excluir TODAS as entradas do backlog (apenas para administradores)
    public function excluir_todos() {
        try {
            $this->db->exec("DELETE FROM backlog");
            return true;
        } catch (PDOException $e) {
            error_log('Erro ao excluir todas as entradas do backlog: ' . $e->getMessage());
            return false;
        }
    }

    // Excluir somente entradas da conta ADMIN padrão (usuário com nome 'admin')
    public function excluir_da_conta_admin_padrao() {
        try {
            // Executa remoção usando subconsulta para evitar múltiplos passos e falhas intermediárias
            $sql = "DELETE FROM backlog WHERE usuario_id IN (SELECT id FROM usuarios WHERE LOWER(nome) = 'admin')";
            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log('Erro ao excluir entradas do backlog da conta ADMIN padrão: ' . $e->getMessage());
            return false;
        }
    }
}