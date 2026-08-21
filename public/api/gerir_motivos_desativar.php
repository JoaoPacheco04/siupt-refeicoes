<?php
/**
 * Endpoint: Desativar motivo de reclamação
 *
 * Faz soft-delete de um motivo (RMR_ATIVO = 0), preservando o histórico
 * de avaliações que já o referenciaram. O motivo deixa de aparecer no
 * dropdown de avaliação mas os registos antigos mantêm-se intactos.
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

$ok = Database::desativarMotivoReclamacao($id);
echo json_encode($ok
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Motivo não encontrado']);
