<?php

require_once __DIR__ . '/TesteBase.php';

final class ValidarPorQrCodeTest extends TesteBase
{
    private int $alunoId;
    private int $funcionarioId;
    private int $tipoId;
    private int $rmId;
    private int $pedidoId;

    protected function setUp(): void
    {
        $this->alunoId = $this->criarUtilizadorTeste('ALUNO_' . uniqid(), 1);
        $this->funcionarioId = $this->criarUtilizadorTeste('FUNC_' . uniqid(), 2);
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Peixe', 3.50, date('Y-m-d'));

        $this->pedidoId = Database::criarPedido($this->alunoId, date('Y-m-d'), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($this->pedidoId);
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->alunoId);
        $this->limparUtilizador($this->funcionarioId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testValidaComSucessoNaPrimeiraVez(): void
    {
        $pedido = Database::obterPedido($this->pedidoId);
        $resultado = Database::validarPorQrCode($pedido['RP_QRCODE'], $this->funcionarioId);

        $this->assertEquals('valido', $resultado['status']);
        $this->assertEquals($this->pedidoId, $resultado['pedido_id']);
    }

    public function testSegundaValidacaoDevolveUtilizado(): void
    {
        $pedido = Database::obterPedido($this->pedidoId);
        Database::validarPorQrCode($pedido['RP_QRCODE'], $this->funcionarioId);

        $segundaTentativa = Database::validarPorQrCode($pedido['RP_QRCODE'], $this->funcionarioId);

        $this->assertEquals('utilizado', $segundaTentativa['status']);
    }

    public function testQrCodeInexistenteDevolveInvalido(): void
    {
        $resultado = Database::validarPorQrCode('qrcode-que-nao-existe-de-todo', $this->funcionarioId);
        $this->assertEquals('invalido', $resultado['status']);
    }
}