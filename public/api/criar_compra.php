<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';
require_once __DIR__ . '/../../src/Services/PagamentoService.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno');
$refeicao_id = (int) ($_GET['refeicao_id'] ?? 0);
$pedido_especial = $_GET['pedido_especial'] ?? null;

$opcoes_validas = ['vegetariano', 'dieta'];
if ($pedido_especial !== null && !in_array($pedido_especial, $opcoes_validas, true)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Pedido especial inválido. Opções: ' . implode(', ', $opcoes_validas)]);
    exit;
}

if ($refeicao_id === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Falta o parâmetro refeicao_id']);
    exit;
}

$resultado = Database::criarCompra($utilizador['id'], $refeicao_id, $pedido_especial);

if ($resultado === 'refeicao_invalida') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Refeição não encontrada']);
} elseif ($resultado === 'ja_comprado') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já tens uma senha para este dia']);
} elseif ($resultado === 'fora_de_prazo') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Fora do prazo de compra (corte às 10h00 do dia anterior)']);
} else {
    // Compra criada com sucesso — invocar pagamento imediatamente
    $compra_id = (int) $resultado;
    $pagamento = PagamentoService::processar($compra_id, true);

    if ($pagamento['status'] === 'paga') {
        echo json_encode(['status' => 'ok', 'compra_id' => $compra_id, 'mensagem' => 'Compra criada e paga com sucesso']);
    } else {
        // Compra criada mas pagamento falhou — ainda comunicamos sucesso parcial
        echo json_encode(['status' => 'ok', 'compra_id' => $compra_id, 'mensagem' => 'Compra criada (pagamento pendente)', 'aviso' => 'pagamento_pendente']);
    }
}
