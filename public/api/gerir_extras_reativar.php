<?php
/**
 * Endpoint AJAX para reativaÃ§Ã£o de um prato extra.
 *
 * Recebe o identificador de um prato extra,
 * reativando-o e devolvendo uma resposta
 * em formato JSON.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$rmId = (int) ($_POST['rm_id'] ?? 0);

if ($rmId <= 0) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados invÃ¡lidos'
    ]);
    exit;
}

$ok = Database::reativarExtra($rmId);

echo json_encode(
    $ok
        ? ['status' => 'ok']
        : [
            'status' => 'erro',
            'mensagem' => 'Extra nÃ£o encontrado'
        ]
);
