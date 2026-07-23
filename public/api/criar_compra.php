<?php
require_once __DIR__ . '/../../src/Database.php';

$compra_id = Database::criarCompra(
    comprador_id: 1,
    refeicao_id: 1,
    preco: 5.00,
    data_refeicao: date('Y-m-d') 
);

echo "Compra criada! ID: " . $compra_id;