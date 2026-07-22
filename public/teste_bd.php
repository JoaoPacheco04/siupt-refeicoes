<?php

require_once __DIR__ . '/../src/Database.php';

try {
    $pdo = Database::conexao();
    echo "Ligacao a base de dados OK!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}