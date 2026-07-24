<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('aluno');
$uid = $_POST['uid'] ?? '';

if ($uid === '') {
    echo "Falta o UID do cartao";
    exit;
}

$resultado = Database::vincularCartao($utilizador['id'], $uid);
echo "Resultado: " . $resultado['status'];