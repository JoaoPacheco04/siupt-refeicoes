<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $instancia = null;

    public static function conexao(): PDO {
        if (self::$instancia === null) {
            $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes";
            self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instancia;
    }

    // ============================================
    // PONTO DE COSTURA 1 — trocar quando chegar o esquema real da UPT
    // (alunos/funcionarios separados, em vez de utilizadores_dev)
    // ============================================
    private static function obterUtilizadorPorNumero(string $numero): array|false {
        $stmt = self::conexao()->prepare("SELECT id, nome, tipo, password FROM utilizadores_dev WHERE numero = ?");
        $stmt->execute([$numero]);
        return $stmt->fetch();
    }

    public static function obterRefeicao(int $refeicao_id): array|false {
        $stmt = self::conexao()->prepare("SELECT * FROM refeicoes_dev WHERE id = ?");
        $stmt->execute([$refeicao_id]);
        return $stmt->fetch();
    }

    public static function criarCompra(int $comprador_id, int $refeicao_id, ?string $pedido_especial = null): int|string {
        $pdo = self::conexao();

        $refeicao = self::obterRefeicao($refeicao_id);
        if (!$refeicao) {
            return 'refeicao_invalida';
        }

        $limite = date('Y-m-d 10:00:00', strtotime($refeicao['data_refeicao'] . ' -1 day'));
        if (date('Y-m-d H:i:s') > $limite) {
            return 'fora_de_prazo';
        }

        $codigo_pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $stmt = $pdo->prepare("INSERT INTO compras (comprador_id, refeicao_id, estado, codigo_pin, pedido_especial, preco_total, data_refeicao) 
                                    VALUES (?, ?, 'pendente', ?, ?, ?, ?)");
            $stmt->execute([$comprador_id, $refeicao_id, $codigo_pin, $pedido_especial, $refeicao['preco'], $refeicao['data_refeicao']]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return 'ja_comprado';
            }
            throw $e;
        }
    }

    public static function obterCompra(int $compra_id): array|false {
        $stmt = self::conexao()->prepare("SELECT * FROM compras WHERE id = ?");
        $stmt->execute([$compra_id]);
        return $stmt->fetch();
    }

    public static function obterCompraComEmail(int $compra_id): array|false {
        $stmt = self::conexao()->prepare("SELECT c.*, r.sopa, r.prato_principal, u.email
                                            FROM compras c
                                            JOIN refeicoes_dev r ON c.refeicao_id = r.id
                                            JOIN utilizadores_dev u ON c.comprador_id = u.id
                                            WHERE c.id = ?");
        $stmt->execute([$compra_id]);
        return $stmt->fetch();
    }

    public static function listarComprasDoAluno(int $comprador_id): array {
        $stmt = self::conexao()->prepare("SELECT c.*, r.sopa, r.prato_principal 
                                            FROM compras c
                                            JOIN refeicoes_dev r ON c.refeicao_id = r.id
                                            WHERE c.comprador_id = ?
                                            ORDER BY c.data_refeicao DESC");
        $stmt->execute([$comprador_id]);
        return $stmt->fetchAll();
    }

    public static function refeicoesJaCompradas(int $comprador_id): array {
        $stmt = self::conexao()->prepare("SELECT DISTINCT refeicao_id FROM compras WHERE comprador_id = ? AND estado != 'cancelada_pela_cantina'");
        $stmt->execute([$comprador_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function validarPorNumeroAlunoPin(string $numero, string $pin, int $funcionario_id): array {
        $pdo = self::conexao();

        $pessoa = self::obterUtilizadorPorNumero($numero);
        if (!$pessoa) {
            return ['status' => 'invalido'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tentativas_pin WHERE numero = ? AND data_tentativa > DATEADD(MINUTE, -10, GETDATE())");
        $stmt->execute([$numero]);
        if ((int) $stmt->fetchColumn() >= 5) {
            return ['status' => 'bloqueado'];
        }

        $stmt = $pdo->prepare("SELECT TOP 1 id, estado, codigo_pin, pedido_especial FROM compras 
                                WHERE comprador_id = ? AND data_refeicao = CAST(GETDATE() AS DATE)
                                ORDER BY id DESC");
        $stmt->execute([$pessoa['id']]);
        $compra = $stmt->fetch();

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
                return ['status' => 'valido', 'nome' => $pessoa['nome'], 'pedido_especial' => $compra['pedido_especial']];
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

    public static function validarPorCartao(string $uid_lido, int $funcionario_id): array {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("SELECT id, nome FROM utilizadores_dev WHERE numero_cartao_uid = ?");
        $stmt->execute([$uid_lido]);
        $pessoa = $stmt->fetch();

        if (!$pessoa) {
            $pdo->prepare("INSERT INTO tentativas_pin (numero) VALUES (?)")->execute([$uid_lido]);
            return ['status' => 'invalido'];
        }

        $stmt = $pdo->prepare("SELECT TOP 1 id, estado, pedido_especial FROM compras 
                                WHERE comprador_id = ? AND data_refeicao = CAST(GETDATE() AS DATE)
                                ORDER BY id DESC");
        $stmt->execute([$pessoa['id']]);
        $compra = $stmt->fetch();

        if (!$compra) {
            $pdo->prepare("INSERT INTO tentativas_pin (numero) VALUES (?)")->execute([$uid_lido]);
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
                return ['status' => 'valido', 'nome' => $pessoa['nome'], 'pedido_especial' => $compra['pedido_especial']];
            }

            $pdo->rollBack();
            return ['status' => 'ja_usada', 'nome' => $pessoa['nome']];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'erro'];
        }
    }

    public static function vincularCartao(int $utilizador_id, string $uid): array {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("SELECT id FROM utilizadores_dev WHERE numero_cartao_uid = ? AND id != ?");
        $stmt->execute([$uid, $utilizador_id]);
        if ($stmt->fetch()) {
            return ['status' => 'uid_ja_associado'];
        }

        $stmt = $pdo->prepare("UPDATE utilizadores_dev SET numero_cartao_uid = ? WHERE id = ?");
        $stmt->execute([$uid, $utilizador_id]);

        return ['status' => 'vinculado'];
    }

    public static function autenticar(string $numero, string $password): array|false {
        $utilizador = self::obterUtilizadorPorNumero($numero);
        if (!$utilizador || $utilizador['password'] !== $password) {
            return false;
        }
        return $utilizador;
    }
}