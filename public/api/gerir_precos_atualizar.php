<?php
/**
 * Endpoint AJAX — Atualizar preço de uma refeição / item.
 *
 * Permite ao admin_cantina alterar o preço em vigor para pratos principais
 * (Carne, Peixe, Vegetariano), Menu Completo, complementos (Sopa, Sobremesa, Bebida)
 * e pratos extra base.
 *
 * POST:
 *  - tipo_id (int)
 *  - preco (float / numeric >= 0)
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$tipoId = isset($_POST['tipo_id']) ? (int) $_POST['tipo_id'] : 0;
$precoRaw = trim($_POST['preco'] ?? '');

// Suporta separador decimal vírgula ou ponto
$precoSanitizado = str_replace(',', '.', $precoRaw);

if ($tipoId <= 0 || $precoRaw === '' || !is_numeric($precoSanitizado)) {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Indica um valor de preço numérico válido.',
    ]);
    exit;
}

$novoPreco = (float) $precoSanitizado;

if ($novoPreco < 0 || $novoPreco > 999.99) {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'O preço deve ser positivo e inferior a 1.000,00 €.',
    ]);
    exit;
}

$res = Database::atualizarPrecoRefeicao($tipoId, $novoPreco);

if ($res === 'tipo_nao_encontrado') {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Tipo de refeição não encontrado.',
    ]);
    exit;
}

if ($res !== true) {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Erro ao atualizar o preço.',
    ]);
    exit;
}

$precoFormatado = number_format($novoPreco, 2, ',', '') . ' €';
$dataHoje = date('d/m/Y');

echo json_encode([
    'status'          => 'ok',
    'mensagem'        => 'Preço atualizado com sucesso.',
    'tipo_id'         => $tipoId,
    'preco'           => $novoPreco,
    'preco_formatado' => $precoFormatado,
    'data_vigor'      => $dataHoje,
]);
