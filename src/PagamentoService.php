<?php
require_once __DIR__ . '/Database.php';

class PagamentoService {

    public static function processar(int $compra_id, bool $sucesso): array {
        return self::simular($compra_id, $sucesso);
    }

    private static function simular(int $compra_id, bool $sucesso): array {
        $pdo = Database::conexao();
        $estado = $sucesso ? 'sucesso' : 'falhado';

        $pdo->prepare("INSERT INTO pagamentos (compra_id, metodo, ref_gateway, estado_pagamento) 
                        VALUES (?, 'simulado', ?, ?)")
            ->execute([$compra_id, 'SIM-' . uniqid(), $estado]);

        if (!$sucesso) {
            return ['status' => 'falhado'];
        }

        $stmt = $pdo->prepare("UPDATE compras SET estado = 'paga' WHERE id = ? AND estado = 'pendente'");
        $stmt->execute([$compra_id]);

        return ['status' => $stmt->rowCount() === 1 ? 'paga' : 'erro'];
    }
}