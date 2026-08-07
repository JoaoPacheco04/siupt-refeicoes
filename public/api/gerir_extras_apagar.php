<?php
/**
 * Endpoint AJAX para remoÃ§Ã£o de um prato extra.
 *
 * Remove o prato extra caso nÃ£o existam compras associadas.
 * Caso contrÃ¡rio, o prato Ã© apenas descontinuado,
 * preservando o histÃ³rico de pedidos.
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

/**
 * Mapeia os cÃ³digos devolvidos pela camada de negÃ³cio
 * para mensagens apresentadas ao utilizador.
 */
$mensagens = [
    'nao_encontrado' => 'Extra nÃ£o encontrado',
    'erro_bd' => 'NÃ£o foi possÃ­vel processar â€” pode haver dados relacionados a impedir.',
];

$resultado = Database::apagarExtra($rmId);

if ($resultado === 'ok') {
    echo json_encode(['status' => 'ok']);
} elseif ($resultado === 'desativado') {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'JÃ¡ foi comprado por alunos, por isso foi apenas descontinuado (deixa de aparecer para compra, mas o histÃ³rico mantÃ©m-se).',
    ]);
} else {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido'
    ]);
}
