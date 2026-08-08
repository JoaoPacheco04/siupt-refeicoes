<?php
/**
 * Endpoint: Atualizar label de motivo de reclamação
 *
 * Edita o texto visível (RMR_LABEL) de um motivo de reclamação existente.
 * O código interno (RMR_CODIGO) nunca é alterado — é a chave usada no
 * histórico de avaliações e alterá-lo quebraria registos anteriores.
 * Requer papel admin_cantina.
 *
 * Parâmetros POST:
 *  - id     int     Identificador do motivo
 *  - label  string  Novo texto visível
 *
 * Resposta JSON:
 *  { "status": "ok" }
 *  { "status": "erro", "mensagem": string }
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$id    = (int) ($_POST['id']    ?? 0);
$label = trim($_POST['label']   ?? '');

if ($id <= 0 || $label === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$ok = Database::atualizarLabelMotivoReclamacao($id, $label);
echo json_encode($ok
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Motivo não encontrado']);
