<?php
// public/api/vincular_cartao.php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

$utilizador = exigirLogin('aluno');
$uid = $_GET['uid'] ?? '';

if ($uid === '') {
    echo "Falta o UID do cartao";
    exit;
}

$resultado = Database::vincularCartao($utilizador['id'], $uid);
echo "Resultado: " . $resultado['status'];