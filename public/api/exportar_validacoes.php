<?php
/**
 * Exporta as validações de refeições de um dia específico em formato CSV.
 * Suporta parâmetro ?data=YYYY-MM-DD (GET); sem parâmetro exporta hoje.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');

// Aceitar data via GET; se omitida ou inválida, usa hoje
$data = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $data = date('Y-m-d');
}

$validacoes = Database::listarValidacoesPorData((int) $utilizador['id'], $data);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="validacoes_' . $data . '.csv"');

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