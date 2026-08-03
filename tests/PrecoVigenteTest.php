<?php

require_once __DIR__ . '/TesteBase.php';

final class PrecoVigenteTest extends TesteBase
{
    private int $tipoId;
    private int $rmId;

    protected function setUp(): void
    {
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Vegetariano', 3.00, date('Y-m-d'));
    }

    protected function tearDown(): void
    {
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testUsaPrecoBaseAntesDeQualquerAlteracao(): void
    {
        $preco = Database::obterPrecoVigente($this->tipoId, '2026-06-01');
        $this->assertEquals(3.00, $preco);
    }

    public function testUsaNovoPrecoAPartirDaDataDeInicio(): void
    {
        $this->criarPrecoAdicionalTeste($this->tipoId, 3.50, '2026-06-01');

        $this->assertEquals(3.00, Database::obterPrecoVigente($this->tipoId, '2026-05-31'));
        $this->assertEquals(3.50, Database::obterPrecoVigente($this->tipoId, '2026-06-01'));
        $this->assertEquals(3.50, Database::obterPrecoVigente($this->tipoId, '2026-12-01'));
    }

    public function testEscolheOMaisRecenteComVariosHistoricos(): void
    {
        $this->criarPrecoAdicionalTeste($this->tipoId, 3.20, '2026-03-01');
        $this->criarPrecoAdicionalTeste($this->tipoId, 3.50, '2026-06-01');
        $this->criarPrecoAdicionalTeste($this->tipoId, 3.80, '2026-09-01');

        $this->assertEquals(3.00, Database::obterPrecoVigente($this->tipoId, '2026-02-01'));
        $this->assertEquals(3.20, Database::obterPrecoVigente($this->tipoId, '2026-05-01'));
        $this->assertEquals(3.50, Database::obterPrecoVigente($this->tipoId, '2026-08-01'));
        $this->assertEquals(3.80, Database::obterPrecoVigente($this->tipoId, '2026-10-01'));
    }

    public function testDevolveNullSemPrecoDefinido(): void
    {
        $stmt = self::pdo()->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) OUTPUT INSERTED.RTP_ID VALUES (?)");
        $stmt->execute(['Tipo Sem Preço ' . uniqid()]);
        $tipoSemPrecoId = (int) $stmt->fetchColumn();

        $preco = Database::obterPrecoVigente($tipoSemPrecoId, date('Y-m-d'));

        $this->assertNull($preco);

        self::pdo()->prepare("DELETE FROM restaurante_tipo_refeicao WHERE RTP_ID = ?")->execute([$tipoSemPrecoId]);
    }
}