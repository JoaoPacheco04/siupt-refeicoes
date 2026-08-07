<?php
require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$codigo = trim($_POST['codigo'] ?? '');
$label = trim($_POST['label'] ?? '');

if ($codigo === '' || $label === '' || !preg_match('/^[a-z0-9_]+$/', $codigo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados invÃ¡lidos â€” cÃ³digo sÃ³ pode ter letras minÃºsculas, nÃºmeros e underscore.']);
    exit;
}

$resultado = Database::criarMotivoReclamacao($codigo, $label);

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'JÃ¡ existe um motivo com esse cÃ³digo.']);
