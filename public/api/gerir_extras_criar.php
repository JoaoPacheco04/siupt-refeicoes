<?php
/**
 * Endpoint AJAX para criação de um prato extra.
 *
 * Recebe o nome e o preço do prato extra,
 * criando o respetivo registo e devolvendo
 * uma resposta em formato JSON.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$nome = trim($_POST['nome'] ?? '');
$preco = $_POST['preco'] ?? '';

if (
    $nome === '' ||
    $preco === '' ||
    !is_numeric($preco) ||
    (float) $preco < 0
) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $rmId = Database::criarPratoExtra($nome, (float) $preco);

    echo json_encode([
        'status' => 'ok',
        'rm_id' => $rmId
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao criar extra'
    ]);
}
