<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('aluno');

$ids = Database::refeicoesJaCompradas($utilizador['id']);

echo json_encode($ids);