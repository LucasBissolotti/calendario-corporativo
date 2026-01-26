<?php
require_once 'includes/config.php';

// Iniciar a sessão
iniciar_sessao();

// Limpar todos os dados da sessão
$_SESSION = array();

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header("Location: login.php");
exit;