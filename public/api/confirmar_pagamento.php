<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';
require_once __DIR__ . '/../../src/Services/PagamentoService.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno');

$compraIdsRaw = $_POST['compra_ids'] ?? '';
$sucesso = ($_POST['resultado'] ?? '') === 'sucesso';

$compraIds = array_filter(array_map('intval', explode(',', $compraIdsRaw)));

if (empty($compraIds)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Falta compra_ids']);
    exit;
}

// PONTO DE COSTURA — no futuro, $refGatewayBatch vem da resposta do gateway real
$refGatewayBatch = 'SIM-' . uniqid();

$resultados = [];
foreach ($compraIds as $id) {
    $compra = Database::obterCompra($id);

    // Segurança: só paga compras que pertencem ao utilizador autenticado
    if (!$compra || (int) $compra['comprador_id'] !== (int) $utilizador['id']) {
        $resultados[] = ['compra_id' => $id, 'status' => 'nao_autorizada'];
        continue;
    }

    $r = PagamentoService::processar($id, $sucesso, $refGatewayBatch);
    $resultados[] = ['compra_id' => $id, 'status' => $r['status']];
}

$pagas = count(array_filter($resultados, fn($r) => $r['status'] === 'paga'));

echo json_encode([
    'status' => 'ok',
    'ref_gateway' => $refGatewayBatch,
    'pagas' => $pagas,
    'total' => count($compraIds),
    'detalhe' => $resultados,
]);