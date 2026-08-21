<?php
/**
 * Endpoint AJAX responsável por processar o resultado
 * devolvido pelo gateway de pagamento simulado.
 *
 * Recebe um ou mais identificadores de pedidos e atualiza
 * o respetivo estado, devolvendo uma resposta em formato JSON.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';
require_once __DIR__ . '/../../src/Services/PagamentoService.php';

header('Content-Type: application/json');

exigirPost();

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$pedidoIdsRaw = $_POST['pedido_ids'] ?? '';
$sucesso = ($_POST['resultado'] ?? '') === 'sucesso';

$pedidoIds = array_unique(
    array_filter(
        array_map('intval', explode(',', $pedidoIdsRaw))
    )
);

if (empty($pedidoIds)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Falta pedido_ids'
    ]);
    exit;
}

/**
 * Gera uma referência única para identificar o lote
 * processado pelo gateway de pagamento.
 */
$refGatewayBatch = 'SIM-' . uniqid();

$resultados = [];

// Cada pedido do lote é processado de forma independente (não atómico):
// se um falhar, os restantes continuam a ser processados normalmente.
// Decisão: um erro pontual não deve bloquear a confirmação dos outros dias.
foreach ($pedidoIds as $id) {

    $pedido = Database::obterPedido($id);

    /**
     * Garante que o pedido existe e pertence
     * ao utilizador autenticado.
     */
    if (!$pedido || (int) $pedido['RP_U_ID'] !== (int) $utilizador['id']) {
        $resultados[] = [
            'pedido_id' => $id,
            'status' => 'nao_autorizado'
        ];
        continue;
    }

    /**
     * Evita o processamento repetido de pedidos
     * que já se encontram pagos.
     */
    if ((int) $pedido['RP_PAGO'] === 1) {
        $resultados[] = [
            'pedido_id'    => $id,
            'status'       => 'ja_pago',
            'qrcode'       => $pedido['RP_QRCODE'],
            'codigo_curto' => $pedido['RP_CODIGO_CURTO'],
        ];
        continue;
    }

    $r = PagamentoService::processar($id, $sucesso, $refGatewayBatch);

    $entrada = [
        'pedido_id' => $id,
        'status'    => $r['status']
    ];

    if ($r['status'] === 'confirmado') {
        $pedidoAtualizado = Database::obterPedido($id);
        $entrada['qrcode']       = ($pedidoAtualizado ?: $pedido)['RP_QRCODE'];
        $entrada['codigo_curto'] = ($pedidoAtualizado ?: $pedido)['RP_CODIGO_CURTO'];
    }

    $resultados[] = $entrada;

}

echo json_encode([
    'status' => 'ok',
    'ref_gateway' => $refGatewayBatch,
    'detalhe' => $resultados,
]);