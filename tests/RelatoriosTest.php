<?php

require_once __DIR__ . '/TesteBase.php';

final class RelatoriosTest extends TesteBase
{
    private int $utilizadorId;
    private int $tipoId;
    private int $rmId;
    private string $anoMesAtual;

    protected function setUp(): void
    {
        $this->utilizadorId = $this->criarUtilizadorTeste('REL_' . uniqid());
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Peixe', 4.00, date('Y-m-d'));
        $this->anoMesAtual = date('Y-m');
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->utilizadorId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testResumoMensalContaPedidoPagoDesteMes(): void
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d'), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $resumo = Database::obterResumoMensal($this->anoMesAtual);

        $this->assertGreaterThanOrEqual(1, $resumo['total_pedidos']);
        $this->assertGreaterThanOrEqual(4.00, $resumo['total_vendido']);
    }

    public function testResumoMensalIgnoraPedidoNaoPago(): void
    {
        $antesResumo = Database::obterResumoMensal($this->anoMesAtual);
        $pedidosAntes = $antesResumo['total_pedidos'];

        Database::criarPedido($this->utilizadorId, date('Y-m-d'), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $depoisResumo = Database::obterResumoMensal($this->anoMesAtual);

        $this->assertEquals($pedidosAntes, $depoisResumo['total_pedidos'], 'Pedidos não pagos não devem contar no resumo');
    }

    public function testVendasPorTipoIncluiOTipoDoTesteAtual(): void
    {
        $pedidoId = Database::criarPedido($this->utilizadorId, date('Y-m-d'), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $vendasPorTipo = Database::obterVendasPorTipoMensal($this->anoMesAtual);
        $nomesTipos = array_column($vendasPorTipo, 'RTP_NOME');

        $this->assertContains('Peixe', $nomesTipos);
    }
}