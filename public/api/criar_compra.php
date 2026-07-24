<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno');
$refeicao_id = (int) ($_POST['refeicao_id'] ?? 0);
$pedido_especial = $_POST['pedido_especial'] ?? null;

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
    // Compra fica "pendente" — o pagamento é confirmado à parte, em lote
    echo json_encode(['status' => 'ok', 'compra_id' => (int) $resultado]);
}