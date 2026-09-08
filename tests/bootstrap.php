<?php

// Força os testes a usarem uma base de dados isolada, nunca a de desenvolvimento.
putenv('DB_NAME=siupt_refeicoes_test');
$_ENV['DB_NAME'] = 'siupt_refeicoes_test';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

// Garante migrações e tabela de configuração na base de dados de teste
Database::garantirColunaDataAbertura();
Database::garantirTabelaConfiguracao();

// Garante os tipos de refeição base e preços de referência na base de dados de teste
$tiposBase = [
    'Carne'         => 3.50,
    'Peixe'         => 3.50,
    'Vegetariano'   => 3.00,
    'Menu Completo' => 5.00,
    'Sopa'          => 0.80,
    'Sobremesa'     => 1.00,
    'Bebida'        => 0.60,
    'Prato extra'   => 4.00,
];

foreach ($tiposBase as $nome => $preco) {
    $stmt = Database::conexao()->prepare("SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = ?");
    $stmt->execute([$nome]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $pratoDia = in_array($nome, ['Carne', 'Peixe', 'Vegetariano'], true) ? 1 : 0;
        $ins = Database::conexao()->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME, RM_PRATO_DIA) VALUES (?, ?)");
        $ins->execute([$nome, $pratoDia]);
        $id = (int) Database::conexao()->lastInsertId();
    }
    $stmtP = Database::conexao()->prepare("SELECT 1 FROM restaurante_preco_tipo_refeicao WHERE RPTR_TP_ID = ?");
    $stmtP->execute([$id]);
    if (!$stmtP->fetchColumn()) {
        Database::conexao()->prepare("INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, '2026-01-01')")->execute([$id, $preco]);
    }
}