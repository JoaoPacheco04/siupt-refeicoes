<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');

$data = $_GET['data'] ?? date('Y-m-d');

// Valida formato YYYY-MM-DD e que é uma data de calendário real
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    exit('Data inválida');
}
[$ano, $mes, $dia] = explode('-', $data);
if (!checkdate((int) $mes, (int) $dia, (int) $ano)) {
    http_response_code(400);
    exit('Data inválida');
}

$validacoes = Database::listarValidacoesPorData((int) $utilizador['id'], $data);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="validacoes_' . $data . '.csv"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, ['Hora', 'Nome', 'Número', 'Refeição', 'Pedido', 'Data da refeição', 'Preço total (€)'], ';');

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