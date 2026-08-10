<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class AvaliarPedidoTest extends DatabaseTestCase
{
    private function criarPedidoValidado(int $userId, int $funcionarioId, string $data): int
    {
        $tipoId = $this->criarTipoComPreco('Peixe', 3.50);
        $rmId = $this->criarPratoEmenta('Dourada', $tipoId, $data);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '23:59:59', 0)"
        )->execute([$tipoId]);
        $pedidoId = Database::criarPedido($userId, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($pedidoId);
        $pedido = Database::obterPedido($pedidoId);
        Database::validarPorQrCode($pedido['RP_QRCODE'], $funcionarioId);
        return $pedidoId;
    }

    public function testAvaliarPedidoComSucesso(): void
    {
        $userId = $this->criarUtilizadorTeste('90000030');
        $funcionarioId = $this->criarUtilizadorTeste('90000031', 'Func', 2);
        $hoje = date('Y-m-d');
        $pedidoId = $this->criarPedidoValidado($userId, $funcionarioId, $hoje);

        $resultado = Database::avaliarPedido($pedidoId, $userId, 5, null);

        $this->assertSame('ok', $resultado);
    }

    public function testAvaliarPedidoRejeitaSegundaAvaliacao(): void
    {
        $userId = $this->criarUtilizadorTeste('90000032');
        $funcionarioId = $this->criarUtilizadorTeste('90000033', 'Func', 2);
        $hoje = date('Y-m-d');
        $pedidoId = $this->criarPedidoValidado($userId, $funcionarioId, $hoje);

        Database::avaliarPedido($pedidoId, $userId, 4, null);
        $segunda = Database::avaliarPedido($pedidoId, $userId, 2, null);

        $this->assertSame('ja_avaliado', $segunda);
    }

    public function testAvaliarPedidoRejeitaSeAindaNaoValidado(): void
    {
        $userId = $this->criarUtilizadorTeste('90000034');
        $tipoId = $this->criarTipoComPreco('Vegetariano', 3.00);
        $dataFutura = $this->proximaDataUtilSemFeriado();
        $rmId = $this->criarPratoEmenta('Salada', $tipoId, $dataFutura);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '23:59:59', 0)"
        )->execute([$tipoId]);
        $pedidoId = Database::criarPedido($userId, $dataFutura, [['rm_id' => $rmId, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($pedidoId);
        // Nunca validado — ainda não foi levantado

        $resultado = Database::avaliarPedido($pedidoId, $userId, 5, null);

        $this->assertSame('nao_autorizado', $resultado);
    }
}