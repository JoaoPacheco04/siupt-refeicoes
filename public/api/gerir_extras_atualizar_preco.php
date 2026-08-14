<?php
/**
 * Endpoint: Atualizar preço de extra
 *
 * Endpoint AJAX para atualização do preço de um tipo de refeição.
 * Recebe o identificador do tipo de refeição e o novo preço,
 * atualizando o respetivo valor e devolvendo uma resposta
 * em formato JSON.
 * Requer papel admin_cantina.
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$tipoId = (int) ($_POST['tipo_id'] ?? 0);
$novoPreco = $_POST['preco'] ?? '';

if (
    $tipoId <= 0 ||
    $novoPreco === '' ||
    !is_numeric($novoPreco) ||
    (float) $novoPreco < 0
) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos'
    ]);
    exit;
}

$tipo = Database::obterNomeTipoRefeicao($tipoId);
if ($tipo === null || !str_starts_with($tipo, 'Extra: ')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Este endpoint só atualiza preços de extras.']);
    exit;
}
Database::atualizarPrecoTipo($tipoId, (float) $novoPreco);

echo json_encode([
    'status' => 'ok'
]);
