<?php
/**
 * Script de monitorização diária de pedidos — SIUPT Refeições
 *
 * Neste modelo, o estado dos pedidos é calculado dinamicamente:
 *   - "nao_pago"  → RP_PAGO = 0 (independente da data)
 *   - "ativo"     → RP_PAGO = 1, RP_UTILIZADO = 0, RP_DATA_REFEICAO > hoje
 *   - "utilizado" → RP_PAGO = 1, RP_UTILIZADO = 1
 *   - "expirado"  → RP_PAGO = 1, RP_UTILIZADO = 0, RP_DATA_REFEICAO <= hoje (calculado, não gravado)
 *
 * Não é necessário fazer UPDATE de estados. Este script faz auditoria e log,
 * e pode ser chamado via cron (e.g., todos os dias às 23:59).
 *
 * Uso: php scripts/monitorizar_pedidos.php
 */

require_once __DIR__ . '/../src/Infrastructure/Database.php';

$pdo = Database::conexao();

$hoje = date('Y-m-d');

// 1. Contar pedidos expirados (pagos, não utilizados, com data já passada)
$stmt1 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_PAGO = 1 AND RP_UTILIZADO = 0 AND RP_DATA_REFEICAO < ?
");
$stmt1->execute([$hoje]);
$totalExpirados = (int) $stmt1->fetchColumn();

// 2. Contar pedidos ativos (pagos, ainda por recolher em datas futuras)
$stmt2 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_PAGO = 1 AND RP_UTILIZADO = 0 AND RP_DATA_REFEICAO >= ?
");
$stmt2->execute([$hoje]);
$totalAtivos = (int) $stmt2->fetchColumn();

// 3. Contar pedidos utilizados (já validados)
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM restaurante_pedido WHERE RP_UTILIZADO = 1");
$stmt3->execute();
$totalUtilizados = (int) $stmt3->fetchColumn();

// 4. Contar pedidos não pagos (independente da data)
$stmt4 = $pdo->prepare("SELECT COUNT(*) FROM restaurante_pedido WHERE RP_PAGO = 0");
$stmt4->execute();
$totalNaoPagos = (int) $stmt4->fetchColumn();

// 5. Contar validações feitas hoje
$stmt5 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_validacao
    WHERE CAST(RV_DATA_VALIDACAO AS DATE) = CAST(GETDATE() AS DATE)
");
$stmt5->execute();
$validacoesHoje = (int) $stmt5->fetchColumn();

$log = date('Y-m-d H:i:s')
    . " | expirados_hoje: {$totalExpirados}"
    . " | ativos: {$totalAtivos}"
    . " | utilizados_total: {$totalUtilizados}"
    . " | nao_pagos: {$totalNaoPagos}"
    . " | validacoes_hoje: {$validacoesHoje}"
    . PHP_EOL;

file_put_contents(__DIR__ . '/monitorizar_pedidos.log', $log, FILE_APPEND);
echo $log;