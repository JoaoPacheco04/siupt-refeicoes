<?php
/**
 * Endpoint: Avaliar refeição
 *
 * Permite ao aluno avaliar (1-5 estrelas) uma refeição já levantada.
 * Se a avaliação for de 1 ou 2 estrelas, aceita também um motivo
 * pré-definido (ex: "comida fria"), para dar ao gestor um sinal
 * acionável no relatório mensal, sem recorrer a texto livre.
 *
 *
 * @package siupt_refeicoes
 * @author João Pacheco
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json');

// Qualquer comprador (aluno ou funcionário) pode avaliar as suas próprias refeições.
$utilizador = exigirLogin('aluno', true);
verificarCsrfToken(true);

$pedidoId = (int) ($_POST['pedido_id'] ?? 0);
$estrelas = (int) ($_POST['estrelas'] ?? 0);
// Normaliza string vazia para null — "sem motivo" é o caso normal (3-5 estrelas).
$motivo = trim($_POST['motivo'] ?? '') ?: null;

if ($pedidoId <= 0 || $estrelas < 1 || $estrelas > 5) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos']);
    exit;
}

// Mensagens amigáveis para os códigos de erro devolvidos por Database::avaliarPedido().
$mensagens = [
    'nao_autorizado' => 'Só podes avaliar refeições já levantadas por ti.',
    'ja_avaliado' => 'Já avaliaste este pedido.',
];

$resultado = Database::avaliarPedido($pedidoId, (int) $utilizador['id'], $estrelas, $motivo);

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido']);