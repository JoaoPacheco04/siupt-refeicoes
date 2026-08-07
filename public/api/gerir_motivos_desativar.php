<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados invÃ¡lidos']);
    exit;
}

$ok = Database::desativarMotivoReclamacao($id);
echo json_encode($ok ? ['status' => 'ok'] : ['status' => 'erro', 'mensagem' => 'Motivo nÃ£o encontrado']);
