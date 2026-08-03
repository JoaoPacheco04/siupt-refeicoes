<?php

require_once __DIR__ . '/TesteBase.php';

final class GestaoExtrasAvancadaTest extends TesteBase
{
    private array $tiposParaLimpar = [];
    private array $pratosParaLimpar = [];

    protected function tearDown(): void
    {
        foreach ($this->pratosParaLimpar as $rmId) {
            self::pdo()->prepare("DELETE FROM restaurante_compra WHERE RC_RM_ID = ?")->execute([$rmId]);
            self::pdo()->prepare("DELETE FROM restaurante_menu WHERE RM_ID = ?")->execute([$rmId]);
        }
        foreach ($this->tiposParaLimpar as $tipoId) {
            self::pdo()->prepare("DELETE FROM restaurante_data_limite WHERE RDL_RTP_ID = ?")->execute([$tipoId]);
            self::pdo()->prepare("DELETE FROM restaurante_preco_tipo_refeicao WHERE RPTR_TP_ID = ?")->execute([$tipoId]);
            self::pdo()->prepare("DELETE FROM restaurante_tipo_refeicao WHERE RTP_ID = ?")->execute([$tipoId]);
        }
    }

    /**
     * Cria dois extras que partilham o mesmo tipo — cenário que
     * separarExtraParaTipoProprio() deve conseguir corrigir.
     */
    private function criarDoisExtrasComTipoPartilhado(): array
    {
        $pdo = self::pdo();

        $stmt = $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) OUTPUT INSERTED.RTP_ID VALUES (?)");
        $stmt->execute(['Extra: Tipo Partilhado ' . uniqid()]);
        $tipoPartilhadoId = (int) $stmt->fetchColumn();
        $this->tiposParaLimpar[] = $tipoPartilhadoId;

        $pdo->prepare("INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, '2026-01-01')")
            ->execute([$tipoPartilhadoId, 3.00]);

        $stmt = $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA) OUTPUT INSERTED.RM_ID VALUES (?, ?, NULL)");
        $stmt->execute(['Extra A ' . uniqid(), $tipoPartilhadoId]);
        $rmA = (int) $stmt->fetchColumn();
        $this->pratosParaLimpar[] = $rmA;

        $stmt = $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA) OUTPUT INSERTED.RM_ID VALUES (?, ?, NULL)");
        $stmt->execute(['Extra B ' . uniqid(), $tipoPartilhadoId]);
        $rmB = (int) $stmt->fetchColumn();
        $this->pratosParaLimpar[] = $rmB;

        return [$tipoPartilhadoId, $rmA, $rmB];
    }

    public function testSepararExtraCriaTipoProprioComMesmoPreco(): void
    {
        [$tipoPartilhadoId, $rmA, $rmB] = $this->criarDoisExtrasComTipoPartilhado();

        $novoTipoId = Database::separarExtraParaTipoProprio($rmA);

        $this->assertIsInt($novoTipoId, 'Deveria devolver o ID do novo tipo criado');
        $this->assertNotEquals($tipoPartilhadoId, $novoTipoId, 'O novo tipo deve ser diferente do partilhado');
        $this->tiposParaLimpar[] = $novoTipoId;

        // O prato A já deve apontar para o novo tipo
        $stmt = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmA]);
        $this->assertEquals($novoTipoId, (int) $stmt->fetchColumn());

        // O prato B deve continuar no tipo partilhado original (não afetado)
        $stmt = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmB]);
        $this->assertEquals($tipoPartilhadoId, (int) $stmt->fetchColumn());

        // O preço deve ter sido preservado (3.00, o mesmo do tipo partilhado)
        $precoNovo = Database::obterPrecoVigente($novoTipoId, date('Y-m-d'));
        $this->assertEquals(3.00, $precoNovo);
    }

    public function testSepararExtraFalhaSeIdNaoForExtra(): void
    {
        // Um RM_ID inexistente não pode ser separado
        $resultado = Database::separarExtraParaTipoProprio(999999999);
        $this->assertEquals('extra_nao_encontrado', $resultado);
    }

    public function testReativarSoFuncionaParaExtrasNaoPratosDaEmenta(): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) OUTPUT INSERTED.RTP_ID VALUES (?)");
        $stmt->execute(['Tipo Ementa Teste ' . uniqid()]);
        $tipoId = (int) $stmt->fetchColumn();
        $this->tiposParaLimpar[] = $tipoId;

        // Prato de EMENTA (tem data) — desativado manualmente para o teste
        $stmt = $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA, RM_ATIVO) OUTPUT INSERTED.RM_ID VALUES (?, ?, ?, 0)");
        $stmt->execute(['Prato Ementa ' . uniqid(), $tipoId, date('Y-m-d', strtotime('+3 days'))]);
        $rmEmentaId = (int) $stmt->fetchColumn();
        $this->pratosParaLimpar[] = $rmEmentaId;

        $resultado = Database::reativarExtra($rmEmentaId);

        $this->assertFalse($resultado, 'Não deve reativar um prato de ementa (RM_DATA não é NULL)');
    }

    public function testReativarFuncionaParaExtraDesativado(): void
    {
        $rmId = Database::criarPratoExtra('Extra Reativar Teste ' . uniqid(), 2.50);
        $stmtTipo = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmtTipo->execute([$rmId]);
        $tipoId = (int) $stmtTipo->fetchColumn();
        $this->tiposParaLimpar[] = $tipoId;
        $this->pratosParaLimpar[] = $rmId;

        self::pdo()->prepare("UPDATE restaurante_menu SET RM_ATIVO = 0 WHERE RM_ID = ?")->execute([$rmId]);

        $resultado = Database::reativarExtra($rmId);

        $this->assertTrue($resultado);

        $stmt = self::pdo()->prepare("SELECT RM_ATIVO FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $this->assertEquals(1, (int) $stmt->fetchColumn());
    }

    public function testAtualizarPrecoTipoPreservaHistoricoAntigo(): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) OUTPUT INSERTED.RTP_ID VALUES (?)");
        $stmt->execute(['Tipo Preco Teste ' . uniqid()]);
        $tipoId = (int) $stmt->fetchColumn();
        $this->tiposParaLimpar[] = $tipoId;

        $pdo->prepare("INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, '2026-01-01')")
            ->execute([$tipoId, 2.00]);

        Database::atualizarPrecoTipo($tipoId, 2.50);

        // Devem existir DUAS linhas no histórico — não é update, é insert novo
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurante_preco_tipo_refeicao WHERE RPTR_TP_ID = ?");
        $stmt->execute([$tipoId]);
        $this->assertEquals(2, (int) $stmt->fetchColumn(), 'Deve manter o preço antigo no histórico, não sobrescrever');

        // O preço vigente hoje deve ser o novo
        $this->assertEquals(2.50, Database::obterPrecoVigente($tipoId, date('Y-m-d')));

        // O preço vigente em janeiro de 2026 continua a ser o antigo
        $this->assertEquals(2.00, Database::obterPrecoVigente($tipoId, '2026-01-15'));
    }

    public function testAtualizarNomeSoFuncionaParaExtras(): void
    {
        $rmId = Database::criarPratoExtra('Nome Original ' . uniqid(), 3.00);
        $stmtTipo = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmtTipo->execute([$rmId]);
        $this->tiposParaLimpar[] = (int) $stmtTipo->fetchColumn();
        $this->pratosParaLimpar[] = $rmId;

        $ok = Database::atualizarNomeExtra($rmId, 'Nome Alterado');
        $this->assertTrue($ok);

        $stmt = self::pdo()->prepare("SELECT RM_NOME FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $this->assertEquals('Nome Alterado', $stmt->fetchColumn());
    }

    public function testAtualizarNomeFalhaParaPratoDeEmenta(): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) OUTPUT INSERTED.RTP_ID VALUES (?)");
        $stmt->execute(['Tipo Ementa Nome ' . uniqid()]);
        $tipoId = (int) $stmt->fetchColumn();
        $this->tiposParaLimpar[] = $tipoId;

        $stmt = $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA) OUTPUT INSERTED.RM_ID VALUES (?, ?, ?)");
        $stmt->execute(['Nome Ementa Original', $tipoId, date('Y-m-d', strtotime('+2 days'))]);
        $rmId = (int) $stmt->fetchColumn();
        $this->pratosParaLimpar[] = $rmId;

        $ok = Database::atualizarNomeExtra($rmId, 'Nome Não Devia Mudar');

        $this->assertFalse($ok, 'Não deve alterar o nome de um prato de ementa através deste método');

        $stmt = self::pdo()->prepare("SELECT RM_NOME FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $this->assertEquals('Nome Ementa Original', $stmt->fetchColumn());
    }
}