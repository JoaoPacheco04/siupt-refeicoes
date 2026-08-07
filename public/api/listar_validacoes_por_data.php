<?php
/**
 * Endpoint AJAX para listar as validaÃ§Ãµes de um funcionÃ¡rio numa data especÃ­fica.
 *
 * N2: Permite consultar validaÃ§Ãµes de dias anteriores a partir da pÃ¡gina validar.php.
 * Usa Database::listarValidacoesPorData() jÃ¡ existente.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('atendente', true);
verificarCsrfToken(true);

$data = $_POST['data'] ?? '';

// Valida o formato da data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de data invÃ¡lido.']);
    exit;
}

[$ano, $mes, $dia] = explode('-', $data);
if (!checkdate((int) $mes, (int) $dia, (int) $ano)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Data invÃ¡lida.']);
    exit;
}

// NÃ£o permite consultar datas futuras
if ($data > date('Y-m-d')) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'NÃ£o Ã© possÃ­vel consultar datas futuras.']);
    exit;
}

$vejoTudo = temPapelSessao('admin_cantina');
$validacoes = $vejoTudo
    ? Database::listarValidacoesPorDataTodos($data)
    : Database::listarValidacoesPorData((int) $utilizador['id'], $data);

echo json_encode([
    'status'     => 'ok',
    'data'       => $data,
    'validacoes' => $validacoes,
    'total'      => count($validacoes),
]);

