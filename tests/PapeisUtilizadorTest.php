<?php

require_once __DIR__ . '/DatabaseTestCase.php';

final class PapeisUtilizadorTest extends DatabaseTestCase
{
    public function testAtribuirPapelComSucesso(): void
    {
        $userId = $this->criarUtilizadorTeste('90000050', 'Futuro Atendente', 2);

        $resultado = Database::atribuirPapel($userId, 'atendente');

        $this->assertSame('ok', $resultado);
        $this->assertTrue(Database::temPapel($userId, 'atendente'));
    }

    public function testAtribuirPapelJaExistenteDevolveJaExiste(): void
    {
        $userId = $this->criarUtilizadorTeste('90000051', 'Func', 2);
        Database::atribuirPapel($userId, 'atendente');

        $resultado = Database::atribuirPapel($userId, 'atendente');

        $this->assertSame('ja_existe', $resultado);
    }

    public function testAtribuirPapelInvalidoEhRejeitado(): void
    {
        $userId = $this->criarUtilizadorTeste('90000052', 'Func', 2);

        $resultado = Database::atribuirPapel($userId, 'super_admin');

        $this->assertSame('papel_invalido', $resultado);
    }

    public function testRevogarPapelComSucesso(): void
    {
        $userId = $this->criarUtilizadorTeste('90000053', 'Func', 2);
        Database::atribuirPapel($userId, 'atendente');

        $resultado = Database::revogarPapel($userId, 'atendente');

        $this->assertSame('ok', $resultado);
        $this->assertFalse(Database::temPapel($userId, 'atendente'));
    }

    public function testRevogarUltimoAdminCantinaEhBloqueado(): void
    {
        // Garante um estado limpo: remove todos os admin_cantina existentes
        // (incluindo os de dados reais/demo) antes de testar a proteção,
        // e restaura-os no fim para não afetar o resto do sistema.
        $pdo = Database::conexao();
        $adminsExistentes = $pdo->query("SELECT RPU_U_ID FROM restaurante_papel_utilizador WHERE RPU_PAPEL = 'admin_cantina'")->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM restaurante_papel_utilizador WHERE RPU_PAPEL = 'admin_cantina'")->execute();

        $userId = $this->criarUtilizadorTeste('90000054', 'Único Admin', 2);
        Database::atribuirPapel($userId, 'admin_cantina');

        $resultado = Database::revogarPapel($userId, 'admin_cantina');

        $this->assertSame('ultimo_admin', $resultado);
        $this->assertTrue(Database::temPapel($userId, 'admin_cantina'), 'O papel não devia ter sido removido');

        // Restaura o estado anterior (relevante se corrermos contra uma BD partilhada)
        foreach ($adminsExistentes as $adminId) {
            $pdo->prepare("INSERT INTO restaurante_papel_utilizador (RPU_U_ID, RPU_PAPEL) VALUES (?, 'admin_cantina')")->execute([$adminId]);
        }
    }
}