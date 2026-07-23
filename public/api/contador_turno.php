<?php
require_once __DIR__ . '/../../src/Database.php';

$funcionario_id = (int) ($_GET['funcionario_id'] ?? 1);
$total = Database::contarValidacoesHoje($funcionario_id);

echo "Validacoes hoje: " . $total;