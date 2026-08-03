<?php

require_once __DIR__ . '/TesteBase.php';

final class ApagarExtraTest extends TesteBase
{
    private int $utilizadorId;

    protected function setUp(): void
    {
        $this->utilizadorId = $this->criarUtilizadorTeste('EXTRA_' . uniqid());
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->utilizadorId);
    }

    public function testApagaDeVezSeNuncaFoiComprado(): void
    {
        $rmId = Database::criarPratoExtra('Hambúrguer Teste ' . uniqid(), 4.00);

        $resultado = Database::apagarExtra($rmId);

        $this->assertEquals('ok', $resultado);

        $stmt = self::pdo()->prepare("SELECT COUNT(*) FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $this->assertEquals(0, $stmt->fetchColumn(), 'O prato deveria ter sido apagado por completo');
    }

    public function testApenasDesativaSeJaFoiComprado(): void
    {
        $rmId = Database::criarPratoExtra('Omelete Teste ' . uniqid(), 3.50);

        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d'), [
            ['rm_id' => $rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $resultado = Database::apagarExtra($rmId);

        $this->assertEquals('desativado', $resultado);

        $stmt = self::pdo()->prepare("SELECT RM_ATIVO FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $ativo = $stmt->fetchColumn();

        $this->assertNotFalse($ativo, 'O prato ainda deve existir, apenas desativado');
        $this->assertEquals(0, (int) $ativo);

        $stmtTipo = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmtTipo->execute([$rmId]);
        $tipoId = (int) $stmtTipo->fetchColumn();
        $this->limparTipoEPrato($tipoId, $rmId);
    }
}