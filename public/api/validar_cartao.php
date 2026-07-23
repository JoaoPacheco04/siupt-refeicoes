<?php
require_once __DIR__ . '/../../src/Database.php';

$numero_lido = $_GET['numero'] ?? '';
$funcionario_id = 1;

if ($numero_lido === '') {
    echo "Falta o parametro numero";
    exit;
}

$resultado = Database::validarPorCartao($numero_lido, $funcionario_id);
echo "Resultado: " . $resultado['status'];
if (!empty($resultado['nome'])) {
    echo " | Nome: " . $resultado['nome'];
}