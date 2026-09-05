<?php
/**
 * Endpoint AJAX — Copiar pratos de um dia para outro dia.
 * POST: data_origem (Y-m-d), data_destino (Y-m-d)
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$dataOrigem  = trim($_POST['data_origem']  ?? '');
$dataDestino = trim($_POST['data_destino'] ?? '');

if ($dataOrigem === '' || $dataDestino === '' || $dataOrigem === $dataDestino) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Datas inválidas.']);
    exit;
}

try {
    $res = Database::copiarPratosDia($dataOrigem, $dataDestino);

    if (isset($res['status']) && $res['status'] !== 'ok') {
        $msgs = [
            'data_passada' => 'Não é possível copiar pratos para uma data anterior a hoje.',
            'dia_feriado'  => 'O dia de destino é um feriado.',
            'origem_vazia' => 'O dia de origem não tem pratos para copiar.',
        ];
        echo json_encode([
            'status'   => $res['status'],
            'mensagem' => $msgs[$res['status']] ?? 'Erro ao copiar pratos.',
        ]);
        exit;
    }

    echo json_encode([
        'status'    => 'ok',
        'copiados'  => $res['copiados'],
        'ignorados' => $res['ignorados'],
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro interno ao copiar dia.']);
}
