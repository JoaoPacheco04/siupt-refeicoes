<?php
/**
 * Endpoint: Atribuir papel de cantina a um utilizador
 *
 * Concede o papel 'atendente' ou 'admin_cantina' a um utilizador,
 * permitindo que o administrador da cantina gerir quem tem acesso
 * à validação e à gestão sem precisar de aceder à base de dados.
 * Requer papel admin_cantina.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$userId = (int) ($_POST['user_id'] ?? 0);
$papel  = trim($_POST['papel'] ?? '');

if ($userId <= 0 || !in_array($papel, ['atendente', 'admin_cantina'], true)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

$resultado = Database::atribuirPapel($userId, $papel);

$labelPapel = $papel === 'admin_cantina' ? 'Administrador de cantina' : 'Atendente';

switch ($resultado) {
    case 'ok':
        echo json_encode(['status' => 'ok', 'mensagem' => "Papel \"{$labelPapel}\" atribuído com sucesso."]);
        break;
    case 'ja_existe':
        echo json_encode(['status' => 'aviso', 'mensagem' => "Este utilizador já tem o papel \"{$labelPapel}\"."]);
        break;
    case 'utilizador_nao_encontrado':
        echo json_encode(['status' => 'erro', 'mensagem' => 'Utilizador não encontrado.']);
        break;
    case 'papel_invalido':
    default:
        echo json_encode(['status' => 'erro', 'mensagem' => 'Papel inválido.']);
}
