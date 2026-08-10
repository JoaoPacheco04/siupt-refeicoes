<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class CriarPedidoTest extends DatabaseTestCase
{
    public function testCriarPedidoComPratoDaEmentaComSucesso(): void
    {
        $userId = $this->criarUtilizadorTeste('90000001', 'Aluno Teste PHPUnit');
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $dataFutura = $this->proximaDataUtilSemFeriado();        
        $rmId = $this->criarPratoEmenta('Bife grelhado', $tipoId, $dataFutura);

        // Garantir que não há prazo a bloquear
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        $resultado = Database::criarPedido($userId, $dataFutura, [
            ['rm_id' => $rmId, 'menu_completo' => false],
        ]);

        $this->assertIsInt($resultado, 'Esperava um ID de pedido (int), obteve: ' . var_export($resultado, true));

        $pedido = Database::obterPedido($resultado);
        $this->assertSame($userId, (int) $pedido['RP_U_ID']);
        $this->assertSame(3.50, (float) $pedido['RP_PRECO_TOTAL']);
    }

    public function testCriarPedidoRejeitaDataNoPassado(): void
    {
        $userId = $this->criarUtilizadorTeste('90000002');
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $rmId = $this->criarPratoEmenta('Peixe', $tipoId, '2020-01-01');

        $resultado = Database::criarPedido($userId, '2020-01-01', [
            ['rm_id' => $rmId, 'menu_completo' => false],
        ]);

        $this->assertSame('data_no_passado', $resultado);
    }

    public function testCriarPedidoRejeitaSegundoPedidoParaOMesmoDia(): void
    {
        $userId = $this->criarUtilizadorTeste('90000003');
        $tipoId = $this->criarTipoComPreco('Peixe', 3.50);
        $dataFutura = $this->proximaDataUtilSemFeriado();
        $rmId1 = $this->criarPratoEmenta('Dourada', $tipoId, $dataFutura);
        $rmId2 = $this->criarPratoEmenta('Salmão', $tipoId, $dataFutura);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        $primeiro = Database::criarPedido($userId, $dataFutura, [['rm_id' => $rmId1, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($primeiro); // O bloqueio de duplicado só considera pedidos pagos

        $segundo = Database::criarPedido($userId, $dataFutura, [['rm_id' => $rmId2, 'menu_completo' => false]]);

        $this->assertSame('pedido_duplicado', $segundo);
    }

    public function testCriarPedidoRejeitaSeForaDePrazo(): void
    {
        $userId = $this->criarUtilizadorTeste('90000004');
        $tipoId = $this->criarTipoComPreco('Vegetariano', 3.00);
        $dataFutura = $this->proximaDataUtilSemFeriado(1); // amanhã ou o próximo dia útil
        $rmId = $this->criarPratoEmenta('Salada de grão', $tipoId, $dataFutura);

        // Calcula quantos dias de antecedência separam hoje da data escolhida,
        // e define o prazo para "hoje às 00:00:01" — já passado a qualquer hora do dia.
        $diasAntecedencia = (new DateTime('today'))->diff(new DateTime($dataFutura))->days;
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '00:00:01', ?)"
        )->execute([$tipoId, $diasAntecedencia]);

        $resultado = Database::criarPedido($userId, $dataFutura, [['rm_id' => $rmId, 'menu_completo' => false]]);

        $this->assertSame('fora_de_prazo', $resultado);
    }
}