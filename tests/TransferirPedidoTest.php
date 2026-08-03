<?php

require_once __DIR__ . '/TesteBase.php';

final class TransferirPedidoTest extends TesteBase
{
    private int $remetenteId;
    private int $destinatarioId;
    private int $tipoId;
    private int $rmId;
    private int $pedidoId;

    protected function setUp(): void
    {
        $this->remetenteId = $this->criarUtilizadorTeste('REM_' . uniqid());
        $this->destinatarioId = $this->criarUtilizadorTeste('DEST_' . uniqid());
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Vegetariano', 3.00, date('Y-m-d', strtotime('+1 day')));

        $this->pedidoId = Database::criarPedido($this->remetenteId, date('Y-m-d', strtotime('+1 day')), [
            ['rm_id' => $this->rmId, 'menu_completo' => false],
        ]);
        Database::marcarPedidoComoPago($this->pedidoId);
    }

    protected function tearDown(): void
    {
        $this->limparUtilizador($this->remetenteId);
        $this->limparUtilizador($this->destinatarioId);
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    private function obterBiccDoUtilizador(int $id): string
    {
        $stmt = self::pdo()->prepare("SELECT U_BICC FROM users WHERE U_ID = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function testTransfereComSucesso(): void
    {
        $bicc = $this->obterBiccDoUtilizador($this->destinatarioId);
        $resultado = Database::transferirPedido($this->pedidoId, $this->remetenteId, $bicc);

        $this->assertEquals('ok', $resultado);

        $pedido = Database::obterPedido($this->pedidoId);
        $this->assertEquals($this->destinatarioId, (int) $pedido['RP_U_ID']);
    }

    public function testNaoPermiteTransferirDuasVezes(): void
    {
        $bicc = $this->obterBiccDoUtilizador($this->destinatarioId);
        Database::transferirPedido($this->pedidoId, $this->remetenteId, $bicc);

        $resultado = Database::transferirPedido($this->pedidoId, $this->remetenteId, $bicc);

        $this->assertEquals('nao_transferivel', $resultado);
    }

    public function testNaoPermiteTransferirParaSiProprio(): void
    {
        $bicc = $this->obterBiccDoUtilizador($this->remetenteId);
        $resultado = Database::transferirPedido($this->pedidoId, $this->remetenteId, $bicc);

        $this->assertEquals('mesmo_utilizador', $resultado);
    }

    public function testNaoPermiteTransferirPedidoJaUtilizado(): void
    {
        self::pdo()->prepare("UPDATE restaurante_pedido SET RP_UTILIZADO = 1 WHERE RP_ID = ?")->execute([$this->pedidoId]);

        $bicc = $this->obterBiccDoUtilizador($this->destinatarioId);
        $resultado = Database::transferirPedido($this->pedidoId, $this->remetenteId, $bicc);

        $this->assertEquals('nao_transferivel', $resultado);
    }

    public function testNaoPermiteTransferirPedidoExpirado(): void
    {
        self::pdo()->prepare("UPDATE restaurante_pedido SET RP_DATA_REFEICAO = ? WHERE RP_ID = ?")
            ->execute([date('Y-m-d', strtotime('-2 days')), $this->pedidoId]);

        $bicc = $this->obterBiccDoUtilizador($this->destinatarioId);
        $resultado = Database::transferirPedido($this->pedidoId, $this->remetenteId, $bicc);

        $this->assertEquals('nao_transferivel', $resultado);
    }

    public function testNaoEncontraDestinatarioInexistente(): void
    {
        $resultado = Database::transferirPedido($this->pedidoId, $this->remetenteId, 'BICC_QUE_NAO_EXISTE_99999');

        $this->assertEquals('destinatario_nao_encontrado', $resultado);
    }
}