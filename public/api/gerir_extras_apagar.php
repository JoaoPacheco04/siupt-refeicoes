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
    'nao_encontrado' => 'Extra não encontrado',
    'erro_bd' => 'Não foi possível processar — pode haver dados relacionados a impedir.',
];

$resultado = Database::apagarExtra($rmId);

if ($resultado === 'ok') {
    echo json_encode(['status' => 'ok']);
} elseif ($resultado === 'desativado') {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Já foi comprado por alunos, por isso foi apenas descontinuado (deixa de aparecer para compra, mas o histórico mantém-se).',
    ]);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido']);
}