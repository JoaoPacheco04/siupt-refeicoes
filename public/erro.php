<?php
/**
 * Página de erro genérica.
 *
 * Apresenta uma mensagem de erro amigável ao utilizador,
 * com código HTTP e mensagem personalizável via query string.
 *
 * Uso interno: header('Location: /public/erro.php?codigo=403');
 * Ou diretamente: include-a a partir de qualquer página.
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';

// Códigos HTTP suportados e as respetivas mensagens
$errosConhecidos = [
    400 => ['titulo' => 'Pedido inválido',          'descricao' => 'O pedido enviado é inválido ou está incompleto.'],
    401 => ['titulo' => 'Não autenticado',          'descricao' => 'Precisas de iniciar sessão para aceder a esta página.'],
    403 => ['titulo' => 'Acesso negado',            'descricao' => 'Não tens permissão para aceder a este recurso.'],
    404 => ['titulo' => 'Página não encontrada',    'descricao' => 'A página que procuras não existe ou foi removida.'],
    500 => ['titulo' => 'Erro interno do servidor', 'descricao' => 'Ocorreu um erro inesperado. Por favor tenta mais tarde.'],
];

$codigo = (int) ($_GET['codigo'] ?? 404);
if (!array_key_exists($codigo, $errosConhecidos)) {
    $codigo = 404;
}

http_response_code($codigo);

$titulo   = $errosConhecidos[$codigo]['titulo'];
$descricao = $errosConhecidos[$codigo]['descricao'];

// Ícones por tipo de erro
$icones = [
    400 => 'bi-exclamation-circle',
    401 => 'bi-lock',
    403 => 'bi-shield-x',
    404 => 'bi-search',
    500 => 'bi-bug',
];
$icone = $icones[$codigo] ?? 'bi-exclamation-triangle';

// Tenta obter o utilizador para decidir o link de "voltar"
$paginaVoltar = 'login.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    $paginaVoltar = !empty($_SESSION['user_papeis']) ? 'validar.php' : 'ementa.php';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT — Erro <?= $codigo ?></title>
    <meta name="robots" content="noindex">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #eef0f4;
        }

        .erro-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(30, 42, 59, 0.1);
            padding: 3rem 2.5rem;
            text-align: center;
            max-width: 440px;
            width: 100%;
        }

        .erro-codigo {
            font-family: 'Manrope', sans-serif;
            font-size: 4rem;
            font-weight: 800;
            color: #5c7cc9;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .erro-icone {
            font-size: 2rem;
            color: #5c7cc9;
            display: block;
            margin-bottom: 0.75rem;
        }

        .erro-titulo {
            font-family: 'Manrope', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e2a3b;
            margin-bottom: 0.5rem;
        }

        .erro-descricao {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1.75rem;
        }

        .btn-voltar {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #5c7cc9;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.4rem;
            font-size: 0.9rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-voltar:hover {
            background: #4a68b0;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="erro-card">
        <i class="bi <?= $icone ?> erro-icone"></i>
        <div class="erro-codigo"><?= $codigo ?></div>
        <div class="erro-titulo"><?= htmlspecialchars($titulo) ?></div>
        <p class="erro-descricao"><?= htmlspecialchars($descricao) ?></p>
        <a href="<?= htmlspecialchars($paginaVoltar) ?>" class="btn-voltar">
            <i class="bi bi-arrow-left"></i> Voltar ao início
        </a>
    </div>
</body>
</html>