<?php

/**
 * Garante que existe um utilizador autenticado e, opcionalmente,
 * que possui o perfil indicado.
 *
 * Tipos suportados:
 *  - 'aluno'        — qualquer utilizador autenticado
 *  - 'funcionario'  — qualquer utilizador autenticado (alias para compatibilidade)
 *  - 'atendente'    — utilizador com papel 'atendente' ou 'admin_cantina'
 *  - 'admin_cantina'— utilizador com papel 'admin_cantina'
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

    // Verificação de autorização por tipo/papel
    if ($tipo_exigido !== null) {
        $temAcesso = false;
        $papeis    = $_SESSION['user_papeis'] ?? [];

        if (in_array($tipo_exigido, ['aluno', 'funcionario'])) {
            // Qualquer utilizador autenticado tem acesso
            $temAcesso = true;
        } elseif ($tipo_exigido === 'atendente') {
            // Atendente OU admin_cantina (admin inclui acesso à validação)
            $temAcesso = in_array('atendente', $papeis) || in_array('admin_cantina', $papeis);
        } elseif ($tipo_exigido === 'admin_cantina') {
            $temAcesso = in_array('admin_cantina', $papeis);
        }

        if (!$temAcesso) {
            if ($isApi) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'erro',
                    'mensagem' => 'Sem permissão para este recurso.'
                ]);
                exit;
            }

            // Redireciona para a página adequada ao perfil
            $paginaCorreta = !empty($papeis)
                ? 'validar.php'
                : 'ementa.php';

            header('Location: ' . APP_BASE_URL . '/' . $paginaCorreta);
            exit;
        }
    }

    return [
        'id'     => $_SESSION['user_id'],
        'nome'   => $_SESSION['user_nome'],
        'tipo'   => $_SESSION['user_tipo'],
        'papeis' => $_SESSION['user_papeis'] ?? [],
    ];
}

/**
 * Verifica rapidamente se o utilizador da sessão atual tem um papel de cantina.
 * Útil para condicionar elementos de UI (ex: links de gestão na navbar).
 */
function temPapelSessao(string $papel): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return in_array($papel, $_SESSION['user_papeis'] ?? []);
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
 * Obtém o token CSRF enviado no pedido, suportando tanto
 * formulários tradicionais (application/x-www-form-urlencoded,
 * multipart/form-data) como pedidos JSON (fetch com
 * Content-Type: application/json), onde $_POST fica sempre vazio.
 *
 * @return string Token recebido (string vazia se não encontrado).
 */
function obterCsrfTokenRecebido(): string
{
    if (!empty($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }

    // Fallback: também aceita o token no cabeçalho (alternativa comum
    // para pedidos JSON, sem precisar de o incluir no corpo)
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($headerToken)) {
        return $headerToken;
    }

    // Fallback: corpo JSON (fetch com Content-Type: application/json)
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $dados = json_decode($raw, true);
        if (is_array($dados) && !empty($dados['csrf_token'])) {
            return $dados['csrf_token'];
        }
    }

    return '';
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

    $tokenRecebido = obterCsrfTokenRecebido();

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