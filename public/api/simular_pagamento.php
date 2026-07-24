<?php
require_once __DIR__ . '/../../src/Services/PagamentoService.php';

$compra_id = (int) ($_GET['compra_id'] ?? 0);
$sucesso = ($_GET['resultado'] ?? '') === 'sucesso';

if ($compra_id === 0) {
    echo "Falta o parametro compra_id";
    exit;
}

$resultado = PagamentoService::processar($compra_id, $sucesso);
echo "Resultado: " . $resultado['status'];
