<?php
/**
 * Endpoint AJAX — Criar prato na ementa diária.
 * POST: nome, tipo_id, data (Y-m-d)
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$nome   = trim($_POST['nome']    ?? '');
$tipoId = (int) ($_POST['tipo_id'] ?? 0);
$data   = trim($_POST['data']    ?? '');

if ($nome === '' || $tipoId <= 0 || $data === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos.']);
    exit;
}

try {
    $resultado = Database::criarPratoEmenta($nome, $tipoId, $data);

    if (is_string($resultado)) {
        $mensagens = [
            'nome_vazio'     => 'O nome não pode estar vazio.',
            'data_invalida'  => 'Data inválida.',
            'tipo_invalido'  => 'Tipo de refeição inválido.',
            'tipo_duplicado' => 'Já existe um item deste tipo configurado para este dia.',
            'dia_feriado'    => 'Não é possível adicionar pratos num dia de feriado.',
            'data_passada'   => 'Não é possível adicionar pratos em datas anteriores a hoje.',
        ];
        echo json_encode([
            'status'   => $resultado,
            'mensagem' => $mensagens[$resultado] ?? 'Erro desconhecido.',
        ]);
        exit;
    }

    echo json_encode(['status' => 'ok', 'rm_id' => $resultado]);
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao criar prato.']);
}
