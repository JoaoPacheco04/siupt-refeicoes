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
}