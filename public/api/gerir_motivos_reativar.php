<?php
/**
 * Endpoint: Reativar motivo de reclamação
 *
 * Restaura um motivo previamente desativado (RMR_ATIVO = 1),
 * tornando-o disponível novamente no dropdown de avaliação.
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

$ok = Database::reativarMotivoReclamacao($id);
echo json_encode($ok
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Motivo não encontrado']);
