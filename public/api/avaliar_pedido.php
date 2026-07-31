<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$pedidoId = (int) ($_POST['pedido_id'] ?? 0);
$estrelas = (int) ($_POST['estrelas'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '') ?: null;

if ($pedidoId <= 0 || $estrelas < 1 || $estrelas > 5) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$mensagens = [
    'nao_autorizado' => 'Só podes avaliar refeições já levantadas por ti.',
    'ja_avaliado' => 'Já avaliaste este pedido.',
];

$resultado = Database::avaliarPedido($pedidoId, (int) $utilizador['id'], $estrelas, $motivo);

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido']);