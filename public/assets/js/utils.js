/**
 * Funções utilitárias partilhadas entre as páginas da aplicação.
 *
 * Carregado antes de qualquer script de página para garantir
 * que as funções estão disponíveis em todo o contexto.
 */

// ── Escape de HTML (Prevenção XSS) ──────────────────────────────────────
/**
 * Escapa caracteres HTML antes de inserir texto no DOM com innerHTML,
 * prevenindo ataques de injeção de código (XSS).
 *
 * @param {*} str Valor a escapar.
 * @returns {string} String com caracteres especiais escapados.
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
