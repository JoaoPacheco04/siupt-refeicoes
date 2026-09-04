<?php
/**
 * Endpoint AJAX — Publicar ou despublicar a ementa de uma semana.
 * POST: inicio (Y-m-d), fim (Y-m-d), acao ('publicar' | 'despublicar')
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$inicio = trim($_POST['inicio'] ?? '');
$fim    = trim($_POST['fim']    ?? '');
$acao   = trim($_POST['acao']   ?? '');

// Valida datas
$dtI = DateTime::createFromFormat('Y-m-d', $inicio);
$dtF = DateTime::createFromFormat('Y-m-d', $fim);

if (
    !$dtI || $dtI->format('Y-m-d') !== $inicio ||
    !$dtF || $dtF->format('Y-m-d') !== $fim ||
    !in_array($acao, ['publicar', 'despublicar'], true)
) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

try {
    if ($acao === 'publicar') {
        $n = Database::publicarSemanaEmenta($inicio, $fim);
        echo json_encode(['status' => 'ok', 'publicados' => $n]);
    } else {
        $n = Database::despublicarSemanaEmenta($inicio, $fim);
        echo json_encode(['status' => 'ok', 'despublicados' => $n]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar publicação.']);
}
