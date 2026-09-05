<?php
/**
 * Endpoint AJAX — Publicar ou despublicar a ementa de uma semana.
 * POST: inicio (Y-m-d), fim (Y-m-d)
 *       Para publicar: modo_abertura ('padrao' | 'imediato')
 *       Para despublicar: acao = 'despublicar'
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$inicio       = trim($_POST['inicio']        ?? '');
$fim          = trim($_POST['fim']           ?? '');
$acao         = trim($_POST['acao']          ?? '');
$modoAbertura = trim($_POST['modo_abertura'] ?? 'padrao');

// Valida datas
$dtI = DateTime::createFromFormat('Y-m-d', $inicio);
$dtF = DateTime::createFromFormat('Y-m-d', $fim);

if (
    !$dtI || $dtI->format('Y-m-d') !== $inicio ||
    !$dtF || $dtF->format('Y-m-d') !== $fim
) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Datas inválidas.']);
    exit;
}

// Quando o JS envia 'despublicar', usa $acao; caso contrário é uma publicação
if ($acao === 'despublicar') {
    try {
        $n = Database::despublicarSemanaEmenta($inicio, $fim);
        echo json_encode(['status' => 'ok', 'despublicados' => $n]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao despublicar.']);
    }
    exit;
}

// Publicar — valida modo de abertura
if (!in_array($modoAbertura, ['padrao', 'imediato'], true)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Modo de abertura inválido.']);
    exit;
}

try {
    $n = Database::publicarSemanaEmenta($inicio, $fim, $modoAbertura);
    echo json_encode(['status' => 'ok', 'publicados' => $n]);
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao publicar.']);
}
