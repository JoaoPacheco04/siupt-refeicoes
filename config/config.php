<?php
/**
 * Configurações globais da aplicação.
 * As credenciais são geridas através do ficheiro .env.
 *
 * @package siupt_refeicoes
 * @author João Pacheco
 */

// ------------------------------------------
// 1. CARREGAMENTO DO .ENV
// ------------------------------------------
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $linhas = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        
        // Ignora comentários e linhas inválidas
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }
        
        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);
        
        // Regista a variável se ainda não existir no ambiente
        if (!array_key_exists($chave, $_ENV) && !array_key_exists($chave, $_SERVER)) {
            putenv("$chave=$valor");
            $_ENV[$chave] = $valor;
        }
    }
}


// BASE DE DADOS (SQL SERVER)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost\\SQLEXPRESS');
define('DB_NAME', getenv('DB_NAME') ?: 'siupt_refeicoes');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ROTAS DA APLICAÇÃO
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: '/siupt-refeicoes/public');

// FUSO HORÁRIO
date_default_timezone_set('Europe/Lisbon');

// SEGURANÇA — Cookies de sessão
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
// Em produção (HTTPS), ativar também:
// ini_set('session.cookie_secure', '1');

// SEGURANÇA — Headers HTTP (enviados em todas as páginas que incluam config.php)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Em produção com HTTPS:
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// REGRAS DE NEGÓCIO (Pratos_Extras)
// Hora limite para compra de extras no próprio dia (Formato: HH:MM:SS)
define('EXTRA_HORA_LIMITE_HOJE', getenv('EXTRA_HORA_LIMITE') ?: '10:00:00');