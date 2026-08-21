<?php
/**
 * Endpoint AJAX para cancelamento de um pedido de refeição.
 *
 * Recebe o identificador de um pedido e, caso este pertença
 * ao utilizador autenticado e ainda esteja pendente,
 * efetua o respetivo cancelamento.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

exigirPost();

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$pedidoId = (int) ($_POST['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos'
    ]);
    exit;
}

/**
 * Cancela o pedido apenas se este pertencer ao utilizador
 * autenticado e ainda se encontrar no estado pendente.
 */
$ok = Database::cancelarPedidoPendente(
    $pedidoId,
    (int) $utilizador['id']
);

echo json_encode(
    $ok
        ? ['status' => 'ok']
        : [
            'status' => 'erro',
            'mensagem' => 'Não foi possível cancelar — o pedido já pode ter sido pago ou não existe.'
        ]
);