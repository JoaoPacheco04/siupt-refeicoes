<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';
require_once __DIR__ . '/../../src/Services/PagamentoService.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$pedidoIdsRaw = $_POST['pedido_ids'] ?? '';
$sucesso = ($_POST['resultado'] ?? '') === 'sucesso';

$pedidoIds = array_filter(array_map('intval', explode(',', $pedidoIdsRaw)));

if (empty($pedidoIds)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Falta pedido_ids']);
    exit;
}

$refGatewayBatch = 'SIM-' . uniqid();
$resultados = [];

foreach ($pedidoIds as $id) {
    $pedido = Database::obterPedido($id);

    if (!$pedido || (int) $pedido['RP_U_ID'] !== (int) $utilizador['id']) {
        $resultados[] = ['pedido_id' => $id, 'status' => 'nao_autorizado'];
        continue;
    }

    // Impede double-payment: se já está pago, devolve estado atual sem reprocessar
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
    $entrada = ['pedido_id' => $id, 'status' => $r['status']];
    if ($r['status'] === 'confirmado') {
        $entrada['qrcode']       = $pedido['RP_QRCODE'];
        $entrada['codigo_curto'] = $pedido['RP_CODIGO_CURTO'];
    }
    $resultados[] = $entrada;
}

echo json_encode([
    'status' => 'ok',
    'ref_gateway' => $refGatewayBatch,
    'detalhe' => $resultados,
]);