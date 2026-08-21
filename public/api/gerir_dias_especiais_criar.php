<?php
/**
 * Endpoint: Criar dia especial
 *
 * Regista um dia especial (encerramento por razão não feriado — férias,
 * greve, evento interno), com opção de permitir ou bloquear a venda de
 * pratos extra nesse dia. Rejeita datas duplicadas e datas já marcadas
 * como feriado. Requer papel admin_cantina.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$data          = trim($_POST['data']          ?? '');
$motivo        = trim($_POST['motivo']        ?? '');
$permiteExtras = !empty($_POST['permite_extras']);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida.']);
    exit;
}

$resultado = Database::criarDiaEspecial($data, $motivo, $permiteExtras);

$mensagens = [
    'data_duplicada' => 'Já existe um dia especial nessa data.',
    'eh_feriado'     => 'Essa data já é um feriado e não pode ser marcada como dia especial.',
];

if ($resultado === 'ok') {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido.']);
}