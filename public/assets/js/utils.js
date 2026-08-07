/**
 * utils.js — Funções utilitárias partilhadas entre páginas.
 *
 * Incluir antes de qualquer script de página que precise destas funções.
 */

/**
 * Escapa caracteres HTML antes de inserir texto no DOM,
 * prevenindo ataques de injeção de código (XSS).
 *
 * @param {*} str  Valor a escapar (convertido para string automaticamente).
 * @returns {string} String com os caracteres HTML escapados.
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
