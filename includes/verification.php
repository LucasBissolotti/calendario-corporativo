<?php
require_once __DIR__ . '/database.php';

class VerificacaoCadastro {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Cria um registro de verificação pendente. Sobrescreve pendente anterior do mesmo email.
    public function criar_pendente(string $nome, string $email, string $senha_plana, string $codigo, int $ttlSegundos = 900) {
        $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);
        $codigo_hash = password_hash($codigo, PASSWORD_DEFAULT);
        $expires_at = time() + $ttlSegundos; // unix epoch

        // Apaga pendência anterior deste e-mail (idempotente)
        $stmtDel = $this->db->prepare("DELETE FROM verificacoes WHERE email = :email");
        $stmtDel->execute([':email' => $email]);

        $sql = "INSERT INTO verificacoes (email, nome, senha_hash, codigo_hash, expires_at, attempts, created_at) 
                VALUES (:email, :nome, :senha_hash, :codigo_hash, :expires_at, 0, strftime('%s','now'))";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':email' => $email,
            ':nome' => $nome,
            ':senha_hash' => $senha_hash,
            ':codigo_hash' => $codigo_hash,
            ':expires_at' => $expires_at,
        ]);
    }

    // Retorna pendência por e-mail
    public function obter_por_email(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM verificacoes WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Incrementa tentativas
    public function incrementar_tentativa(string $email) {
        $stmt = $this->db->prepare("UPDATE verificacoes SET attempts = attempts + 1 WHERE email = :email");
        $stmt->execute([':email' => $email]);
    }

    // Remove pendência
    public function remover(string $email) {
        $stmt = $this->db->prepare("DELETE FROM verificacoes WHERE email = :email");
        $stmt->execute([':email' => $email]);
    }

    // Valida o código: retorna array com nome, email, senha_hash se válido; caso contrário, null
    public function validar_codigo(string $email, string $codigo, int $maxTentativas = 5) {
        $pendente = $this->obter_por_email($email);
        if (!$pendente) return null;

        // Expirado
        if ((int)$pendente['expires_at'] < time()) {
            $this->remover($email);
            return null;
        }

        // Tentativas excedidas
        if ((int)$pendente['attempts'] >= $maxTentativas) {
            $this->remover($email);
            return null;
        }

        // Checa o código
        $ok = password_verify($codigo, $pendente['codigo_hash']);
        if (!$ok) {
            $this->incrementar_tentativa($email);
            return null;
        }

        // Sucesso, retorna dados necessários para criar usuário
        return [
            'nome' => $pendente['nome'],
            'email' => $pendente['email'],
            'senha_hash' => $pendente['senha_hash'],
        ];
    }
}
