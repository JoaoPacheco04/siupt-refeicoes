<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno', true);

$dataRefeicao = $_POST['data_refeicao'] ?? '';
$itensJson = $_POST['itens'] ?? '';

if ($dataRefeicao === '' || $itensJson === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Faltam parametros: data_refeicao e itens']);
    exit;
}

$itens = json_decode($itensJson, true);
if (!is_array($itens) || empty($itens)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Itens invalidos']);
    exit;
}

$itensValidados = [];
foreach ($itens as $item) {
    if (!isset($item['rm_id'])) {
        continue;
    }
    $itensValidados[] = [
        'rm_id' => (int) $item['rm_id'],
        'menu_completo' => !empty($item['menu_completo']),
    ];
}

$resultado = Database::criarPedido($utilizador['id'], $dataRefeicao, $itensValidados);

$mensagens = [
    'sem_itens' => 'Nenhum item selecionado',
    'prato_invalido' => 'Prato não encontrado',
    'menu_completo_invalido_para_extra' => 'Menu completo só é válido para pratos da ementa',
    'fora_de_prazo' => 'Fora do prazo de compra (corte às 14h30 do dia anterior)',
    'menu_completo_nao_configurado' => 'Preço do menu completo não está configurado',
    'sem_preco_definido' => 'Preço não definido para este prato',
];

if (is_string($resultado) && isset($mensagens[$resultado])) {
    echo json_encode(['status' => 'erro', 'mensagem' => $mensagens[$resultado]]);
} elseif (is_int($resultado)) {
    $pedido = Database::obterPedido($resultado);
    echo json_encode([
        'status' => 'ok',
        'pedido_id' => $resultado,
        'qrcode' => $pedido['RP_QRCODE'],
        'preco_total' => $pedido['RP_PRECO_TOTAL'],
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro desconhecido']);
}