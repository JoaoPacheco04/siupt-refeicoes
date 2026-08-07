<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$data          = trim($_POST['data']          ?? '');
$motivo        = trim($_POST['motivo']        ?? '');
$permiteExtras = !empty($_POST['permite_extras']);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data invalida.']);
    exit;
}

$resultado = Database::criarDiaEspecial($data, $motivo, $permiteExtras);

$mensagens = [
    'data_duplicada' => 'Ja existe um dia especial nessa data.',
    'eh_feriado'     => 'Essa data ja e um feriado e nao pode ser dia especial.',
];

if ($resultado === 'ok') {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido.']);
}