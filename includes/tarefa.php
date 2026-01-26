<?php
require_once 'config.php';
require_once 'database.php';
require_once 'backlog.php';

class Tarefa {
    private $db;
    private $backlog;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->backlog = new Backlog();
    }
    
    // Criar nova tarefa
    public function criar($dados) {
        $sql = "INSERT INTO tarefas (
                    usuario_id, criador_nome, titulo, categoria, cor, 
                    data_inicio, data_fim, hora_inicio, hora_fim,
                    dia_inteiro, dias_uteis, participantes, localizacao, 
                    descricao, checklist, status, tipo_servico
                ) VALUES (
                    :usuario_id, :criador_nome, :titulo, :categoria, :cor, 
                    :data_inicio, :data_fim, :hora_inicio, :hora_fim,
                    :dia_inteiro, :dias_uteis, :participantes, :localizacao, 
                    :descricao, :checklist, :status, :tipo_servico
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':usuario_id', $dados['usuario_id'], PDO::PARAM_INT);
        $stmt->bindValue(':criador_nome', $dados['criador_nome'] ?? '', PDO::PARAM_STR);
        $stmt->bindParam(':titulo', $dados['titulo']);
        $stmt->bindParam(':categoria', $dados['categoria']);
        $stmt->bindParam(':cor', $dados['cor']);
        $stmt->bindParam(':data_inicio', $dados['data_inicio']);
        $stmt->bindParam(':data_fim', $dados['data_fim']);
        $stmt->bindParam(':hora_inicio', $dados['hora_inicio']);
        $stmt->bindParam(':hora_fim', $dados['hora_fim']);
        $stmt->bindParam(':dia_inteiro', $dados['dia_inteiro'], PDO::PARAM_INT);
        $stmt->bindParam(':dias_uteis', $dados['dias_uteis'], PDO::PARAM_INT);
        $stmt->bindParam(':participantes', $dados['participantes']);
        $stmt->bindParam(':localizacao', $dados['localizacao']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindParam(':checklist', $dados['checklist']);
        $stmt->bindParam(':status', $dados['status']);
        $stmt->bindParam(':tipo_servico', $dados['tipo_servico']);
        
        if ($stmt->execute()) {
            $tarefa_id = $this->db->lastInsertId();
            
            // Registrar no backlog
            $detalhes = json_encode([
                'titulo' => $dados['titulo'],
                'categoria' => $dados['categoria'],
                'data_inicio' => $dados['data_inicio'],
                'data_fim' => $dados['data_fim']
            ]);
            $this->backlog->registrar(
                $dados['usuario_id'],
                'criar',
                'tarefa',
                $tarefa_id,
                $detalhes
            );
            
            return $tarefa_id;
        }
        
        return false;
    }
    
    // Obter tarefa por ID
    public function obter_por_id($id) {
        $sql = "SELECT t.*, t.usuario_id, u.nome as nome_usuario 
                FROM tarefas t
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE t.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Atualizar tarefa existente
    public function atualizar($id, $dados, $is_admin = false) {
        // Se for admin, pode editar qualquer tarefa, senão apenas as próprias
        $where_clause = $is_admin ? "WHERE id = :id" : "WHERE id = :id AND usuario_id = :usuario_id";
        
        $sql = "UPDATE tarefas SET
                    titulo = :titulo,
                    categoria = :categoria,
                    cor = :cor,
                    data_inicio = :data_inicio,
                    data_fim = :data_fim,
                    hora_inicio = :hora_inicio,
                    hora_fim = :hora_fim,
                    dia_inteiro = :dia_inteiro,
                    dias_uteis = :dias_uteis,
                    participantes = :participantes,
                    localizacao = :localizacao,
                    descricao = :descricao,
                    checklist = :checklist,
                    status = :status,
                    tipo_servico = :tipo_servico
                " . $where_clause;
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':titulo', $dados['titulo']);
        $stmt->bindParam(':categoria', $dados['categoria']);
        $stmt->bindParam(':cor', $dados['cor']);
        $stmt->bindParam(':data_inicio', $dados['data_inicio']);
        $stmt->bindParam(':data_fim', $dados['data_fim']);
        $stmt->bindParam(':hora_inicio', $dados['hora_inicio']);
        $stmt->bindParam(':hora_fim', $dados['hora_fim']);
        $stmt->bindParam(':dia_inteiro', $dados['dia_inteiro'], PDO::PARAM_INT);
        $stmt->bindParam(':dias_uteis', $dados['dias_uteis'], PDO::PARAM_INT);
        $stmt->bindParam(':participantes', $dados['participantes']);
        $stmt->bindParam(':localizacao', $dados['localizacao']);
        $stmt->bindParam(':descricao', $dados['descricao']);
        $stmt->bindParam(':checklist', $dados['checklist']);
        $stmt->bindParam(':status', $dados['status']);
        $stmt->bindParam(':tipo_servico', $dados['tipo_servico']);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if (!$is_admin) {
            $stmt->bindParam(':usuario_id', $dados['usuario_id'], PDO::PARAM_INT);
        }
        
        // Obter dados antigos para registrar no backlog
        $tarefa_antiga = $this->obter_por_id($id);
        
        if ($stmt->execute()) {
            // Registrar no backlog
            $detalhes = json_encode([
                'anterior' => [
                    'titulo' => $tarefa_antiga['titulo'],
                    'categoria' => $tarefa_antiga['categoria'],
                    'data_inicio' => $tarefa_antiga['data_inicio'],
                    'data_fim' => $tarefa_antiga['data_fim']
                ],
                'atual' => [
                    'titulo' => $dados['titulo'],
                    'categoria' => $dados['categoria'],
                    'data_inicio' => $dados['data_inicio'],
                    'data_fim' => $dados['data_fim']
                ]
            ]);
            
            $this->backlog->registrar(
                $dados['usuario_id'],
                'atualizar',
                'tarefa',
                $id,
                $detalhes
            );
            
            return true;
        }
        
        return false;
    }
    
    // Excluir tarefa
    public function excluir($id, $usuario_id, $is_admin = false) {
        // Se for admin, pode excluir qualquer tarefa, senão apenas as próprias
        $where_clause = $is_admin ? "WHERE id = :id" : "WHERE id = :id AND usuario_id = :usuario_id";
        
        $sql = "DELETE FROM tarefas " . $where_clause;
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if (!$is_admin) {
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        }
        
        // Obter dados da tarefa antes de excluir para registrar no backlog
        $tarefa = $this->obter_por_id($id);
        
        if ($stmt->execute()) {
            // Registrar no backlog
            $detalhes = json_encode([
                'titulo' => $tarefa['titulo'],
                'categoria' => $tarefa['categoria'],
                'data_inicio' => $tarefa['data_inicio'],
                'data_fim' => $tarefa['data_fim']
            ]);
            
            $this->backlog->registrar(
                $is_admin ? ($tarefa['usuario_id'] ?: $usuario_id) : $usuario_id,
                'excluir',
                'tarefa',
                $id,
                $detalhes
            );
            
            return true;
        }
        
        return false;
    }
    
    // Listar tarefas por período
    public function listar_por_periodo($data_inicio, $data_fim) {
        $sql = "SELECT t.*, u.nome as nome_usuario 
                FROM tarefas t
                JOIN usuarios u ON t.usuario_id = u.id
                WHERE 
                    (t.data_inicio BETWEEN :data_inicio AND :data_fim) OR
                    (t.data_fim BETWEEN :data_inicio AND :data_fim) OR
                    (t.data_inicio <= :data_inicio AND t.data_fim >= :data_fim)
                ORDER BY t.data_inicio, t.hora_inicio";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim', $data_fim);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Listar tarefas por usuário
    public function listar_por_usuario($usuario_id) {
        $sql = "SELECT * FROM tarefas WHERE usuario_id = :usuario_id ORDER BY data_inicio, hora_inicio";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Adicionar anexo a uma tarefa
    public function adicionar_anexo($tarefa_id, $nome, $tipo, $caminho) {
        $sql = "INSERT INTO anexos (tarefa_id, nome_arquivo, tipo_arquivo, caminho) 
                VALUES (:tarefa_id, :nome, :tipo, :caminho)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':tarefa_id', $tarefa_id, PDO::PARAM_INT);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':caminho', $caminho);
        
        return $stmt->execute();
    }
    
    // Listar anexos de uma tarefa
    public function listar_anexos($tarefa_id) {
        $sql = "SELECT * FROM anexos WHERE tarefa_id = :tarefa_id ORDER BY data_upload";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':tarefa_id', $tarefa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Excluir anexo
    public function excluir_anexo($anexo_id, $tarefa_id) {
        $sql = "DELETE FROM anexos WHERE id = :anexo_id AND tarefa_id = :tarefa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':anexo_id', $anexo_id, PDO::PARAM_INT);
        $stmt->bindParam(':tarefa_id', $tarefa_id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    // Listar todas as tarefas (para administradores)
    public function listar_todas() {
        $sql = "SELECT t.*, u.nome as nome_usuario 
                FROM tarefas t
                JOIN usuarios u ON t.usuario_id = u.id
                ORDER BY t.data_inicio, t.hora_inicio";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}