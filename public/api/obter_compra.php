<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('aluno');
$compra_id = (int) ($_GET['id'] ?? 0);

$compra = Database::obterCompra($compra_id);

if (!$compra || $compra['comprador_id'] != $utilizador['id']) {
    http_response_code(404);
    echo "Compra nao encontrada";
    exit;
}

echo json_encode($compra);