<?php
/**
 * Endpoint: Pesquisar utilizadores
 *
 * Pesquisa utilizadores pelo nome ou número BICC para o painel de
 * atribuição de papéis em gerir_atendentes.php.
 * Devolve no máximo 10 resultados, incluindo os papéis de cantina atuais.
 * Requer papel admin_cantina.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');
$utilizador = exigirLogin('admin_cantina', true);

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['status' => 'ok', 'utilizadores' => []]);
    exit;
}

$resultados = Database::pesquisarUtilizador($query);
echo json_encode(['status' => 'ok', 'utilizadores' => $resultados]);
