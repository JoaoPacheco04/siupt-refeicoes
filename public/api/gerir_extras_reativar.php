<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('funcionario', true);
verificarCsrfToken(true);

$rmId = (int) ($_POST['rm_id'] ?? 0);

if ($rmId <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$ok = Database::reativarExtra($rmId);
echo json_encode($ok
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Extra não encontrado']);