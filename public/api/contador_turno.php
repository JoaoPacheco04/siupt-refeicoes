<?php


require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');
$total = Database::contarValidacoesHoje($utilizador['id']);

echo "Validacoes hoje: " . $total;
