<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$id = (int) ($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');

if ($id <= 0 || $label === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados invÃ¡lidos']);
    exit;
}

$ok = Database::atualizarLabelMotivoReclamacao($id, $label);
echo json_encode($ok ? ['status' => 'ok'] : ['status' => 'erro', 'mensagem' => 'Motivo nÃ£o encontrado']);
