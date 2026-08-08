<?php
/**
 * Endpoint: Criar feriado manual
 *
 * Regista um novo feriado na tabela restaurante_feriado.
 * Rejeita duplicados (mesma data). Requer papel admin_cantina.
 *
 * Parâmetros POST:
 *  - data  string  Data no formato YYYY-MM-DD (obrigatório)
 *  - nome  string  Nome do feriado (obrigatório)
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

$data = trim($_POST['data'] ?? '');
$nome = trim($_POST['nome'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $nome === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

$resultado = Database::criarFeriado($data, $nome);
echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Já existe um feriado nessa data.']);
