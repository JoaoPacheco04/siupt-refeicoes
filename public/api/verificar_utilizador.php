<?php
/**
 * Endpoint para verificar um utilizador pelo número (BICC) antes da transferência.
 * Devolve apenas o nome público — sem password, perfil, ou dados sensíveis.
 * Usado no passo de confirmação do modal de transferência.
 *
 * Rate limiting: máx 10 tentativas por IP em 15 minutos, mesmo padrão do login.php,
 * para impedir enumeração de BICCs válidos por força bruta.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

exigirPost();

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$bicc = trim($_POST['bicc'] ?? '');

if ($bicc === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Número em falta.']);
    exit;
}

$destino = Database::obterUtilizadorPorBICC($bicc);

if (!$destino) {
    echo json_encode(['status' => 'nao_encontrado', 'mensagem' => 'Nenhum utilizador encontrado com esse número.']);
    exit;
}

if ((int) $destino['U_ID'] === (int) $utilizador['id']) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não podes transferir para ti próprio.']);
    exit;
}

// Devolve apenas o nome — sem expor outros campos
echo json_encode([
    'status' => 'ok',
    'nome'   => $destino['U_NOME'],
]);