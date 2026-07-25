<?php

require_once __DIR__ . '/../../config/config.php';

class Database {
    private static ?PDO $instancia = null;

    private const PERFIL_ALUNO = 1;
    private const PERFIL_FUNCIONARIO = 2;

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
    // Utilizadores
    // ============================================
    public static function obterUtilizadorPorBICC(string $bicc): array|false {
        $stmt = self::conexao()->prepare("SELECT U_ID, U_BICC, U_PASS, U_NOME, U_EMAIL, U_PERFIL FROM users WHERE U_BICC = ?");
        $stmt->execute([$bicc]);
        return $stmt->fetch();
    }

    public static function autenticar(string $bicc, string $password): array|false {
        $utilizador = self::obterUtilizadorPorBICC($bicc);
        if (!$utilizador || !password_verify($password, $utilizador['U_PASS'])) {
            return false;
        }
        return $utilizador;
    }

    public static function perfilParaTipo(int $perfil): string {
        return $perfil === self::PERFIL_FUNCIONARIO ? 'funcionario' : 'aluno';
    }

    // ============================================
    // Menu e preços
    // ============================================
    public static function listarPratosEmentaSemana(string $inicio, string $fim): array {
        $stmt = self::conexao()->prepare("
            SELECT rm.RM_ID, rm.RM_NOME, rm.RM_DATA, rm.RM_TP_ID, rtp.RTP_NOME, rtp.RM_PRATO_DIA
            FROM restaurante_menu rm
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rm.RM_DATA BETWEEN ? AND ?
            ORDER BY rm.RM_DATA, rtp.RTP_NOME
        ");
        $stmt->execute([$inicio, $fim]);
        return $stmt->fetchAll();
    }

    public static function listarPratosExtras(): array {
        $stmt = self::conexao()->prepare("
            SELECT rm.RM_ID, rm.RM_NOME, rm.RM_TP_ID, rtp.RTP_NOME
            FROM restaurante_menu rm
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rm.RM_DATA IS NULL
            ORDER BY rtp.RTP_NOME, rm.RM_NOME
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function obterTipoIdPorNome(string $nome): ?int {
        $stmt = self::conexao()->prepare("SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = ?");
        $stmt->execute([$nome]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public static function obterPrecoVigente(int $tipoRefeicaoId, string $dataPedido): ?float {
        $stmt = self::conexao()->prepare("
            SELECT TOP 1 RPTR_PRECO
            FROM restaurante_preco_tipo_refeicao
            WHERE RPTR_TP_ID = ? AND RPTR_DATAINICIO <= ?
            ORDER BY RPTR_DATAINICIO DESC
        ");
        $stmt->execute([$tipoRefeicaoId, $dataPedido]);
        $preco = $stmt->fetchColumn();
        return $preco !== false ? (float) $preco : null;
    }

    /**
     * Resolve preços vigentes para vários tipos numa única query (evita N+1 na ementa).
     * Retorna [tipo_id => preco]
     */
    public static function obterPrecosVigentesBatch(array $tipoIds, string $data): array {
        if (empty($tipoIds)) return [];
        $tipoIds = array_values(array_unique(array_map('intval', $tipoIds)));
        $ph = implode(',', array_fill(0, count($tipoIds), '?'));
        $stmt = self::conexao()->prepare("
            SELECT x.RPTR_TP_ID, x.RPTR_PRECO
            FROM (
                SELECT RPTR_TP_ID, RPTR_PRECO,
                       ROW_NUMBER() OVER (PARTITION BY RPTR_TP_ID ORDER BY RPTR_DATAINICIO DESC) AS rn
                FROM restaurante_preco_tipo_refeicao
                WHERE RPTR_DATAINICIO <= ? AND RPTR_TP_ID IN ($ph)
            ) x
            WHERE x.rn = 1
        ");
        $stmt->execute(array_merge([$data], $tipoIds));
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[(int) $row['RPTR_TP_ID']] = (float) $row['RPTR_PRECO'];
        }
        return $resultado;
    }

    /**
     * Busca linhas de vários pedidos numa única query (evita N+1 no histórico).
     * Retorna [pedido_id => [linhas]]
     */
    public static function listarLinhasDePedidos(array $pedidoIds): array {
        if (empty($pedidoIds)) return [];
        $ph = implode(',', array_fill(0, count($pedidoIds), '?'));
        $stmt = self::conexao()->prepare("
            SELECT rc.RC_RP_ID, rc.RC_MENU_COMPLETO, rc.RC_PRECO, rm.RM_NOME, rtp.RTP_NOME
            FROM restaurante_compra rc
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rc.RC_RP_ID IN ($ph)
        ");
        $stmt->execute($pedidoIds);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[(int) $row['RC_RP_ID']][] = $row;
        }
        return $resultado;
    }

    /**
     * Devolve as datas (de uma lista) para as quais o utilizador já tem pedido ativo.
     */
    public static function listarDatasComPedidoAtivo(int $utilizadorId, array $datas): array {
        if (empty($datas)) return [];
        $ph = implode(',', array_fill(0, count($datas), '?'));
        $hoje = date('Y-m-d');
        $stmt = self::conexao()->prepare("
            SELECT DISTINCT RP_DATA_REFEICAO
            FROM restaurante_pedido
            WHERE RP_U_ID = ? AND RP_DATA_REFEICAO IN ($ph)
              AND RP_UTILIZADO = 0 AND RP_PAGO = 1 AND RP_DATA_REFEICAO >= ?
        ");
        $stmt->execute(array_merge([$utilizadorId], $datas, [$hoje]));
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Devolve os itens extras já comprados (pago, ativo) numa lista de datas,
     * como "rm_id|data", para evitar duplicados de extras.
     */
    public static function listarItensExtrasComprados(int $utilizadorId, array $datas): array {
        if (empty($datas)) return [];
        $ph = implode(',', array_fill(0, count($datas), '?'));
        $stmt = self::conexao()->prepare("
            SELECT DISTINCT rc.RC_RM_ID, rp.RP_DATA_REFEICAO
            FROM restaurante_compra rc
            JOIN restaurante_pedido rp ON rc.RC_RP_ID = rp.RP_ID
            WHERE rp.RP_U_ID = ? AND rp.RP_UTILIZADO = 0 AND rp.RP_PAGO = 1
              AND rp.RP_DATA_REFEICAO IN ($ph)
        ");
        $stmt->execute(array_merge([$utilizadorId], $datas));
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[] = $row['RC_RM_ID'] . '|' . $row['RP_DATA_REFEICAO'];
        }
        return $resultado;
    }

    public static function obterDataLimite(int $tipoRefeicaoId): array|false {
        $stmt = self::conexao()->prepare("SELECT RDL_HORA, RDL_DIA_ANTECEDENCIA FROM restaurante_data_limite WHERE RDL_RTP_ID = ?");
        $stmt->execute([$tipoRefeicaoId]);
        return $stmt->fetch();
    }

    public static function obterDataLimitePrincipalTexto(): ?string {
        foreach (['Carne', 'Peixe', 'Vegetariano'] as $tipo) {
            $tipoId = self::obterTipoIdPorNome($tipo);
            if ($tipoId === null) continue;
            $limite = self::obterDataLimite($tipoId);
            if (!$limite) continue;

            $hora = rtrim(substr($limite['RDL_HORA'], 0, 5), ':');
            $dias = (int) $limite['RDL_DIA_ANTECEDENCIA'];

            if ($dias === 1) {
                return "até às {$hora} do dia anterior";
            } elseif ($dias === 0) {
                return "até às {$hora} do próprio dia";
            } else {
                return "até às {$hora} com {$dias} dias de antecedência";
            }
        }
        return null;
    }

    public static function foraDePrazo(int $tipoRefeicaoId, string $dataRefeicao): bool {
        $limite = self::obterDataLimite($tipoRefeicaoId);
        if (!$limite) {
            return false;
        }
        $dataLimite = date(
            'Y-m-d ' . $limite['RDL_HORA'],
            strtotime($dataRefeicao . ' -' . $limite['RDL_DIA_ANTECEDENCIA'] . ' days')
        );
        return date('Y-m-d H:i:s') > $dataLimite;
    }

    // ============================================
    // Pedidos e compras
    // ============================================

    /**
     * $itens = [ ['rm_id' => int, 'menu_completo' => bool], ... ]
     */
    public static function criarPedido(int $utilizadorId, string $dataRefeicao, array $itens): int|string {
        if (empty($itens)) {
            return 'sem_itens';
        }

        $pdo = self::conexao();
        $pdo->beginTransaction();

        try {
            $precoTotal = 0;
            $linhasValidadas = [];

            foreach ($itens as $item) {
                $stmt = $pdo->prepare("SELECT RM_ID, RM_TP_ID, RM_DATA FROM restaurante_menu WHERE RM_ID = ?");
                $stmt->execute([$item['rm_id']]);
                $prato = $stmt->fetch();

                if (!$prato) {
                    $pdo->rollBack();
                    return 'prato_invalido';
                }

                $menuCompleto = !empty($item['menu_completo']);

                if ($menuCompleto && $prato['RM_DATA'] === null) {
                    $pdo->rollBack();
                    return 'menu_completo_invalido_para_extra';
                }

                if (self::foraDePrazo((int) $prato['RM_TP_ID'], $dataRefeicao)) {
                    $pdo->rollBack();
                    return 'fora_de_prazo';
                }

                if ($menuCompleto) {
                    $tipoPrecoId = self::obterTipoIdPorNome('Menu Completo');
                    if ($tipoPrecoId === null) {
                        $pdo->rollBack();
                        return 'menu_completo_nao_configurado';
                    }
                } else {
                    $tipoPrecoId = (int) $prato['RM_TP_ID'];
                }

                $preco = self::obterPrecoVigente($tipoPrecoId, $dataRefeicao);
                if ($preco === null) {
                    $pdo->rollBack();
                    return 'sem_preco_definido';
                }

                $linhasValidadas[] = [
                    'rm_id' => $prato['RM_ID'],
                    'menu_completo' => $menuCompleto,
                    'preco' => $preco,
                ];
                $precoTotal += $preco;
            }

            $qrcode = bin2hex(random_bytes(24)); // 48 caracteres, imprevisível

            $stmt = $pdo->prepare("INSERT INTO restaurante_pedido (RP_U_ID, RP_DATA_REFEICAO, RP_PRECO_TOTAL, RP_QRCODE, RP_UTILIZADO)
                                    VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$utilizadorId, $dataRefeicao, $precoTotal, $qrcode]);
            $pedidoId = (int) $pdo->lastInsertId();

            // Código curto aleatório — verifica colisão antes de gravar (extremamente raro, mas seguro)
do {
    $codigoCurto = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $existe = $pdo->prepare("SELECT 1 FROM restaurante_pedido WHERE RP_CODIGO_CURTO = ?");
    $existe->execute([$codigoCurto]);
} while ($existe->fetch());

$pdo->prepare("UPDATE restaurante_pedido SET RP_CODIGO_CURTO = ? WHERE RP_ID = ?")
    ->execute([$codigoCurto, $pedidoId]);

            $stmtLinha = $pdo->prepare("INSERT INTO restaurante_compra (RC_RP_ID, RC_MENU_COMPLETO, RC_RM_ID, RC_PRECO)
                                         VALUES (?, ?, ?, ?)");
            foreach ($linhasValidadas as $linha) {
                $stmtLinha->execute([$pedidoId, $linha['menu_completo'] ? 1 : 0, $linha['rm_id'], $linha['preco']]);
            }

            $pdo->commit();
            return $pedidoId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function obterPedido(int $pedidoId): array|false {
        $stmt = self::conexao()->prepare("SELECT * FROM restaurante_pedido WHERE RP_ID = ?");
        $stmt->execute([$pedidoId]);
        return $stmt->fetch();
    }

    public static function obterPedidoComEmail(int $pedidoId): array|false {
        $stmt = self::conexao()->prepare("
            SELECT rp.*, u.U_EMAIL, u.U_NOME
            FROM restaurante_pedido rp
            JOIN users u ON rp.RP_U_ID = u.U_ID
            WHERE rp.RP_ID = ?
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetch();
    }

    public static function listarLinhasDoPedido(int $pedidoId): array {
        $stmt = self::conexao()->prepare("
            SELECT rc.*, rm.RM_NOME, rtp.RTP_NOME
            FROM restaurante_compra rc
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rc.RC_RP_ID = ?
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll();
    }

    public static function listarPedidosDoUtilizador(int $utilizadorId): array {
        $stmt = self::conexao()->prepare("
            SELECT * FROM restaurante_pedido
            WHERE RP_U_ID = ?
            ORDER BY RP_DATA_REFEICAO DESC
        ");
        $stmt->execute([$utilizadorId]);
        $pedidos = $stmt->fetchAll();

        foreach ($pedidos as &$p) {
            $p['estado'] = self::calcularEstadoPedido($p);
        }
        return $pedidos;
    }

    // "Vencido" não é guardado — é sempre calculado no momento da leitura.
    public static function calcularEstadoPedido(array $pedido): string {
        if (!$pedido['RP_PAGO']) {
            return 'nao_pago';
        }
        if ($pedido['RP_UTILIZADO']) {
            return 'utilizado';
        }
        if ($pedido['RP_DATA_REFEICAO'] < date('Y-m-d')) {
            return 'vencido';
        }
        return 'ativo';
    }

    // ============================================
    // Pagamento (simulado)
    // ============================================
    public static function registarTentativaPagamento(int $pedidoId, string $estado, ?string $refGateway = null): void {
        $ref = $refGateway ?? ('SIM-' . uniqid());
        self::conexao()->prepare("
            INSERT INTO restaurante_pagamento (RPG_RP_ID, RPG_METODO, RPG_REF_GATEWAY, RPG_ESTADO)
            VALUES (?, 'simulado', ?, ?)
        ")->execute([$pedidoId, $ref, $estado]);
    }

    public static function marcarPedidoComoPago(int $pedidoId): void {
        self::conexao()->prepare("UPDATE restaurante_pedido SET RP_PAGO = 1 WHERE RP_ID = ?")
            ->execute([$pedidoId]);
    }

    // ============================================
    // Validação por QR code ou código curto (lado do funcionário)
    // ============================================
    public static function validarPorQrCode(string $qrcode, int $funcionarioId): array {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("
            SELECT rp.*, u.U_NOME FROM restaurante_pedido rp 
            JOIN users u ON rp.RP_U_ID = u.U_ID 
            WHERE rp.RP_QRCODE = ? OR rp.RP_CODIGO_CURTO = ?
        ");
        $stmt->execute([$qrcode, strtoupper(trim($qrcode))]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            return ['status' => 'invalido'];
        }

        $estado = self::calcularEstadoPedido($pedido);
        if ($estado !== 'ativo') {
            return ['status' => $estado, 'nome' => $pedido['U_NOME']];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE restaurante_pedido SET RP_UTILIZADO = 1 WHERE RP_ID = ? AND RP_UTILIZADO = 0");
            $stmt->execute([$pedido['RP_ID']]);

            if ($stmt->rowCount() === 1) {
                $pdo->prepare("INSERT INTO restaurante_validacao (RV_RP_ID, RV_FUNCIONARIO_ID) VALUES (?, ?)")
                    ->execute([$pedido['RP_ID'], $funcionarioId]);
                $pdo->commit();
                return ['status' => 'valido', 'nome' => $pedido['U_NOME'], 'pedido_id' => $pedido['RP_ID']];
            }

            $pdo->rollBack();
            return ['status' => 'utilizado', 'nome' => $pedido['U_NOME']];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'erro'];
        }
    }

    public static function contarValidacoesHoje(int $funcionarioId): int {
        $stmt = self::conexao()->prepare("
            SELECT COUNT(*) FROM restaurante_validacao
            WHERE RV_FUNCIONARIO_ID = ? AND CAST(RV_DATA_VALIDACAO AS DATE) = CAST(GETDATE() AS DATE)
        ");
        $stmt->execute([$funcionarioId]);
        return (int) $stmt->fetchColumn();
    }

    public static function listarValidacoesHoje(int $funcionarioId): array {
        $stmt = self::conexao()->prepare("
            SELECT rv.RV_ID, rv.RV_DATA_VALIDACAO, rp.RP_ID, rp.RP_DATA_REFEICAO, u.U_NOME
            FROM restaurante_validacao rv
            JOIN restaurante_pedido rp ON rv.RV_RP_ID = rp.RP_ID
            JOIN users u ON rp.RP_U_ID = u.U_ID
            WHERE rv.RV_FUNCIONARIO_ID = ? AND CAST(rv.RV_DATA_VALIDACAO AS DATE) = CAST(GETDATE() AS DATE)
            ORDER BY rv.RV_DATA_VALIDACAO DESC
        ");
        $stmt->execute([$funcionarioId]);
        return $stmt->fetchAll();
    }
}