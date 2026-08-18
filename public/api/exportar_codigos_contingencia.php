<?php
/**
 * Gera uma lista imprimível (PDF) dos códigos curtos válidos do dia,
 * para uso como contingência se o sistema de validação (scanner QR)
 * ficar indisponível durante o serviço. Pensado para ser impresso no
 * início do turno, antes do pico do almoço — não gerado durante uma falha.
 * A funcionária risca à mão os códigos à medida que os alunos levantam.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$utilizador = exigirLogin('atendente');

$refeicoes = Database::listarRefeicoesPorLevantarHoje();
$hoje = date('d/m/Y');
$hora = date('H:i');

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
    @page { margin: 25px 30px; }
    body { font-family: DejaVu Sans, Helvetica, sans-serif; color: #1e2a3b; font-size: 11px; margin: 0; }
    .header { border-bottom: 2px solid #1a3a63; padding-bottom: 10px; margin-bottom: 12px; }
    .header h1 { font-size: 16px; margin: 0 0 4px 0; color: #1a3a63; text-transform: uppercase; }
    .header p { color: #64748b; margin: 0; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 10px; }
    th { background: #f1f5f9; color: #334155; font-weight: bold; border-top: 1px solid #cbd5e1; }
    tr:nth-child(even) { background: #fafafa; }
    .checkbox { width: 30px; text-align: center; font-size: 12px; color: #94a3b8; }
    .codigo { font-family: monospace; font-weight: bold; font-size: 12px; letter-spacing: 1.5px; color: #0f172a; }
    .num { color: #64748b; font-size: 9.5px; }
    .vazio { text-align: center; color: #64748b; padding: 20px; }
    .footer-info { margin-top: 15px; font-size: 9px; color: #94a3b8; text-align: right; }
</style></head><body>';

$html .= '<div class="header">';
$html .= '<h1>SIUPT Refeições &mdash; Lista de Contingência</h1>';
$html .= '<p>Gerado em ' . $hoje . ' às ' . $hora . ' &bull; <strong>Instruções:</strong> Utilizar em caso de falha de rede/sistema. Riscar o código à medida que as refeições são entregues.</p>';
$html .= '</div>';

$html .= '<table><tr><th class="checkbox">&#9633;</th><th>Código</th><th>Nome</th><th>Nº</th><th>Refeição</th></tr>';
foreach ($refeicoes as $r) {
    $html .= '<tr>
        <td class="checkbox">&#9633;</td>
        <td class="codigo">' . htmlspecialchars($r['RP_CODIGO_CURTO'], ENT_QUOTES, 'UTF-8') . '</td>
        <td><strong>' . htmlspecialchars($r['U_NOME'], ENT_QUOTES, 'UTF-8') . '</strong></td>
        <td class="num">' . htmlspecialchars($r['U_BICC'], ENT_QUOTES, 'UTF-8') . '</td>
        <td>' . htmlspecialchars($r['itens'] ?? 'Sem itens registados', ENT_QUOTES, 'UTF-8') . '</td>
    </tr>';
}
$html .= '</table>';

if (empty($refeicoes)) {
    $html .= '<p class="vazio">Não existem refeições pendentes de levantamento para hoje.</p>';
}

$html .= '<div class="footer-info">Universidade Portucalense &bull; SIUPT Refeições</div>';
$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('lista_contingencia_' . date('Y-m-d') . '.pdf', ['Attachment' => false]);
