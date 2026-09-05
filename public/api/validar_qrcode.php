<?php
/**
 * Endpoint AJAX para validação de uma refeição através de QR Code.
 *
 * Recebe o QR Code apresentado pelo aluno,
 * valida o pedido e devolve uma resposta
 * em formato JSON.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

exigirPost();

$utilizador = exigirLogin('atendente', true);
verificarCsrfToken(true);

$qrcode = trim($_POST['qrcode'] ?? '');

if ($qrcode === '') {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'QR code não fornecido'
    ]);
    exit;
}

$resultado = Database::validarPorQrCode($qrcode, (int) $utilizador['id']);

/**
 * Enriquece a resposta com o detalhe do pedido
 * e o número de validações efetuadas pelo funcionário.
 */
if (isset($resultado['pedido_id'])) {
    $resultado['linhas'] = Database::listarLinhasDoPedido((int) $resultado['pedido_id']);
}

$resultado['validacoes_hoje']       = Database::contarValidacoesHoje((int) $utilizador['id']);
$resultado['refeicoes_por_levantar'] = Database::contarRefeicoesAtivasHoje();

echo json_encode($resultado);
