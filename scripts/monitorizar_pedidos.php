<?php
/**
 * Script de monitorização diária dos pedidos de refeição.
 *
 * Recolhe indicadores sobre o estado dos pedidos e das validações,
 * registando a informação num ficheiro de log.
 *
 * Deve ser executado EXCLUSIVAMENTE via linha de comandos (CLI) ou
 * tarefa agendada (cron). Não é acessível via HTTP.
 */

// ── Proteção: só permite execução via CLI ─────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado: este script só pode ser executado via linha de comandos.');
}

require_once __DIR__ . '/../src/Infrastructure/Database.php';

$pdo = Database::conexao();

$hoje = date('Y-m-d');

/**
 * Obtém os indicadores utilizados na monitorização diária.
 */
$stmt1 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_PAGO = 1 AND RP_UTILIZADO = 0 AND RP_DATA_REFEICAO < ?
");
$stmt1->execute([$hoje]);
$totalExpirados = (int) $stmt1->fetchColumn();

$stmt2 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_PAGO = 1 AND RP_UTILIZADO = 0 AND RP_DATA_REFEICAO >= ?
");
$stmt2->execute([$hoje]);
$totalAtivos = (int) $stmt2->fetchColumn();

$stmt3 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_UTILIZADO = 1
");
$stmt3->execute();
$totalUtilizados = (int) $stmt3->fetchColumn();

$stmt4 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_PAGO = 0
");
$stmt4->execute();
$totalNaoPagos = (int) $stmt4->fetchColumn();

$stmt5 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_validacao
    WHERE CAST(RV_DATA_VALIDACAO AS DATE) = CAST(GETDATE() AS DATE)
");
$stmt5->execute();
$validacoesHoje = (int) $stmt5->fetchColumn();

/**
 * Regista os indicadores no ficheiro de log.
 */
$log = date('Y-m-d H:i:s')
    . " | expirados_hoje: {$totalExpirados}"
    . " | ativos: {$totalAtivos}"
    . " | utilizados_total: {$totalUtilizados}"
    . " | nao_pagos: {$totalNaoPagos}"
    . " | validacoes_hoje: {$validacoesHoje}"
    . PHP_EOL;

file_put_contents(__DIR__ . '/monitorizar_pedidos.log', $log, FILE_APPEND);

echo $log;