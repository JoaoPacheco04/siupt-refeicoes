<?php
/**
 * Exportar Relatório Mensal Completo de Refeições em formato CSV (Excel).
 *
 * Exporta todas as secções apresentadas em relatorio.php:
 *  1. Indicadores Principais (KPIs do Mês)
 *  2. Distribuição das Escolhas (% e quantidades por tipo)
 *  3. Vendas por Tipo — Ementa
 *  4. Vendas por Tipo — Extras
 *  5. Vendas Diárias Detalhadas
 *  6. Avaliações e Satisfação dos Alunos por Prato
 *  7. Motivos de Reclamação Registados
 *  8. Registo Detalhado de Transações Individuais
 *
 * Requer papel: admin_cantina
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina');

$anoMes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
    $anoMes = date('Y-m');
}

// 1. Obter dados agregados e estatísticos
$resumo = Database::obterResumoMensal($anoMes);
$vendasPorTipo = Database::obterVendasPorTipoMensal($anoMes);
$vendasDiarias = Database::obterVendasDiariasMensal($anoMes);
$mediaAvaliacoes = Database::obterMediaAvaliacoesMensal($anoMes);
$avaliacoesPorPrato = Database::obterMediaAvaliacoesPorPrato(1, $anoMes);
$motivosProblemas = Database::obterMotivosProblemasMensal($anoMes);

// Motivos labels
$motivosLabels = [];
foreach (Database::listarTodosMotivosReclamacao() as $m) {
    $motivosLabels[$m['RMR_CODIGO']] = $m['RMR_LABEL'];
}

// Mês selecionado
$mesesNomes = [
    '01' => 'Janeiro',   '02' => 'Fevereiro', '03' => 'Março',     '04' => 'Abril',
    '05' => 'Maio',      '06' => 'Junho',     '07' => 'Julho',     '08' => 'Agosto',
    '09' => 'Setembro',  '10' => 'Outubro',   '11' => 'Novembro',  '12' => 'Dezembro',
];
[$anoSel, $mesSel] = explode('-', $anoMes);
$nomeMes = $mesesNomes[$mesSel] ?? $mesSel;

// Variação vs mês anterior
$diferenca = $resumo['total_vendido'] - $resumo['total_vendido_mes_anterior'];
$percentagem = $resumo['total_vendido_mes_anterior'] > 0
    ? ($diferenca / $resumo['total_vendido_mes_anterior']) * 100
    : null;

$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => (int) ($t['RM_PRATO_DIA'] ?? 0) !== 0 || $t['RTP_NOME'] === 'Menu Completo'));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => (int) ($t['RM_PRATO_DIA'] ?? 0) === 0 && $t['RTP_NOME'] !== 'Menu Completo'));
$totalExtras = array_sum(array_column($extrasVendas, 'total'));
$qtdExtras = array_sum(array_column($extrasVendas, 'quantidade'));

// Distribuição percentual
$totalQtdGeral = array_sum(array_column($vendasPorTipo, 'quantidade'));
$distribuicaoTipos = [];
if ($totalQtdGeral > 0) {
    foreach ($vendasPorTipo as $vt) {
        $qtd = (int) $vt['quantidade'];
        $distribuicaoTipos[] = [
            'nome'        => trim($vt['RTP_NOME']),
            'quantidade'  => $qtd,
            'percentagem' => round(($qtd / $totalQtdGeral) * 100, 1),
        ];
    }
}

// 2. Transações individuais detalhadas
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
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Headers HTTP para download do CSV
$nomeFicheiro = "relatorio_cantina_{$anoMes}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"{$nomeFicheiro}\"");
header('Pragma: no-cache');
header('Expires: 0');

$saida = fopen('php://output', 'w');
// UTF-8 com BOM para Excel
fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Função auxiliar para escrever linhas em branco
$linhaEmBranco = function() use ($saida) {
    fputcsv($saida, [], ';');
};

// ── CABEÇALHO DO RELATÓRIO ────────────────────────────────────────────
fputcsv($saida, ['UNIVERSIDADE PORTUCALENSE - SERVIÇOS DE CANTINA'], ';');
fputcsv($saida, ['RELATÓRIO MENSAL DE ATIVIDADE E VENDAS', "{$nomeMes} {$anoSel}"], ';');
fputcsv($saida, ['Data de Emissão', date('d/m/Y H:i'), 'Emitido por', $utilizador['nome']], ';');
$linhaEmBranco();

// ── 1. INDICADORES PRINCIPAIS (KPIS) ──────────────────────────────────
fputcsv($saida, ['=== 1. INDICADORES PRINCIPAIS DO MÊS ==='], ';');
fputcsv($saida, ['Indicador', 'Valor', 'Observação'], ';');
fputcsv($saida, ['Total Vendido', number_format($resumo['total_vendido'], 2, ',', '') . ' €', $percentagem !== null ? ($percentagem >= 0 ? '+' : '') . number_format($percentagem, 1, ',', '') . '% vs mês anterior' : 'Sem histórico anterior'], ';');
fputcsv($saida, ['Pedidos Pagos', $resumo['total_pedidos'], 'pedidos'], ';');
fputcsv($saida, ['Refeições Levantadas', $resumo['total_levantados'], 'refeições'], ';');
fputcsv($saida, ['Refeições Não Levantadas', $resumo['total_nao_levantados'], 'refeições'], ';');
fputcsv($saida, ['Preço Médio por Pedido', number_format($resumo['preco_medio'], 2, ',', '') . ' €', 'média por pedido pago'], ';');
$linhaEmBranco();

// ── 2. DISTRIBUIÇÃO DAS ESCOLHAS ─────────────────────────────────────
fputcsv($saida, ['=== 2. DISTRIBUIÇÃO DAS ESCOLHAS ==='], ';');
fputcsv($saida, ['Tipo de Refeição', 'Quantidade (x)', 'Percentagem (%)'], ';');
if (empty($distribuicaoTipos)) {
    fputcsv($saida, ['Sem dados para apresentar'], ';');
} else {
    foreach ($distribuicaoTipos as $seg) {
        fputcsv($saida, [
            $seg['nome'],
            $seg['quantidade'],
            number_format($seg['percentagem'], 1, ',', '') . '%'
        ], ';');
    }
}
$linhaEmBranco();

// ── 3. VENDAS POR TIPO — EMENTA ──────────────────────────────────────
fputcsv($saida, ['=== 3. VENDAS POR TIPO — EMENTA ==='], ';');
fputcsv($saida, ['Prato / Tipo', 'Quantidade (x)', 'Total Faturado (€)'], ';');
$somaQtdEmenta = 0;
$somaTotalEmenta = 0.0;
if (empty($pratosEmenta)) {
    fputcsv($saida, ['Sem vendas de pratos da ementa neste mês'], ';');
} else {
    foreach ($pratosEmenta as $t) {
        $somaQtdEmenta += (int) $t['quantidade'];
        $somaTotalEmenta += (float) $t['total'];
        fputcsv($saida, [
            $t['RTP_NOME'],
            $t['quantidade'],
            number_format((float) $t['total'], 2, ',', '')
        ], ';');
    }
    fputcsv($saida, ['TOTAL EMENTA', $somaQtdEmenta, number_format($somaTotalEmenta, 2, ',', '')], ';');
}
$linhaEmBranco();

// ── 4. VENDAS POR TIPO — EXTRAS ──────────────────────────────────────
fputcsv($saida, ['=== 4. VENDAS POR TIPO — PRATOS EXTRA ==='], ';');
fputcsv($saida, ['Prato Extra', 'Quantidade (x)', 'Total Faturado (€)'], ';');
if (empty($extrasVendas)) {
    fputcsv($saida, ['Sem vendas de extras neste mês'], ';');
} else {
    foreach ($extrasVendas as $t) {
        fputcsv($saida, [
            str_replace('Extra: ', '', $t['RTP_NOME']),
            $t['quantidade'],
            number_format((float) $t['total'], 2, ',', '')
        ], ';');
    }
    fputcsv($saida, ['TOTAL EXTRAS', $qtdExtras, number_format($totalExtras, 2, ',', '')], ';');
}
$linhaEmBranco();

// ── 5. VENDAS DIÁRIAS DETALHADAS ─────────────────────────────────────
fputcsv($saida, ['=== 5. EVOLUÇÃO DE VENDAS DIÁRIAS ==='], ';');
fputcsv($saida, ['Data', 'Nº Pedidos', 'Total Vendido (€)'], ';');
if (empty($vendasDiarias)) {
    fputcsv($saida, ['Sem vendas registadas neste mês'], ';');
} else {
    foreach ($vendasDiarias as $d) {
        fputcsv($saida, [
            date('d/m/Y', strtotime($d['RP_DATA_REFEICAO'])),
            $d['total_pedidos'],
            number_format((float) $d['total_vendido'], 2, ',', '')
        ], ';');
    }
}
$linhaEmBranco();

// ── 6. AVALIAÇÕES E SATISFAÇÃO POR PRATO ─────────────────────────────
fputcsv($saida, ['=== 6. ÍNDICE DE SATISFAÇÃO E AVALIAÇÕES POR PRATO ==='], ';');
if ($mediaAvaliacoes['total'] > 0) {
    fputcsv($saida, ['Classificação Média Global da Cantina', number_format((float) $mediaAvaliacoes['media'], 1, ',', '') . ' / 5', "{$mediaAvaliacoes['total']} avaliações no total"], ';');
}
fputcsv($saida, ['Prato', 'Avaliação Média (1 a 5)', 'Nº de Avaliações'], ';');
if (empty($avaliacoesPorPrato)) {
    fputcsv($saida, ['Sem avaliações por prato neste mês'], ';');
} else {
    foreach ($avaliacoesPorPrato as $prato) {
        fputcsv($saida, [
            $prato['RM_NOME'],
            number_format((float) $prato['media'], 1, ',', ''),
            (int) $prato['total']
        ], ';');
    }
}
$linhaEmBranco();

// ── 7. MOTIVOS DE RECLAMAÇÃO (1-2 ESTRELAS) ──────────────────────────
fputcsv($saida, ['=== 7. MOTIVOS DE RECLAMAÇÃO REGISTADOS ==='], ';');
fputcsv($saida, ['Motivo / Problema', 'Nº Ocorrências', 'Pratos Associados'], ';');
if (empty($motivosProblemas)) {
    fputcsv($saida, ['Sem reclamações registadas neste mês'], ';');
} else {
    foreach ($motivosProblemas as $m) {
        $labelMotivo = $motivosLabels[$m['RAV_MOTIVO']] ?? $m['RAV_MOTIVO'];
        $pratosCitados = [];
        if (!empty($m['pratos_associados'])) {
            $entradas = array_filter(array_map('trim', explode(';;', $m['pratos_associados'])));
            foreach ($entradas as $entrada) {
                $partes = explode('|', $entrada, 2);
                $nPrato = trim($partes[0]);
                if ($nPrato !== '') {
                    $pratosCitados[$nPrato] = ($pratosCitados[$nPrato] ?? 0) + 1;
                }
            }
        }
        $listaPratosStr = [];
        foreach ($pratosCitados as $pNome => $pQtd) {
            $listaPratosStr[] = $pNome . ' (' . $pQtd . 'x)';
        }
        $pratosTexto = !empty($listaPratosStr) ? implode(', ', $listaPratosStr) : 'Não especificado';

        fputcsv($saida, [
            $labelMotivo,
            $m['total'],
            $pratosTexto
        ], ';');
    }
}
$linhaEmBranco();

// ── 8. REGISTO DETALHADO DE TRANSAÇÕES INDIVIDUAIS ───────────────────
fputcsv($saida, ['=== 8. REGISTO DETALHADO DE TRANSAÇÕES DO MÊS ==='], ';');
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

foreach ($transacoes as $linha) {
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
