<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class PrecosPrazosTest extends DatabaseTestCase
{
    public function testObterPrecoVigenteDevolveOMaisRecenteAteAData(): void
    {
        $tipoId = $this->criarTipoComPreco('Carne Teste', 3.50, '2024-01-01');
        Database::conexao()->prepare(
            "INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, ?)"
        )->execute([$tipoId, 4.00, '2025-06-01']);

        // Antes da subida de preço, deve devolver o valor antigo
        $this->assertSame(3.50, Database::obterPrecoVigente($tipoId, '2025-01-01'));
        // Depois da subida, deve devolver o novo valor
        $this->assertSame(4.00, Database::obterPrecoVigente($tipoId, '2025-12-01'));
    }

    public function testObterPrecoVigenteDevolveNullSeNaoHouverPreco(): void
    {
        Database::conexao()->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) VALUES ('Sem Preço')")->execute();
        $tipoId = (int) Database::conexao()->lastInsertId();

        $this->assertNull(Database::obterPrecoVigente($tipoId, '2025-01-01'));
    }

    public function testForaDePrazoAntesDaHoraLimite(): void
    {
        $tipoId = $this->criarTipoComPreco('Prazo Teste', 3.00);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '14:30:00', 1)"
        )->execute([$tipoId]);

        // Uma data suficientemente longe no futuro nunca deve estar fora de prazo
        $dataFutura = date('Y-m-d', strtotime('+10 days'));
        $this->assertFalse(Database::foraDePrazo($tipoId, $dataFutura));
    }

    public function testForaDePrazoDepoisDaHoraLimite(): void
    {
        $tipoId = $this->criarTipoComPreco('Prazo Passado', 3.00);
        Database::conexao()->prepare(
            "INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES (?, '00:00:00', 0)"
        )->execute([$tipoId]);

        // Prazo às 00:00 do próprio dia — uma data de ontem já expirou de certeza
        $dataPassada = date('Y-m-d', strtotime('-1 day'));
        $this->assertTrue(Database::foraDePrazo($tipoId, $dataPassada));
    }
}