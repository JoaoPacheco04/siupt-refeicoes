<?php

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('aluno');
$compras = Database::listarComprasDoAluno($utilizador['id']);

echo json_encode($compras, JSON_PRETTY_PRINT);
