<?php
/**
 * Endpoint AJAX para atualizaÃ§Ã£o do preÃ§o de um tipo de refeiÃ§Ã£o.
 *
 * Recebe o identificador do tipo de refeiÃ§Ã£o e o novo preÃ§o,
 * atualizando o respetivo valor e devolvendo uma resposta
 * em formato JSON.
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
        'mensagem' => 'Dados invÃ¡lidos'
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
