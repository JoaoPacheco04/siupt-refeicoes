<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class CancelarPedidoPendenteTest extends DatabaseTestCase
{
    public function testCancelarPedidoPendenteComSucesso(): void
    {
        $userId = $this->criarUtilizadorTeste('90000070');
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $data = $this->proximaDataUtilSemFeriado();
        $rmId = $this->criarPratoEmenta('Bife', $tipoId, $data);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        $pedidoId = (int) Database::criarPedido($userId, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);
        // Nunca marcado como pago — fica pendente

        $resultado = Database::cancelarPedidoPendente($pedidoId, $userId);

        $this->assertTrue($resultado);
        $this->assertFalse(Database::obterPedido($pedidoId));
    }

    public function testCancelarPedidoJaPagoEhRejeitado(): void
    {
        $userId = $this->criarUtilizadorTeste('90000071');
        $tipoId = $this->criarTipoComPreco('Peixe', 3.50);
        $data = $this->proximaDataUtilSemFeriado();
        $rmId = $this->criarPratoEmenta('Dourada', $tipoId, $data);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        $pedidoId = (int) Database::criarPedido($userId, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($pedidoId);

        $resultado = Database::cancelarPedidoPendente($pedidoId, $userId);

        $this->assertFalse($resultado);
        $this->assertNotFalse(Database::obterPedido($pedidoId), 'O pedido pago não devia ter sido apagado');
    }

    public function testCancelarPedidoDeOutroUtilizadorEhRejeitado(): void
    {
        $dono = $this->criarUtilizadorTeste('90000072');
        $intruso = $this->criarUtilizadorTeste('90000073');
        $tipoId = $this->criarTipoComPreco('Vegetariano', 3.00);
        $data = $this->proximaDataUtilSemFeriado();
        $rmId = $this->criarPratoEmenta('Salada', $tipoId, $data);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        $pedidoId = (int) Database::criarPedido($dono, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);

        $resultado = Database::cancelarPedidoPendente($pedidoId, $intruso);

        $this->assertFalse($resultado);
        $this->assertNotFalse(Database::obterPedido($pedidoId));
    }
}