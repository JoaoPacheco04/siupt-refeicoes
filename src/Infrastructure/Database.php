<?php

require_once __DIR__ . '/../../config/config.php';

class Database {
    private static ?PDO $instancia = null;

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
            WHERE rm.RM_DATA IS NULL AND rm.RM_ATIVO = 1
            ORDER BY rtp.RTP_NOME, rm.RM_NOME
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listarDetalhesExtrasParaGestao(): array {
        $hoje = date('Y-m-d');
        $stmt = self::conexao()->prepare("
            SELECT
                rm.RM_ID, rm.RM_NOME, rm.RM_TP_ID, rtp.RTP_NOME, rm.RM_ATIVO,
                (
                    SELECT TOP 1 RPTR_PRECO
                    FROM restaurante_preco_tipo_refeicao
                    WHERE RPTR_TP_ID = rm.RM_TP_ID AND RPTR_DATAINICIO <= ?
                    ORDER BY RPTR_DATAINICIO DESC
                ) as preco_atual
            FROM restaurante_menu rm
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rm.RM_DATA IS NULL
            ORDER BY rm.RM_ATIVO DESC, rm.RM_NOME
        ");
        $stmt->execute([$hoje]);
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
     * Devolve as datas (de uma lista) para as quais o utilizador já tem prato principal (ementa) ativo.
     * Não inclui pedidos que contenham apenas pratos extras.
     */
    public static function listarDatasComPedidoAtivo(int $utilizadorId, array $datas): array {
        if (empty($datas)) return [];
        $ph = implode(',', array_fill(0, count($datas), '?'));
        $hoje = date('Y-m-d');
        $stmt = self::conexao()->prepare("
            SELECT DISTINCT rp.RP_DATA_REFEICAO
            FROM restaurante_pedido rp
            JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            WHERE rp.RP_U_ID = ? AND rp.RP_DATA_REFEICAO IN ($ph)
              AND rp.RP_PAGO = 1 AND rp.RP_DATA_REFEICAO >= ?
              AND rm.RM_DATA IS NOT NULL
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
           WHERE rp.RP_U_ID = ? AND rp.RP_PAGO = 1
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

    /**
     * Busca limites de vários tipos numa única query (evita N+1 na ementa).
     * Retorna [tipo_id => ['RDL_HORA' => ..., 'RDL_DIA_ANTECEDENCIA' => ...]]
     */
    public static function obterDataLimitesBatch(array $tipoIds): array {
        if (empty($tipoIds)) return [];
        $tipoIds = array_values(array_unique(array_map('intval', $tipoIds)));
        $ph = implode(',', array_fill(0, count($tipoIds), '?'));
        $stmt = self::conexao()->prepare("
            SELECT RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA
            FROM restaurante_data_limite
            WHERE RDL_RTP_ID IN ($ph)
        ");
        $stmt->execute($tipoIds);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[(int) $row['RDL_RTP_ID']] = $row;
        }
        return $resultado;
    }

    /**
     * Versão de foraDePrazo que usa dados já carregados em batch (sem query extra).
     */
    public static function foraDePrazoBatch(int $tipoRefeicaoId, string $dataRefeicao, array $limitesBatch): bool {
        $limite = $limitesBatch[$tipoRefeicaoId] ?? null;
        if (!$limite) {
            return false;
        }
        $dataLimite = date(
            'Y-m-d ' . $limite['RDL_HORA'],
            strtotime($dataRefeicao . ' -' . $limite['RDL_DIA_ANTECEDENCIA'] . ' days')
        );
        return date('Y-m-d H:i:s') > $dataLimite;
    }

    /**
     * B2 FIX: usa uma única query com JOIN para obter o limite do primeiro
     * tipo principal (Carne / Peixe / Vegetariano) que tenha limite definido,
     * eliminando as 6 queries individuais anteriores.
     */
    public static function obterDataLimitePrincipalTexto(): ?string {
        $stmt = self::conexao()->prepare("
            SELECT TOP 1 rdl.RDL_HORA, rdl.RDL_DIA_ANTECEDENCIA
            FROM restaurante_tipo_refeicao rtp
            JOIN restaurante_data_limite rdl ON rdl.RDL_RTP_ID = rtp.RTP_ID
            WHERE rtp.RTP_NOME IN ('Carne', 'Peixe', 'Vegetariano')
            ORDER BY CASE rtp.RTP_NOME WHEN 'Carne' THEN 1 WHEN 'Peixe' THEN 2 ELSE 3 END
        ");
        $stmt->execute();
        $limite = $stmt->fetch();

        if (!$limite) return null;

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

    // ── F4: Gestão de prazos de compra (backoffice) ─────────────────────────

    /**
     * Lista todos os prazos configurados com o nome do tipo de refeição.
     */
    public static function listarPrazos(): array {
        $stmt = self::conexao()->prepare("
            SELECT rdl.RDL_ID, rdl.RDL_RTP_ID, rtp.RTP_NOME,
                   rdl.RDL_HORA, rdl.RDL_DIA_ANTECEDENCIA
            FROM restaurante_data_limite rdl
            JOIN restaurante_tipo_refeicao rtp ON rdl.RDL_RTP_ID = rtp.RTP_ID
            ORDER BY rtp.RTP_NOME
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Atualiza a hora limite e a antecedência de um prazo existente.
     * Retorna true em caso de sucesso, string de erro caso contrário.
     */
    public static function atualizarPrazo(int $id, string $hora, int $diasAntecedencia): bool|string {
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
            return 'hora_invalida';
        }
        if ($diasAntecedencia < 0 || $diasAntecedencia > 7) {
            return 'antecedencia_invalida';
        }
        // Normalizar para HH:MM:00
        $hora = substr($hora, 0, 5) . ':00';
        $stmt = self::conexao()->prepare("
            UPDATE restaurante_data_limite
            SET RDL_HORA = ?, RDL_DIA_ANTECEDENCIA = ?
            WHERE RDL_ID = ?
        ");
        $stmt->execute([$hora, $diasAntecedencia, $id]);
        return $stmt->rowCount() > 0;
    }


    /**
     * Verifica se uma data específica é feriado. Usado tanto para bloquear
     * a criação de pedidos como para marcar visualmente a ementa.
     */
    public static function ehFeriado(string $data): bool {
        $stmt = self::conexao()->prepare("SELECT 1 FROM restaurante_feriado WHERE RF_DATA = ?");
        $stmt->execute([$data]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Devolve os feriados dentro de um intervalo de datas, como
     * [data => nome], para marcar a ementa semanal sem fazer uma
     * query por dia (evita N+1).
     */
    public static function listarFeriadosNoPeriodo(string $inicio, string $fim): array {
        $stmt = self::conexao()->prepare("
            SELECT RF_DATA, RF_NOME FROM restaurante_feriado
            WHERE RF_DATA BETWEEN ? AND ?
        ");
        $stmt->execute([$inicio, $fim]);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            // RF_DATA vem como objeto DateTime do driver — normaliza para string Y-m-d
            $dataStr = $row['RF_DATA'] instanceof DateTime ? $row['RF_DATA']->format('Y-m-d') : (string) $row['RF_DATA'];
            $resultado[$dataStr] = $row['RF_NOME'];
        }
        return $resultado;
    }

    /**
     * Devolve as datas com ementa configurada dentro de um período,
     * para distinguir dias "Encerrado" de dias normais sem ementa.
     */
    public static function listarDatasComEmentaConfigurada(string $inicio, string $fim): array {
        $stmt = self::conexao()->prepare("
            SELECT DISTINCT CONVERT(VARCHAR(10), RM_DATA, 120) AS RM_DATA
            FROM restaurante_menu
            WHERE RM_DATA IS NOT NULL AND RM_DATA BETWEEN ? AND ?
        ");
        $stmt->execute([$inicio, $fim]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function listarFeriados(): array {
        $stmt = self::conexao()->prepare("SELECT RF_ID, RF_DATA, RF_NOME FROM restaurante_feriado ORDER BY RF_DATA");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function criarFeriado(string $data, string $nome): string {
        $stmt = self::conexao()->prepare("SELECT 1 FROM restaurante_feriado WHERE RF_DATA = ?");
        $stmt->execute([$data]);
        if ($stmt->fetch()) {
            return 'data_duplicada';
        }
        self::conexao()->prepare("INSERT INTO restaurante_feriado (RF_DATA, RF_NOME) VALUES (?, ?)")->execute([$data, $nome]);
        return 'ok';
    }

    public static function apagarFeriado(int $id): bool {
        $stmt = self::conexao()->prepare("DELETE FROM restaurante_feriado WHERE RF_ID = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }


    // ============================================
    // Dias especiais (encerrado / extras permitidos)
    // ============================================

    /**
     * Devolve os dias especiais dentro de um intervalo como
     * [data => ['RDE_MOTIVO' => ..., 'RDE_PERMITE_EXTRAS' => ...]]
     * para marcar a ementa semanal sem fazer uma query por dia.
     */
    public static function listarDiasEspeciaisNoPeriodo(string $inicio, string $fim): array {
        $stmt = self::conexao()->prepare("
            SELECT RDE_DATA, RDE_MOTIVO, RDE_PERMITE_EXTRAS
            FROM restaurante_dia_especial
            WHERE RDE_DATA BETWEEN ? AND ?
        ");
        $stmt->execute([$inicio, $fim]);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $dataStr = $row['RDE_DATA'] instanceof DateTime
                ? $row['RDE_DATA']->format('Y-m-d')
                : (string) $row['RDE_DATA'];
            $resultado[$dataStr] = $row;
        }
        return $resultado;
    }

    /**
     * Lista todos os dias especiais registados, para a página de gestão.
     */
    public static function listarTodosDiasEspeciais(): array {
        $stmt = self::conexao()->prepare("
            SELECT RDE_ID, RDE_DATA, RDE_MOTIVO, RDE_PERMITE_EXTRAS
            FROM restaurante_dia_especial
            ORDER BY RDE_DATA
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Cria um dia especial. Devolve 'data_duplicada' se já existir,
     * 'eh_feriado' se a data já for feriado, ou 'ok'.
     */
    public static function criarDiaEspecial(string $data, string $motivo, bool $permiteExtras): string {
        // Não sobrepor a um feriado existente
        if (self::ehFeriado($data)) {
            return 'eh_feriado';
        }
        $stmt = self::conexao()->prepare("SELECT 1 FROM restaurante_dia_especial WHERE RDE_DATA = ?");
        $stmt->execute([$data]);
        if ($stmt->fetch()) {
            return 'data_duplicada';
        }
        self::conexao()->prepare("
            INSERT INTO restaurante_dia_especial (RDE_DATA, RDE_MOTIVO, RDE_PERMITE_EXTRAS)
            VALUES (?, ?, ?)
        ")->execute([$data, $motivo ?: null, $permiteExtras ? 1 : 0]);
        return 'ok';
    }

    /**
     * Remove um dia especial pelo ID.
     */
    public static function apagarDiaEspecial(int $id): bool {
        $stmt = self::conexao()->prepare("DELETE FROM restaurante_dia_especial WHERE RDE_ID = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica se uma data é um dia especial e devolve a linha ou false.
     * Usado em criarPedido() para verificar se extras são permitidos.
     */
    public static function ehDiaEspecial(string $data): array|false {
        $stmt = self::conexao()->prepare("
            SELECT RDE_ID, RDE_MOTIVO, RDE_PERMITE_EXTRAS
            FROM restaurante_dia_especial WHERE RDE_DATA = ?
        ");
        $stmt->execute([$data]);
        return $stmt->fetch();
    }

    // ============================================
    // Papéis de utilizador (atendente / admin_cantina)
    // ============================================

    /**
     * Devolve os papéis de cantina atribuídos a um utilizador.
     * Ex: ['atendente', 'admin_cantina']
     * Alunos/colaboradores sem papel devolvem [].
     */
    public static function obterPapeisUtilizador(int $userId): array {
        $stmt = self::conexao()->prepare("
            SELECT RPU_PAPEL FROM restaurante_papel_utilizador WHERE RPU_U_ID = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Verifica se um utilizador tem um papel específico de cantina.
     */
    public static function temPapel(int $userId, string $papel): bool {
        $stmt = self::conexao()->prepare("
            SELECT 1 FROM restaurante_papel_utilizador
            WHERE RPU_U_ID = ? AND RPU_PAPEL = ?
        ");
        $stmt->execute([$userId, $papel]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Lista todos os utilizadores que têm pelo menos um papel de cantina,
     * agrupando os seus papéis numa string (ex: "atendente, admin_cantina").
     * Ordenado pelo nome, para facilitar a gestão.
     * Usado na página gerir_atendentes.php.
     *
     * @return array Cada linha: [U_ID, U_NOME, U_BICC, U_PERFIL, papeis]
     */
    public static function listarUtilizadoresComPapeis(): array {
        $stmt = self::conexao()->prepare("
            SELECT
                u.U_ID, u.U_NOME, u.U_BICC, u.U_PERFIL,
                STRING_AGG(rpu.RPU_PAPEL, ', ') WITHIN GROUP (ORDER BY rpu.RPU_PAPEL) AS papeis
            FROM users u
            JOIN restaurante_papel_utilizador rpu ON u.U_ID = rpu.RPU_U_ID
            GROUP BY u.U_ID, u.U_NOME, u.U_BICC, u.U_PERFIL
            ORDER BY u.U_NOME
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Pesquisa utilizadores por número BICC ou por nome (parcial, case-insensitive).
     * Devolve no máximo 10 resultados para não sobrecarregar a UI.
     * Inclui os papéis já atribuídos a cada utilizador encontrado.
     * Usado no campo de pesquisa de gerir_atendentes.php.
     *
     * @param  string $query Texto a pesquisar (mínimo 2 caracteres recomendado)
     * @return array  Cada linha: [U_ID, U_NOME, U_BICC, U_PERFIL, papeis]
     */
    public static function pesquisarUtilizador(string $query): array {
        $like = '%' . $query . '%';
        $stmt = self::conexao()->prepare("
            SELECT TOP 10
                u.U_ID, u.U_NOME, u.U_BICC, u.U_PERFIL,
                (
                    SELECT STRING_AGG(rpu2.RPU_PAPEL, ',') WITHIN GROUP (ORDER BY rpu2.RPU_PAPEL)
                    FROM restaurante_papel_utilizador rpu2
                    WHERE rpu2.RPU_U_ID = u.U_ID
                ) AS papeis
            FROM users u
            WHERE u.U_PERFIL = 2 AND (u.U_BICC LIKE ? OR u.U_NOME LIKE ?)
            ORDER BY u.U_NOME
        ");
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    /**
     * Atribui um papel de cantina a um utilizador.
     * Papéis válidos: 'atendente', 'admin_cantina'.
     * Se o papel já existir (UNIQUE constraint), devolve 'ja_existe' sem erro.
     *
     * @return string 'ok' | 'papel_invalido' | 'utilizador_nao_encontrado' | 'ja_existe'
     */
    public static function atribuirPapel(int $userId, string $papel): string {
        if (!in_array($papel, ['atendente', 'admin_cantina'], true)) {
            return 'papel_invalido';
        }

        // Verificar se o utilizador existe
        $stmt = self::conexao()->prepare("SELECT 1 FROM users WHERE U_ID = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            return 'utilizador_nao_encontrado';
        }

        // Verificar se já tem o papel
        if (self::temPapel($userId, $papel)) {
            return 'ja_existe';
        }

        self::conexao()->prepare("
            INSERT INTO restaurante_papel_utilizador (RPU_U_ID, RPU_PAPEL) VALUES (?, ?)
        ")->execute([$userId, $papel]);

        return 'ok';
    }

    /**
     * Revoga um papel de cantina de um utilizador.
     * Proteção: impede revogar o último admin_cantina do sistema,
     * para evitar ficar sem nenhum administrador.
     *
     * @return string 'ok' | 'papel_invalido' | 'nao_encontrado' | 'ultimo_admin'
     */
    public static function revogarPapel(int $userId, string $papel): string {
        if (!in_array($papel, ['atendente', 'admin_cantina'], true)) {
            return 'papel_invalido';
        }

        // Proteger o último admin_cantina — sem ele ninguém consegue aceder à gestão
        if ($papel === 'admin_cantina') {
            $stmt = self::conexao()->prepare("
                SELECT COUNT(*) FROM restaurante_papel_utilizador WHERE RPU_PAPEL = 'admin_cantina'
            ");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() <= 1) {
                return 'ultimo_admin';
            }
        }

        $stmt = self::conexao()->prepare("
            DELETE FROM restaurante_papel_utilizador WHERE RPU_U_ID = ? AND RPU_PAPEL = ?
        ");
        $stmt->execute([$userId, $papel]);
        return $stmt->rowCount() > 0 ? 'ok' : 'nao_encontrado';
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

        // Nunca aceitar uma data já passada — feito antes de abrir transação,
        // não há nada ainda para reverter.
        if ($dataRefeicao < date('Y-m-d')) {
            return 'data_no_passado';
        }

        $pdo = self::conexao();
        $pdo->beginTransaction();

        try {
            // Determina se o pedido inclui pelo menos um prato principal (RM_DATA IS NOT NULL).
            $rmIds = array_values(array_unique(array_map(
                fn($i) => (int) $i['rm_id'],
                $itens
            )));
            $ph = implode(',', array_fill(0, count($rmIds), '?'));
            $stmtPratoPrincipal = $pdo->prepare("
                SELECT COUNT(*) FROM restaurante_menu
                WHERE RM_ID IN ($ph) AND RM_DATA IS NOT NULL
            ");
            $stmtPratoPrincipal->execute($rmIds);
            $temPratoPrincipal = (int) $stmtPratoPrincipal->fetchColumn() > 0;

            $ehFeriado = self::ehFeriado($dataRefeicao);
            if ($ehFeriado) {
                $pdo->rollBack();
                return 'dia_feriado';
            }

            // Valida o prazo de compra do prato principal (14h30 do dia anterior ou antecedência configurada)
            if ($temPratoPrincipal) {
                $stmtTipos = $pdo->prepare("
                    SELECT DISTINCT RM_TP_ID FROM restaurante_menu
                    WHERE RM_ID IN ($ph) AND RM_DATA IS NOT NULL
                ");
                $stmtTipos->execute($rmIds);
                $tiposPrincipais = array_map('intval', $stmtTipos->fetchAll(PDO::FETCH_COLUMN));

                $limitesRelevantes = self::obterDataLimitesBatch($tiposPrincipais);
                foreach ($tiposPrincipais as $tipoId) {
                    if (self::foraDePrazoBatch($tipoId, $dataRefeicao, $limitesRelevantes)) {
                        $pdo->rollBack();
                        return 'fora_de_prazo';
                    }
                }
            }

            // Pré-calcular estado do dia UMA SÓ VEZ antes do loop de itens (evita N+1).
            $temEmentaNesseDia = !empty(self::listarDatasComEmentaConfigurada($dataRefeicao, $dataRefeicao));
            $diaEspecial = $temEmentaNesseDia ? false : self::ehDiaEspecial($dataRefeicao);
            
            if ($temEmentaNesseDia) {
                // Se tem ementa, os extras só são bloqueados se for feriado
                $extrasPermitidos = !$ehFeriado;
            } else {
                // Se NÃO tem ementa, a cantina está encerrada por defeito.
                // Extras só são permitidos se houver uma autorização explícita (Dia Especial com PERMITE_EXTRAS = 1)
                $extrasPermitidos = $diaEspecial && (bool) $diaEspecial['RDE_PERMITE_EXTRAS'] && !$ehFeriado;
            }

            // Só bloqueia duplicado se o pedido novo incluir um prato principal E o utilizador já tiver um prato principal pago para essa data.
            if ($temPratoPrincipal) {
                $stmtDup = $pdo->prepare("
                    SELECT COUNT(*) FROM restaurante_pedido rp WITH (UPDLOCK, HOLDLOCK)
                    JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
                    JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
                    WHERE rp.RP_U_ID = ? AND rp.RP_DATA_REFEICAO = ? AND rp.RP_PAGO = 1
                      AND rm.RM_DATA IS NOT NULL
                ");
                $stmtDup->execute([$utilizadorId, $dataRefeicao]);
                if ((int) $stmtDup->fetchColumn() > 0) {
                    $pdo->rollBack();
                    return 'pedido_duplicado';
                }
            }

            $precoTotal = 0;
            $linhasValidadas = [];

            foreach ($itens as $item) {
                $stmt = $pdo->prepare("SELECT RM_ID, RM_TP_ID, RM_DATA, RM_ATIVO FROM restaurante_menu WHERE RM_ID = ?");
                $stmt->execute([$item['rm_id']]);
                $prato = $stmt->fetch();

                if (!$prato || (isset($prato['RM_ATIVO']) && (int) $prato['RM_ATIVO'] === 0)) {
                    $pdo->rollBack();
                    return 'prato_invalido';
                }

                if ($prato['RM_DATA'] !== null) {
                    if (!$temEmentaNesseDia) {
                        $pdo->rollBack();
                        return 'dia_encerrado';
                    }
                } else {
                    if (!$extrasPermitidos) {
                        $pdo->rollBack();
                        return 'dia_encerrado';
                    }

                    // Valida o prazo dos extras (até às 10h do próprio dia da refeição)
                    if (self::extraForaDeHorarioHoje($dataRefeicao)) {
                        $pdo->rollBack();
                        return 'extra_fora_de_horario';
                    }

                    // Impede comprar o mesmo extra duas vezes para o mesmo dia.
                    $stmtExtraDup = $pdo->prepare("
                        SELECT COUNT(*) FROM restaurante_compra rc
                        JOIN restaurante_pedido rp ON rc.RC_RP_ID = rp.RP_ID
                        WHERE rp.RP_U_ID = ? AND rp.RP_PAGO = 1
                          AND rp.RP_DATA_REFEICAO = ? AND rc.RC_RM_ID = ?
                    ");
                    $stmtExtraDup->execute([$utilizadorId, $dataRefeicao, $prato['RM_ID']]);
                    if ((int) $stmtExtraDup->fetchColumn() > 0) {
                        $pdo->rollBack();
                        return 'extra_duplicado';
                    }
                }

                $menuCompleto = !empty($item['menu_completo']);

                if ($menuCompleto && $prato['RM_DATA'] === null) {
                    $pdo->rollBack();
                    return 'menu_completo_invalido_para_extra';
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

            $qrcode = bin2hex(random_bytes(24)); // 48 caracteres

            $stmt = $pdo->prepare("INSERT INTO restaurante_pedido (RP_U_ID, RP_DATA_REFEICAO, RP_PRECO_TOTAL, RP_QRCODE, RP_UTILIZADO)
                                    VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$utilizadorId, $dataRefeicao, $precoTotal, $qrcode]);
            $pedidoId = (int) $pdo->lastInsertId();

            $tentativas = 0;
            do {
                if (++$tentativas > 10) {
                    throw new RuntimeException('Não foi possível gerar um código curto único após 10 tentativas.');
                }
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

    /**
    /**
     * Remove automaticamente pedidos não pagos cuja data de refeição já passou.
     */
    public static function limparPedidosNaoPagosPassados(int $utilizadorId): void {
        try {
            $hoje = date('Y-m-d');
            $pdo = self::conexao();
            $stmt = $pdo->prepare("
                SELECT RP_ID FROM restaurante_pedido
                WHERE RP_U_ID = ? AND RP_PAGO = 0 AND RP_DATA_REFEICAO < ?
            ");
            $stmt->execute([$utilizadorId, $hoje]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($ids as $id) {
                $pdo->prepare("DELETE FROM restaurante_pagamento WHERE RPG_RP_ID = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM restaurante_compra WHERE RC_RP_ID = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM restaurante_pedido WHERE RP_ID = ? AND RP_PAGO = 0")->execute([$id]);
            }
        } catch (Throwable $e) {
            // Silencioso para não interromper fluxo
        }
    }

    /**
     * Lista todos os pedidos de um utilizador, com estado calculado.
     * Aceita um filtro de mês opcional (formato 'YYYY-MM') para paginação
     * por período — funcionalidade 2 (filtro de data no histórico).
     */
    public static function listarPedidosDoUtilizador(int $utilizadorId, ?string $anoMes = null): array {
        // Auto-limpa pedidos não pagos cuja data já passou
        self::limparPedidosNaoPagosPassados($utilizadorId);

        $params = [$utilizadorId];
        $filtroMes = '';
        if ($anoMes !== null && preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            $inicio = $anoMes . '-01';
            $fim    = date('Y-m-t', strtotime($inicio));
            $filtroMes = 'AND RP_DATA_REFEICAO BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fim;
        }
        $stmt = self::conexao()->prepare("
            SELECT * FROM restaurante_pedido
            WHERE RP_U_ID = ? $filtroMes
            ORDER BY RP_DATA_REFEICAO DESC
        ");
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll();
        foreach ($pedidos as &$p) {
            $p['estado'] = self::calcularEstadoPedido($p);
        }
        return $pedidos;
    }

    /**
     * Estatísticas pessoais do utilizador para o painel do histórico.
     * Opcionalmente filtradas por mês (formato 'YYYY-MM').
     * Retorna: total_pedidos, total_gasto, total_levantados, prato_favorito.
     */
    public static function obterEstatisticasUtilizador(int $utilizadorId, ?string $anoMes = null): array {
        $params = [$utilizadorId];
        $filtroMes = '';
        if ($anoMes !== null && preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            $inicio = $anoMes . '-01';
            $fim    = date('Y-m-t', strtotime($inicio));
            $filtroMes = 'AND RP_DATA_REFEICAO BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fim;
        }

        $stmt = self::conexao()->prepare("
            SELECT
                COUNT(*)                              AS total_pedidos,
                ISNULL(SUM(RP_PRECO_TOTAL), 0)        AS total_gasto,
                SUM(CASE WHEN RP_UTILIZADO = 1 THEN 1 ELSE 0 END) AS total_levantados
            FROM restaurante_pedido
            WHERE RP_U_ID = ? AND RP_PAGO = 1 $filtroMes
        ");
        $stmt->execute($params);
        $resumo = $stmt->fetch();

        // Prato mais comprado (apenas pratos do dia para ter nome significativo)
        $params2 = [$utilizadorId];
        $filtroMes2 = '';
        if ($anoMes !== null && preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            $filtroMes2 = 'AND rp.RP_DATA_REFEICAO BETWEEN ? AND ?';
            $params2[] = $inicio;
            $params2[] = $fim;
        }
        $stmtFav = self::conexao()->prepare("
            SELECT TOP 1 rtp.RTP_NOME AS tipo, COUNT(*) AS total
            FROM restaurante_compra rc
            JOIN restaurante_pedido rp ON rc.RC_RP_ID = rp.RP_ID
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rp.RP_U_ID = ? AND rp.RP_PAGO = 1
              AND rm.RM_DATA IS NOT NULL
              AND rtp.RTP_NOME IN ('Carne', 'Peixe', 'Vegetariano')
              $filtroMes2
            GROUP BY rtp.RTP_NOME
            ORDER BY total DESC
        ");
        $stmtFav->execute($params2);
        $favorito = $stmtFav->fetch();

        return [
            'total_pedidos'    => (int)   ($resumo['total_pedidos']   ?? 0),
            'total_gasto'      => (float) ($resumo['total_gasto']     ?? 0),
            'total_levantados' => (int)   ($resumo['total_levantados'] ?? 0),
            'prato_favorito'   => $favorito ? $favorito['tipo'] : null,
        ];
    }

    // "Expirado" não é guardado — é sempre calculado no momento da leitura.
    public static function calcularEstadoPedido(array $pedido): string {
        $dataRefeicao = $pedido['RP_DATA_REFEICAO'] instanceof DateTime
            ? $pedido['RP_DATA_REFEICAO']->format('Y-m-d')
            : (string) $pedido['RP_DATA_REFEICAO'];

        if (!$pedido['RP_PAGO']) {
            if ($dataRefeicao < date('Y-m-d')) {
                return 'expirado';
            }
            return 'nao_pago';
        }
        if ($pedido['RP_UTILIZADO']) {
            return 'utilizado';
        }
        if ($dataRefeicao < date('Y-m-d')) {
            return 'expirado';
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
            SELECT rp.*, u.U_NOME, u.U_BICC FROM restaurante_pedido rp 
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
            return ['status' => $estado, 'nome' => $pedido['U_NOME'], 'numero' => $pedido['U_BICC']];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE restaurante_pedido SET RP_UTILIZADO = 1 WHERE RP_ID = ? AND RP_UTILIZADO = 0");
            $stmt->execute([$pedido['RP_ID']]);

            if ($stmt->rowCount() === 1) {
                $pdo->prepare("INSERT INTO restaurante_validacao (RV_RP_ID, RV_FUNCIONARIO_ID) VALUES (?, ?)")
                    ->execute([$pedido['RP_ID'], $funcionarioId]);
                $pdo->commit();
                return [
                    'status' => 'valido',
                    'nome' => $pedido['U_NOME'],
                    'numero' => $pedido['U_BICC'],
                    'pedido_id' => $pedido['RP_ID'],
                ];
            }

            $pdo->rollBack();
            return ['status' => 'utilizado', 'nome' => $pedido['U_NOME'], 'numero' => $pedido['U_BICC']];
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
        return self::listarValidacoesPorData($funcionarioId, date('Y-m-d'));
    }

    /**
     * Lista validações feitas por um funcionário numa data específica,
     * incluindo os itens de cada refeição (agregados numa string).
     */
    public static function listarValidacoesPorData(int $funcionarioId, string $data): array {
        $stmt = self::conexao()->prepare("
            SELECT rv.RV_ID, rv.RV_DATA_VALIDACAO, rp.RP_ID, rp.RP_DATA_REFEICAO,
                   rp.RP_PRECO_TOTAL, u.U_NOME, u.U_BICC,
                   STRING_AGG(rm.RM_NOME, ', ') WITHIN GROUP (ORDER BY rm.RM_NOME) AS itens
            FROM restaurante_validacao rv
            JOIN restaurante_pedido rp ON rv.RV_RP_ID = rp.RP_ID
            JOIN users u ON rp.RP_U_ID = u.U_ID
            LEFT JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            LEFT JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            WHERE rv.RV_FUNCIONARIO_ID = ? AND CAST(rv.RV_DATA_VALIDACAO AS DATE) = ?
            GROUP BY rv.RV_ID, rv.RV_DATA_VALIDACAO, rp.RP_ID, rp.RP_DATA_REFEICAO, rp.RP_PRECO_TOTAL, u.U_NOME, u.U_BICC
            ORDER BY rv.RV_DATA_VALIDACAO DESC
        ");
        $stmt->execute([$funcionarioId, $data]);
        return $stmt->fetchAll();
    }

    public static function listarValidacoesHojeTodos(): array {
        return self::listarValidacoesPorDataTodos(date('Y-m-d'));
    }

    public static function listarValidacoesPorDataTodos(string $data): array {
        $stmt = self::conexao()->prepare("
            SELECT rv.RV_ID, rv.RV_DATA_VALIDACAO, rp.RP_ID, rp.RP_DATA_REFEICAO,
                   rp.RP_PRECO_TOTAL, u.U_NOME, u.U_BICC, func.U_NOME AS funcionario_nome,
                   STRING_AGG(rm.RM_NOME, ', ') WITHIN GROUP (ORDER BY rm.RM_NOME) AS itens
            FROM restaurante_validacao rv
            JOIN restaurante_pedido rp ON rv.RV_RP_ID = rp.RP_ID
            JOIN users u ON rp.RP_U_ID = u.U_ID
            JOIN users func ON rv.RV_FUNCIONARIO_ID = func.U_ID
            LEFT JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            LEFT JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            WHERE CAST(rv.RV_DATA_VALIDACAO AS DATE) = ?
            GROUP BY rv.RV_ID, rv.RV_DATA_VALIDACAO, rp.RP_ID, rp.RP_DATA_REFEICAO, rp.RP_PRECO_TOTAL, u.U_NOME, u.U_BICC, func.U_NOME
            ORDER BY rv.RV_DATA_VALIDACAO DESC
        ");
        $stmt->execute([$data]);
        return $stmt->fetchAll();
    }

    public static function extraForaDeHorarioHoje(string $dataRefeicao): bool {
        if ($dataRefeicao !== date('Y-m-d')) {
            return false;
        }
        if (!defined('EXTRA_HORA_LIMITE_HOJE')) {
            return false;
        }
        return date('H:i:s') > EXTRA_HORA_LIMITE_HOJE;
    }

    public static function obterNomeTipoRefeicao(int $tipoId): ?string {
        $stmt = self::conexao()->prepare("SELECT RTP_NOME FROM restaurante_tipo_refeicao WHERE RTP_ID = ?");
        $stmt->execute([$tipoId]);
        $nome = $stmt->fetchColumn();
        return $nome !== false ? (string) $nome : null;
    }

    // ============================================
    // Gestão de Extras (Funcionário)
    // ============================================

    /**
     * Cria um tipo de refeição dedicado para um extra individual — garante que
     * cada extra tem preço 100% independente, sem partilhar com outros pratos.
     */
    public static function criarTipoRefeicaoExtra(string $nomePrato): int {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("INSERT INTO restaurante_tipo_refeicao (RTP_NOME) VALUES (?)");
        $stmt->execute(["Extra: {$nomePrato}"]);
        $tipoId = (int) $pdo->lastInsertId();

        // Limite de compra por defeito: até às 10h do próprio dia
        $pdo->prepare("
            INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA)
            VALUES (?, '10:00:00', 0)
        ")->execute([$tipoId]);

        return $tipoId;
    }

    /**
     * Cria um novo prato extra (RM_DATA = NULL) com um tipo de refeição dedicado
     * (criado automaticamente), garantindo preço independente de outros extras.
     */
    public static function criarPratoExtra(string $nome, float $precoInicial): int {
        $pdo = self::conexao();
        $pdo->beginTransaction();

        try {
            $tipoId = self::criarTipoRefeicaoExtra($nome);

            $stmt = $pdo->prepare("INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA) VALUES (?, ?, NULL)");
            $stmt->execute([$nome, $tipoId]);
            $rmId = (int) $pdo->lastInsertId();

            $stmtPreco = $pdo->prepare("
                INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO)
                VALUES (?, ?, ?)
            ");
            $stmtPreco->execute([$tipoId, $precoInicial, date('Y-m-d')]);

            $pdo->commit();
            return $rmId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Migra um extra existente (que partilha tipo com outros pratos) para um
     * tipo de refeição dedicado, preservando o preço atual como ponto de partida.
     */
    public static function separarExtraParaTipoProprio(int $rmId): string|int {
        $pdo = self::conexao();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT RM_NOME, RM_TP_ID FROM restaurante_menu WHERE RM_ID = ? AND RM_DATA IS NULL");
            $stmt->execute([$rmId]);
            $prato = $stmt->fetch();

            if (!$prato) {
                $pdo->rollBack();
                return 'extra_nao_encontrado';
            }

            $precoAtual = self::obterPrecoVigente((int) $prato['RM_TP_ID'], date('Y-m-d'));
            if ($precoAtual === null) {
                $pdo->rollBack();
                return 'sem_preco_definido';
            }

            $novoTipoId = self::criarTipoRefeicaoExtra($prato['RM_NOME']);

            $pdo->prepare("UPDATE restaurante_menu SET RM_TP_ID = ? WHERE RM_ID = ?")->execute([$novoTipoId, $rmId]);

            $pdo->prepare("INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES (?, ?, ?)")->execute([$novoTipoId, $precoAtual, date('Y-m-d')]);

            $pdo->commit();
            return $novoTipoId;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function listarTiposRefeicao(): array {
        $stmt = self::conexao()->prepare("SELECT RTP_ID, RTP_NOME FROM restaurante_tipo_refeicao ORDER BY RTP_NOME");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function atualizarNomeExtra(int $rmId, string $novoNome): bool {
        $stmt = self::conexao()->prepare("UPDATE restaurante_menu SET RM_NOME = ? WHERE RM_ID = ? AND RM_DATA IS NULL");
        $stmt->execute([$novoNome, $rmId]);
        return $stmt->rowCount() > 0;
    }

    public static function atualizarPrecoTipo(int $tipoId, float $novoPreco): void {
        self::conexao()->prepare("
            INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO)
            VALUES (?, ?, ?)
        ")->execute([$tipoId, $novoPreco, date('Y-m-d')]);
    }

    public static function apagarExtra(int $rmId): string {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurante_compra WHERE RC_RM_ID = ?");
        $stmt->execute([$rmId]);
        $jaComprado = (int) $stmt->fetchColumn() > 0;

        if ($jaComprado) {
            // Não pode ser apagado — desativa em vez disso (soft-delete)
            $stmt = $pdo->prepare("UPDATE restaurante_menu SET RM_ATIVO = 0 WHERE RM_ID = ? AND RM_DATA IS NULL");
            $stmt->execute([$rmId]);
            return $stmt->rowCount() > 0 ? 'desativado' : 'nao_encontrado';
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM restaurante_menu WHERE RM_ID = ? AND RM_DATA IS NULL");
            $stmt->execute([$rmId]);
            return $stmt->rowCount() > 0 ? 'ok' : 'nao_encontrado';
        } catch (PDOException $e) {
            return 'erro_bd';
        }
    }

    public static function reativarExtra(int $rmId): bool {
        $stmt = self::conexao()->prepare("UPDATE restaurante_menu SET RM_ATIVO = 1 WHERE RM_ID = ? AND RM_DATA IS NULL");
        $stmt->execute([$rmId]);
        return $stmt->rowCount() > 0;
    }

    // ============================================
    // Relatórios (Funcionário/Gestor)
    // ============================================

    /**
     * Resumo de vendas de um mês: total vendido, nº de refeições, taxa de não levantados.
     * $anoMes no formato 'YYYY-MM'.
     */
    public static function obterResumoMensal(string $anoMes): array {
        $inicio = $anoMes . '-01';
        $fim = date('Y-m-t', strtotime($inicio));

        $stmt = self::conexao()->prepare("
            SELECT
                COUNT(DISTINCT rp.RP_ID) AS total_pedidos,
                ISNULL(SUM(rp.RP_PRECO_TOTAL), 0) AS total_vendido,
                SUM(CASE WHEN rp.RP_UTILIZADO = 1 THEN 1 ELSE 0 END) AS total_levantados,
                SUM(CASE WHEN rp.RP_UTILIZADO = 0 AND rp.RP_DATA_REFEICAO < CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) AS total_nao_levantados
            FROM restaurante_pedido rp
            WHERE rp.RP_PAGO = 1
              AND rp.RP_DATA_REFEICAO BETWEEN ? AND ?
        ");
        $stmt->execute([$inicio, $fim]);
        $resultado = $stmt->fetch();

        $precoMedio = $resultado['total_pedidos'] > 0
            ? (float) $resultado['total_vendido'] / (int) $resultado['total_pedidos']
            : 0;

        $mesAnterior = date('Y-m', strtotime($inicio . ' -1 month'));

        return [
            'total_pedidos'              => (int) $resultado['total_pedidos'],
            'total_vendido'              => (float) $resultado['total_vendido'],
            'total_levantados'           => (int) $resultado['total_levantados'],
            'total_nao_levantados'       => (int) $resultado['total_nao_levantados'],
            'total_vendido_mes_anterior' => self::obterTotalVendidoMensal($mesAnterior),
            'preco_medio'                => $precoMedio,
        ];
    }

    /**
     * Vendas agregadas por tipo de prato (Carne/Peixe/Vegetariano/extras, etc.), dentro do mês.
     * $anoMes no formato 'YYYY-MM'.
     */
    public static function obterVendasPorTipoMensal(string $anoMes): array {
        $inicio = $anoMes . '-01';
        $fim = date('Y-m-t', strtotime($inicio));

        // Inclui RM_PRATO_DIA para distinguir pratos da ementa de extras
        $stmt = self::conexao()->prepare("
            SELECT rtp.RTP_NOME, rtp.RM_PRATO_DIA, COUNT(*) AS quantidade, SUM(rc.RC_PRECO) AS total
            FROM restaurante_compra rc
            JOIN restaurante_pedido rp ON rc.RC_RP_ID = rp.RP_ID
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rp.RP_PAGO = 1
              AND rp.RP_DATA_REFEICAO BETWEEN ? AND ?
            GROUP BY rtp.RTP_NOME, rtp.RM_PRATO_DIA
            ORDER BY total DESC
        ");
        $stmt->execute([$inicio, $fim]);
        return $stmt->fetchAll();
    }

    /**
     * Vendas diárias dentro do mês, para o gráfico de evolução.
     * $anoMes no formato 'YYYY-MM'.
     */
    public static function obterVendasDiariasMensal(string $anoMes): array {
        $inicio = $anoMes . '-01';
        $fim = date('Y-m-t', strtotime($inicio));

        $stmt = self::conexao()->prepare("
            SELECT rp.RP_DATA_REFEICAO, COUNT(DISTINCT rp.RP_ID) AS total_pedidos, SUM(rp.RP_PRECO_TOTAL) AS total_vendido
            FROM restaurante_pedido rp
            WHERE rp.RP_PAGO = 1
              AND rp.RP_DATA_REFEICAO BETWEEN ? AND ?
            GROUP BY rp.RP_DATA_REFEICAO
            ORDER BY rp.RP_DATA_REFEICAO
        ");
        $stmt->execute([$inicio, $fim]);
        return $stmt->fetchAll();
    }

    /**
     * Versão simplificada de obterResumoMensal, só com o total vendido —
     * usada para comparação com o mês anterior, sem recursão nem repetição de queries pesadas.
     */
    public static function obterTotalVendidoMensal(string $anoMes): float {
        $inicio = $anoMes . '-01';
        $fim = date('Y-m-t', strtotime($inicio));

        $stmt = self::conexao()->prepare("
            SELECT ISNULL(SUM(RP_PRECO_TOTAL), 0) AS total
            FROM restaurante_pedido
            WHERE RP_PAGO = 1 AND RP_DATA_REFEICAO BETWEEN ? AND ?
        ");
        $stmt->execute([$inicio, $fim]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Média de avaliações agregada por nome do prato (histórico, não só da semana atual).
     * Retorna [nome => ['media' => float, 'total' => int]]
     */
    public static function obterMediaAvaliacoesPorNomes(array $nomes): array {
        if (empty($nomes)) return [];
        $nomes = array_values(array_unique($nomes));
        $ph = implode(',', array_fill(0, count($nomes), '?'));
        // BUG 6 FIX: a avaliação está ao nível do pedido, não do item individual.
        // Sem filtro, a mesma avaliação seria atribuída a TODOS os itens do pedido
        // (ex: Carne + Sopa + Sobremesa receberiam a mesma nota), inflando as médias
        // dos componentes secundários.
        // Solução: só atribuir a avaliação ao prato principal (Carne/Peixe/Vegetariano,
        // identificados por RM_DATA IS NOT NULL E tipo não sendo Sopa/Sobremesa/Bebida)
        // OU a um prato extra (RM_DATA IS NULL).
        $stmt = self::conexao()->prepare("
            SELECT rm.RM_NOME, AVG(CAST(rav.RAV_ESTRELAS AS FLOAT)) AS media, COUNT(*) AS total
            FROM restaurante_avaliacao rav
            JOIN restaurante_pedido rp ON rav.RAV_RP_ID = rp.RP_ID
            JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE rm.RM_NOME IN ($ph)
              AND (
                rm.RM_DATA IS NULL
                OR rtp.RTP_NOME NOT IN ('Sopa', 'Sobremesa', 'Bebida', 'Menu Completo')
              )
            GROUP BY rm.RM_NOME
        ");
        $stmt->execute($nomes);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[$row['RM_NOME']] = ['media' => (float) $row['media'], 'total' => (int) $row['total']];
        }
        return $resultado;
    }

    public static function obterMediaAvaliacoesMensal(string $anoMes): array {
        $inicio = $anoMes . '-01';
        $fim = date('Y-m-t', strtotime($inicio));

        $stmt = self::conexao()->prepare("
            SELECT AVG(CAST(rav.RAV_ESTRELAS AS FLOAT)) AS media, COUNT(*) AS total
            FROM restaurante_avaliacao rav
            JOIN restaurante_pedido rp ON rav.RAV_RP_ID = rp.RP_ID
            WHERE rp.RP_DATA_REFEICAO BETWEEN ? AND ?
        ");
        $stmt->execute([$inicio, $fim]);
        $resultado = $stmt->fetch();

        return [
            'media' => $resultado['media'] !== null ? (float) $resultado['media'] : null,
            'total' => (int) $resultado['total'],
        ];
    }

/**
     * Média de avaliações por prato, opcionalmente filtrada por mês.
     * Ordenado do melhor para o pior (DESC) para destaque imediato no relatório.
     *
     * @param int         $minimoAvaliacoes Mínimo de avaliações para incluir o prato
     * @param string|null $anoMes          Filtro opcional no formato 'YYYY-MM'
     */
    public static function obterMediaAvaliacoesPorPrato(int $minimoAvaliacoes = 1, ?string $anoMes = null): array {
        $filtroMes = '';
        $params    = [];

        if ($anoMes !== null && preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            $inicio    = $anoMes . '-01';
            $fim       = date('Y-m-t', strtotime($inicio));
            $filtroMes = 'AND rp.RP_DATA_REFEICAO BETWEEN ? AND ?';
            $params    = [$inicio, $fim];
        }

        // BUG 6 FIX: mesmo filtro de obterMediaAvaliacoesPorNomes — só atribui
        // avaliações ao prato principal ou a extras, nunca a sopa/sobremesa/bebida.
        $stmt = self::conexao()->prepare("
            SELECT rm.RM_NOME, AVG(CAST(rav.RAV_ESTRELAS AS FLOAT)) AS media, COUNT(*) AS total
            FROM restaurante_avaliacao rav
            JOIN restaurante_pedido rp ON rav.RAV_RP_ID = rp.RP_ID
            JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
            WHERE 1=1 $filtroMes
              AND (
                rm.RM_DATA IS NULL
                OR rtp.RTP_NOME NOT IN ('Sopa', 'Sobremesa', 'Bebida', 'Menu Completo')
              )
            GROUP BY rm.RM_NOME
            HAVING COUNT(*) >= ?
            ORDER BY media DESC
        ");
        $stmt->execute(array_merge($params, [$minimoAvaliacoes]));
        return $stmt->fetchAll();
    }


    public static function cancelarPedidoPendente(int $pedidoId, int $utilizadorId): bool {
        $pdo = self::conexao();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT RP_ID FROM restaurante_pedido
                WHERE RP_ID = ? AND RP_U_ID = ? AND RP_PAGO = 0
            ");
            $stmt->execute([$pedidoId, $utilizadorId]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare("DELETE FROM restaurante_transferencia_tentativa WHERE RTT_RP_ID = ?")
                ->execute([$pedidoId]);

            $pdo->prepare("DELETE FROM restaurante_transferencia WHERE RT_RP_ID = ?")
                ->execute([$pedidoId]);

            $pdo->prepare("DELETE FROM restaurante_pagamento WHERE RPG_RP_ID = ?")
                ->execute([$pedidoId]);

            $pdo->prepare("DELETE FROM restaurante_compra WHERE RC_RP_ID = ?")
                ->execute([$pedidoId]);

            $stmt = $pdo->prepare("
                DELETE FROM restaurante_pedido
                WHERE RP_ID = ? AND RP_U_ID = ? AND RP_PAGO = 0
            ");
            $stmt->execute([$pedidoId, $utilizadorId]);

            $pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function avaliarPedido(int $pedidoId, int $utilizadorId, int $estrelas, ?string $motivo): string {
        $stmt = self::conexao()->prepare("
            SELECT RP_ID FROM restaurante_pedido WHERE RP_ID = ? AND RP_U_ID = ? AND RP_UTILIZADO = 1
        ");
        $stmt->execute([$pedidoId, $utilizadorId]);
        if (!$stmt->fetch()) {
            return 'nao_autorizado';
        }

        $stmt = self::conexao()->prepare("SELECT RAV_ID FROM restaurante_avaliacao WHERE RAV_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        if ($stmt->fetch()) {
            return 'ja_avaliado';
        }

        // Valida o motivo contra a tabela restaurante_motivo_reclamacao (editável no backoffice)
        $motivoFinal = ($estrelas <= 2 && $motivo !== null && self::motivoReclamacaoValido($motivo)) ? $motivo : null;

        self::conexao()->prepare("
            INSERT INTO restaurante_avaliacao (RAV_RP_ID, RAV_ESTRELAS, RAV_MOTIVO)
            VALUES (?, ?, ?)
        ")->execute([$pedidoId, $estrelas, $motivoFinal]);

        return 'ok';
    }


    public static function obterMotivosProblemasMensal(string $anoMes): array {
        $inicio = $anoMes . '-01';
        $fim = date('Y-m-t', strtotime($inicio));

        $stmt = self::conexao()->prepare("
            SELECT rav.RAV_MOTIVO, COUNT(*) AS total,
                   STRING_AGG(rm.RM_NOME, ', ') WITHIN GROUP (ORDER BY rm.RM_NOME) AS pratos_associados
            FROM restaurante_avaliacao rav
            JOIN restaurante_pedido rp ON rav.RAV_RP_ID = rp.RP_ID
            LEFT JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            LEFT JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            WHERE rav.RAV_MOTIVO IS NOT NULL AND rp.RP_DATA_REFEICAO BETWEEN ? AND ?
            GROUP BY rav.RAV_MOTIVO
            ORDER BY total DESC
        ");
        $stmt->execute([$inicio, $fim]);
        return $stmt->fetchAll();
    }

    public static function listarAvaliacoesPorPedidos(array $pedidoIds): array {
        if (empty($pedidoIds)) return [];
        $ph = implode(',', array_fill(0, count($pedidoIds), '?'));
        $stmt = self::conexao()->prepare("
            SELECT RAV_RP_ID, RAV_ESTRELAS FROM restaurante_avaliacao WHERE RAV_RP_ID IN ($ph)
        ");
        $stmt->execute($pedidoIds);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[(int) $row['RAV_RP_ID']] = $row;
        }
        return $resultado;
    }
    public static function contarPedidosPorAvaliar(int $utilizadorId): int {
        $stmt = self::conexao()->prepare("
            SELECT COUNT(*) FROM restaurante_pedido rp
            WHERE rp.RP_U_ID = ? AND rp.RP_UTILIZADO = 1
              AND NOT EXISTS (SELECT 1 FROM restaurante_avaliacao rav WHERE rav.RAV_RP_ID = rp.RP_ID)
        ");
        $stmt->execute([$utilizadorId]);
        return (int) $stmt->fetchColumn();
    }
    public static function transferirPedido(int $pedidoId, int $deUtilizadorId, string $biccDestino): string {
        $pdo = self::conexao();

        $stmt = $pdo->prepare("SELECT * FROM restaurante_pedido WHERE RP_ID = ? AND RP_U_ID = ?");
        $stmt->execute([$pedidoId, $deUtilizadorId]);
        $pedido = $stmt->fetch();

        if (!$pedido || self::calcularEstadoPedido($pedido) !== 'ativo') {
            self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'nao_transferivel');
            return 'nao_transferivel';
        }

        // Impede reenviar um pedido que já foi transferido antes
        $stmt = $pdo->prepare("SELECT 1 FROM restaurante_transferencia WHERE RT_RP_ID = ?");
        $stmt->execute([$pedidoId]);
        if ($stmt->fetch()) {
            self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'ja_transferido');
            return 'ja_transferido';
        }

        $destino = self::obterUtilizadorPorBICC($biccDestino);
        if (!$destino) {
            self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'destinatario_nao_encontrado');
            return 'destinatario_nao_encontrado';
        }
        if ((int) $destino['U_ID'] === $deUtilizadorId) {
            self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'mesmo_utilizador');
            return 'mesmo_utilizador';
        }

        // Impede transferir para um utilizador que já tem um pedido ativo para o mesmo dia
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM restaurante_pedido
            WHERE RP_U_ID = ? AND RP_DATA_REFEICAO = ? AND RP_PAGO = 1 AND RP_UTILIZADO = 0
        ");
        $stmt->execute([$destino['U_ID'], $pedido['RP_DATA_REFEICAO']]);
        if ((int) $stmt->fetchColumn() > 0) {
            self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'destinatario_ja_tem_pedido');
            return 'destinatario_ja_tem_pedido';
        }
        $pdo->beginTransaction();
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE restaurante_pedido
                SET RP_U_ID = ?
                WHERE RP_ID = ? AND RP_U_ID = ? AND RP_PAGO = 1 AND RP_UTILIZADO = 0
            ");
            $stmtUpdate->execute([$destino['U_ID'], $pedidoId, $deUtilizadorId]);

            if ($stmtUpdate->rowCount() !== 1) {
                $pdo->rollBack();
                self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'nao_transferivel');
                return 'nao_transferivel';
            }

            $pdo->prepare("
                INSERT INTO restaurante_transferencia (RT_RP_ID, RT_DE_U_ID, RT_PARA_U_ID)
                VALUES (?, ?, ?)
            ")->execute([$pedidoId, $deUtilizadorId, $destino['U_ID']]);

            $pdo->commit();
            return 'ok';
        } catch (Exception $e) {
            $pdo->rollBack();
            self::registarTentativaTransferenciaFalhada($pedidoId, $deUtilizadorId, $biccDestino, 'erro_bd');
            return 'erro_bd';
        }
    }
    public static function contarRefeicoesAtivasHoje(): int {
        $stmt = self::conexao()->prepare("
            SELECT COUNT(*) FROM restaurante_pedido
            WHERE RP_PAGO = 1 AND RP_UTILIZADO = 0 AND RP_DATA_REFEICAO = CAST(GETDATE() AS DATE)
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista os pedidos pagos e ainda não levantados do dia atual,
     * com nome/número do comprador e itens — usado para gerar uma
     * lista de contingência em papel, caso o sistema de validação
     * fique indisponível durante o serviço.
     */
    public static function listarRefeicoesPorLevantarHoje(): array {
        $stmt = self::conexao()->prepare("
            SELECT rp.RP_ID, rp.RP_CODIGO_CURTO, u.U_NOME, u.U_BICC,
                   STRING_AGG(rm.RM_NOME, ', ') WITHIN GROUP (ORDER BY rm.RM_NOME) AS itens
            FROM restaurante_pedido rp
            JOIN users u ON rp.RP_U_ID = u.U_ID
            LEFT JOIN restaurante_compra rc ON rc.RC_RP_ID = rp.RP_ID
            LEFT JOIN restaurante_menu rm ON rc.RC_RM_ID = rm.RM_ID
            WHERE rp.RP_PAGO = 1 AND rp.RP_UTILIZADO = 0 
              AND rp.RP_DATA_REFEICAO = CAST(GETDATE() AS DATE)
            GROUP BY rp.RP_ID, rp.RP_CODIGO_CURTO, u.U_NOME, u.U_BICC
            ORDER BY u.U_NOME
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Busca informação de transferência (quem enviou) para uma lista de pedidos,
     * em batch para evitar N+1. Só devolve entrada para pedidos que foram
     * mesmo recebidos por transferência.
     */
    public static function listarTransferenciasPorPedidos(array $pedidoIds): array {
        if (empty($pedidoIds)) return [];
        $ph = implode(',', array_fill(0, count($pedidoIds), '?'));
        $stmt = self::conexao()->prepare("
            SELECT rt.RT_RP_ID, u.U_NOME AS nome_remetente
            FROM restaurante_transferencia rt
            JOIN users u ON rt.RT_DE_U_ID = u.U_ID
            WHERE rt.RT_RP_ID IN ($ph)
        ");
        $stmt->execute($pedidoIds);
        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[(int) $row['RT_RP_ID']] = $row['nome_remetente'];
        }
        return $resultado;
    }
    
    /**
     * Regista uma tentativa de transferência falhada, para auditoria e deteção
     * de possíveis abusos (ex: várias tentativas seguidas para BICCs inexistentes).
     * Falhas ao registar não devem impedir a resposta normal ao utilizador.
     */
    private static function registarTentativaTransferenciaFalhada(int $pedidoId, int $deUtilizadorId, string $biccDestino, string $motivo): void {
        try {
            self::conexao()->prepare("
                INSERT INTO restaurante_transferencia_tentativa (RTT_RP_ID, RTT_DE_U_ID, RTT_BICC_DESTINO, RTT_MOTIVO_FALHA)
                VALUES (?, ?, ?, ?)
            ")->execute([$pedidoId, $deUtilizadorId, $biccDestino, $motivo]);
        } catch (Exception $e) {
            // Falha silenciosa: não deixar que um problema no log de auditoria
            // impeça a resposta normal (sucesso ou erro) ao utilizador
        }
    }

    /**
     * Lista os motivos de reclamação ativos, para popular o dropdown
     * de avaliação. Geridos pela funcionária via gerir_motivos.php.
     */
    public static function listarMotivosReclamacaoAtivos(): array {
        $stmt = self::conexao()->prepare("
            SELECT RMR_CODIGO, RMR_LABEL 
            FROM restaurante_motivo_reclamacao 
            WHERE RMR_ATIVO = 1
            ORDER BY RMR_LABEL
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lista TODOS os motivos (ativos e inativos), para a página de gestão.
     */
    public static function listarTodosMotivosReclamacao(): array {
        $stmt = self::conexao()->prepare("
            SELECT RMR_ID, RMR_CODIGO, RMR_LABEL, RMR_ATIVO 
            FROM restaurante_motivo_reclamacao 
            ORDER BY RMR_ATIVO DESC, RMR_LABEL
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Verifica se um código de motivo é válido (ativo) na base de dados.
     */
    public static function motivoReclamacaoValido(string $codigo): bool {
        $stmt = self::conexao()->prepare("
            SELECT 1 FROM restaurante_motivo_reclamacao 
            WHERE RMR_CODIGO = ? AND RMR_ATIVO = 1
        ");
        $stmt->execute([$codigo]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Gera um slug único em snake_case a partir do texto do motivo (ex: "Comida fria" -> "comida_fria").
     */
    public static function gerarSlugMotivo(string $label): string {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($slug === false || $slug === '') {
            $slug = $label;
        }
        $slug = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim($slug)));
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'motivo_' . time();
        }
        $base = substr($slug, 0, 40);
        $codigo = $base;
        $i = 2;
        while (true) {
            $stmt = self::conexao()->prepare("SELECT 1 FROM restaurante_motivo_reclamacao WHERE RMR_CODIGO = ?");
            $stmt->execute([$codigo]);
            if (!$stmt->fetch()) {
                break;
            }
            $codigo = $base . '_' . $i;
            $i++;
        }
        return $codigo;
    }

    /**
     * Cria um novo motivo de reclamação. Se o código não for fornecido, é gerado automaticamente.
     */
    public static function criarMotivoReclamacao(string $codigoOuLabel, ?string $label = null): string {
        if ($label === null) {
            $labelTexto = trim($codigoOuLabel);
            $codigo = self::gerarSlugMotivo($labelTexto);
        } else {
            $codigo = trim($codigoOuLabel);
            $labelTexto = trim($label);
        }

        $stmt = self::conexao()->prepare("SELECT 1 FROM restaurante_motivo_reclamacao WHERE RMR_CODIGO = ?");
        $stmt->execute([$codigo]);
        if ($stmt->fetch()) {
            return 'codigo_duplicado';
        }

        self::conexao()->prepare("
            INSERT INTO restaurante_motivo_reclamacao (RMR_CODIGO, RMR_LABEL) VALUES (?, ?)
        ")->execute([$codigo, $labelTexto]);

        return 'ok';
    }

    /**
     * Desativa (soft-delete) um motivo — preserva histórico de avaliações antigas.
     */
    public static function desativarMotivoReclamacao(int $id): bool {
        $stmt = self::conexao()->prepare("UPDATE restaurante_motivo_reclamacao SET RMR_ATIVO = 0 WHERE RMR_ID = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reativa um motivo previamente desativado.
     */
    public static function reativarMotivoReclamacao(int $id): bool {
        $stmt = self::conexao()->prepare("UPDATE restaurante_motivo_reclamacao SET RMR_ATIVO = 1 WHERE RMR_ID = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza o texto (label) de um motivo já existente.
     * O código interno (RMR_CODIGO) nunca muda — é a chave usada
     * em avaliações já registadas, alterá-lo quebraria o histórico.
     */
    public static function atualizarLabelMotivoReclamacao(int $id, string $novoLabel): bool {
        $stmt = self::conexao()->prepare("UPDATE restaurante_motivo_reclamacao SET RMR_LABEL = ? WHERE RMR_ID = ?");
        $stmt->execute([$novoLabel, $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Gera automaticamente todos os feriados de um ano: fixos nacionais,
     * o municipal do Porto (São João), e os móveis (dependentes da Páscoa).
     * Não duplica datas já existentes.
     *
     * @return array{inseridos: int, ja_existiam: int, total: int}
     */
    public static function gerarTodosFeriadosDoAno(int $ano): array {
        $inseridos  = 0;
        $jaExistiam = 0;

        $feriadosFixos = [
            "{$ano}-01-01" => 'Ano Novo',
            "{$ano}-04-25" => 'Dia da Liberdade',
            "{$ano}-05-01" => 'Dia do Trabalhador',
            "{$ano}-06-10" => 'Dia de Portugal',
            "{$ano}-06-24" => 'São João (feriado municipal do Porto)',
            "{$ano}-08-15" => 'Assunção de Nossa Senhora',
            "{$ano}-10-05" => 'Implantação da República',
            "{$ano}-11-01" => 'Dia de Todos os Santos',
            "{$ano}-12-01" => 'Restauração da Independência',
            "{$ano}-12-08" => 'Imaculada Conceição',
            "{$ano}-12-25" => 'Natal',
        ];
        foreach ($feriadosFixos as $data => $nome) {
            self::inserirFeriadoSeNaoExistir($data, $nome) ? $inseridos++ : $jaExistiam++;
        }

        $timestampPascoa = easter_date($ano);
        $pascoa = new DateTime('@' . $timestampPascoa);
        $pascoa->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'Europe/Lisbon'));

        $feriadosMoveis = [
            'Páscoa'            =>   0,
            'Sexta-feira Santa' =>  -2,
            'Carnaval'          => -47,
            'Corpo de Deus'     =>  60,
        ];
        foreach ($feriadosMoveis as $nome => $offsetDias) {
            $data = (clone $pascoa)->modify("{$offsetDias} days")->format('Y-m-d');
            self::inserirFeriadoSeNaoExistir($data, $nome) ? $inseridos++ : $jaExistiam++;
        }

        return ['inseridos' => $inseridos, 'ja_existiam' => $jaExistiam, 'total' => $inseridos + $jaExistiam];
    }

    /**
     * Insere um feriado se a data ainda não existir.
     * @return bool true se inseriu, false se já existia.
     */
    private static function inserirFeriadoSeNaoExistir(string $data, string $nome): bool {
        $stmt = self::conexao()->prepare("SELECT 1 FROM restaurante_feriado WHERE RF_DATA = ?");
        $stmt->execute([$data]);
        if ($stmt->fetch()) {
            return false;
        }
        self::conexao()->prepare("INSERT INTO restaurante_feriado (RF_DATA, RF_NOME) VALUES (?, ?)")
            ->execute([$data, $nome]);
        return true;
    }

    /**
     * Verifica se os feriados de um ano já foram gerados automaticamente,
     * para evitar a regeneração completa a cada carregamento da ementa.
     * O limiar de 14 dá margem a até 1 remoção manual sem desencadear regeneração.
     */
    public static function feriadosDoAnoJaExistem(int $ano): bool {
        $stmt = self::conexao()->prepare("SELECT COUNT(*) FROM restaurante_feriado WHERE YEAR(RF_DATA) = ?");
        $stmt->execute([$ano]);
        return (int) $stmt->fetchColumn() >= 14;
    }

    public static function pedidoJaPago(int $pedidoId): bool
    {
        $pdo  = self::conexao();
        $stmt = $pdo->prepare('SELECT RP_PAGO FROM restaurante_pedido WHERE RP_ID = :id');
        $stmt->execute(['id' => $pedidoId]);
        return (bool) $stmt->fetchColumn();
    }
}