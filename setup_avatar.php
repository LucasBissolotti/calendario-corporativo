<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

// Certificar-se de que o usuário está logado como admin
iniciar_sessao();
requer_login();

// Verificar se o usuário é admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die("Acesso negado. Apenas administradores podem acessar esta página.");
}

// Obter conexão com o banco de dados
$db = Database::getInstance()->getConnection();

try {
    // Verificar se a coluna avatar já existe
    $sql = "PRAGMA table_info(usuarios)";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $coluna_existe = false;
    foreach ($colunas as $coluna) {
        if ($coluna['name'] === 'avatar') {
            $coluna_existe = true;
            break;
        }
    }
    
    // Se a coluna não existe, adicioná-la
    if (!$coluna_existe) {
        $sql = "ALTER TABLE usuarios ADD COLUMN avatar TEXT";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        echo "Coluna 'avatar' adicionada com sucesso à tabela 'usuarios'.";
    } else {
        echo "A coluna 'avatar' já existe na tabela 'usuarios'.";
    }
    
    // Criar diretório para uploads se não existir
    $upload_dir = __DIR__ . '/uploads/avatares';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "<br>Diretório de uploads criado com sucesso.";
    }
    
    echo "<br><br>Configuração concluída. <a href='index.php'>Voltar à página inicial</a>";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>