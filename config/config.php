<?php
/**
 * Configurações globais da aplicação.
 *
 * As credenciais são lidas do ficheiro .env na raiz do projeto,
 * evitando que informação sensível seja commitada no repositório.
 * Para desenvolvimento local, copia .env.example para .env e
 * preenche os valores adequados.
 *
 * @package siupt_refeicoes
 * @author João Pacheco
 */

// ==========================================
// CARREGAMENTO DO FICHEIRO .env
// ==========================================

/**
 * Lê o ficheiro .env e define cada variável como variável de ambiente
 * (se ainda não estiver definida pelo servidor ou pelo sistema operativo).
 * Ignora linhas em branco e comentários (# ...).
 */
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $linhas = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }
        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);
        // Só define se não estiver já no ambiente (ex: variáveis do servidor)
        if (!array_key_exists($chave, $_ENV) && !array_key_exists($chave, $_SERVER)) {
            putenv("$chave=$valor");
            $_ENV[$chave] = $valor;
        }
    }
}

// ==========================================
// BASE DE DADOS (SQL SERVER)
// ==========================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost\\SQLEXPRESS');
define('DB_NAME', getenv('DB_NAME') ?: 'siupt_refeicoes');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ==========================================
// ROTAS DA APLICAÇÃO
// ==========================================

/**
 * Caminho base da aplicação.
 *
 * Altera o valor APP_BASE_URL no ficheiro .env caso a aplicação
 * seja instalada numa localização diferente.
 */
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: '/siupt-refeicoes/public');

// ==========================================
// FUSO HORÁRIO
// ==========================================

/**
 * Define explicitamente o fuso horário para Europe/Lisbon.
 * Sem isto, date() e new DateTime() usam o fuso do php.ini do servidor,
 * que pode não estar configurado para Portugal em produção — causando
 * erros silenciosos nos cálculos de prazos de compra.
 */
date_default_timezone_set('Europe/Lisbon');

// ==========================================
// PRAZOS DE EXTRAS
// ==========================================

/**
 * Hora limite de compra de extras para o próprio dia (formato HH:MM:SS).
 * Usada em Database::extraForaDeHorarioHoje() para bloquear compras tardias.
 *
 * NOTA: Se esta constante não estiver definida, a função retorna sempre false
 * (extras sempre disponíveis), sem dar qualquer erro. Por isso é definida aqui
 * com um valor explícito, em vez de depender de uma definição externa silenciosa.
 */
define('EXTRA_HORA_LIMITE_HOJE', getenv('EXTRA_HORA_LIMITE') ?: '10:00:00');
