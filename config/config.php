<?php
/**
 * Configurações globais da aplicação.
 *
 * Este ficheiro centraliza as configurações da aplicação,
 * incluindo a ligação à base de dados, os caminhos da
 * aplicação e os parâmetros de negócio.
 *
 * @package siupt_refeicoes
 * @author João Pacheco
 */

// ==========================================
// BASE DE DADOS (SQL SERVER)
// ==========================================

define('DB_HOST', 'localhost\SQLEXPRESS');
define('DB_NAME', 'siupt_refeicoes');
define('DB_USER', ''); // Deixe vazio para utilizar Autenticação do Windows.
define('DB_PASS', '');

// ==========================================
// ROTAS DA APLICAÇÃO
// ==========================================

/**
 * Caminho base da aplicação.
 *
 * Altere este valor caso a aplicação seja instalada
 * numa localização diferente.
 */
define('APP_BASE_URL', '/siupt-refeicoes/public');

// ==========================================
// CONFIGURAÇÕES DE NEGÓCIO
// ==========================================

/**
 * Hora limite para compra de refeições extra no próprio dia.
 *
 * Formato: HH:MM:SS.
 */
define('EXTRA_HORA_LIMITE_HOJE', '14:00:00');