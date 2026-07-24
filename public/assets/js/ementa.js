const checkboxes = document.querySelectorAll('.checkbox-refeicao');
const btnComprar = document.getElementById('btnComprar');
const totalSelecionadasEl = document.getElementById('totalSelecionadas');
const totalValorEl = document.getElementById('totalValor');
const btnSelecionarSemana = document.getElementById('btnSelecionarSemana');

// ── Actualiza o resumo no rodapé fixo ──────────────────────────────────────
function atualizarResumo() {
    const selecionadas = [...checkboxes].filter(c => c.checked);
    const total = selecionadas.reduce((soma, c) => soma + parseFloat(c.dataset.preco), 0);
    totalSelecionadasEl.textContent = selecionadas.length;
    totalValorEl.textContent = total.toFixed(2).replace('.', ',') + '€';
    btnComprar.disabled = selecionadas.length === 0;
}

checkboxes.forEach(c => c.addEventListener('change', atualizarResumo));

btnSelecionarSemana.addEventListener('click', () => {
    checkboxes.forEach(c => { if (!c.disabled) c.checked = true; });
    atualizarResumo();
});

// ── Botão de Confirmar compra ──────────────────────────────────────────────
btnComprar.addEventListener('click', () => {
    const selecionadas = [...checkboxes].filter(c => c.checked);
    if (selecionadas.length === 0) return;

    const total = selecionadas.reduce((soma, c) => soma + parseFloat(c.dataset.preco), 0);

    const listaHtml = selecionadas
        .map(c => `<div class="resumo-modal-item"><span>${c.dataset.label}</span><span>${parseFloat(c.dataset.preco).toFixed(2)}€</span></div>`)
        .join('');

    // Modal de confirmação antes de comprar
    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        closeLabel: 'Fechar',
        cssClass: ['tingle-siupt']
    });

    modal.setContent(`
        <div class="modal-siupt-header">
            <i class="bi bi-bag-check"></i>
            <h4>Confirmar compra</h4>
        </div>
        <p class="text-muted small mb-3">Confirma os dias selecionados antes de prosseguires.</p>
        ${listaHtml}
        <div class="resumo-modal-total"><span>Total</span><span>${total.toFixed(2).replace('.', ',')}€</span></div>
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    modal.addFooterBtn('Confirmar e pagar', 'tingle-btn tingle-btn--primary', async () => {
        modal.close();
        await processarCompra(selecionadas, listaHtml, total);
    });

    modal.open();
});

// ── Processar a compra via API ─────────────────────────────────────────────
// BUG 2 fix: listaHtml e total são agora parâmetros, não variáveis de scope externo
async function processarCompra(selecionadas, listaHtml, total) {
    // Estado de loading no botão
    btnComprar.disabled = true;
    const btnSpan = btnComprar.querySelector('span');
    if (btnSpan) btnSpan.textContent = 'A processar...';

    let sucesso = 0;
    const falhas = [];

    for (const c of selecionadas) {
        try {
            // BUG 5 fix: usar JSON em vez de texto.includes()
            const resposta = await fetch(`api/criar_compra.php?refeicao_id=${c.dataset.id}`);
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                sucesso++;
                c.checked = false;
                c.disabled = true;
            } else {
                falhas.push(dados.mensagem || 'Erro desconhecido');
            }
        } catch (e) {
            falhas.push('Erro de rede ao processar refeição');
        }
    }

    // Restaurar botão
    if (btnSpan) btnSpan.textContent = 'Confirmar compra';
    atualizarResumo();

    // BUG 2 fix: usar modalResultado (variável local correcta) em vez de modal
    const modalResultado = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt']
    });

    let conteudoResultado;
    if (sucesso > 0 && falhas.length === 0) {
        // Sucesso total
        conteudoResultado = `
            <div class="modal-siupt-header sucesso">
                <i class="bi bi-check-circle-fill"></i>
                <h4>Compra confirmada!</h4>
            </div>
            ${listaHtml}
            <div class="resumo-modal-total"><span>Total pago</span><span>${total.toFixed(2).replace('.', ',')}€</span></div>
            <div class="modal-email-aviso">
                <i class="bi bi-envelope-check"></i>
                Vais receber por email o comprovativo com o código de validação —
                usa o cartão de estudante na cantina, ou apresenta esse código se não o tiveres contigo.
            </div>
        `;
    } else if (sucesso > 0 && falhas.length > 0) {
        // Sucesso parcial
        conteudoResultado = `
            <div class="modal-siupt-header aviso">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h4>${sucesso} compra(s) confirmada(s)</h4>
            </div>
            <p class="text-muted small">${falhas.length} refeição(ões) não puderam ser processadas:</p>
            ${falhas.map(f => `<div class="resumo-modal-item erro-item"><i class="bi bi-x-circle"></i> ${f}</div>`).join('')}
        `;
    } else {
        // Falha total
        conteudoResultado = `
            <div class="modal-siupt-header erro">
                <i class="bi bi-x-circle-fill"></i>
                <h4>Erro ao processar</h4>
            </div>
            ${falhas.map(f => `<div class="resumo-modal-item erro-item"><i class="bi bi-x-circle"></i> ${f}</div>`).join('')}
        `;
    }

    modalResultado.setContent(conteudoResultado);
    modalResultado.addFooterBtn('Continuar', 'tingle-btn tingle-btn--primary', () => location.reload());
    modalResultado.open();
}