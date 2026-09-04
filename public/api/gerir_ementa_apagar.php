<?php
/**
 * Endpoint AJAX — Apagar prato da ementa.
 * POST: rm_id
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$rmId = (int) ($_POST['rm_id'] ?? 0);

if ($rmId <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ID inválido.']);
    exit;
}

try {
    $resultado = Database::apagarPratoEmenta($rmId);

    $mensagens = [
        'ok'            => null,
        'nao_encontrado'=> 'Prato não encontrado.',
        'tem_pedidos'   => 'Não é possível apagar: já existem reservas para este prato.',
    ];

    if ($resultado === 'ok') {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode([
            'status'   => $resultado === 'tem_pedidos' ? 'tem_pedidos' : 'erro',
            'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido.',
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao apagar prato.']);
}
