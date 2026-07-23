<?php
require_once __DIR__ . '/../../src/Database.php';

$numero_aluno = $_GET['numero_aluno'] ?? '';
$pin = $_GET['pin'] ?? '';
$funcionario_id = 1;

if ($numero_aluno === '' || $pin === '') {
    echo "Faltam parametros: numero_aluno e pin";
    exit;
}

$resultado = Database::validarPorNumeroAlunoPin($numero_aluno, $pin, $funcionario_id);
echo "Resultado: " . $resultado['status'];
if (!empty($resultado['nome'])) {
    echo " | Nome: " . $resultado['nome'];
}