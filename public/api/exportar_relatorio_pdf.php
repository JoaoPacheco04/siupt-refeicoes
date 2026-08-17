<?php
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

$resumo = Database::obterResumoMensal($anoMes);
$vendasPorTipo = Database::obterVendasPorTipoMensal($anoMes);
$vendasDiarias = Database::obterVendasDiariasMensal($anoMes);

$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => (int) ($t['RM_PRATO_DIA'] ?? 0) !== 0 || $t['RTP_NOME'] === 'Menu Completo'));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => (int) ($t['RM_PRATO_DIA'] ?? 0) === 0 && $t['RTP_NOME'] !== 'Menu Completo'));

$precoMedio = $resumo['total_pedidos'] > 0 ? $resumo['total_vendido'] / $resumo['total_pedidos'] : 0;
$diferenca = $resumo['total_vendido'] - $resumo['total_vendido_mes_anterior'];
$percentagem = $resumo['total_vendido_mes_anterior'] > 0 ? ($diferenca / $resumo['total_vendido_mes_anterior']) * 100 : null;

$mesesNomes = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',     '04' => 'Abril',
    '05' => 'Maio',    '06' => 'Junho',     '07' => 'Julho',     '08' => 'Agosto',
    '09' => 'Setembro','10' => 'Outubro',   '11' => 'Novembro',  '12' => 'Dezembro',
];
[$anoSel, $mesSel] = explode('-', $anoMes);
$nomeMes = $mesesNomes[$mesSel] ?? $mesSel;

$html = '<html><head><meta charset="UTF-8"><style>
    body { font-family: sans-serif; color: #1e2a3b; font-size: 12px; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    h2 { font-size: 14px; color: #555; margin-top: 22px; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 6px 10px; border-bottom: 1px solid #eee; text-align: left; }
    .total { text-align: right; font-weight: bold; }
    .resumo { width: 100%; margin: 12px 0; }
    .resumo td { border: 1px solid #ddd; text-align: center; padding: 10px; }
    .resumo strong { font-size: 15px; display: block; }
    .destaque { background: #fffbeb; }
</style></head><body>';

$html .= "<h1>Relatório mensal &mdash; {$nomeMes} {$anoSel}</h1>";
$html .= '<p style="color:#888;">Gerado em ' . date('d/m/Y H:i') . '</p>';

$html .= '<table class="resumo"><tr>
    <td><strong>' . number_format($resumo['total_vendido'], 2, ',', '.') . '€</strong>Total vendido</td>
    <td><strong>' . $resumo['total_pedidos'] . '</strong>Pedidos pagos</td>
    <td><strong>' . $resumo['total_levantados'] . '</strong>Levantadas</td>
    <td><strong>' . $resumo['total_nao_levantados'] . '</strong>Não levantadas</td>
    <td><strong>' . number_format($precoMedio, 2, ',', '.') . '€</strong>Preço médio</td>
</tr></table>';

if ($percentagem !== null) {
    $sinal = $percentagem >= 0 ? '+' : '';
    $html .= '<p style="color:#666;">Variação face ao mês anterior: <strong>' . $sinal . number_format($percentagem, 1, ',', '.') . '%</strong></p>';
}

$html .= '<h2>Vendas por tipo &mdash; ementa</h2><table><tr><th>Tipo</th><th>Qtd</th><th>Total</th></tr>';
foreach ($pratosEmenta as $i => $t) {
    $classe = $i === 0 ? ' class="destaque"' : '';
    $html .= "<tr{$classe}><td>" . htmlspecialchars($t['RTP_NOME']) . '</td><td>' . $t['quantidade'] . 'x</td>'
        . '<td class="total">' . number_format((float) $t['total'], 2, ',', '.') . '€</td></tr>';
}
$html .= '</table>';

$html .= '<h2>Vendas por tipo &mdash; extras</h2><table><tr><th>Tipo</th><th>Qtd</th><th>Total</th></tr>';
foreach ($extrasVendas as $i => $t) {
    $classe = $i === 0 ? ' class="destaque"' : '';
    $html .= "<tr{$classe}><td>" . htmlspecialchars(str_replace('Extra: ', '', $t['RTP_NOME'])) . '</td><td>' . $t['quantidade'] . 'x</td>'
        . '<td class="total">' . number_format((float) $t['total'], 2, ',', '.') . '€</td></tr>';
}
$html .= '</table>';

$html .= '<h2>Vendas diárias</h2><table><tr><th>Data</th><th>Pedidos</th><th>Total</th></tr>';
foreach ($vendasDiarias as $d) {
    $html .= '<tr><td>' . date('d/m/Y', strtotime($d['RP_DATA_REFEICAO'])) . '</td><td>' . $d['total_pedidos'] . '</td>'
        . '<td class="total">' . number_format((float) $d['total_vendido'], 2, ',', '.') . '€</td></tr>';
}
$html .= '</table></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("relatorio_{$anoMes}.pdf", ['Attachment' => true]);
