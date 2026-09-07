<?php
/**
 * Endpoint: Atualizar horas limite de compra (prazos)
 *
 * Permite ao funcionário com papel admin_cantina atualizar as horas limite
 * e antecedência para pratos da ementa (individual ou todos) e pratos extra.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');

exigirPost();

$utilizador = exigirLogin('admin_cantina', true);
verificarCsrfToken(true);

$acao = trim($_POST['acao'] ?? '');
$hora = trim($_POST['hora'] ?? '');
$diasAntecedencia = isset($_POST['dias_antecedencia']) ? (int) $_POST['dias_antecedencia'] : 0;

if ($hora === '') {
    echo json_encode(['status' => 'erro', 'mensagem' => 'A hora limite é obrigatória.']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Formato de hora inválido. Usa o formato HH:MM.']);
    exit;
}

if ($diasAntecedencia < 0 || $diasAntecedencia > 7) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'O número de dias de antecedência deve estar entre 0 e 7.']);
    exit;
}

switch ($acao) {
    case 'atualizar_tipo':
        $tipoId = (int) ($_POST['tipo_id'] ?? 0);
        if ($tipoId <= 0) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Tipo de refeição inválido.']);
            exit;
        }
        $res = Database::definirPrazoTipo($tipoId, $hora, $diasAntecedencia);
        if ($res === true) {
            echo json_encode([
                'status' => 'ok',
                'mensagem' => 'Hora limite do prato atualizada com sucesso.'
            ]);
        } else {
            echo json_encode([
                'status' => 'erro',
                'mensagem' => $res === 'hora_invalida' ? 'Hora inválida.' : 'Não foi possível atualizar o prazo.'
            ]);
        }
        break;

    case 'atualizar_todos_ementa':
        $res = Database::atualizarPrazosEmenta($hora, $diasAntecedencia);
        if ($res === true) {
            echo json_encode([
                'status' => 'ok',
                'mensagem' => 'Horas limite de todos os pratos da ementa atualizadas com sucesso.'
            ]);
        } else {
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Não foi possível atualizar os prazos da ementa.'
            ]);
        }
        break;

    case 'atualizar_extras':
        $res = Database::atualizarPrazoExtras($hora, $diasAntecedencia);
        if ($res === true) {
            echo json_encode([
                'status' => 'ok',
                'mensagem' => 'Hora limite para pratos extra atualizada com sucesso.'
            ]);
        } else {
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Não foi possível atualizar o prazo dos extras.'
            ]);
        }
        break;

    default:
        echo json_encode(['status' => 'erro', 'mensagem' => 'Ação desconhecida.']);
        break;
}
