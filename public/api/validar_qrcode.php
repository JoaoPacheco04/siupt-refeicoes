<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('funcionario', true);
verificarCsrfToken(true);
$qrcode = trim($_POST['qrcode'] ?? '');

if ($qrcode === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'QR code não fornecido']);
    exit;
}

$resultado = Database::validarPorQrCode($qrcode, (int) $utilizador['id']);

// Se válido, incluir as linhas do pedido na resposta
if ($resultado['status'] === 'valido' && isset($resultado['pedido_id'])) {
    $resultado['linhas'] = Database::listarLinhasDoPedido((int) $resultado['pedido_id']);
}

// Incluir contador de validações de hoje
$resultado['validacoes_hoje'] = Database::contarValidacoesHoje((int) $utilizador['id']);

echo json_encode($resultado);
