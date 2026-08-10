<?php

require_once __DIR__ . '/DatabaseTestCase.php';
require_once __DIR__ . '/../src/Services/PagamentoService.php';

final class DuploPagamentoTest extends DatabaseTestCase
{
    private function criarPedidoPendente(): int
    {
        $userId = $this->criarUtilizadorTeste('90000060');
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $data = $this->proximaDataUtilSemFeriado();
        $rmId = $this->criarPratoEmenta('Bife', $tipoId, $data);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        return (int) Database::criarPedido($userId, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);
    }

    public function testPrimeiroProcessamentoConfirmaPagamento(): void
    {
        $pedidoId = $this->criarPedidoPendente();

        $resultado = PagamentoService::processar($pedidoId, true, 'SIM-TESTE-1');

        $this->assertSame('confirmado', $resultado['status']);
        $pedido = Database::obterPedido($pedidoId);
        $this->assertEquals(1, $pedido['RP_PAGO']);
    }

    public function testSegundoProcessamentoEhRejeitadoComoJaProcessado(): void
    {
        $pedidoId = $this->criarPedidoPendente();

        PagamentoService::processar($pedidoId, true, 'SIM-TESTE-2A');
        $segundo = PagamentoService::processar($pedidoId, true, 'SIM-TESTE-2B');

        $this->assertSame('ja_processado', $segundo['status']);
    }

    public function testSegundoProcessamentoNaoDuplicaTentativaDePagamento(): void
    {
        $pedidoId = $this->criarPedidoPendente();

        PagamentoService::processar($pedidoId, true, 'SIM-TESTE-3A');
        PagamentoService::processar($pedidoId, true, 'SIM-TESTE-3B');

        $stmt = Database::conexao()->prepare("SELECT COUNT(*) FROM restaurante_pagamento WHERE RPG_RP_ID = ?");
        $stmt->execute([$pedidoId]);

        // Só deve existir 1 tentativa registada — a segunda chamada foi
        // bloqueada por pedidoJaPago() antes de chegar a registarTentativaPagamento().
        $this->assertEquals(1, (int) $stmt->fetchColumn());
    }

    public function testPagamentoFalhadoNaoMarcaPedidoComoPago(): void
    {
        $pedidoId = $this->criarPedidoPendente();

        $resultado = PagamentoService::processar($pedidoId, false, 'SIM-TESTE-4');

        $this->assertSame('falhado', $resultado['status']);
        $pedido = Database::obterPedido($pedidoId);
        $this->assertEquals(0, $pedido['RP_PAGO']);
    }
}