<?php

require_once __DIR__ . '/TesteBase.php';

final class CancelarPedidoTest extends TesteBase
{
    private int $utilizadorId;
    private int $outroUtilizadorId;
    private int $tipoId;
    private int $rmId;

    protected function setUp(): void
    {
        $this->utilizadorId = $this->criarUtilizadorTeste('CANC_' . uniqid());
        $this->outroUtilizadorId = $this->criarUtilizadorTeste('OUTRO_' . uniqid());
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Carne', 3.50, date('Y-m-d', strtotime('+1 day')));
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->utilizadorId);
        $this->limparUtilizador($this->outroUtilizadorId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testCancelaPedidoPendenteComSucesso(): void
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d', strtotime('+1 day')), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $resultado = Database::cancelarPedidoPendente($pedidoId, $this->utilizadorId);

        $this->assertTrue($resultado);
        $this->assertFalse(Database::obterPedido($pedidoId), 'O pedido deveria ter sido apagado');
    }

    public function testNaoCancelaPedidoJaPago(): void
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d', strtotime('+1 day')), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $resultado = Database::cancelarPedidoPendente($pedidoId, $this->utilizadorId);

        $this->assertFalse($resultado);
        $this->assertNotFalse(Database::obterPedido($pedidoId), 'O pedido pago não deveria ter sido apagado');
    }

    public function testNaoCancelaPedidoDeOutroUtilizador(): void
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d', strtotime('+1 day')), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $resultado = Database::cancelarPedidoPendente($pedidoId, $this->outroUtilizadorId);

        $this->assertFalse($resultado);
        $this->assertNotFalse(Database::obterPedido($pedidoId), 'O pedido não deveria ter sido apagado por outro utilizador');
    }
}