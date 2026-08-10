<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class GerarFeriadosAutomaticosTest extends DatabaseTestCase
{
    private int $anoTeste;

    protected function setUp(): void
    {
        parent::setUp();
        // Ano futuro, longe do calendário real, para não colidir com feriados já gerados
        $this->anoTeste = (int) date('Y') + 50;
    }

    protected function tearDown(): void
    {
        $pdo = Database::conexao();
        $pdo->prepare("DELETE FROM restaurante_feriado WHERE YEAR(RF_DATA) = ?")->execute([$this->anoTeste]);
        $pdo->prepare("DELETE FROM restaurante_feriado_geracao WHERE RFG_ANO = ?")->execute([$this->anoTeste]);
        parent::tearDown();
    }

    public function testGerarTodosFeriadosDoAnoInsereTodos(): void
    {
        $resultado = Database::gerarTodosFeriadosDoAno($this->anoTeste);

        // 11 fixos + 4 móveis = 15
        $this->assertSame(15, $resultado['inseridos']);
        $this->assertSame(0, $resultado['ja_existiam']);
        $this->assertSame(15, $resultado['total']);
    }

    public function testGerarTodosFeriadosDoAnoNaoDuplicaNaSegundaChamada(): void
    {
        Database::gerarTodosFeriadosDoAno($this->anoTeste);

        $segunda = Database::gerarTodosFeriadosDoAno($this->anoTeste);

        $this->assertSame(0, $segunda['inseridos']);
        $this->assertSame(15, $segunda['ja_existiam']);
    }

    public function testFeriadosDoAnoJaExistemDetetaCorretamente(): void
    {
        $this->assertFalse(Database::feriadosDoAnoJaExistem($this->anoTeste));

        Database::gerarTodosFeriadosDoAno($this->anoTeste);

        $this->assertTrue(Database::feriadosDoAnoJaExistem($this->anoTeste));
    }

    public function testFeriadosDoAnoJaExistemToleraRemocaoPontual(): void
    {
        // Regressão do bug da âncora frágil: remover um feriado (ex: Ano Novo)
        // não devia forçar a regeneração completa do ano.
        Database::gerarTodosFeriadosDoAno($this->anoTeste);
        Database::conexao()->prepare(
            "DELETE FROM restaurante_feriado WHERE RF_DATA = ?"
        )->execute(["{$this->anoTeste}-01-01"]);

        // 14 dos 15 continuam lá — o limiar de feriadosDoAnoJaExistem() é >= 14
        $this->assertTrue(Database::feriadosDoAnoJaExistem($this->anoTeste));
    }
}