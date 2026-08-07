<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$data = trim($_POST['data'] ?? '');
$nome = trim($_POST['nome'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $nome === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados invÃ¡lidos']);
    exit;
}

$resultado = Database::criarFeriado($data, $nome);
echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'JÃ¡ existe um feriado nessa data.']);
