<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('aluno');

$refeicao_id = (int) ($_GET['refeicao_id'] ?? 0);
$pedido_especial = $_GET['pedido_especial'] ?? null;

$opcoes_validas = ['vegetariano', 'dieta'];
if ($pedido_especial !== null && !in_array($pedido_especial, $opcoes_validas, true)) {
    echo "Pedido especial invalido. Opcoes: " . implode(', ', $opcoes_validas);
    exit;
}

if ($refeicao_id === 0) {
    echo "Falta o parametro refeicao_id";
    exit;
}

$resultado = Database::criarCompra($utilizador['id'], $refeicao_id, $pedido_especial);

if ($resultado === 'refeicao_invalida') {
    echo "Erro: refeicao nao encontrada";
} elseif ($resultado === 'ja_comprado') {
    echo "Ja tens uma senha para este dia";
} elseif ($resultado === 'fora_de_prazo') {
    echo "Fora do prazo de compra (corte as 10h00 do dia anterior)";
} else {
    echo "Compra criada! ID: " . $resultado;
}