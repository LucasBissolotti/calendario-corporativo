<?php
require_once '../includes/config.php';
require_once '../includes/usuario.php';
require_once '../includes/tarefa.php';

// Verificar se o usuário está logado e é admin
iniciar_sessao();
requer_login();
requer_admin();

// Verificar CSRF token
if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['erro' => 'Token de segurança inválido']));
}

header('Content-Type: application/json');

// Instanciar classes
$usuario_obj = new Usuario();
$tarefa_obj = new Tarefa();

// Obter todos os usuários
$usuarios = $usuario_obj->listar_todos();

// Obter todas as tarefas
$tarefas = $tarefa_obj->listar_todas();

// Preparar dados de estatísticas
$hoje = date('Y-m-d');
$stats = [
    'total_usuarios' => count($usuarios),
    'total_admins' => count(array_filter($usuarios, function($u) { return $u['is_admin'] == 1; })),
    'total_tarefas' => count($tarefas),
    'tarefas_hoje' => count(array_filter($tarefas, function($t) use ($hoje) {
        return $t['data_inicio'] <= $hoje && $t['data_fim'] >= $hoje;
    })),
    'tarefas_por_categoria' => []
];

// Contar tarefas por categoria
$categorias = array();
foreach ($tarefas as $tarefa) {
    $categoria = $tarefa['categoria'];
    if (!isset($stats['tarefas_por_categoria'][$categoria])) {
        $stats['tarefas_por_categoria'][$categoria] = 1;
    } else {
        $stats['tarefas_por_categoria'][$categoria]++;
    }
}

// Retornar dados em formato JSON
echo json_encode($stats);