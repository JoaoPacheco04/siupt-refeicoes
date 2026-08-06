<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');
$utilizador = exigirLogin('funcionario', true);
verificarCsrfToken(true);

$ano = (int) ($_POST['ano'] ?? 0);
if ($ano < date('Y') - 2 || $ano > date('Y') + 5) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Ano inválido.']);
    exit;
}

Database::gerarTodosFeriadosDoAno($ano);

echo json_encode(['status' => 'ok', 'mensagem' => "Feriados de {$ano} foram gerados ou atualizados com sucesso."]);