<?php
/**
 * Endpoint AJAX para atualizaÃ§Ã£o do nome de um prato extra.
 *
 * Recebe o identificador e o novo nome do prato,
 * atualizando a informaÃ§Ã£o e devolvendo uma resposta
 * em formato JSON.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$rmId = (int) ($_POST['rm_id'] ?? 0);
$novoNome = trim($_POST['nome'] ?? '');

if ($rmId <= 0 || $novoNome === '') {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados invÃ¡lidos'
    ]);
    exit;
}

$ok = Database::atualizarNomeExtra($rmId, $novoNome);

echo json_encode(
    $ok
        ? ['status' => 'ok']
        : [
            'status' => 'erro',
            'mensagem' => 'Extra nÃ£o encontrado ou nÃ£o Ã© um prato extra'
        ]
);
