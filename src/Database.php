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

    public static function obterRefeicao(int $refeicao_id): array|false {
        $stmt = self::conexao()->prepare("SELECT * FROM refeicoes_dev WHERE id = ?");
        $stmt->execute([$refeicao_id]);
        return $stmt->fetch();
    }

    public static function criarCompra(int $comprador_id, int $refeicao_id): int|string {
        $pdo = self::conexao();

        $refeicao = self::obterRefeicao($refeicao_id);
        if (!$refeicao) {
            return 'refeicao_invalida';
        }

        $codigo_pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO compras (comprador_id, refeicao_id, estado, codigo_pin, preco_total, data_refeicao) 
                                VALUES (?, ?, 'pendente', ?, ?, ?)");
        $stmt->execute([$comprador_id, $refeicao_id, $codigo_pin, $refeicao['preco'], $refeicao['data_refeicao']]);

        return (int) $pdo->lastInsertId();
    }

    public static function obterCompra(int $compra_id): array|false {
        $stmt = self::conexao()->prepare("SELECT * FROM compras WHERE id = ?");
        $stmt->execute([$compra_id]);
        return $stmt->fetch();
    }

   public static function validarPorNumeroAlunoPin(string $numero, string $pin, int $funcionario_id): array {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("SELECT id, nome FROM utilizadores_dev WHERE numero = ?");
        $stmt->execute([$numero]);
        $pessoa = $stmt->fetch();

        if (!$pessoa) {
            return ['status' => 'invalido'];
        }

        // Rate limiting: bloqueia após 5 tentativas falhadas nos últimos 10 minutos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tentativas_pin WHERE numero = ? AND data_tentativa > DATEADD(MINUTE, -10, GETDATE())");
        $stmt->execute([$numero]);
        if ((int) $stmt->fetchColumn() >= 5) {
            return ['status' => 'bloqueado'];
        }

        $stmt = $pdo->prepare("SELECT id, estado, codigo_pin FROM compras 
                                WHERE comprador_id = ? AND data_refeicao = CAST(GETDATE() AS DATE)");
        $stmt->execute([$pessoa['id']]);
        $compra = $stmt->fetch();

        // Falha se não há compra para hoje, OU se o PIN não bate certo
        if (!$compra || $compra['codigo_pin'] !== $pin) {
            $pdo->prepare("INSERT INTO tentativas_pin (numero) VALUES (?)")->execute([$numero]);
            return ['status' => 'invalido'];
        }

        if ($compra['estado'] !== 'paga') {
            return ['status' => $compra['estado'] === 'utilizada' ? 'ja_usada' : 'pendente', 'nome' => $pessoa['nome']];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE compras SET estado = 'utilizada' WHERE id = ? AND estado = 'paga'");
            $stmt->execute([$compra['id']]);

            if ($stmt->rowCount() === 1) {
                $pdo->prepare("INSERT INTO validacoes (compra_id, funcionario_id, metodo_leitura) VALUES (?, ?, 'numero_pin')")
                    ->execute([$compra['id'], $funcionario_id]);
                $pdo->commit();
                return ['status' => 'valido', 'nome' => $pessoa['nome']];
            }

            $pdo->rollBack();
            return ['status' => 'ja_usada', 'nome' => $pessoa['nome']];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'erro'];
        }
    }

    public static function contarValidacoesHoje(int $funcionario_id): int {
        $stmt = self::conexao()->prepare("SELECT COUNT(*) FROM validacoes 
                                            WHERE funcionario_id = ? AND CAST(data_validacao AS DATE) = CAST(GETDATE() AS DATE)");
        $stmt->execute([$funcionario_id]);
        return (int) $stmt->fetchColumn();
    }

    public static function validarPorCartao(string $numero_lido, int $funcionario_id): array {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("SELECT id, nome FROM utilizadores_dev WHERE numero = ?");
        $stmt->execute([$numero_lido]);
        $pessoa = $stmt->fetch();

        if (!$pessoa) {
            return ['status' => 'invalido'];
        }

        $stmt = $pdo->prepare("SELECT id, estado FROM compras 
                                WHERE comprador_id = ? AND data_refeicao = CAST(GETDATE() AS DATE)");
        $stmt->execute([$pessoa['id']]);
        $compra = $stmt->fetch();

        if (!$compra) {
            return ['status' => 'invalido', 'nome' => $pessoa['nome']];
        }
        if ($compra['estado'] !== 'paga') {
            return ['status' => $compra['estado'] === 'utilizada' ? 'ja_usada' : 'pendente', 'nome' => $pessoa['nome']];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE compras SET estado = 'utilizada' WHERE id = ? AND estado = 'paga'");
            $stmt->execute([$compra['id']]);

            if ($stmt->rowCount() === 1) {
                $pdo->prepare("INSERT INTO validacoes (compra_id, funcionario_id, metodo_leitura) VALUES (?, ?, 'cartao')")
                    ->execute([$compra['id'], $funcionario_id]);
                $pdo->commit();
                return ['status' => 'valido', 'nome' => $pessoa['nome']];
            }

            $pdo->rollBack();
            return ['status' => 'ja_usada', 'nome' => $pessoa['nome']];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'erro'];
        }
    }

     public static function autenticar(string $numero, string $password): array|false {
        $stmt = self::conexao()->prepare("SELECT id, nome, tipo, password FROM utilizadores_dev WHERE numero = ?");
        $stmt->execute([$numero]);
        $utilizador = $stmt->fetch();

        if (!$utilizador || $utilizador['password'] !== $password) {
            return false;
        }

        return $utilizador;
    }
}