<?php

function exigirLogin(?string $tipo_exigido = null, bool $isApi = false): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão expirada. Faz login novamente.']);
            exit;
        }
        $destino = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . APP_BASE_URL . '/login.php' . ($destino ? '?next=' . $destino : ''));
        exit;
    }

    if ($tipo_exigido !== null && $_SESSION['user_tipo'] !== $tipo_exigido) {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'erro', 'mensagem' => 'Sem permissão para este recurso.']);
            exit;
        }
        $paginaCorreta = $_SESSION['user_tipo'] === 'funcionario' ? 'validar.php' : 'ementa.php';
        header('Location: ' . $paginaCorreta);
        exit;
    }

    return [
        'id'   => $_SESSION['user_id'],
        'nome' => $_SESSION['user_nome'],
        'tipo' => $_SESSION['user_tipo'],
    ];
}