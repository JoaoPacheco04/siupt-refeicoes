<?php

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    private array $utilizadoresCriados = [];
    private array $tiposCriados = [];

    protected function tearDown(): void
    {
        $pdo = Database::conexao();

        // Se o código testado deixou uma transação aberta por causa de uma exceção, fecha-a.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        foreach ($this->utilizadoresCriados as $userId) {
            $this->apagarDadosDoUtilizador($userId, $pdo);
        }
        foreach ($this->tiposCriados as $tipoId) {
            $pdo->prepare("DELETE FROM restaurante_data_limite WHERE RDL_RTP_ID = ?")->execute([$tipoId]);
            $pdo->prepare("DELETE FROM restaurante_menu WHERE RM_TP_ID = ?")->execute([$tipoId]);
            $pdo->prepare("DELETE FROM restaurante_preco_tipo_refeicao WHERE RPTR_TP_ID = ?")->execute([$tipoId]);
            $pdo->prepare("DELETE FROM restaurante_tipo_refeicao WHERE RTP_ID = ?")->execute([$tipoId]);
        }

        parent::tearDown();
    }

    private function apagarDadosDoUtilizador(int $userId, PDO $pdo): void
    {
        $pdo->prepare("DELETE FROM restaurante_validacao WHERE RV_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$userId]);
        $pdo->prepare("DELETE FROM restaurante_pagamento WHERE RPG_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$userId]);
        $pdo->prepare("DELETE FROM restaurante_avaliacao WHERE RAV_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$userId]);
        $pdo->prepare("DELETE FROM restaurante_compra WHERE RC_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$userId]);
        $pdo->prepare("DELETE FROM restaurante_transferencia_tentativa WHERE RTT_DE_U_ID = ? OR RTT_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?)")->execute([$userId, $userId]);
        $pdo->prepare("DELETE FROM restaurante_transferencia WHERE RT_RP_ID IN (SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = ?) OR RT_DE_U_ID = ? OR RT_PARA_U_ID = ?")->execute([$userId, $userId, $userId]);
        $pdo->prepare("DELETE FROM restaurante_papel_utilizador WHERE RPU_U_ID = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM restaurante_pedido WHERE RP_U_ID = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM users WHERE U_ID = ?")->execute([$userId]);
    }

    protected function criarUtilizadorTeste(string $bicc, string $nome = 'Teste', int $perfil = 1): int
    {
        $pdo = Database::conexao();
        $pdo->prepare("INSERT INTO users (U_BICC, U_PASS, U_NOME, U_EMAIL, U_PERFIL) VALUES (?, ?, ?, ?, ?)")
            ->execute([$bicc, password_hash('teste123', PASSWORD_DEFAULT), $nome, "$bicc@teste.pt", $perfil]);
        $id = (int) $pdo->lastInsertId();
        $this->utilizadoresCriados[] = $id;
        return $id;
    }

    protected function criarTipoComPreco(string $nome, float $preco, string $dataInicio = '2020-01-01'): int
    {
        $pdo = Database::conexao();
        $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) VALUES (?)")->execute([$nome]);
        $tipoId = (int) $pdo->lastInsertId();
        $this->tiposCriados[] = $tipoId;
        $pdo->prepare("INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, ?)")
            ->execute([$tipoId, $preco, $dataInicio]);
        return $tipoId;
    }

    protected function criarPratoEmenta(string $nome, int $tipoId, string $data): int
    {
        $pdo = Database::conexao();
        $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA, RM_ATIVO) VALUES (?, ?, ?, 1)")
            ->execute([$nome, $tipoId, $data]);
        return (int) $pdo->lastInsertId();
    }

    /** Devolve uma data futura que não é feriado nem fim de semana. */
    protected function proximaDataUtilSemFeriado(int $diasMinimo = 5): string
    {
        $pdo = Database::conexao();
        $data = new DateTime("+{$diasMinimo} days");
        for ($i = 0; $i < 30; $i++) {
            $diaSemana = (int) $data->format('N'); // 6=sábado, 7=domingo
            $dataStr = $data->format('Y-m-d');
            $stmt = $pdo->prepare("SELECT 1 FROM restaurante_feriado WHERE RF_DATA = ?");
            $stmt->execute([$dataStr]);
            if ($diaSemana < 6 && !$stmt->fetchColumn()) {
                return $dataStr;
            }
            $data->modify('+1 day');
        }
        throw new RuntimeException('Não foi possível encontrar uma data útil sem feriado.');
    }
}