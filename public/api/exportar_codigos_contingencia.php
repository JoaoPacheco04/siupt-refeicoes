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

$utilizador = exigirLogin('funcionario');

$refeicoes = Database::listarRefeicoesPorLevantarHoje();
$hoje = date('d/m/Y');

$html = '<html><head><meta charset="UTF-8"><style>
    body { font-family: sans-serif; color: #1e2a3b; font-size: 12px; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    p.info { color: #888; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #f0f4f8; }
    .checkbox { width: 30px; text-align: center; }
    .codigo { font-family: monospace; font-weight: bold; letter-spacing: 1px; }
</style></head><body>';

$html .= "<h1>Lista de contingência — validação manual</h1>";
$html .= "<p class=\"info\">Gerado em {$hoje} — usar apenas se o sistema de validação estiver indisponível. Riscar o código à medida que o aluno levanta a refeição.</p>";

$html .= '<table><tr><th class="checkbox">✓</th><th>Código</th><th>Nome</th><th>Nº</th><th>Refeição</th></tr>';
foreach ($refeicoes as $r) {
    $html .= '<tr>
        <td class="checkbox">☐</td>
        <td class="codigo">' . htmlspecialchars($r['RP_CODIGO_CURTO']) . '</td>
        <td>' . htmlspecialchars($r['U_NOME']) . '</td>
        <td>' . htmlspecialchars($r['U_BICC']) . '</td>
        <td>' . htmlspecialchars($r['itens'] ?? 'Sem itens registados') . '</td>
    </tr>';
}
$html .= '</table>';

if (empty($refeicoes)) {
    $html .= '<p>Sem refeições por levantar hoje.</p>';
}

$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('lista_contingencia_' . date('Y-m-d') . '.pdf', ['Attachment' => false]);