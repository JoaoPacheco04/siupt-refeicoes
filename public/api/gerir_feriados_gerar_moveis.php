<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$ano = (int) ($_POST['ano'] ?? 0);
if ($ano < date('Y') - 2 || $ano > date('Y') + 5) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Ano inválido.']);
    exit;
}

$resultado = Database::gerarTodosFeriadosDoAno($ano);
$inseridos  = $resultado['inseridos'];
$jaExistiam = $resultado['ja_existiam'];

if ($inseridos === 0) {
    $mensagem = "Já existem todos os {$jaExistiam} feriados para {$ano}. Nenhum foi adicionado.";
} elseif ($jaExistiam === 0) {
    $mensagem = "{$inseridos} feriado(s) gerado(s) com sucesso para {$ano}.";
} else {
    $mensagem = "{$inseridos} feriado(s) adicionado(s). {$jaExistiam} já existiam e foram mantidos.";
}

echo json_encode([
    'status'     => 'ok',
    'mensagem'   => $mensagem,
    'inseridos'  => $inseridos,
    'ja_existiam' => $jaExistiam,
]);