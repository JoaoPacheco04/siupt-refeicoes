<?php
/**
 * Exportar relatório mensal de refeições em formato CSV para Excel.
 * Requer papel: admin_cantina
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina');

$anoMes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
    $anoMes = date('Y-m');
}

$pdo = Database::conexao();
$stmt = $pdo->prepare("
    SELECT 
        rp.RP_ID,
        rc.RC_DATA_COMPRA,
        rp.RP_DATA_REFEICAO,
        u.U_BICC,
        u.U_NOME,
        t.RTP_NOME AS TIPO_PRATO,
        m.RM_NOME AS NOME_PRATO,
        rc.RC_PRECO,
        rp.RP_PAGO,
        rp.RP_UTILIZADO,
        rv.RV_DATA_VALIDACAO
    FROM restaurante_pedido rp
    JOIN users u ON rp.RP_U_ID = u.U_ID
    JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
    JOIN restaurante_menu m ON rc.RC_RM_ID = m.RM_ID
    JOIN restaurante_tipo_refeicao t ON m.RM_TP_ID = t.RTP_ID
    LEFT JOIN restaurante_validacao rv ON rv.RV_RP_ID = rp.RP_ID
    WHERE rp.RP_DATA_REFEICAO LIKE ?
    ORDER BY rp.RP_DATA_REFEICAO ASC, rp.RP_ID ASC
");
$stmt->execute([$anoMes . '%']);
$linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Headers HTTP para download do CSV
$nomeFicheiro = "relatorio_cantina_{$anoMes}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"{$nomeFicheiro}\"");
header('Pragma: no-cache');
header('Expires: 0');

// Saída UTF-8 com BOM para garantir abertura correta no Excel em Windows
$saida = fopen('php://output', 'w');
fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Cabeçalho das colunas (delimitador ponto e vírgula padrão para Excel PT)
fputcsv($saida, [
    'ID Pedido',
    'Data Compra',
    'Data Refeição',
    'Nº Cartão/BICC',
    'Utilizador',
    'Tipo de Refeição',
    'Nome do Prato',
    'Valor (€)',
    'Estado Pagamento',
    'Estado Levantamento',
    'Data/Hora Levantamento'
], ';');

foreach ($linhas as $linha) {
    $pago       = (int) $linha['RP_PAGO'] === 1 ? 'Pago' : 'Pendente';
    $levantado  = (int) $linha['RP_UTILIZADO'] === 1 ? 'Levantado' : 'Não levantado';
    $valor      = number_format((float) $linha['RC_PRECO'], 2, ',', '');
    $dataCompra = !empty($linha['RC_DATA_COMPRA']) ? date('d/m/Y H:i', strtotime($linha['RC_DATA_COMPRA'])) : '';
    $dataRef    = !empty($linha['RP_DATA_REFEICAO']) ? date('d/m/Y', strtotime($linha['RP_DATA_REFEICAO'])) : '';
    $dataLev    = !empty($linha['RV_DATA_VALIDACAO']) ? date('d/m/Y H:i', strtotime($linha['RV_DATA_VALIDACAO'])) : '';

    fputcsv($saida, [
        $linha['RP_ID'],
        $dataCompra,
        $dataRef,
        $linha['U_BICC'],
        $linha['U_NOME'],
        $linha['TIPO_PRATO'],
        $linha['NOME_PRATO'],
        $valor,
        $pago,
        $levantado,
        $dataLev,
    ], ';');
}

fclose($saida);
exit;
