<?php

use PHPUnit\Framework\TestCase;

abstract class TesteBase extends TestCase
{
    protected static function pdo(): PDO
    {
        require_once __DIR__ . '/../config/config.test.php';
        require_once __DIR__ . '/../src/Infrastructure/Database.php';
        return Database::conexao();
    }

    protected function criarUtilizadorTeste(string $bicc, int $perfil = 1): int
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare("
            INSERT INTO users (U_BICC, U_PASS, U_NOME, U_EMAIL, U_PERFIL)
            OUTPUT INSERTED.U_ID
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bicc, password_hash('teste123', PASSWORD_BCRYPT), 'Teste ' . $bicc, $bicc . '@teste.pt', $perfil]);
        return (int) $stmt->fetchColumn();
    }

    protected function criarPratoTeste(string $nomeTipo, float $preco, ?string $data): array
    {
        $pdo = self::pdo();

        $stmt = $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) OUTPUT INSERTED.RTP_ID VALUES (?)");
        $stmt->execute([$nomeTipo]);
        $tipoId = (int) $stmt->fetchColumn();

        $pdo->prepare("INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, '2026-01-01')")
            ->execute([$tipoId, $preco]);

        $stmt = $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA) OUTPUT INSERTED.RM_ID VALUES (?, ?, ?)");
        $stmt->execute(['Prato Teste', $tipoId, $data]);
        $rmId = (int) $stmt->fetchColumn();

        return [$tipoId, $rmId];
    }

    protected function criarDataLimiteTeste(int $tipoId, string $hora, int $diasAntecedencia): void
    {
        self::pdo()->prepare("
            INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA)
            VALUES (?, ?, ?)
        ")->execute([$tipoId, $hora, $diasAntecedencia]);
    }

    protected function criarPrecoAdicionalTeste(int $tipoId, float $preco, string $dataInicio): void
    {
        self::pdo()->prepare("
            INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO)
            VALUES (?, ?, ?)
        ")->execute([$tipoId, $preco, $dataInicio]);
    }

   protected function limparUtilizador(int $utilizadorId): void
{
    $pdo = self::pdo();
    $pdo->prepare("DELETE FROM restaurante_transferencia_tentativa WHERE RTT_DE_U_ID = ? OR RTT_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$utilizadorId, $utilizadorId]);
    
    $pdo->prepare("DELETE FROM restaurante_transferencia WHERE RT_DE_U_ID = ? OR RT_PARA_U_ID = ?")->execute([$utilizadorId, $utilizadorId]);
    $pdo->prepare("
        DELETE FROM restaurante_validacao 
        WHERE RV_FUNCIONARIO_ID = ? 
           OR RV_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)
    ")->execute([$utilizadorId, $utilizadorId]);
    $pdo->prepare("
        DELETE FROM restaurante_avaliacao 
        WHERE RAV_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)
    ")->execute([$utilizadorId]);
    $pdo->prepare("DELETE FROM restaurante_pagamento WHERE RPG_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$utilizadorId]);
    $pdo->prepare("DELETE FROM restaurante_compra WHERE RC_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$utilizadorId]);
    $pdo->prepare("DELETE FROM restaurante_pedido WHERE RP_U_ID = ?")->execute([$utilizadorId]);
    $pdo->prepare("DELETE FROM users WHERE U_ID = ?")->execute([$utilizadorId]);
}

    protected function limparTipoEPrato(int $tipoId, int $rmId): void
    {
        $pdo = self::pdo();
        $pdo->prepare("DELETE FROM restaurante_compra WHERE RC_RM_ID = ?")->execute([$rmId]);
        $pdo->prepare("DELETE FROM restaurante_menu WHERE RM_ID = ?")->execute([$rmId]);
        $pdo->prepare("DELETE FROM restaurante_data_limite WHERE RDL_RTP_ID = ?")->execute([$tipoId]);
        $pdo->prepare("DELETE FROM restaurante_preco_tipo_refeicao WHERE RPTR_TP_ID = ?")->execute([$tipoId]);
        $pdo->prepare("DELETE FROM restaurante_tipo_refeicao WHERE RTP_ID = ?")->execute([$tipoId]);
    }
}