<?php
require_once 'database.php';

class Categoria {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->garantirTabela();
    }

    private function garantirTabela() {
        // Cria a tabela se não existir (para bases já existentes)
        $sql = "CREATE TABLE IF NOT EXISTS categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT UNIQUE NOT NULL,
            cor TEXT NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );";
        $this->db->exec($sql);
    }

    public function listar_todas() {
        $stmt = $this->db->query("SELECT * FROM categorias ORDER BY nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($nome, $cor) {
        $sql = "INSERT INTO categorias (nome, cor) VALUES (:nome, :cor)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nome' => trim($nome),
            ':cor' => trim($cor)
        ]);
    }

    public function excluir($id) {
        // Antes de excluir, verificar se há tarefas usando esta categoria
        // Obter o nome da categoria
        $stmtNome = $this->db->prepare("SELECT nome FROM categorias WHERE id = :id");
        $stmtNome->execute([':id' => (int)$id]);
        $row = $stmtNome->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['nome'])) {
            return false;
        }

        $nomeCategoria = $row['nome'];

        // Verificar se alguma tarefa faz referência a esta categoria
        $stmtUso = $this->db->prepare("SELECT COUNT(*) AS total FROM tarefas WHERE categoria = :nome");
        $stmtUso->execute([':nome' => $nomeCategoria]);
        $uso = (int)$stmtUso->fetchColumn();
        if ($uso > 0) {
            // Categoria em uso, não pode ser excluída
            return false;
        }

        // Prosseguir com exclusão
        $sql = "DELETE FROM categorias WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => (int)$id]);
    }

    public function obter_por_nome($nome) {
        $sql = "SELECT * FROM categorias WHERE nome = :nome";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
