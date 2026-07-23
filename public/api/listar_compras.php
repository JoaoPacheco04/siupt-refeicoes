<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('aluno');

$compras = Database::listarComprasDoAluno($utilizador['id']);

echo json_encode($compras, JSON_PRETTY_PRINT);