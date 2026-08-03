<?php

require_once __DIR__ . '/TesteBase.php';

final class CriarPedidoTest extends TesteBase
{
    private int $utilizadorId;
    private int $tipoId;
    private int $rmId;

    protected function setUp(): void
    {
        $this->utilizadorId = $this->criarUtilizadorTeste('TESTE_' . uniqid());
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Carne', 3.50, date('Y-m-d', strtotime('+1 day')));
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->utilizadorId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testCriaPedidoComSucesso(): void
    {
        $dataRefeicao = date('Y-m-d', strtotime('+1 day'));
        $resultado = Database::criarPedido($this->utilizadorId, $dataRefeicao, [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $this->assertIsInt($resultado, 'Deveria devolver o ID do pedido criado');

        $pedido = Database::obterPedido($resultado);
        $this->assertEquals(3.50, (float) $pedido['RP_PRECO_TOTAL']);
        $this->assertEquals(0, (int) $pedido['RP_PAGO']);
    }

    public function testRejeitaPedidoDuplicadoNoMesmoDia(): void
    {
        $dataRefeicao = date('Y-m-d', strtotime('+1 day'));
        $primeiroId = Database::criarPedido($this->utilizadorId, $dataRefeicao, [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($primeiroId);

        $resultado = Database::criarPedido($this->utilizadorId, $dataRefeicao, [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $this->assertEquals('pedido_duplicado', $resultado);
    }

    public function testRejeitaListaVaziaDeItens(): void
    {
        $resultado = Database::criarPedido($this->utilizadorId, date('Y-m-d', strtotime('+1 day')), []);
        $this->assertEquals('sem_itens', $resultado);
    }

    public function testRejeitaExtraDuplicadoNoMesmoDia(): void
    {
        $rmExtraId = Database::criarPratoExtra('Extra Teste ' . uniqid(), 4.00);

        $dataExtra = date('Y-m-d');
        $primeiroId = Database::criarPedido($this->utilizadorId, $dataExtra, [
            ['rm_id' => $rmExtraId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($primeiroId);

        $resultado = Database::criarPedido($this->utilizadorId, $dataExtra, [
            ['rm_id' => $rmExtraId, 'menu_completo' => false],
        ]);

        $this->assertEquals('extra_duplicado', $resultado);

        $stmtTipo = self::pdo()->prepare("SELECT RM_TP_ID FROM restaurante_menu WHERE RM_ID = ?");
        $stmtTipo->execute([$rmExtraId]);
        $tipoExtraId = (int) $stmtTipo->fetchColumn();
        $this->limparTipoEPrato($tipoExtraId, $rmExtraId);
    }

    public function testRejeitaDataNoPassado(): void
    {
        $dataPassada = date('Y-m-d', strtotime('-1 day'));
        $resultado = Database::criarPedido($this->utilizadorId, $dataPassada, [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);

        $this->assertEquals('data_no_passado', $resultado);
    }
}