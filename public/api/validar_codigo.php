<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('funcionario');

$numero_aluno = $_GET['numero_aluno'] ?? '';
$pin = $_GET['pin'] ?? '';

if ($numero_aluno === '' || $pin === '') {
    echo "Faltam parametros: numero_aluno e pin";
    exit;
}

$resultado = Database::validarPorNumeroAlunoPin($numero_aluno, $pin, $utilizador['id']);
echo "Resultado: " . $resultado['status'];
if (!empty($resultado['nome'])) {
    echo " | Nome: " . $resultado['nome'];
}
if (!empty($resultado['pedido_especial'])) {
    echo " | Pedido: " . $resultado['pedido_especial'];
}