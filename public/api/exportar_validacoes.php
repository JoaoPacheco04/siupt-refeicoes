<?php
/**
 * Exporta as validaÃ§Ãµes de refeiÃ§Ãµes de um dia especÃ­fico em formato CSV.
 * Suporta parÃ¢metro ?data=YYYY-MM-DD (GET); sem parÃ¢metro exporta hoje.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('atendente');

// Aceitar data via GET; se omitida ou invÃ¡lida, usa hoje
$data = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $data = date('Y-m-d');
}

$vejoTudo = temPapelSessao('admin_cantina');
$validacoes = $vejoTudo
    ? Database::listarValidacoesPorDataTodos($data)
    : Database::listarValidacoesPorData((int) $utilizador['id'], $data);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="validacoes_' . $data . '.csv"');

/**
 * Gera o ficheiro CSV e escreve os dados das validaÃ§Ãµes.
 */
$saida = fopen('php://output', 'w');

fwrite($saida, "\xEF\xBB\xBF");

fputcsv(
    $saida,
    ['Hora', 'Nome', 'NÃºmero', 'RefeiÃ§Ã£o', 'Pedido', 'Data da refeiÃ§Ã£o', 'PreÃ§o total (â‚¬)'],
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
