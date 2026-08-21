<?php
/**
 * Endpoint: Revogar papel de cantina de um utilizador
 *
 * Remove o papel 'atendente' ou 'admin_cantina' de um utilizador.
 * Proteção especial: impede a remoção do último admin_cantina do sistema,
 * garantindo que nunca fica sem administrador.
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

$userId = (int) ($_POST['user_id'] ?? 0);
$papel  = trim($_POST['papel'] ?? '');

if ($userId <= 0 || !in_array($papel, ['atendente', 'admin_cantina'], true)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

$resultado = Database::revogarPapel($userId, $papel);

$labelPapel = $papel === 'admin_cantina' ? 'Administrador de cantina' : 'Atendente';

switch ($resultado) {
    case 'ok':
        echo json_encode(['status' => 'ok', 'mensagem' => "Papel \"{$labelPapel}\" removido com sucesso."]);
        break;
    case 'ultimo_admin':
        echo json_encode([
            'status'   => 'erro',
            'mensagem' => 'Não é possível remover o último administrador de cantina do sistema. Atribui primeiro esse papel a outro utilizador.',
        ]);
        break;
    case 'nao_encontrado':
        echo json_encode(['status' => 'erro', 'mensagem' => 'Este utilizador não tem esse papel.']);
        break;
    case 'papel_invalido':
    default:
        echo json_encode(['status' => 'erro', 'mensagem' => 'Papel inválido.']);
}
