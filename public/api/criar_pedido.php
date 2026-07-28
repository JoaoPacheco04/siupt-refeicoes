<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$dataRefeicao = $_POST['data_refeicao'] ?? '';
$itensJson = $_POST['itens'] ?? '';

if ($dataRefeicao === '' || $itensJson === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Faltam parametros: data_refeicao e itens']);
    exit;
}

// Valida formato YYYY-MM-DD e que é uma data de calendário real
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRefeicao)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data inválido']);
    exit;
}
[$ano, $mes, $dia] = explode('-', $dataRefeicao);
if (!checkdate((int) $mes, (int) $dia, (int) $ano)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida']);
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
    'fora_de_prazo' => 'Fora do prazo de compra para este prato',
    'extra_fora_de_horario' => 'Já não é possível comprar para hoje — fora do horário de compra',
    'menu_completo_nao_configurado' => 'Preço do menu completo não está configurado',
    'sem_preco_definido' => 'Preço não definido para este prato',
    'pedido_duplicado' => 'Já tens um pedido pago para este dia', 
];

if (is_string($resultado) && isset($mensagens[$resultado])) {
    echo json_encode(['status' => 'erro', 'mensagem' => $mensagens[$resultado]]);
} elseif (is_int($resultado)) {
    $pedido = Database::obterPedido($resultado);
    echo json_encode([
        'status' => 'ok',
        'pedido_id' => $resultado,
        'qrcode' => $pedido['RP_QRCODE'],
        'codigo_curto' => $pedido['RP_CODIGO_CURTO'],
        'preco_total' => $pedido['RP_PRECO_TOTAL'],
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro desconhecido']);
}