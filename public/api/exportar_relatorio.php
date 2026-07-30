<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');

$anoMes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
    $anoMes = date('Y-m');
}

$resumo = Database::obterResumoMensal($anoMes);
$vendasPorTipo = Database::obterVendasPorTipoMensal($anoMes);
$vendasDiarias = Database::obterVendasDiariasMensal($anoMes);

$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => !str_starts_with($t['RTP_NOME'], 'Extra: ')));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => str_starts_with($t['RTP_NOME'], 'Extra: ')));

$precoMedio = $resumo['total_pedidos'] > 0 ? $resumo['total_vendido'] / $resumo['total_pedidos'] : 0;
$diferenca = $resumo['total_vendido'] - $resumo['total_vendido_mes_anterior'];
$percentagem = $resumo['total_vendido_mes_anterior'] > 0 ? ($diferenca / $resumo['total_vendido_mes_anterior']) * 100 : null;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_' . $anoMes . '.csv"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, ['Resumo'], ';');
fputcsv($saida, ['Total vendido', number_format($resumo['total_vendido'], 2, ',', '')], ';');
fputcsv($saida, ['Pedidos pagos', $resumo['total_pedidos']], ';');
fputcsv($saida, ['Refeições levantadas', $resumo['total_levantados']], ';');
fputcsv($saida, ['Não levantadas', $resumo['total_nao_levantados']], ';');
fputcsv($saida, ['Preço médio por pedido', number_format($precoMedio, 2, ',', '')], ';');
if ($percentagem !== null) {
    fputcsv($saida, ['Variação vs. mês anterior', number_format($percentagem, 1, ',', '') . '%'], ';');
}
fputcsv($saida, [], ';');

fputcsv($saida, ['Vendas por tipo — ementa'], ';');
fputcsv($saida, ['Tipo', 'Quantidade', 'Total (€)'], ';');
foreach ($pratosEmenta as $t) {
    fputcsv($saida, [$t['RTP_NOME'], $t['quantidade'], number_format((float) $t['total'], 2, ',', '')], ';');
}
fputcsv($saida, [], ';');

fputcsv($saida, ['Vendas por tipo — extras'], ';');
fputcsv($saida, ['Tipo', 'Quantidade', 'Total (€)'], ';');
foreach ($extrasVendas as $t) {
    fputcsv($saida, [str_replace('Extra: ', '', $t['RTP_NOME']), $t['quantidade'], number_format((float) $t['total'], 2, ',', '')], ';');
}
fputcsv($saida, [], ';');

fputcsv($saida, ['Vendas diárias'], ';');
fputcsv($saida, ['Data', 'Pedidos', 'Total (€)'], ';');
foreach ($vendasDiarias as $d) {
    fputcsv($saida, [
        date('d/m/Y', strtotime($d['RP_DATA_REFEICAO'])),
        $d['total_pedidos'],
        number_format((float) $d['total_vendido'], 2, ',', ''),
    ], ';');
}

fclose($saida);
exit;