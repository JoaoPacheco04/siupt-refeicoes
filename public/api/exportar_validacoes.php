<?php
/**
 * Endpoint: Exportar validações em CSV
 *
 * Gera um ficheiro CSV com as validações de refeições de um dia específico.
 *
 * Parâmetros GET:
 *  - data  string  Data no formato YYYY-MM-DD (opcional; omitida = hoje)
 *
 * Comportamento por papel:
 *  - admin_cantina: exporta as validações de TODOS os funcionários nesse dia
 *  - atendente:     exporta apenas as suas próprias validações
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('atendente');

// Aceitar data via GET; se omitida ou inválida, usa hoje
$data = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $data = date('Y-m-d');
}

$vejoTudo   = temPapelSessao('admin_cantina');
$validacoes = $vejoTudo
    ? Database::listarValidacoesPorDataTodos($data)
    : Database::listarValidacoesPorData((int) $utilizador['id'], $data);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="validacoes_' . $data . '.csv"');

$saida = fopen('php://output', 'w');

// BOM UTF-8: garante que o Excel abre o ficheiro sem garrafão de caracteres especiais
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
