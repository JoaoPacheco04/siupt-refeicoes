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

$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Rate limiting: máx 10 tentativas por IP em 15 minutos ────────────────
$ipCliente     = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$chaveRateKey  = 'verificar_utilizador_tentativas_' . md5($ipCliente);
$janelaTempo   = 900;  // 15 minutos em segundos
$maxTentativas = 10;

if (!isset($_SESSION[$chaveRateKey])) {
    $_SESSION[$chaveRateKey] = ['total' => 0, 'desde' => time()];
}

// Reinicia a janela se já passou o tempo limite
if (time() - $_SESSION[$chaveRateKey]['desde'] > $janelaTempo) {
    $_SESSION[$chaveRateKey] = ['total' => 0, 'desde' => time()];
}

if ($_SESSION[$chaveRateKey]['total'] >= $maxTentativas) {
    http_response_code(429);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Demasiadas tentativas. Aguarda 15 minutos e tenta novamente.',
    ]);
    exit;
}

$bicc = trim($_POST['bicc'] ?? '');

if ($bicc === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Número em falta.']);
    exit;
}

// Regista a tentativa ANTES de consultar a BD — mesmo que o número exista,
// conta para o limite (impede também enumeração bem-sucedida repetida)
$_SESSION[$chaveRateKey]['total']++;

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