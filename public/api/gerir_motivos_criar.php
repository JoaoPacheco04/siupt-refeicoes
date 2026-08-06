<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('funcionario', true);
verificarCsrfToken(true);

$codigo = trim($_POST['codigo'] ?? '');
$label = trim($_POST['label'] ?? '');

if ($codigo === '' || $label === '' || !preg_match('/^[a-z0-9_]+$/', $codigo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos — código só pode ter letras minúsculas, números e underscore.']);
    exit;
}

$resultado = Database::criarMotivoReclamacao($codigo, $label);

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Já existe um motivo com esse código.']);