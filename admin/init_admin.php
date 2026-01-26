<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

// Esta função garante que a conta de admin existe e está atualizada
function garantir_admin() {
    $db = Database::getInstance()->getConnection();
    
    // Verificar se o admin já existe
    $sql = "SELECT COUNT(*) FROM usuarios WHERE nome = 'admin' AND is_admin = 1";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        // Se não existir, criar a conta admin
        $nome = 'admin';
        $email = 'admin@admin.com';
        $senha = password_hash('admin123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (?, ?, ?, 1)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$nome, $email, $senha]);
        
        echo "Conta de administrador criada com sucesso!";
    } else {
        // Se existir, verificar se precisa atualizar a senha
        $senha_atual = 'admin123';
        $senha_hash = password_hash($senha_atual, PASSWORD_DEFAULT);
        
        // Atualizar a senha para garantir que está correta
        $sql = "UPDATE usuarios SET senha = ? WHERE nome = 'admin' AND is_admin = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$senha_hash]);
        
        echo "Conta de administrador atualizada com sucesso!";
    }
}

// Executar a função
garantir_admin();

echo "<p>Você pode voltar para a <a href='index.php'>página principal</a> ou ir para a <a href='login.php'>página de login</a>.</p>";