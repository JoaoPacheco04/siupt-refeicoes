<?php
/**
 * Script de monitorização diária de pedidos — SIUPT Refeições
 *
 * Neste modelo, o estado dos pedidos é calculado dinamicamente:
 *   - "ativo"     → RP_UTILIZADO = 0 e RP_DATA_REFEICAO > hoje
 *   - "utilizado" → RP_UTILIZADO = 1
 *   - "vencido"   → RP_UTILIZADO = 0 e RP_DATA_REFEICAO <= hoje  (calculado, não gravado)
 *
 * Não é necessário fazer UPDATE de estados. Este script faz auditoria e log,
 * e pode ser chamado via cron (e.g., todos os dias às 23:59).
 *
 * Uso: php scripts/monitorizar_pedidos.php
 */

require_once __DIR__ . '/../src/Infrastructure/Database.php';

$pdo = Database::conexao();

$hoje = date('Y-m-d');

// 1. Contar pedidos vencidos hoje (não utilizados cuja data já passou ou é hoje)
$stmt1 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_UTILIZADO = 0 AND RP_DATA_REFEICAO <= ?
");
$stmt1->execute([$hoje]);
$totalVencidos = (int) $stmt1->fetchColumn();

// 2. Contar pedidos ativos (ainda por recolher em datas futuras)
$stmt2 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_pedido
    WHERE RP_UTILIZADO = 0 AND RP_DATA_REFEICAO > ?
");
$stmt2->execute([$hoje]);
$totalAtivos = (int) $stmt2->fetchColumn();

// 3. Contar pedidos utilizados (já validados)
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM restaurante_pedido WHERE RP_UTILIZADO = 1");
$stmt3->execute();
$totalUtilizados = (int) $stmt3->fetchColumn();

// 4. Contar validações feitas hoje
$stmt4 = $pdo->prepare("
    SELECT COUNT(*) FROM restaurante_validacao
    WHERE CAST(RV_DATA_VALIDACAO AS DATE) = CAST(GETDATE() AS DATE)
");
$stmt4->execute();
$validacoesHoje = (int) $stmt4->fetchColumn();

$log = date('Y-m-d H:i:s')
    . " | vencidos_hoje: {$totalVencidos}"
    . " | ativos: {$totalAtivos}"
    . " | utilizados_total: {$totalUtilizados}"
    . " | validacoes_hoje: {$validacoesHoje}"
    . PHP_EOL;

file_put_contents(__DIR__ . '/monitorizar_pedidos.log', $log, FILE_APPEND);
echo $log;
