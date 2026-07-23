<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('funcionario');

$numero_lido = $_GET['numero'] ?? '';

if ($numero_lido === '') {
    echo "Falta o parametro numero";
    exit;
}

$resultado = Database::validarPorCartao($numero_lido, $utilizador['id']);
echo "Resultado: " . $resultado['status'];
if (!empty($resultado['nome'])) {
    echo " | Nome: " . $resultado['nome'];
}
if (!empty($resultado['pedido_especial'])) {
    echo " | Pedido: " . $resultado['pedido_especial'];
}