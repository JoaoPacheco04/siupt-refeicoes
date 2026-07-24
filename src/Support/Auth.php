<?php

function exigirLogin(?string $tipo_exigido = null): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo "Acesso negado: sessao invalida. Faz login primeiro.";
        exit;
    }

    if ($tipo_exigido !== null && $_SESSION['user_tipo'] !== $tipo_exigido) {
        http_response_code(403);
        echo "Acesso negado: este perfil nao tem permissao para esta pagina.";
        exit;
    }

    return [
        'id' => $_SESSION['user_id'],
        'nome' => $_SESSION['user_nome'],
        'tipo' => $_SESSION['user_tipo'],
    ];
}
