<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class ApagarExtraTest extends DatabaseTestCase
{
    public function testApagarExtraSemComprasEliminaDefinitivamente(): void
    {
        $rmId = (int) Database::criarPratoExtra('Extra Teste PHPUnit', 2.50);

        $resultado = Database::apagarExtra($rmId);

        $this->assertSame('ok', $resultado);
        $stmt = Database::conexao()->prepare("SELECT 1 FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $this->assertFalse($stmt->fetch());
    }

    public function testApagarExtraJaCompradoFazSoftDelete(): void
    {
        $userId = $this->criarUtilizadorTeste('90000040');
        $rmId = (int) Database::criarPratoExtra('Extra Comprado PHPUnit', 3.00);
        $data = $this->proximaDataUtilSemFeriado();

        // Garante explicitamente que a venda de extras está permitida nesse dia,
        // sem depender do comportamento por defeito.
        Database::criarDiaEspecial($data, 'Teste PHPUnit', true);

        $pedidoId = Database::criarPedido($userId, $data, [['rm_id' => $rmId, 'menu_completo' => false]]);
        $this->assertIsInt($pedidoId, 'criarPedido falhou: ' . var_export($pedidoId, true));
        Database::marcarPedidoComoPago((int) $pedidoId);

        $resultado = Database::apagarExtra($rmId);

        $this->assertSame('desativado', $resultado);
        $stmt = Database::conexao()->prepare("SELECT RM_ATIVO FROM restaurante_menu WHERE RM_ID = ?");
        $stmt->execute([$rmId]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}