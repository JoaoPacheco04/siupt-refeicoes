<?php

/**
 * Garante que existe um utilizador autenticado e, opcionalmente,
 * que possui o perfil indicado.
 *
 * Redireciona para a página de login ou devolve uma resposta JSON
 * caso a autenticação ou autorização falhe.
 *
 * @param string|null $tipo_exigido Tipo de utilizador exigido.
 * @param bool $isApi Indica se o pedido é efetuado por um endpoint AJAX/API.
 *
 * @return array Dados do utilizador autenticado.
 */
function exigirLogin(?string $tipo_exigido = null, bool $isApi = false): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Sessão expirada. Faz login novamente.'
            ]);
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
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Sem permissão para este recurso.'
            ]);
            exit;
        }

        $paginaCorreta = $_SESSION['user_tipo'] === 'funcionario'
            ? 'validar.php'
            : 'ementa.php';

        header('Location: ' . $paginaCorreta);
        exit;
    }

    return [
        'id'   => $_SESSION['user_id'],
        'nome' => $_SESSION['user_nome'],
        'tipo' => $_SESSION['user_tipo'],
    ];
}

// ============================================
// PROTEÇÃO CSRF
// ============================================

/**
 * Gera um token CSRF para a sessão atual.
 *
 * @return string Token CSRF.
 */
function gerarCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF enviado no pedido.
 *
 * Em caso de falha, devolve uma resposta JSON ou termina
 * a execução da página, consoante o tipo de pedido.
 *
 * @param bool $isApi Indica se o pedido é efetuado por um endpoint AJAX/API.
 */
function verificarCsrfToken(bool $isApi = false): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $tokenRecebido)
    ) {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'erro',
                'mensagem' => 'Token de segurança inválido.'
            ]);
            exit;
        }

        http_response_code(403);
        exit('Acesso negado: token CSRF inválido.');
    }
}