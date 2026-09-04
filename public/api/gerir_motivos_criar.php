<?php
/**
 * Endpoint: Criar motivo de reclamação
 *
 * Regista um novo motivo na tabela restaurante_motivo_reclamacao.
 * O código interno (RMR_CODIGO) deve ser único, em minúsculas, sem espaços.
 * Requer papel admin_cantina.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$label  = trim($_POST['label']  ?? '');
$codigo = trim($_POST['codigo'] ?? '');

if ($label === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O texto do motivo é obrigatório.']);
    exit;
}

if ($codigo !== '' && !preg_match('/^[a-z0-9_]+$/', $codigo)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Código inválido.']);
    exit;
}

$resultado = $codigo !== ''
    ? Database::criarMotivoReclamacao($codigo, $label)
    : Database::criarMotivoReclamacao($label);

$mensagemErro = match ($resultado) {
    'label_duplicado'  => 'Já existe um motivo com esse texto (verifica maiúsculas/minúsculas).',
    'codigo_duplicado' => 'Já existe um motivo com esse código interno.',
    default            => 'Não foi possível criar o motivo.',
};

echo json_encode($resultado === 'ok'
    ? ['status' => 'ok']
    : ['status' => 'erro', 'mensagem' => $mensagemErro]);
