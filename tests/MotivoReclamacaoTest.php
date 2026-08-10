<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class MotivoReclamacaoTest extends DatabaseTestCase
{
    private array $motivosCriados = [];

    protected function tearDown(): void
    {
        $pdo = Database::conexao();
        foreach ($this->motivosCriados as $codigo) {
            $pdo->prepare("DELETE FROM restaurante_motivo_reclamacao WHERE RMR_CODIGO = ?")->execute([$codigo]);
        }
        parent::tearDown();
    }

    private function criarPedidoValidadoParaAvaliar(): array
    {
        $userId = $this->criarUtilizadorTeste('90000080');
        $funcionarioId = $this->criarUtilizadorTeste('90000081', 'Func', 2);
        $tipoId = $this->criarTipoComPreco('Carne', 3.50);
        $hoje = date('Y-m-d');
        $rmId = $this->criarPratoEmenta('Bife', $tipoId, $hoje);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '23:59:59', 0)"
        )->execute([$tipoId]);

        $pedidoId = (int) Database::criarPedido($userId, $hoje, [['rm_id' => $rmId, 'menu_completo' => false]]);
        Database::marcarPedidoComoPago($pedidoId);
        $pedido = Database::obterPedido($pedidoId);
        Database::validarPorQrCode($pedido['RP_QRCODE'], $funcionarioId);

        return [$pedidoId, $userId];
    }

    public function testAvaliarComMotivoValidoGuardaOMotivo(): void
    {
        $this->motivosCriados[] = 'comida_fria_teste';
        Database::criarMotivoReclamacao('comida_fria_teste', 'Comida fria (teste)');

        [$pedidoId, $userId] = $this->criarPedidoValidadoParaAvaliar();

        Database::avaliarPedido($pedidoId, $userId, 2, 'comida_fria_teste');

        $stmt = Database::conexao()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $this->assertSame('comida_fria_teste', $stmt->fetchColumn());
    }

    public function testAvaliarComMotivoInexistenteIgnoraOMotivo(): void
    {
        [$pedidoId, $userId] = $this->criarPedidoValidadoParaAvaliar();

        Database::avaliarPedido($pedidoId, $userId, 1, 'motivo_que_nao_existe');

        $stmt = Database::conexao()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $this->assertNull($stmt->fetchColumn() ?: null);
    }

    public function testAvaliarComMotivoDesativadoIgnoraOMotivo(): void
    {
        $this->motivosCriados[] = 'motivo_desativado_teste';
        Database::criarMotivoReclamacao('motivo_desativado_teste', 'Motivo Desativado (teste)');
        $stmt = Database::conexao()->prepare("SELECT RMR_ID FROM restaurante_motivo_reclamacao WHERE RMR_CODIGO = ?");
        $stmt->execute(['motivo_desativado_teste']);
        Database::desativarMotivoReclamacao((int) $stmt->fetchColumn());

        [$pedidoId, $userId] = $this->criarPedidoValidadoParaAvaliar();

        Database::avaliarPedido($pedidoId, $userId, 1, 'motivo_desativado_teste');

        $stmt = Database::conexao()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $this->assertNull($stmt->fetchColumn() ?: null);
    }

    public function testAvaliarComEstrelasAltasIgnoraMotivoMesmoQueValido(): void
    {
        $this->motivosCriados[] = 'motivo_valido_teste';
        Database::criarMotivoReclamacao('motivo_valido_teste', 'Motivo Válido (teste)');

        [$pedidoId, $userId] = $this->criarPedidoValidadoParaAvaliar();

        // Com 5 estrelas, o motivo não deve ser guardado mesmo sendo válido
        // (a regra de negócio só associa motivo a avaliações de 1-2 estrelas)
        Database::avaliarPedido($pedidoId, $userId, 5, 'motivo_valido_teste');

        $stmt = Database::conexao()->prepare("SELECT RAV_MOTIVO FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        $this->assertNull($stmt->fetchColumn() ?: null);
    }
}