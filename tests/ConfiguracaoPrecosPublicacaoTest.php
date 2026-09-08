<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class ConfiguracaoPrecosPublicacaoTest extends DatabaseTestCase
{
    public function testConfiguracaoGravarEObter(): void
    {
        Database::garantirTabelaConfiguracao();
        $chaveTeste = 'teste_unitario_chave_' . bin2hex(random_bytes(4));
        $valorTeste = 'valor_teste_123';

        $ok = Database::gravarConfiguracao($chaveTeste, $valorTeste);
        $this->assertTrue($ok);

        $obtido = Database::obterConfiguracao($chaveTeste);
        $this->assertSame($valorTeste, $obtido);

        // Atualização da mesma chave
        $novoValor = 'valor_alterado_456';
        $ok2 = Database::gravarConfiguracao($chaveTeste, $novoValor);
        $this->assertTrue($ok2);

        $this->assertSame($novoValor, Database::obterConfiguracao($chaveTeste));

        // Limpeza
        Database::conexao()->prepare("DELETE FROM restaurante_configuracao WHERE RC_CHAVE = ?")->execute([$chaveTeste]);
    }

    public function testConfiguracaoPublicacaoEmentaPadraoEAtualizacao(): void
    {
        $original = Database::obterConfiguracaoPublicacaoEmenta();
        $this->assertArrayHasKey('dias_antecedencia', $original);
        $this->assertArrayHasKey('hora', $original);
        $this->assertArrayHasKey('texto', $original);

        // Atualizar para quinta-feira às 18h00 (4 dias de antecedência)
        $res = Database::atualizarConfiguracaoPublicacaoEmenta(4, '18:00');
        $this->assertTrue($res);

        $atual = Database::obterConfiguracaoPublicacaoEmenta();
        $this->assertSame(4, $atual['dias_antecedencia']);
        $this->assertSame('18:00:00', $atual['hora']);
        $this->assertStringContainsString('18h00', $atual['texto']);

        // Validação de erro com hora inválida
        $invalido = Database::atualizarConfiguracaoPublicacaoEmenta(3, '99:99');
        $this->assertSame('hora_invalida', $invalido);

        // Validação de erro com antecedência fora dos limites
        $invalidoAnt = Database::atualizarConfiguracaoPublicacaoEmenta(25, '12:00');
        $this->assertSame('antecedencia_invalida', $invalidoAnt);

        // Restaura original
        Database::atualizarConfiguracaoPublicacaoEmenta((int) $original['dias_antecedencia'], substr($original['hora'], 0, 5));
    }

    public function testSemanaJaVisivelParaAlunosComHorarioConfigurado(): void
    {
        $segundaFutura = date('Y-m-d', strtotime('next Monday +3 weeks'));
        $sextaFutura = date('Y-m-d', strtotime("$segundaFutura +4 days"));

        // Com publicação 1 dia antes (domingo), uma ementa daqui a 3 semanas não deve estar visível hoje
        Database::atualizarConfiguracaoPublicacaoEmenta(1, '14:30');
        $this->assertFalse(Database::semanaJaVisivelParaAlunos($segundaFutura, $sextaFutura));

        // Uma semana passada (ex: 2 semanas atrás) deve estar visível
        $segundaPassada = date('Y-m-d', strtotime('last Monday -2 weeks'));
        $sextaPassada = date('Y-m-d', strtotime("$segundaPassada +4 days"));
        $this->assertTrue(Database::semanaJaVisivelParaAlunos($segundaPassada, $sextaPassada));

        // Restaura
        Database::atualizarConfiguracaoPublicacaoEmenta(3, '14:30');
    }

    public function testListarPrecosVigentesTodosTipos(): void
    {
        $precos = Database::listarPrecosVigentesTodosTipos();
        $this->assertNotEmpty($precos);

        $nomes = array_column($precos, 'RTP_NOME');
        $this->assertContains('Carne', $nomes);
        $this->assertContains('Peixe', $nomes);
        $this->assertContains('Vegetariano', $nomes);
        $this->assertContains('Menu Completo', $nomes);
        $this->assertContains('Sopa', $nomes);
        $this->assertContains('Sobremesa', $nomes);
        $this->assertContains('Bebida', $nomes);
    }

    public function testAtualizarPrecoRefeicaoPreservaHistorico(): void
    {
        $tipoId = Database::obterTipoIdPorNome('Carne');
        $this->assertNotNull($tipoId);

        $precoAntes = Database::obterPrecoVigente($tipoId, date('Y-m-d'));

        // Conta quantos registos de preço existiam antes
        $stmtContagem = Database::conexao()->prepare("SELECT COUNT(*) FROM restaurante_preco_tipo_refeicao WHERE RPTR_TP_ID = ?");
        $stmtContagem->execute([$tipoId]);
        $contagemAntes = (int) $stmtContagem->fetchColumn();

        // Altera o preço
        $novoPreco = 3.99;
        $res = Database::atualizarPrecoRefeicao($tipoId, $novoPreco);
        $this->assertTrue($res);

        // Preço atual deve agora ser o novo
        $precoDepois = Database::obterPrecoVigente($tipoId, date('Y-m-d'));
        $this->assertSame($novoPreco, $precoDepois);

        // Deve existir mais um registo (histórico preservado)
        $stmtContagem->execute([$tipoId]);
        $contagemDepois = (int) $stmtContagem->fetchColumn();
        $this->assertGreaterThan($contagemAntes, $contagemDepois);

        // Restaura o preço anterior
        if ($precoAntes !== null) {
            Database::atualizarPrecoRefeicao($tipoId, $precoAntes);
        }
    }
}
