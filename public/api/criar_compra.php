<?php
require_once __DIR__ . '/../../src/Database.php';

$comprador_id = (int) ($_GET['comprador_id'] ?? 0);
$refeicao_id = (int) ($_GET['refeicao_id'] ?? 0);

if ($comprador_id === 0 || $refeicao_id === 0) {
    echo "Faltam parametros: comprador_id e refeicao_id";
    exit;
}

$resultado = Database::criarCompra($comprador_id, $refeicao_id);

if ($resultado === 'refeicao_invalida') {
    echo "Erro: refeicao nao encontrada";
} else {
    echo "Compra criada! ID: " . $resultado;
}