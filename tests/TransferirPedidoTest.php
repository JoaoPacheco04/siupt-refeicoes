<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class TransferirPedidoTest extends DatabaseTestCase
{
    private function criarPedidoPagoParaDia(int $userId, string $data): int
    {
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $rmId = $this->criarPratoEmenta('Bife', $tipoId, $data);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '23:59:59', 0)"
        )->execute([$tipoId]);
        $pedidoId = Database::criarPedido($userId, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($pedidoId);
        return $pedidoId;
    }

    public function testTransferirPedidoComSucesso(): void
    {
        $origem = $this->criarUtilizadorTeste('90000020', 'Aluno Origem');
        $destino = $this->criarUtilizadorTeste('90000021', 'Aluno Destino');
        $dataFutura = $this->proximaDataUtilSemFeriado();
        $pedidoId = $this->criarPedidoPagoParaDia($origem, $dataFutura);

        $resultado = Database::transferirPedido($pedidoId, $origem, '90000021');

        $this->assertSame('ok', $resultado);
        $pedido = Database::obterPedido($pedidoId);
        $this->assertSame($destino, (int) $pedido['RP_U_ID']);
    }

    public function testTransferirPedidoRejeitaSegundaTransferencia(): void
    {
        $origem = $this->criarUtilizadorTeste('90000022');
        $destino = $this->criarUtilizadorTeste('90000023');
        $terceiro = $this->criarUtilizadorTeste('90000024');
        $dataFutura = $this->proximaDataUtilSemFeriado();
        $pedidoId = $this->criarPedidoPagoParaDia($origem, $dataFutura);

        Database::transferirPedido($pedidoId, $origem, '90000023');
        $segunda = Database::transferirPedido($pedidoId, $destino, '90000024');

        $this->assertSame('ja_transferido', $segunda);
    }

    public function testTransferirPedidoRejeitaDestinatarioInexistente(): void
    {
        $origem = $this->criarUtilizadorTeste('90000025');
        $dataFutura = $this->proximaDataUtilSemFeriado();
        $pedidoId = $this->criarPedidoPagoParaDia($origem, $dataFutura);

        $resultado = Database::transferirPedido($pedidoId, $origem, '99999999');

        $this->assertSame('destinatario_nao_encontrado', $resultado);
    }

    public function testTransferirPedidoRejeitaParaSiMesmo(): void
    {
        $origem = $this->criarUtilizadorTeste('90000026', 'Aluno', 1);
        $dataFutura = $this->proximaDataUtilSemFeriado();
        $pedidoId = $this->criarPedidoPagoParaDia($origem, $dataFutura);

        $resultado = Database::transferirPedido($pedidoId, $origem, '90000026');

        $this->assertSame('mesmo_utilizador', $resultado);
    }
}