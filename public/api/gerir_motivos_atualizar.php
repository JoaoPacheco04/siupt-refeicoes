<?php
/**
 * Endpoint: Atualizar label de motivo de reclamação
 *
 * Edita o texto visível (RMR_LABEL) de um motivo de reclamação existente.
 * O código interno (RMR_CODIGO) nunca é alterado — é a chave usada no
 * histórico de avaliações e alterá-lo quebraria registos anteriores.
 * Requer papel admin_cantina.
 *
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$id    = (int) ($_POST['id']    ?? 0);
$label = trim($_POST['label']   ?? '');

if ($id <= 0 || $label === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$resultado = Database::atualizarLabelMotivoReclamacao($id, $label);

if ($resultado === 'label_duplicado') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Já existe outro motivo com esse texto.']);
} elseif ($resultado) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Motivo não encontrado']);
}
