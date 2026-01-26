<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/envio_semanal.php';

// Script para ser executado via agendamento (cron/Task Scheduler)
// Envia o resumo semanal sempre às sextas-feiras, evitando duplicidade no mesmo dia.

$hojeSemana = (int)date('N'); // 1=segunda ... 7=domingo
$forcar = (isset($_GET['forcar']) && $_GET['forcar'] === '1');
$modoTeste = (isset($_GET['teste']) && $_GET['teste'] === '1');

if (!$forcar && $hojeSemana !== 5) {
    $msg = 'Envio semanal ignorado: hoje não é sexta-feira.';
    echo $msg;
    error_log($msg);
    exit;
}

$resultado = enviar_resumo_semanal($forcar, $modoTeste);

if ($resultado['status'] === 'ok') {
    $msg = 'Resumo semanal enviado. ' . ($resultado['enviados'] ?? 0) . ' / ' . ($resultado['total'] ?? 0) . ' destinatários.';
    echo $msg;
    error_log($msg);
} elseif ($resultado['status'] === 'skipped') {
    echo $resultado['mensagem'] ?? 'Envio ignorado';
} else {
    echo 'Falha no envio semanal: ' . ($resultado['mensagem'] ?? 'Erro');
}
