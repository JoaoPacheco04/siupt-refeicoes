<?php
/**
 * Endpoint: Apagar permanentemente um motivo de reclamação
 *
 * Só é permitido se o motivo já estiver desativado (RMR_ATIVO = 0).
 * As avaliações antigas não são afetadas (RAV_MOTIVO é texto livre, não FK).
 * Requer papel admin_cantina.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$resultado = Database::apagarMotivoReclamacao($id);

match ($resultado) {
    'ok'          => print json_encode(['status' => 'ok']),
    'ainda_ativo' => print json_encode(['status' => 'erro', 'mensagem' => 'Não é possível apagar um motivo ativo. Desative-o primeiro.']),
    default       => print json_encode(['status' => 'erro', 'mensagem' => 'Motivo não encontrado.']),
};
