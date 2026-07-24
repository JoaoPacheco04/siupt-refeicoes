<?php


require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('aluno');
$ids = Database::refeicoesJaCompradas($utilizador['id']);

echo json_encode($ids);
