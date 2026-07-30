<?php

require_once __DIR__ . '/../Infrastructure/Database.php';

/**
 * Serviço responsável pelo processamento de pagamentos.
 */
class PagamentoService
{
    /**
     * Processa o resultado de um pagamento.
     *
     * @param int $pedidoId Identificador do pedido.
     * @param bool $sucesso Indica se o pagamento foi concluído com sucesso.
     * @param string|null $refGatewayBatch Referência do lote enviada pelo gateway.
     *
     * @return array Resultado do processamento.
     */
    public static function processar(
        int $pedidoId,
        bool $sucesso,
        ?string $refGatewayBatch = null
    ): array {
        return self::simular($pedidoId, $sucesso, $refGatewayBatch);
    }

    private static function simular(
        int $pedidoId,
        bool $sucesso,
        ?string $refGatewayBatch = null
    ): array {
        $estado = $sucesso ? 'sucesso' : 'falhado';
        $pdo = Database::conexao();

        $pdo->beginTransaction();

        try {
            Database::registarTentativaPagamento(
                $pedidoId,
                $estado,
                $refGatewayBatch
            );

            if ($sucesso) {
                Database::marcarPedidoComoPago($pedidoId);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'status' => $sucesso ? 'confirmado' : 'falhado'
        ];
    }
}