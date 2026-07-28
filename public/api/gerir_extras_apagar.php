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

$mensagens = [
    'ja_comprado' => 'Este extra já foi comprado por alunos e não pode ser eliminado. Podes renomeá-lo em vez disso.',
    'nao_encontrado' => 'Extra não encontrado',
    'erro_bd' => 'Não foi possível eliminar — pode haver dados relacionados a impedir.',
];

$resultado = Database::apagarExtra($rmId);

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido']);