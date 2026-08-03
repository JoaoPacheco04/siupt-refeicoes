<?php
/**
 * Endpoint AJAX para listar as validações de um funcionário numa data específica.
 *
 * N2: Permite consultar validações de dias anteriores a partir da página validar.php.
 * Usa Database::listarValidacoesPorData() já existente.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('funcionario', true);
verificarCsrfToken(true);

$data = $_POST['data'] ?? '';

// Valida o formato da data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data inválido.']);
    exit;
}

[$ano, $mes, $dia] = explode('-', $data);
if (!checkdate((int) $mes, (int) $dia, (int) $ano)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data inválida.']);
    exit;
}

// Não permite consultar datas futuras
if ($data > date('Y-m-d')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não é possível consultar datas futuras.']);
    exit;
}

$validacoes = Database::listarValidacoesPorData((int) $utilizador['id'], $data);

echo json_encode([
    'status'     => 'ok',
    'data'       => $data,
    'validacoes' => $validacoes,
    'total'      => count($validacoes),
]);
