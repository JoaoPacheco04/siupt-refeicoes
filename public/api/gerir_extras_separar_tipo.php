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
    'extra_nao_encontrado' => 'Extra não encontrado',
    'sem_preco_definido' => 'Este extra não tem preço definido atualmente',
];

try {
    $resultado = Database::separarExtraParaTipoProprio($rmId);
    if (is_string($resultado) && isset($mensagens[$resultado])) {
        echo json_encode(['status' => 'erro', 'mensagem' => $mensagens[$resultado]]);
    } else {
        echo json_encode(['status' => 'ok', 'novo_tipo_id' => $resultado]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao separar preço']);
}