<?php
require_once __DIR__ . '/../Infrastructure/Database.php';

class PagamentoService {

    public static function processar(int $pedidoId, bool $sucesso, ?string $refGatewayBatch = null): array {
        return self::simular($pedidoId, $sucesso, $refGatewayBatch);
    }

    private static function simular(int $pedidoId, bool $sucesso, ?string $refGatewayBatch = null): array {
        $estado = $sucesso ? 'sucesso' : 'falhado';

        Database::registarTentativaPagamento($pedidoId, $estado, $refGatewayBatch);

        if (!$sucesso) {
            return ['status' => 'falhado'];
        }

        Database::marcarPedidoComoPago($pedidoId);

        return ['status' => 'confirmado'];
    }
}