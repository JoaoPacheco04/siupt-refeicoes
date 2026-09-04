<?php
/**
 * Endpoint AJAX — Devolver pratos da ementa de uma semana em JSON.
 * GET: inicio (Y-m-d), fim (Y-m-d)
 *
 * Retorna os pratos agrupados por data e tipo, mais feriados e dias especiais.
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

$utilizador = exigirLogin('admin_cantina', true);

$inicio = $_GET['inicio'] ?? '';
$fim    = $_GET['fim']    ?? '';

// Valida formato de datas
$dtInicio = DateTime::createFromFormat('Y-m-d', $inicio);
$dtFim    = DateTime::createFromFormat('Y-m-d', $fim);

if (
    !$dtInicio || $dtInicio->format('Y-m-d') !== $inicio ||
    !$dtFim    || $dtFim->format('Y-m-d')    !== $fim    ||
    $dtInicio > $dtFim
) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Datas inválidas.']);
    exit;
}

try {
    $pratos        = Database::listarPratosEmentaSemana($inicio, $fim);
    $feriados      = Database::listarFeriadosNoPeriodo($inicio, $fim);
    $diasEspeciais = Database::listarDiasEspeciaisNoPeriodo($inicio, $fim);
    $estadoPubl    = Database::semanaPublicada($inicio, $fim);

    // Conta reservas por prato (para saber se pode ser apagado)
    $rmIds = array_column($pratos, 'RM_ID');
    $reservasPorPrato = [];
    if (!empty($rmIds)) {
        $ph   = implode(',', array_fill(0, count($rmIds), '?'));
        $stmt = Database::conexao()->prepare("
            SELECT RC_RM_ID, COUNT(*) AS total
            FROM restaurante_compra
            WHERE RC_RM_ID IN ($ph)
            GROUP BY RC_RM_ID
        ");
        $stmt->execute($rmIds);
        foreach ($stmt->fetchAll() as $row) {
            $reservasPorPrato[(int) $row['RC_RM_ID']] = (int) $row['total'];
        }
    }

    // Normaliza datas dos pratos (podem ser DateTime no driver SQL Server)
    $pratosNormalizados = array_map(function ($p) use ($reservasPorPrato) {
        $data = $p['RM_DATA'] instanceof DateTime
            ? $p['RM_DATA']->format('Y-m-d')
            : (string) $p['RM_DATA'];
        return [
            'rm_id'       => (int) $p['RM_ID'],
            'nome'        => $p['RM_NOME'],
            'data'        => $data,
            'tipo_id'     => (int) $p['RM_TP_ID'],
            'tipo_nome'   => $p['RTP_NOME'],
            'prato_dia'   => (bool) $p['RM_PRATO_DIA'],
            'publicado'   => (bool) $p['RM_PUBLICADO'],
            'tem_reservas'=> ($reservasPorPrato[(int) $p['RM_ID']] ?? 0) > 0,
        ];
    }, $pratos);

    echo json_encode([
        'status'           => 'ok',
        'pratos'           => $pratosNormalizados,
        'feriados'         => $feriados,
        'dias_especiais'   => $diasEspeciais,
        'semana_publicada' => $estadoPubl['publicada'],
        'total_pratos'     => $estadoPubl['total'],
        'total_publicados' => $estadoPubl['publicados'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao carregar ementa.']);
}
