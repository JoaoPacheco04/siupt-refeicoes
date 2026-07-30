<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$pedidoId = (int) ($_POST['pedido_id'] ?? 0);
$biccDestino = trim($_POST['bicc_destino'] ?? '');

if ($pedidoId <= 0 || $biccDestino === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$mensagens = [
    'nao_transferivel' => 'Este pedido já não pode ser transferido (só pedidos ativos).',
    'destinatario_nao_encontrado' => 'Não encontrámos nenhum utilizador com esse número.',
    'mesmo_utilizador' => 'Não podes transferir para ti próprio.',
    'erro_bd' => 'Erro ao processar a transferência.',
];

$resultado = Database::transferirPedido($pedidoId, (int) $utilizador['id'], $biccDestino);

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido']);