<?php

require_once __DIR__ . '/TesteBase.php';

final class AvaliarPedidoTest extends TesteBase
{
    private int $utilizadorId;
    private int $tipoId;
    private int $rmId;

    protected function setUp(): void
    {
        $this->utilizadorId = $this->criarUtilizadorTeste('AVAL_' . uniqid());
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Peixe', 3.50, date('Y-m-d'));
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->utilizadorId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    private function criarPedidoUtilizado(): int
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d'), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);
        self::pdo()->prepare("UPDATE restaurante_pedido SET RP_UTILIZADO = 1 WHERE RP_ID = ?")->execute([$pedidoId]);
        return $pedidoId;
    }

    public function testAvaliaComSucesso(): void
    {
        $pedidoId = $this->criarPedidoUtilizado();

        $resultado = Database::avaliarPedido($pedidoId, $this->utilizadorId, 5, null);

        $this->assertEquals('ok', $resultado);
    }

    public function testNaoPermiteAvaliarPedidoNaoUtilizado(): void
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d'), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $resultado = Database::avaliarPedido($pedidoId, $this->utilizadorId, 5, null);

        $this->assertEquals('nao_autorizado', $resultado);
    }

    public function testNaoPermiteAvaliarDuasVezes(): void
    {
        $pedidoId = $this->criarPedidoUtilizado();
        Database::avaliarPedido($pedidoId, $this->utilizadorId, 4, null);

        $segundaTentativa = Database::avaliarPedido($pedidoId, $this->utilizadorId, 2, 'comida_fria');

        $this->assertEquals('ja_avaliado', $segundaTentativa);
    }

    public function testGuardaMotivoComEstrelasBaixasEMotivoValido(): void
    {
        $pedidoId = $this->criarPedidoUtilizado();
        Database::avaliarPedido($pedidoId, $this->utilizadorId, 1, 'comida_fria');

        $stmt = self::pdo()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $motivo = $stmt->fetchColumn();

        $this->assertEquals('comida_fria', $motivo);
    }

    public function testIgnoraMotivoComEstrelasAltas(): void
    {
        $pedidoId = $this->criarPedidoUtilizado();
        Database::avaliarPedido($pedidoId, $this->utilizadorId, 5, 'comida_fria');

        $stmt = self::pdo()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $motivo = $stmt->fetchColumn();

        $this->assertNull($motivo);
    }

    public function testIgnoraMotivoInvalidoMesmoComEstrelasBaixas(): void
    {
        $pedidoId = $this->criarPedidoUtilizado();
        Database::avaliarPedido($pedidoId, $this->utilizadorId, 1, 'motivo_inventado');

        $stmt = self::pdo()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $motivo = $stmt->fetchColumn();

        $this->assertNull($motivo);
    }
}