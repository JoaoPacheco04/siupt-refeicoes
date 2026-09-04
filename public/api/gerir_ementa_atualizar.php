<?php
/**
 * Endpoint AJAX — Atualizar nome de um prato da ementa.
 * POST: rm_id, nome
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$rmId = (int) ($_POST['rm_id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');

if ($rmId <= 0 || $nome === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

try {
    $ok = Database::atualizarNomePratoEmenta($rmId, $nome);
    echo json_encode($ok
        ? ['status' => 'ok']
        : ['status' => 'erro', 'mensagem' => 'Prato não encontrado ou sem alteração.']
    );
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar prato.']);
}
