const checkboxes = document.querySelectorAll('.checkbox-refeicao');
const btnComprar = document.getElementById('btnComprar');
const totalSelecionadasEl = document.getElementById('totalSelecionadas');
const totalValorEl = document.getElementById('totalValor');
const btnSelecionarSemana = document.getElementById('btnSelecionarSemana');

function atualizarResumo() {
    const selecionadas = [...checkboxes].filter(c => c.checked);
    const total = selecionadas.reduce((soma, c) => soma + parseFloat(c.dataset.preco), 0);
    totalSelecionadasEl.textContent = selecionadas.length;
    totalValorEl.textContent = total.toFixed(2).replace('.', ',') + '€';
    btnComprar.disabled = selecionadas.length === 0;
}

// ── Checkbox liga/desliga o seletor de pedido especial correspondente ──────
checkboxes.forEach(c => {
    c.addEventListener('change', () => {
        const select = document.querySelector(`.pedido-especial-select[data-for="${c.dataset.id}"]`);
        if (select) {
            select.disabled = !c.checked;
            if (!c.checked) {
                select.value = '';
                c.dataset.pedido = '';
            }
        }
        atualizarResumo();
    });
});

document.querySelectorAll('.pedido-especial-select').forEach(sel => {
    sel.addEventListener('change', () => {
        const cb = document.querySelector(`.checkbox-refeicao[data-id="${sel.dataset.for}"]`);
        if (cb) cb.dataset.pedido = sel.value;
    });
});

btnSelecionarSemana.addEventListener('click', () => {
    checkboxes.forEach(c => { if (!c.disabled) c.checked = true; });
    atualizarResumo();
});

btnComprar.addEventListener('click', () => {
    const selecionadas = [...checkboxes].filter(c => c.checked);
    if (selecionadas.length === 0) return;

    const total = selecionadas.reduce((soma, c) => soma + parseFloat(c.dataset.preco), 0);

    const listaHtml = selecionadas
        .map(c => `<div class="resumo-modal-item"><span>${c.dataset.label}</span><span>${parseFloat(c.dataset.preco).toFixed(2)}€</span></div>`)
        .join('');

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
        await processarCompra(selecionadas, total);
    });

    modal.open();
});

// ── Passo 1: cria as compras (estado "pendente"), sem pagamento ────────────
async function processarCompra(selecionadas, total) {
    btnComprar.disabled = true;
    const btnSpan = btnComprar.querySelector('span');
    if (btnSpan) btnSpan.textContent = 'A criar pedido...';

    const compraIds = [];
    const falhasCriacao = [];

    for (const c of selecionadas) {
        try {
            const body = new URLSearchParams({ refeicao_id: c.dataset.id });
            if (c.dataset.pedido) body.set('pedido_especial', c.dataset.pedido);

            const resposta = await fetch('api/criar_compra.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                compraIds.push(dados.compra_id);
            } else {
                falhasCriacao.push(dados.mensagem || 'Erro desconhecido');
            }
        } catch (e) {
            falhasCriacao.push('Erro de rede ao criar compra');
        }
    }

    if (compraIds.length === 0) {
        if (btnSpan) btnSpan.textContent = 'Confirmar compra';
        atualizarResumo();
        mostrarErro(falhasCriacao.length ? falhasCriacao : ['Não foi possível criar nenhuma compra.']);
        return;
    }

    mostrarEcraPagamento(compraIds, total, falhasCriacao);
}

// ── Passo 2: ecrã de pagamento — aqui entra a integração real MB WAY ───────
function mostrarEcraPagamento(compraIds, total, falhasCriacao) {
    const modalPagamento = new tingle.modal({ footer: false, closeMethods: [] });
    modalPagamento.setContent(`
        <div class="text-center py-3">
            <i class="bi bi-phone" style="font-size:2.5rem;color:#3d8bb5;"></i>
            <h4 class="mt-2">A aguardar confirmação</h4>
            <p class="text-muted small">Aceita o pedido de pagamento MB WAY no teu telemóvel.<br>
            Valor a pagar: <strong>${total.toFixed(2).replace('.', ',')}€</strong></p>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button id="simSucesso" class="btn btn-success btn-sm">Simular aceite</button>
                <button id="simFalha" class="btn btn-outline-danger btn-sm">Simular recusa</button>
            </div>
            <p class="text-muted mt-3" style="font-size:0.7rem;">
                (Botões apenas em ambiente de desenvolvimento — a integração real substitui isto por uma notificação assíncrona do gateway.)
            </p>
        </div>
    `);
    modalPagamento.open();

    document.getElementById('simSucesso').onclick = () => confirmarPagamento(compraIds, true, modalPagamento, falhasCriacao);
    document.getElementById('simFalha').onclick = () => confirmarPagamento(compraIds, false, modalPagamento, falhasCriacao);
}

// ── Passo 3: confirma o pagamento do lote todo numa só chamada ─────────────
async function confirmarPagamento(compraIds, sucesso, modalPagamento, falhasCriacao) {
    modalPagamento.close();

    let dados;
    try {
        const resposta = await fetch('api/confirmar_pagamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                compra_ids: compraIds.join(','),
                resultado: sucesso ? 'sucesso' : 'falha'
            })
        });
        dados = await resposta.json();
    } catch (e) {
        dados = { status: 'erro', pagas: 0, total: compraIds.length };
    }

    const pagas = dados.pagas ?? 0;
    const totalTentadas = dados.total ?? compraIds.length;

    const modalResultado = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt']
    });

    let conteudoResultado;
    if (pagas === totalTentadas && pagas > 0) {
        conteudoResultado = `
            <div class="modal-siupt-header sucesso">
                <i class="bi bi-check-circle-fill"></i>
                <h4>Compra confirmada!</h4>
            </div>
            <p>${pagas} refeição(ões) paga(s) com sucesso.</p>
            <div class="modal-email-aviso">
                <i class="bi bi-envelope-check"></i>
                Vais receber por email o comprovativo com o código de validação —
                usa o cartão de estudante na cantina, ou apresenta esse código se não o tiveres contigo.
            </div>
        `;
    } else if (pagas > 0) {
        conteudoResultado = `
            <div class="modal-siupt-header aviso">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h4>${pagas} de ${totalTentadas} refeição(ões) paga(s)</h4>
            </div>
            <p class="text-muted small">As restantes ficaram pendentes — podes tentar de novo a partir do histórico de compras.</p>
        `;
    } else {
        conteudoResultado = `
            <div class="modal-siupt-header erro">
                <i class="bi bi-x-circle-fill"></i>
                <h4>Pagamento não confirmado</h4>
            </div>
            <p class="text-muted small">A compra ficou registada como pendente. Podes tentar pagar novamente a partir do histórico de compras.</p>
        `;
    }

    if (falhasCriacao.length > 0) {
        conteudoResultado += `
            <p class="text-muted small mt-2">${falhasCriacao.length} refeição(ões) não chegaram a ser criadas:</p>
            ${falhasCriacao.map(f => `<div class="resumo-modal-item erro-item"><i class="bi bi-x-circle"></i> ${f}</div>`).join('')}
        `;
    }

    modalResultado.setContent(conteudoResultado);
    modalResultado.addFooterBtn('Continuar', 'tingle-btn tingle-btn--primary', () => location.reload());
    modalResultado.open();
}

function mostrarErro(mensagens) {
    const modal = new tingle.modal({ footer: true, closeMethods: ['overlay', 'button', 'escape'] });
    modal.setContent(`
        <div class="modal-siupt-header erro">
            <i class="bi bi-x-circle-fill"></i>
            <h4>Erro ao processar</h4>
        </div>
        ${mensagens.map(f => `<div class="resumo-modal-item erro-item"><i class="bi bi-x-circle"></i> ${f}</div>`).join('')}
    `);
    modal.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modal.close());
    modal.open();
}