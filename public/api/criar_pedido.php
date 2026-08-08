<?php
/**
 * Endpoint: Criar pedido
 *
 * Recebe a data da refeição e a lista de itens selecionados,
 * valida os dados e delega toda a lógica de negócio a Database::criarPedido().
 *
 * Resposta JSON:
 *  { "status": "ok",   "pedido_id": int }
 *  { "status": "erro", "mensagem": string }
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

// Qualquer utilizador autenticado (aluno ou funcionário) pode criar pedidos.
$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$dataRefeicao = trim($_POST['data_refeicao'] ?? '');
$itensRaw     = trim($_POST['itens'] ?? '');

// Validação básica dos campos obrigatórios.
if ($dataRefeicao === '' || $itensRaw === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados em falta.']);
    exit;
}

// Valida o formato da data (YYYY-MM-DD).
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRefeicao)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida.']);
    exit;
}

// Desserializa e valida a lista de itens.
$itens = json_decode($itensRaw, true);
if (!is_array($itens) || empty($itens)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Lista de itens inválida.']);
    exit;
}

// Garante que cada item tem rm_id inteiro e menu_completo booleano.
$itensSanitizados = [];
foreach ($itens as $item) {
    $rmId = (int) ($item['rm_id'] ?? 0);
    if ($rmId <= 0) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Item inválido na lista.']);
        exit;
    }
    $itensSanitizados[] = [
        'rm_id'        => $rmId,
        'menu_completo' => !empty($item['menu_completo']),
    ];
}

// Mensagens de erro amigáveis para os códigos devolvidos por Database::criarPedido().
$mensagens = [
    'sem_itens'                         => 'Nenhum item selecionado.',
    'data_no_passado'                   => 'Não é possível criar pedidos para datas passadas.',
    'dia_feriado'                       => 'A cantina está encerrada por motivo de feriado.',
    'fora_de_prazo'                     => 'O prazo de compra para este dia já terminou.',
    'pedido_duplicado'                  => 'Já tens um pedido ativo para este dia.',
    'dia_encerrado'                     => 'A cantina está encerrada neste dia.',
    'extra_fora_de_horario'             => 'O prazo de compra de extras para hoje já terminou.',
    'extra_duplicado'                   => 'Já compraste este prato extra para este dia.',
    'prato_invalido'                    => 'Um dos pratos selecionados não existe.',
    'sem_preco_definido'                => 'Preço não configurado para um dos itens.',
    'menu_completo_invalido_para_extra' => 'O menu completo não pode ser aplicado a pratos extra.',
    'menu_completo_nao_configurado'     => 'Preço do menu completo não configurado.',
];

$resultado = Database::criarPedido((int) $utilizador['id'], $dataRefeicao, $itensSanitizados);

if (is_int($resultado)) {
    // Sucesso — devolve o ID do pedido criado para o frontend fazer o pagamento.
    echo json_encode(['status' => 'ok', 'pedido_id' => $resultado]);
} else {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => $mensagens[$resultado] ?? 'Não foi possível criar o pedido.',
    ]);
}