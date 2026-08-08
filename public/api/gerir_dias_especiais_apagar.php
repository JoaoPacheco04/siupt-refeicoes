<?php
/**
 * Endpoint: Apagar dia especial
 *
 * Remove um registo de dia especial da tabela restaurante_dia_especial.
 * Requer papel admin_cantina.
 *
 * Parâmetros POST:
 *  - id  int  Identificador do dia especial (obrigatório)
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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

$ok = Database::apagarDiaEspecial($id);
echo json_encode($ok
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => 'Dia especial não encontrado.']);