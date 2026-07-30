<?php
/**
 * Exporta as validações de refeições do dia em formato CSV.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');

$validacoes = Database::listarValidacoesHoje((int) $utilizador['id']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="validacoes_' . date('Y-m-d') . '.csv"');

/**
 * Gera o ficheiro CSV e escreve os dados das validações.
 */
$saida = fopen('php://output', 'w');

fwrite($saida, "\xEF\xBB\xBF");

fputcsv(
    $saida,
    ['Hora', 'Nome', 'Número', 'Refeição', 'Pedido', 'Data da refeição', 'Preço total (€)'],
    ';'
);

foreach ($validacoes as $v) {
    fputcsv($saida, [
        date('H:i', strtotime($v['RV_DATA_VALIDACAO'])),
        $v['U_NOME'],
        $v['U_BICC'],
        $v['itens'] ?? '',
        $v['RP_ID'],
        date('d/m/Y', strtotime($v['RP_DATA_REFEICAO'])),
        number_format((float) $v['RP_PRECO_TOTAL'], 2, ',', ''),
    ], ';');
}

fclose($saida);
exit;