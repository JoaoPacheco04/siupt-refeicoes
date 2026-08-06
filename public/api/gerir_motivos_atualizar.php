<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('funcionario', true);
verificarCsrfToken(true);

$id = (int) ($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');

if ($id <= 0 || $label === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$ok = Database::atualizarLabelMotivoReclamacao($id, $label);
echo json_encode($ok ? ['status' => 'ok'] : ['status' => 'erro', 'mensagem' => 'Motivo não encontrado']);