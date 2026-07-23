<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $instancia = null;

    public static function conexao(): PDO {
        if (self::$instancia === null) {
            $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes";
            self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }
        return self::$instancia;
    }

    public static function criarCompra(int $comprador_id, int $refeicao_id, float $preco, string $data_refeicao): int {
        $pdo = self::conexao();
        $codigo_pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO compras (comprador_id, refeicao_id, estado, codigo_pin, preco_total, data_refeicao) 
                                VALUES (?, ?, 'pendente', ?, ?, ?)");
        $stmt->execute([$comprador_id, $refeicao_id, $codigo_pin, $preco, $data_refeicao]);

        return (int) $pdo->lastInsertId();
    }

    public static function obterCompra(int $compra_id): array|false {
        $stmt = self::conexao()->prepare("SELECT * FROM compras WHERE id = ?");
        $stmt->execute([$compra_id]);
        return $stmt->fetch();
    }

    public static function validarPorNumeroAlunoPin(string $numero, string $pin, int $funcionario_id): array {
    $pdo = self::conexao();

    // 1. Encontra a pessoa pelo número (aluno ou funcionário), não pelo comprador_id diretamente
    $stmt = $pdo->prepare("SELECT id, nome FROM utilizadores_dev WHERE numero = ?");
    $stmt->execute([$numero]);
    $pessoa = $stmt->fetch();

    if (!$pessoa) {
        return ['status' => 'invalido'];
    }

    $stmt = $pdo->prepare("SELECT id, estado FROM compras 
                            WHERE codigo_pin = ? AND comprador_id = ? AND data_refeicao = CAST(GETDATE() AS DATE)");
    $stmt->execute([$pin, $pessoa['id']]);
    $compra = $stmt->fetch();

    if (!$compra) {
        return ['status' => 'invalido'];
    }
    if ($compra['estado'] !== 'paga') {
        return ['status' => $compra['estado'] === 'utilizada' ? 'ja_usada' : 'pendente', 'nome' => $pessoa['nome']];
    }

    $stmt = $pdo->prepare("UPDATE compras SET estado = 'utilizada' WHERE id = ? AND estado = 'paga'");
    $stmt->execute([$compra['id']]);

    if ($stmt->rowCount() === 1) {
        $pdo->prepare("INSERT INTO validacoes (compra_id, funcionario_id, metodo_leitura) VALUES (?, ?, 'numero_pin')")
            ->execute([$compra['id'], $funcionario_id]);
        return ['status' => 'valido', 'nome' => $pessoa['nome']];
    }

    return ['status' => 'ja_usada', 'nome' => $pessoa['nome']];
}
}