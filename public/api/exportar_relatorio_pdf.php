<?php
/**
 * Exportar Relatório Mensal Completo da Cantina em PDF.
 *
 * Inclui todos os dados e secções visíveis na página relatorio.php:
 *  - Indicadores Chave de Desempenho (Vendas, Levantamentos, Preço Médio, Variação)
 *  - Distribuição das Escolhas (% e quantidades por tipo)
 *  - Vendas por Tipo — Ementa (detalhado)
 *  - Vendas por Tipo — Pratos Extra (detalhado)
 *  - Evolução Diária de Vendas
 *  - Avaliações e Satisfação dos Utentes por Prato
 *  - Motivos de Reclamação das Avaliações Negativas
 *
 * Requer papel: admin_cantina
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$utilizador = exigirLogin('admin_cantina');

$anoMes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
    $anoMes = date('Y-m');
}

// 1. Carregar dados do relatório
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

// Meses
$mesesNomes = [
    '01' => 'Janeiro',   '02' => 'Fevereiro', '03' => 'Março',     '04' => 'Abril',
    '05' => 'Maio',      '06' => 'Junho',     '07' => 'Julho',     '08' => 'Agosto',
    '09' => 'Setembro',  '10' => 'Outubro',   '11' => 'Novembro',  '12' => 'Dezembro',
];
[$anoSel, $mesSel] = explode('-', $anoMes);
$nomeMes = $mesesNomes[$mesSel] ?? $mesSel;

// Cálculos de variação e pratos
$diferenca = $resumo['total_vendido'] - $resumo['total_vendido_mes_anterior'];
$percentagem = $resumo['total_vendido_mes_anterior'] > 0
    ? ($diferenca / $resumo['total_vendido_mes_anterior']) * 100
    : null;

$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => (int) ($t['RM_PRATO_DIA'] ?? 0) !== 0 || $t['RTP_NOME'] === 'Menu Completo'));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => (int) ($t['RM_PRATO_DIA'] ?? 0) === 0 && $t['RTP_NOME'] !== 'Menu Completo'));
$totalExtras = array_sum(array_column($extrasVendas, 'total'));
$qtdExtras = array_sum(array_column($extrasVendas, 'quantidade'));

// Distribuição das escolhas
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

// 2. Construção do HTML com UTF-8 e estilos otimizados para Dompdf
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 1.2cm 1.4cm;
    }
    body {
        font-family: "DejaVu Sans", sans-serif;
        color: #1e293b;
        font-size: 10pt;
        line-height: 1.35;
    }
    .header-relatorio {
        border-bottom: 2px solid #1e3a8a;
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    .instituicao {
        font-size: 9pt;
        font-weight: bold;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .titulo-principal {
        font-size: 17pt;
        font-weight: bold;
        color: #0f172a;
        margin: 4px 0 2px 0;
    }
    .meta-emissao {
        font-size: 8.5pt;
        color: #64748b;
    }

    /* Cartões de Indicadores */
    .kpi-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 6px;
        margin-bottom: 18px;
    }
    .kpi-card {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 8px 6px;
        text-align: center;
        width: 20%;
    }
    .kpi-card--alerta {
        background: #fef2f2;
        border-color: #fca5a5;
    }
    .kpi-valor {
        font-size: 13pt;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .kpi-card--alerta .kpi-valor {
        color: #b91c1c;
    }
    .kpi-rotulo {
        font-size: 7.5pt;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
    }
    .kpi-variacao {
        font-size: 7.5pt;
        margin-top: 3px;
        font-weight: bold;
    }
    .kpi-variacao--positiva { color: #16a34a; }
    .kpi-variacao--negativa { color: #dc2626; }

    /* Secções e Tabelas */
    h2 {
        font-size: 11pt;
        color: #1e3a8a;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 4px;
        margin: 18px 0 8px 0;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    table.dados {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 9pt;
    }
    table.dados th {
        background: #f1f5f9;
        color: #334155;
        font-weight: bold;
        text-align: left;
        padding: 6px 8px;
        border-bottom: 1px solid #cbd5e1;
        font-size: 8.5pt;
    }
    table.dados td {
        padding: 5px 8px;
        border-bottom: 1px solid #f1f5f9;
    }
    table.dados tr:nth-child(even) td {
        background: #fafafa;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .destaque-linha {
        font-weight: bold;
        background: #eff6ff !important;
    }
    .total-geral {
        font-weight: bold;
        border-top: 2px solid #cbd5e1;
        background: #f8fafc !important;
    }
    .badge-estrelas {
        color: #eab308;
        letter-spacing: 1px;
    }
    .vazio {
        color: #64748b;
        font-style: italic;
        padding: 6px 0;
        font-size: 8.5pt;
    }

    .footer-oficial {
        margin-top: 25px;
        border-top: 1px solid #cbd5e1;
        padding-top: 8px;
        font-size: 8pt;
        color: #64748b;
        text-align: center;
    }
</style>
</head>
<body>

<div class="header-relatorio">
    <div class="instituicao">Universidade Portucalense &bull; Serviços de Cantina e Alimentação</div>
    <div class="titulo-principal">Relatório Mensal de Atividade &mdash; ' . htmlspecialchars($nomeMes . ' ' . $anoSel) . '</div>
    <div class="meta-emissao">Emitido em ' . date('d/m/Y \à\s H:i') . ' &bull; Utilizador: ' . htmlspecialchars($utilizador['nome']) . '</div>
</div>

<!-- Indicadores Principais -->
<table class="kpi-table">
    <tr>
        <td class="kpi-card">
            <div class="kpi-valor">' . number_format($resumo['total_vendido'], 2, ',', '.') . ' €</div>
            <div class="kpi-rotulo">Total Vendido</div>';
if ($percentagem !== null) {
    $classeVar = $percentagem >= 0 ? 'kpi-variacao--positiva' : 'kpi-variacao--negativa';
    $sinal = $percentagem >= 0 ? '+' : '';
    $html .= '<div class="kpi-variacao ' . $classeVar . '">' . $sinal . number_format($percentagem, 1, ',', '.') . '% vs mês ant.</div>';
}
$html .= '</td>
        <td class="kpi-card">
            <div class="kpi-valor">' . $resumo['total_pedidos'] . '</div>
            <div class="kpi-rotulo">Pedidos Pagos</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-valor">' . $resumo['total_levantados'] . '</div>
            <div class="kpi-rotulo">Refeições Levantadas</div>
        </td>
        <td class="kpi-card ' . ($resumo['total_nao_levantados'] > 0 ? 'kpi-card--alerta' : '') . '">
            <div class="kpi-valor">' . $resumo['total_nao_levantados'] . '</div>
            <div class="kpi-rotulo">Não Levantadas</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-valor">' . number_format($resumo['preco_medio'], 2, ',', '.') . ' €</div>
            <div class="kpi-rotulo">Preço Médio / Pedido</div>
        </td>
    </tr>
</table>';

// 1. Distribuição das Escolhas
if (!empty($distribuicaoTipos)) {
    $html .= '<h2>Distribuição das Escolhas (' . $totalQtdGeral . ' refeições consumidas)</h2>';
    $html .= '<table class="dados">
        <thead>
            <tr>
                <th>Tipo de Refeição</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Percentagem</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($distribuicaoTipos as $seg) {
        $html .= '<tr>
            <td>' . htmlspecialchars($seg['nome']) . '</td>
            <td class="text-right">' . $seg['quantidade'] . 'x</td>
            <td class="text-right"><strong>' . $seg['percentagem'] . '%</strong></td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

// 2. Vendas por Tipo — Ementa
$html .= '<h2>Vendas por Tipo &mdash; Ementa</h2>';
if (empty($pratosEmenta)) {
    $html .= '<p class="vazio">Sem vendas de pratos da ementa neste mês.</p>';
} else {
    $html .= '<table class="dados">
        <thead>
            <tr>
                <th>Prato / Tipo</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Total Faturado</th>
            </tr>
        </thead>
        <tbody>';
    $somaQtdEmenta = 0;
    $somaTotalEmenta = 0.0;
    foreach ($pratosEmenta as $i => $t) {
        $somaQtdEmenta += (int) $t['quantidade'];
        $somaTotalEmenta += (float) $t['total'];
        $classe = $i === 0 ? ' class="destaque-linha"' : '';
        $html .= '<tr' . $classe . '>
            <td>' . htmlspecialchars($t['RTP_NOME']) . ($i === 0 ? ' ★' : '') . '</td>
            <td class="text-right">' . $t['quantidade'] . 'x</td>
            <td class="text-right">' . number_format((float) $t['total'], 2, ',', '.') . ' €</td>
        </tr>';
    }
    $html .= '<tr class="total-geral">
        <td>Total Ementa</td>
        <td class="text-right">' . $somaQtdEmenta . 'x</td>
        <td class="text-right">' . number_format($somaTotalEmenta, 2, ',', '.') . ' €</td>
    </tr>';
    $html .= '</tbody></table>';
}

// 3. Vendas por Tipo — Extras
$html .= '<h2>Vendas por Tipo &mdash; Pratos Extra</h2>';
if (empty($extrasVendas)) {
    $html .= '<p class="vazio">Sem vendas de pratos extra neste mês.</p>';
} else {
    $html .= '<table class="dados">
        <thead>
            <tr>
                <th>Prato Extra</th>
                <th class="text-right">Quantidade</th>
                <th class="text-right">Total Faturado</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($extrasVendas as $i => $t) {
        $classe = $i === 0 ? ' class="destaque-linha"' : '';
        $html .= '<tr' . $classe . '>
            <td>' . htmlspecialchars(str_replace('Extra: ', '', $t['RTP_NOME'])) . ($i === 0 ? ' ★' : '') . '</td>
            <td class="text-right">' . $t['quantidade'] . 'x</td>
            <td class="text-right">' . number_format((float) $t['total'], 2, ',', '.') . ' €</td>
        </tr>';
    }
    $html .= '<tr class="total-geral">
        <td>Total Extras</td>
        <td class="text-right">' . $qtdExtras . 'x</td>
        <td class="text-right">' . number_format($totalExtras, 2, ',', '.') . ' €</td>
    </tr>';
    $html .= '</tbody></table>';
}

// 4. Vendas Diárias Detalhadas
$html .= '<h2>Evolução de Vendas Diárias &mdash; Detalhe</h2>';
if (empty($vendasDiarias)) {
    $html .= '<p class="vazio">Sem vendas registadas neste mês.</p>';
} else {
    $html .= '<table class="dados">
        <thead>
            <tr>
                <th>Data</th>
                <th class="text-right">Nº de Pedidos</th>
                <th class="text-right">Total Vendido</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($vendasDiarias as $d) {
        $html .= '<tr>
            <td>' . date('d/m/Y', strtotime($d['RP_DATA_REFEICAO'])) . '</td>
            <td class="text-right">' . $d['total_pedidos'] . ' pedido(s)</td>
            <td class="text-right">' . number_format((float) $d['total_vendido'], 2, ',', '.') . ' €</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

// 5. Avaliações e Satisfação dos Alunos por Prato
$html .= '<h2>Índice de Satisfação & Avaliações por Prato</h2>';
if ($mediaAvaliacoes['total'] > 0) {
    $mediaGeral = (float) $mediaAvaliacoes['media'];
    $estrelasGerais = str_repeat('★', (int) round($mediaGeral)) . str_repeat('☆', 5 - (int) round($mediaGeral));
    $html .= '<p style="margin-bottom: 8px;"><strong>Classificação Média da Cantina:</strong> <span class="badge-estrelas">' . $estrelasGerais . '</span> ' . number_format($mediaGeral, 1, ',', '.') . ' / 5 (' . $mediaAvaliacoes['total'] . ' avaliações registadas)</p>';
}

if (empty($avaliacoesPorPrato)) {
    $html .= '<p class="vazio">Sem avaliações registadas por prato neste mês.</p>';
} else {
    $html .= '<table class="dados">
        <thead>
            <tr>
                <th>Prato</th>
                <th class="text-center">Avaliação Média</th>
                <th class="text-right">Nº de Avaliações</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($avaliacoesPorPrato as $prato) {
        $mediaPrato = (float) $prato['media'];
        $estrelas = str_repeat('★', (int) round($mediaPrato)) . str_repeat('☆', 5 - (int) round($mediaPrato));
        $html .= '<tr>
            <td>' . htmlspecialchars($prato['RM_NOME']) . '</td>
            <td class="text-center"><span class="badge-estrelas">' . $estrelas . '</span> <strong>' . number_format($mediaPrato, 1, ',', '.') . '</strong> / 5</td>
            <td class="text-right">' . (int) $prato['total'] . ' avaliação(ões)</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

// 6. Motivos de Reclamação (Avaliações Negativas)
$html .= '<h2>Motivos de Reclamação (Avaliações 1-2 Estrelas)</h2>';
if (empty($motivosProblemas)) {
    $html .= '<p class="vazio">Sem reclamações registadas para este mês.</p>';
} else {
    $html .= '<table class="dados">
        <thead>
            <tr>
                <th>Motivo / Problema</th>
                <th class="text-right">Ocorrências</th>
                <th>Pratos Associados Citados</th>
            </tr>
        </thead>
        <tbody>';
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
            $listaPratosStr[] = htmlspecialchars($pNome) . ' (' . $pQtd . '×)';
        }
        $pratosTexto = !empty($listaPratosStr) ? implode(', ', $listaPratosStr) : 'Não especificado';

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($labelMotivo) . '</strong></td>
            <td class="text-right"><strong>' . (int) $m['total'] . '×</strong></td>
            <td>' . $pratosTexto . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

// Rodapé
$html .= '<div class="footer-oficial">
    SIUPT &bull; Sistema Integrado da Universidade Portucalense &bull; Cantina Universitária
</div>';

$html .= '</body></html>';

// 3. Renderização do PDF com Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nomeFicheiro = "relatorio_cantina_{$anoMes}.pdf";
$dompdf->stream($nomeFicheiro, ['Attachment' => true]);
exit;
