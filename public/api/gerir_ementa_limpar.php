<?php
/**
 * Endpoint AJAX — Limpar pratos de ementa num determinado período (dia ou semana).
 * POST: inicio (Y-m-d), fim (Y-m-d), csrf_token
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$inicio = trim($_POST['inicio'] ?? '');
$fim    = trim($_POST['fim']    ?? '');

foreach ([$inicio, $fim] as $d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Datas inválidas.']);
        exit;
    }
}

if ($inicio > $fim) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Intervalo de datas inválido.']);
    exit;
}

try {
    $res = Database::limparEmentaPeriodo($inicio, $fim);
    echo json_encode([
        'status'     => 'ok',
        'apagados'   => $res['apagados'],
        'bloqueados' => $res['bloqueados'],
    ]);
} catch (Exception $e) {
    error_log('Erro ao limpar ementa: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao remover pratos da ementa.']);
}
