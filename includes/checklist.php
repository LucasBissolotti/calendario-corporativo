<?php
require_once 'database.php';

class Checklist {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->garantirTabela();
    }

    private function garantirTabela() {
        // Cria a tabela se não existir (para bases já existentes)
        $sql = "CREATE TABLE IF NOT EXISTS checklists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT UNIQUE NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );";
        $this->db->exec($sql);
    }

    public function listar_todos() {
        $stmt = $this->db->query("SELECT * FROM checklists ORDER BY nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($nome) {
        $sql = "INSERT INTO checklists (nome) VALUES (:nome)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nome' => trim($nome)
        ]);
    }

    public function excluir($id) {
        $sql = "DELETE FROM checklists WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => (int)$id]);
    }

    public function obter_por_nome($nome) {
        $sql = "SELECT * FROM checklists WHERE nome = :nome";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
