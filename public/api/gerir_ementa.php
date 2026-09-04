<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$dados = json_decode(file_get_contents('php://input'), true);
$modoAbertura = ($dados['modo_abertura'] ?? 'padrao') === 'imediato' ? 'imediato' : 'padrao';

$atualizados = Database::publicarSemanaEmenta($dados['inicio'], $dados['fim'], $modoAbertura);

echo json_encode(['status' => 'ok', 'pratos_publicados' => $atualizados]);