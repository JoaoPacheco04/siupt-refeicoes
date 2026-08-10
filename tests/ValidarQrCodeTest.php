<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class ValidarQrCodeTest extends DatabaseTestCase
{
    private function criarPedidoPagoDeHoje(int $userId): array
    {
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $hoje = date('Y-m-d');
        $rmId = $this->criarPratoEmenta('Bife', $tipoId, $hoje);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '23:59:59', 0)"
        )->execute([$tipoId]);

        $pedidoId = Database::criarPedido($userId, $hoje, [['rm_id' => $rmId, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($pedidoId);
        $pedido = Database::obterPedido($pedidoId);
        return [$pedidoId, $pedido['RP_QRCODE']];
    }

    public function testValidarPorQrCodeComSucesso(): void
    {
        $userId = $this->criarUtilizadorTeste('90000010', 'Aluno Válido');
        $funcionarioId = $this->criarUtilizadorTeste('90000011', 'Funcionário Teste', 2);
        [, $qrcode] = $this->criarPedidoPagoDeHoje($userId);

        $resultado = Database::validarPorQrCode($qrcode, $funcionarioId);

        $this->assertSame('valido', $resultado['status']);
    }

    public function testValidarPorQrCodeRejeitaSegundaValidacao(): void
    {
        $userId = $this->criarUtilizadorTeste('90000012');
        $funcionarioId = $this->criarUtilizadorTeste('90000013', 'Func', 2);
        [, $qrcode] = $this->criarPedidoPagoDeHoje($userId);

        Database::validarPorQrCode($qrcode, $funcionarioId);
        $segunda = Database::validarPorQrCode($qrcode, $funcionarioId);

        $this->assertSame('utilizado', $segunda['status']);
    }

    public function testValidarPorQrCodeInvalidoDevolveInvalido(): void
    {
        $funcionarioId = $this->criarUtilizadorTeste('90000014', 'Func', 2);

        $resultado = Database::validarPorQrCode('codigo-que-nao-existe', $funcionarioId);

        $this->assertSame('invalido', $resultado['status']);
    }
}