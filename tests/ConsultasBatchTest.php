<?php

require_once __DIR__ . '/TesteBase.php';

final class ConsultasBatchTest extends TesteBase
{
    private int $utilizadorId;
    private int $tipoId;
    private int $rmId;

    protected function setUp(): void
    {
        $this->utilizadorId = $this->criarUtilizadorTeste('BATCH_' . uniqid());
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Carne', 3.50, date('Y-m-d', strtotime('+1 day')));
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->utilizadorId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testListarDatasComPedidoAtivoIncluiSoAsDatasPagas(): void
    {
        $dataComPedido = date('Y-m-d', strtotime('+1 day'));
        $dataSemPedido = date('Y-m-d', strtotime('+2 days'));

        $pedidoId = Database::criarPedido($this->utilizadorId, $dataComPedido, [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $resultado = Database::listarDatasComPedidoAtivo($this->utilizadorId, [$dataComPedido, $dataSemPedido]);

        $this->assertContains($dataComPedido, $resultado);
        $this->assertNotContains($dataSemPedido, $resultado);
    }

    public function testListarDatasComPedidoAtivoIgnoraPedidoNaoPago(): void
    {
        $dataFutura = date('Y-m-d', strtotime('+1 day'));

        // Cria mas NÃO paga
        Database::criarPedido($this->utilizadorId, $dataFutura, [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $resultado = Database::listarDatasComPedidoAtivo($this->utilizadorId, [$dataFutura]);

        $this->assertNotContains($dataFutura, $resultado, 'Pedido não pago não conta como "pedido ativo"');
    }

    public function testListarDatasComPedidoAtivoDevolveVazioParaListaVazia(): void
    {
        $resultado = Database::listarDatasComPedidoAtivo($this->utilizadorId, []);
        $this->assertEquals([], $resultado);
    }

    public function testListarItensExtrasCompradosDevolveChaveCorreta(): void
    {
        $rmExtraId = Database::criarPratoExtra('Extra Batch Teste ' . uniqid(), 4.00);
        $stmtTipo = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmtTipo->execute([$rmExtraId]);
        $tipoExtraId = (int) $stmtTipo->fetchColumn();

        $dataExtra = date('Y-m-d');
        $pedidoId = Database::criarPedido($this->utilizadorId, $dataExtra, [
            ['rm_id' => $rmExtraId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($pedidoId);

        $resultado = Database::listarItensExtrasComprados($this->utilizadorId, [$dataExtra]);

        $chaveEsperada = $rmExtraId . '|' . $dataExtra;
        $this->assertContains($chaveEsperada, $resultado);

        // limpeza específica deste teste
        $this->limparTipoEPrato($tipoExtraId, $rmExtraId);
    }

    public function testListarItensExtrasCompradosIgnoraPedidoNaoPago(): void
    {
        $rmExtraId = Database::criarPratoExtra('Extra Nao Pago Teste ' . uniqid(), 4.00);
        $stmtTipo = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmtTipo->execute([$rmExtraId]);
        $tipoExtraId = (int) $stmtTipo->fetchColumn();

        $dataExtra = date('Y-m-d');
        // Cria mas NÃO paga
        Database::criarPedido($this->utilizadorId, $dataExtra, [
            ['rm_id' => $rmExtraId, 'menu_completo' => false],
        ]);

        $resultado = Database::listarItensExtrasComprados($this->utilizadorId, [$dataExtra]);

        $chaveEsperada = $rmExtraId . '|' . $dataExtra;
        $this->assertNotContains($chaveEsperada, $resultado);

        $this->limparTipoEPrato($tipoExtraId, $rmExtraId);
    }

    public function testListarItensExtrasCompradosDevolveVazioParaListaVazia(): void
    {
        $resultado = Database::listarItensExtrasComprados($this->utilizadorId, []);
        $this->assertEquals([], $resultado);
    }
}