<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class FeriadosDiaEspecialTest extends DatabaseTestCase
{
    private array $feriadosCriados = [];
    private array $diasEspeciaisCriados = [];

    protected function tearDown(): void
    {
        $pdo = Database::conexao();
        foreach ($this->feriadosCriados as $data) {
            $pdo->prepare("DELETE FROM restaurante_feriado WHERE RF_DATA = ?")->execute([$data]);
        }
        foreach ($this->diasEspeciaisCriados as $data) {
            $pdo->prepare("DELETE FROM restaurante_dia_especial WHERE RDE_DATA = ?")->execute([$data]);
        }
        parent::tearDown();
    }

    public function testCriarFeriadoComSucesso(): void
    {
        $data = date('Y-m-d', strtotime('+400 days')); // ano seguinte, improvável já existir
        $this->feriadosCriados[] = $data;

        $resultado = Database::criarFeriado($data, 'Feriado de Teste PHPUnit');

        $this->assertSame('ok', $resultado);
        $this->assertTrue(Database::ehFeriado($data));
    }

    public function testCriarFeriadoRejeitaDataDuplicada(): void
    {
        $data = date('Y-m-d', strtotime('+401 days'));
        $this->feriadosCriados[] = $data;

        Database::criarFeriado($data, 'Primeiro');
        $segundo = Database::criarFeriado($data, 'Segundo');

        $this->assertSame('data_duplicada', $segundo);
    }

    public function testCriarDiaEspecialComExtrasPermitidos(): void
    {
        $data = date('Y-m-d', strtotime('+402 days'));
        $this->diasEspeciaisCriados[] = $data;

        $resultado = Database::criarDiaEspecial($data, 'Evento interno', true);

        $this->assertSame('ok', $resultado);
        $diaEspecial = Database::ehDiaEspecial($data);
        $this->assertNotFalse($diaEspecial);
        $this->assertEquals(1, $diaEspecial['RDE_PERMITE_EXTRAS']);
    }

    public function testCriarDiaEspecialComExtrasNaoPermitidos(): void
    {
        $data = date('Y-m-d', strtotime('+403 days'));
        $this->diasEspeciaisCriados[] = $data;

        Database::criarDiaEspecial($data, 'Férias de Agosto', false);

        $diaEspecial = Database::ehDiaEspecial($data);
        $this->assertEquals(0, $diaEspecial['RDE_PERMITE_EXTRAS']);
    }

    public function testCriarDiaEspecialRejeitaSeJaForFeriado(): void
    {
        $data = date('Y-m-d', strtotime('+404 days'));
        $this->feriadosCriados[] = $data;
        Database::criarFeriado($data, 'Feriado Existente');

        $resultado = Database::criarDiaEspecial($data, 'Tentativa sobre feriado', true);

        $this->assertSame('eh_feriado', $resultado);
    }
}