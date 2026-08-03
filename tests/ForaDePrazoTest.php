<?php

require_once __DIR__ . '/TesteBase.php';

final class ForaDePrazoTest extends TesteBase
{
    private int $tipoId;
    private int $rmId;

    protected function setUp(): void
    {
        [$this->tipoId, $this->rmId] = $this->criarPratoTeste('Carne', 3.50, date('Y-m-d'));
    }

    protected function tearDown(): void
    {
        $this->limparTipoEPrato($this->tipoId, $this->rmId);
    }

    public function testDentroDoPrazoQuandoFaltamVariosDias(): void
    {
        $this->criarDataLimiteTeste($this->tipoId, '14:30:00', 1);
        $dataRefeicao = date('Y-m-d', strtotime('+5 days'));

        $resultado = Database::foraDePrazo($this->tipoId, $dataRefeicao);

        $this->assertFalse($resultado);
    }

    public function testForaDePrazoQuandoDataJaPassou(): void
    {
        $this->criarDataLimiteTeste($this->tipoId, '14:30:00', 1);
        $dataRefeicao = date('Y-m-d', strtotime('-1 day'));

        $resultado = Database::foraDePrazo($this->tipoId, $dataRefeicao);

        $this->assertTrue($resultado);
    }

    public function testSemLimiteConfiguradoNuncaEstaForaDePrazo(): void
    {
        $resultado = Database::foraDePrazo($this->tipoId, date('Y-m-d', strtotime('-10 days')));

        $this->assertFalse($resultado, 'Sem limite definido, a função deve assumir que nunca está fora de prazo');
    }

    public function testBatchDevolveOMesmoResultadoQueAVersaoIndividual(): void
    {
        $this->criarDataLimiteTeste($this->tipoId, '10:00:00', 0);

        $dataRefeicao = date('Y-m-d');
        $limitesBatch = Database::obterDataLimitesBatch([$this->tipoId]);

        $individual = Database::foraDePrazo($this->tipoId, $dataRefeicao);
        $emBatch = Database::foraDePrazoBatch($this->tipoId, $dataRefeicao, $limitesBatch);

        $this->assertEquals($individual, $emBatch);
    }
}