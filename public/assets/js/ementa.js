const btnComprar = document.getElementById('btnComprar');
const totalSelecionadasEl = document.getElementById('totalSelecionadas');
const totalValorEl = document.getElementById('totalValor');

// ── Permitir desmarcar o prato principal ao clicar de novo ─────────────────
const ultimoSelecionado = {};

document.querySelectorAll('.radio-prato-principal').forEach(radio => {
    const grupo = radio.name;

    radio.addEventListener('click', function () {
        if (ultimoSelecionado[grupo] === this) {
            this.checked = false;
            ultimoSelecionado[grupo] = null;

            const card = this.closest('.dia-card');
            if (card) {
                const menuCompletoBox = card.querySelector('.checkbox-menu-completo');
                if (menuCompletoBox) menuCompletoBox.checked = false;
                card.querySelectorAll('.checkbox-componente').forEach(c => c.checked = false);
                syncComponentesVisibility(card);
            }

            atualizarResumo();
        } else {
            ultimoSelecionado[grupo] = this;
            atualizarResumo();
        }
    });
});

// ── Visibilidade dos componentes (Sopa/Sobremesa/Bebida) ──────────────────
// Controlada só pelo checkbox "Menu completo": desmarcado = visível,
// marcado = escondido. Nunca mexe em style.display diretamente — só na classe.
function syncComponentesVisibility(card) {
    const menuCompletoBox = card.querySelector('.checkbox-menu-completo');
    const componentes = card.querySelector('.dia-componentes');
    if (!componentes) return;

    const mostrar = !(menuCompletoBox?.checked ?? false);
    componentes.classList.toggle('visivel', mostrar);
}

// Define o estado inicial correto em todos os cartões, assim que a página carrega
document.querySelectorAll('.dia-card').forEach(syncComponentesVisibility);

document.querySelectorAll('.checkbox-menu-completo').forEach(box => {
    box.addEventListener('change', () => {
        const card = box.closest('.dia-card');
        if (card) {
            syncComponentesVisibility(card);
            if (box.checked) {
                card.querySelectorAll('.checkbox-componente').forEach(c => c.checked = false);
            }
        }
        atualizarResumo();
    });
});

document.querySelectorAll('.checkbox-componente, .checkbox-extra').forEach(el => {
    el.addEventListener('change', atualizarResumo);
});

function coletarSelecoes() {
    const selecoes = [];

    document.querySelectorAll('.dia-card').forEach(card => {
        const data = card.dataset.data;
        const radioPrato = card.querySelector('.radio-prato-principal:checked');
        if (!radioPrato) return;

        const menuCompletoBox     = card.querySelector('.checkbox-menu-completo');
        const menuCompletoChecked = menuCompletoBox?.checked ?? false;

        const precoItem = menuCompletoChecked
            ? parseFloat(menuCompletoBox.dataset.precoMc || radioPrato.dataset.preco)
            : parseFloat(radioPrato.dataset.preco);

        const itens = [{
            rm_id: radioPrato.dataset.rmId,
            nome: radioPrato.dataset.nome,
            preco: precoItem,
            menu_completo: menuCompletoChecked
        }];

        if (!menuCompletoChecked) {
            card.querySelectorAll('.checkbox-componente:checked').forEach(c => {
                itens.push({
                    rm_id: c.dataset.rmId,
                    nome: c.dataset.nome,
                    preco: parseFloat(c.dataset.preco),
                    menu_completo: false
                });
            });
        }

        selecoes.push({ data, itens });
    });

    const extrasSelecionados = [...document.querySelectorAll('.checkbox-extra:checked')];
    if (extrasSelecionados.length > 0) {
        const dataExtras = document.getElementById('dataExtras')?.value;
        selecoes.push({
            data: dataExtras,
            itens: extrasSelecionados.map(c => ({
                rm_id: c.dataset.rmId,
                nome: c.dataset.nome,
                preco: parseFloat(c.dataset.preco),
                menu_completo: false
            }))
        });
    }

    return selecoes;
}

function atualizarResumo() {
    const selecoes = coletarSelecoes();
    let totalItens = 0;
    let totalValor = 0;

    selecoes.forEach(grupo => {
        grupo.itens.forEach(item => {
            totalItens++;
            totalValor += item.preco;
        });
    });

    totalSelecionadasEl.textContent = totalItens;
    totalValorEl.textContent = totalValor.toFixed(2).replace('.', ',') + '€';
    btnComprar.disabled = totalItens === 0;
}

btnComprar.addEventListener('click', () => {
    const selecoes = coletarSelecoes();
    if (selecoes.length === 0) return;

    const totalGeral = selecoes.reduce((soma, g) => soma + g.itens.reduce((s, i) => s + i.preco, 0), 0);
    const listaHtml = selecoes
        .map(g => g.itens.map(i => `<div class="resumo-modal-item"><span>${i.nome}</span><span>${i.preco.toFixed(2)}€</span></div>`).join(''))
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
        ${listaHtml}
        <div class="resumo-modal-total"><span>Total</span><span>${totalGeral.toFixed(2).replace('.', ',')}€</span></div>
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    modal.addFooterBtn('Confirmar e pagar', 'tingle-btn tingle-btn--primary', async () => {
        modal.close();
        await processarPedidos(selecoes, totalGeral);
    });

    modal.open();
});

async function processarPedidos(selecoes, totalGeral) {
    btnComprar.disabled = true;
    const btnSpan = btnComprar.querySelector('span');
    if (btnSpan) btnSpan.textContent = 'A criar pedido...';

    const pedidoIds = [];
    const falhas = [];

    for (const grupo of selecoes) {
        try {
            const body = new URLSearchParams({
                data_refeicao: grupo.data,
                itens: JSON.stringify(grupo.itens.map(i => ({ rm_id: i.rm_id, menu_completo: i.menu_completo })))
            });

            const resposta = await fetch('api/criar_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                pedidoIds.push(dados.pedido_id);
            } else {
                falhas.push(dados.mensagem || 'Erro desconhecido');
            }
        } catch (e) {
            falhas.push('Erro de rede ao criar pedido');
        }
    }

    if (pedidoIds.length === 0) {
        if (btnSpan) btnSpan.textContent = 'Confirmar compra';
        atualizarResumo();
        mostrarErro(falhas.length ? falhas : ['Não foi possível criar nenhum pedido.']);
        return;
    }

    mostrarEcraPagamento(pedidoIds, totalGeral, falhas);
}

function mostrarEcraPagamento(pedidoIds, total, falhas) {
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
        </div>
    `);
    modalPagamento.open();

    document.getElementById('simSucesso').onclick = () => confirmarPagamento(pedidoIds, true, modalPagamento, falhas);
    document.getElementById('simFalha').onclick = () => confirmarPagamento(pedidoIds, false, modalPagamento, falhas);
}

async function confirmarPagamento(pedidoIds, sucesso, modalPagamento, falhas) {
    modalPagamento.close();

    let dados;
    try {
        const resposta = await fetch('api/confirmar_pagamento_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_ids: pedidoIds.join(','),
                resultado: sucesso ? 'sucesso' : 'falha'
            })
        });
        dados = await resposta.json();
    } catch (e) {
        dados = { status: 'erro', detalhe: [] };
    }

    const confirmados = (dados.detalhe || []).filter(d => d.status === 'confirmado');

    const modalResultado = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt']
    });

    let conteudo;
    if (confirmados.length > 0) {
        const qrHtml = confirmados.map((c, idx) => `
            <div class="qr-resultado">
                <p class="text-muted small mb-1">Pedido #${c.pedido_id}</p>
                <canvas id="qr-${idx}"></canvas>
            </div>
        `).join('');

        conteudo = `
            <div class="modal-siupt-header sucesso">
                <i class="bi bi-check-circle-fill"></i>
                <h4>Compra confirmada!</h4>
            </div>
            <p>${confirmados.length} pedido(s) confirmado(s). Mostra o QR code na cantina.</p>
            ${qrHtml}
        `;
    } else {
        conteudo = `
            <div class="modal-siupt-header erro">
                <i class="bi bi-x-circle-fill"></i>
                <h4>Pagamento não confirmado</h4>
            </div>
            <p class="text-muted small">Podes tentar novamente a partir do histórico de compras.</p>
        `;
    }

    modalResultado.setContent(conteudo);
    modalResultado.addFooterBtn('Continuar', 'tingle-btn tingle-btn--primary', () => location.reload());
    modalResultado.open();

    confirmados.forEach((c, idx) => {
        QRCode.toCanvas(document.getElementById(`qr-${idx}`), c.qrcode, { width: 160 });
    });
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