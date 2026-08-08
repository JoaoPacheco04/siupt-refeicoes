<?php
/**
 * Endpoint: Criar motivo de reclamação
 *
 * Regista um novo motivo na tabela restaurante_motivo_reclamacao.
 * O código interno (RMR_CODIGO) deve ser único, em minúsculas, sem espaços.
 * Requer papel admin_cantina.
 *
 * Parâmetros POST:
 *  - codigo  string  Código interno identificador (ex: comida_fria) — só letras minúsculas, números e underscore
 *  - label   string  Texto visível ao utilizador (ex: "Comida fria")
 *
 * Resposta JSON:
 *  { "status": "ok" }
 *  { "status": "erro", "mensagem": string }
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$codigo = trim($_POST['codigo'] ?? '');
$label  = trim($_POST['label']  ?? '');

if ($codigo === '' || $label === '' || !preg_match('/^[a-z0-9_]+$/', $codigo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos — código só pode ter letras minúsculas, números e underscore.']);
    exit;
}

$resultado = Database::criarMotivoReclamacao($codigo, $label);
echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Já existe um motivo com esse código.']);
