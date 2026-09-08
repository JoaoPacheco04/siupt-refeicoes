<?php
/**
 * Endpoint AJAX — Atualizar configuração da publicação padrão da ementa.
 *
 * Permite ao admin_cantina definir os dias de antecedência (em relação à 2.ª feira)
 * e o horário em que a ementa semanal se torna automaticamente visível aos alunos.
 *
 * POST:
 *  - dias_antecedencia (int: 0 a 14)
 *  - hora (string: HH:MM)
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();
$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$hora             = trim($_POST['hora'] ?? '');
$diasAntecedencia = isset($_POST['dias_antecedencia']) ? (int) $_POST['dias_antecedencia'] : null;

if ($hora === '' || $diasAntecedencia === null) {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Parâmetros em falta.',
    ]);
    exit;
}

$resultado = Database::atualizarConfiguracaoPublicacaoEmenta($diasAntecedencia, $hora);

if ($resultado === 'hora_invalida') {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'O formato da hora de publicação é inválido (deve ser HH:MM).',
    ]);
    exit;
}

if ($resultado === 'antecedencia_invalida') {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'O valor de antecedência deve situar-se entre 0 e 14 dias.',
    ]);
    exit;
}

if ($resultado !== true) {
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Erro ao atualizar a configuração de publicação.',
    ]);
    exit;
}

$configAtual = Database::obterConfiguracaoPublicacaoEmenta();

echo json_encode([
    'status'   => 'ok',
    'mensagem' => 'Horário padrão de publicação atualizado com sucesso.',
    'config'   => $configAtual,
]);
