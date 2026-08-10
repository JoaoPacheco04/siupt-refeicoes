// ── Banner "por avaliar" — fechar e lembrar com expiração de 1h ──────────
const bannerAvaliar = document.getElementById('bannerAvaliar');
if (bannerAvaliar) {
    const totalAtual = bannerAvaliar.dataset.total;
    const EXPIRACAO_MS = 3600 * 1000; // 1 hora

    // Lê o estado guardado: { total, timestamp }
    let estadoGuardado = null;
    try { estadoGuardado = JSON.parse(localStorage.getItem('banner_avaliar')); } catch (_) {}

    const expirou = !estadoGuardado || (Date.now() - estadoGuardado.ts) > EXPIRACAO_MS;
    const mesmaConta = estadoGuardado && estadoGuardado.total === totalAtual;

    // Só oculta se fechou recentemente E o número ainda é o mesmo
    if (!expirou && mesmaConta) {
        bannerAvaliar.style.display = 'none';
    }

    document.getElementById('btnFecharBanner')?.addEventListener('click', () => {
        localStorage.setItem('banner_avaliar', JSON.stringify({ total: totalAtual, ts: Date.now() }));
        bannerAvaliar.style.display = 'none';
    });
}

// Elementos principais da interface
const btnComprar = document.getElementById('btnComprar');
const totalSelecionadasEl = document.getElementById('totalSelecionadas');
const totalValorEl = document.getElementById('totalValor');

// Guarda o último número de itens para animar o contador apenas quando muda
let _ultimoTotalItens = 0;

// Escape de HTML para usar em innerHTML (previne XSS)
/**
 * Escapa caracteres HTML antes de inserir texto com innerHTML,
 * prevenindo ataques XSS.
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Permitir desmarcar o prato principal ao clicar de novo ─────────────────
/* ======================================================================
   GESTÃO DA SELEÇÃO DO PRATO PRINCIPAL
   ====================================================================== */

/**
 * Permite desmarcar um prato principal clicando novamente
 * no mesmo radio button.
 */
const ultimoSelecionado = {};

document.querySelectorAll('.radio-prato-principal').forEach(radio => {
    const grupo = radio.name;

    radio.addEventListener('click', function () {

        // Se o utilizador clicar novamente no prato selecionado, remove a seleção
        if (ultimoSelecionado[grupo] === this) {
            this.checked = false;
            ultimoSelecionado[grupo] = null;

            const card = this.closest('.dia-card');

            if (card) {
                const menuCompletoBox = card.querySelector('.checkbox-menu-completo');
                if (menuCompletoBox) {
                    menuCompletoBox.checked = false;
                }

                // Remove também todos os componentes adicionais
                card.querySelectorAll('.checkbox-componente').forEach(c => c.checked = false);

                //  mesmo após desmarcar o prato)
                syncComponentesVisibility(card);
            }

            atualizarResumo();
        } else {
            ultimoSelecionado[grupo] = this;

            // quando um prato é selecionado pela primeira vez (ex: ao mudar de radio)
            const card = this.closest('.dia-card');
            if (card) syncComponentesVisibility(card);

            atualizarResumo();
        }
    });
});

// ── Visibilidade dos componentes (Sopa/Sobremesa/Bebida) ──────────────────
/* ======================================================================
   VISIBILIDADE DOS COMPONENTES DO MENU
   ====================================================================== */

/**
 * Mostra ou esconde os componentes (sopa, bebida e sobremesa)
 * conforme exista prato principal selecionado e o utilizador
 * tenha ou não escolhido menu completo.
 */
function syncComponentesVisibility(card) {
    const menuCompletoBox = card.querySelector('.checkbox-menu-completo');
    const componentes     = card.querySelector('.dia-componentes');
    const wrap            = card.querySelector('.dia-componentes-wrap');
    if (!componentes) return;

    const temPrato = card.querySelector('.radio-prato-principal:checked') !== null;
    const mostrar  = temPrato && !(menuCompletoBox?.checked ?? false);

    componentes.classList.toggle('visivel', mostrar);
    if (wrap) {
        wrap.classList.toggle('tem-prato', temPrato);
    }
}

// Atualiza todos os cartões ao carregar a página
document.querySelectorAll('.dia-card').forEach(syncComponentesVisibility);

/**
 * Sempre que o utilizador ativa/desativa o menu completo,
 * os componentes individuais deixam de poder ser escolhidos.
 */
document.querySelectorAll('.checkbox-menu-completo').forEach(box => {

    box.addEventListener('change', () => {

        const card = box.closest('.dia-card');

        if (card) {

            syncComponentesVisibility(card);

            const resumo = card.querySelector('.menu-completo-resumo');
            if (resumo) resumo.classList.toggle('visivel', box.checked);

            if (box.checked) {
                card.querySelectorAll('.checkbox-componente').forEach(c => c.checked = false);
            }
        }

        atualizarResumo();
    });

});

// Sempre que existe alteração nas opções recalcula-se o resumo
document.querySelectorAll('.checkbox-componente, .checkbox-extra').forEach(el => {
    el.addEventListener('change', atualizarResumo);
});

// ── Extras já comprados por data — desativa e risca a checkbox ────────────
/* ======================================================================
   GESTÃO DOS EXTRAS
   ====================================================================== */

/**
 * Desativa automaticamente os extras que o utilizador
 * já comprou para a data atualmente selecionada.
 */
function syncExtrasDisponiveis() {
    const dataSelecionada = document.getElementById('dataExtras')?.value;
    if (!dataSelecionada) return;

    document.querySelectorAll('.checkbox-extra').forEach(cb => {
        const chave = `${cb.dataset.rmId}|${dataSelecionada}`;
        const jaComprado = (window.extrasJaComprados || []).includes(chave);

        cb.disabled = jaComprado;
        if (jaComprado) {
            cb.checked = false;
        }

        const label = cb.closest('.componente-opcao');
        if (label) {
            label.classList.toggle('ja-comprado', jaComprado);
            label.title = jaComprado ? 'Já compraste este extra para este dia' : '';
        }
    });

    atualizarResumo();
}

document.getElementById('dataExtras')?.addEventListener('change', syncExtrasDisponiveis);
syncExtrasDisponiveis();

/* ======================================================================
   RECOLHA DAS SELEÇÕES EFETUADAS
   ====================================================================== */

/**
 * Constrói uma estrutura contendo todos os pratos,
 * componentes e extras escolhidos pelo utilizador.
 */
function coletarSelecoes() {
    const selecoes = [];

    document.querySelectorAll('.dia-card').forEach(card => {
        const data = card.dataset.data;
        const radioPrato = card.querySelector('.radio-prato-principal:checked');
        if (!radioPrato) return;

        const menuCompletoBox     = card.querySelector('.checkbox-menu-completo');
        const menuCompletoChecked = menuCompletoBox?.checked ?? false;

        const precoMcRaw = menuCompletoBox?.dataset?.precoMc;
        let precoItem;
        if (menuCompletoChecked) {
            if (precoMcRaw !== undefined && precoMcRaw !== '') {
                precoItem = parseFloat(precoMcRaw);
            } else {
                console.warn('data-preco-mc em falta no checkbox de menu completo — resumo pode não refletir o preço real cobrado pelo servidor.');
                precoItem = parseFloat(radioPrato.dataset.preco);
            }
        } else {
            precoItem = parseFloat(radioPrato.dataset.preco);
        }

        const itens = [{
            rm_id: radioPrato.dataset.rmId,
            nome: radioPrato.dataset.nome,
            preco: precoItem,
            menu_completo: menuCompletoChecked
        }];

        // Caso não seja menu completo adicionam-se os componentes individuais
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

    // Acrescenta os extras selecionados
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

/* ======================================================================
   ATUALIZAÇÃO DO RESUMO DA COMPRA
   ====================================================================== */

/**
 * Recalcula o número de itens e o valor total da compra,
 * atualizando a interface em tempo real.
 */
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

    // Anima o contador apenas quando existe alteração
    if (totalItens !== _ultimoTotalItens) {
        totalSelecionadasEl.classList.remove('bounce');
        void totalSelecionadasEl.offsetWidth;
        totalSelecionadasEl.classList.add('bounce');
        _ultimoTotalItens = totalItens;
    }

    totalSelecionadasEl.textContent = totalItens;
    totalValorEl.textContent =
        totalValor.toFixed(2).replace('.', ',') + '€';

    btnComprar.disabled = totalItens === 0;
}

/* ======================================================================
   CONFIRMAÇÃO DA COMPRA
   ====================================================================== */

/**
 * Abre um modal de confirmação contendo o resumo
 * da compra antes da criação dos pedidos.
 */
btnComprar.addEventListener('click', () => {
    const selecoes = coletarSelecoes();
    if (selecoes.length === 0) return;

    btnComprar.disabled = true;
    const btnSpanOriginal = btnComprar.querySelector('span');
    if (btnSpanOriginal) btnSpanOriginal.textContent = 'A preparar...';

    const totalGeral = selecoes.reduce((soma, g) => soma + g.itens.reduce((s, i) => s + i.preco, 0), 0);
    const listaHtml = selecoes
        .map(g => {
            const dataFormatada = new Date(g.data + 'T00:00:00').toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'short' });
            const itensHtml = g.itens.map(i => `<div class="resumo-modal-item"><span>${escHtml(i.nome)}</span><span>${i.preco.toFixed(2)}€</span></div>`).join('');
            return `<div class="resumo-modal-grupo"><div class="resumo-modal-data">${dataFormatada}</div>${itensHtml}</div>`;
        })
        .join('');

    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        closeLabel: 'Fechar',
        cssClass: ['tingle-siupt'],
        onClose: function () {
            btnComprar.disabled = false;
            if (btnSpanOriginal) btnSpanOriginal.textContent = 'Confirmar compra';
        }
    });

    modal.setContent(`
        <div class="modal-siupt-header">
            <i class="bi bi-bag-check"></i>
            <h4>Confirmar compra</h4>
        </div>
        <div class="resumo-modal-lista">
            ${listaHtml}
        </div>
        <div class="resumo-modal-total">
            <span>Total</span>
            <span>${totalGeral.toFixed(2).replace('.', ',')}€</span>
        </div>
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    const btnConfirmar = modal.addFooterBtn('Confirmar e pagar', 'tingle-btn tingle-btn--primary', async () => {
        btnConfirmar.disabled = true;
        btnConfirmar.textContent = 'A processar…';
        modal.close();
        await processarPedidos(selecoes);
    });

    modal.open();
});

/* ======================================================================
   CRIAÇÃO DOS PEDIDOS
   ====================================================================== */

// ── Overlay de loading─────────────────────────────────────────────
const loadingOverlay = document.createElement('div');
loadingOverlay.id = 'loadingOverlay';
loadingOverlay.innerHTML = `
    <div class="loading-spinner-wrap">
        <div class="loading-spinner"></div>
        <span class="loading-spinner-texto">A criar pedido…</span>
    </div>
`;
document.body.appendChild(loadingOverlay);

function mostrarLoading(texto = 'A criar pedido…') {
    loadingOverlay.querySelector('.loading-spinner-texto').textContent = texto;
    loadingOverlay.classList.add('visivel');
}
function esconderLoading() {
    loadingOverlay.classList.remove('visivel');
}

/**
 * Envia cada conjunto de seleções para a API,
 * criando os respetivos pedidos no servidor.
 */
async function processarPedidos(selecoes) {

    mostrarLoading('A processar pedido…');
    btnComprar.disabled = true;
    const btnSpan = btnComprar.querySelector('span');
    if (btnSpan) btnSpan.textContent = 'A criar pedido...';

    const pedidoIds = [];
    const falhas = [];
    let totalConfirmado = 0;

    for (const grupo of selecoes) {
        try {
            const body = new URLSearchParams({
                data_refeicao: grupo.data,
                itens: JSON.stringify(grupo.itens.map(i => ({ rm_id: i.rm_id, menu_completo: i.menu_completo }))),
                csrf_token: window.CSRF_TOKEN
            });

            const resposta = await fetch('api/criar_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                pedidoIds.push(dados.pedido_id);
                totalConfirmado += grupo.itens.reduce((s, i) => s + i.preco, 0);
            } else {
                falhas.push(dados.mensagem || 'Erro desconhecido');
            }
        } catch (e) {
            falhas.push('Erro de rede ao criar pedido');
        }
    }

    esconderLoading();

    if (pedidoIds.length === 0) {
        if (btnSpan) btnSpan.textContent = 'Confirmar compra';
        atualizarResumo();
        mostrarErro(falhas.length ? falhas : ['Não foi possível criar nenhum pedido.']);
        return;
    }

    mostrarEcraPagamento(pedidoIds, totalConfirmado, falhas);
}


/* ======================================================================
   SIMULAÇÃO DO PAGAMENTO
   ====================================================================== */

/**
 * Mostra o ecrã que simula uma confirmação
 * MB WAY antes da confirmação definitiva.
 */
function mostrarEcraPagamento(pedidoIds, total, falhas) {

    const avisoFalhas = (falhas && falhas.length > 0)
        ? `<div class="alert alert-warning small text-start mt-3 mb-0">
             <i class="bi bi-exclamation-triangle"></i>
             ${falhas.length} item(ns) não foi possível processar: ${falhas.map(escHtml).join('; ')}
           </div>`
        : '';

    const modalPagamento = new tingle.modal({ footer: false, closeMethods: [] });
    modalPagamento.setContent(`
        <div class="text-center py-3">
            <i class="bi bi-phone" style="font-size:2.5rem;color:#3d8bb5;"></i>
            <h4 class="mt-2">A aguardar confirmação</h4>
            <p class="text-muted small">Aceita o pedido de pagamento MB WAY no teu telemóvel.<br>
            Valor a pagar: <strong>${total.toFixed(2).replace('.', ',')}€</strong></p>
            ${avisoFalhas}
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

/**
 * Confirma (ou rejeita) o pagamento através
 * da API e apresenta os QR Codes dos pedidos
 * confirmados.
 */
async function confirmarPagamento(pedidoIds, sucesso, modalPagamento, falhas) {

    modalPagamento.close();

    let dados;
    try {
        const resposta = await fetch('api/confirmar_pagamento_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_ids: pedidoIds.join(','),
                resultado: sucesso ? 'sucesso' : 'falha',
                csrf_token: window.CSRF_TOKEN
            })
        });
        dados = await resposta.json();
    } catch (e) {
        dados = { status: 'erro', detalhe: [] };
    }

    const confirmados = (dados.detalhe || []).filter(d => d.status === 'confirmado' || d.status === 'ja_pago');

    const modalResultado = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt']
    });

    let conteudo;
    if (confirmados.length > 0) {
        const qrHtml = confirmados.map((c, idx) => `
            <div class="qr-resultado">
                <p class="text-muted small mb-1">Pedido #${escHtml(c.pedido_id)}</p>
                <div id="qr-${idx}" style="display:inline-block;"></div>
                <p class="codigo-curto">${escHtml(c.codigo_curto ?? '')}</p>
            </div>
        `).join('');

        conteudo = `
            <div class="modal-siupt-header sucesso">
                <i class="bi bi-check-circle-fill"></i>
                <h4>Compra confirmada!</h4>
            </div>
            <p>${confirmados.length} pedido(s) confirmado(s). Mostra o QR code na cantina.</p>
            <div class="qr-container">${qrHtml}</div>
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
        const el = document.getElementById(`qr-${idx}`);
        if (!el) return;
        try {
            new QRCode(el, { text: c.qrcode, width: 160, height: 160, colorDark: '#1e2a3b', colorLight: '#ffffff' });
        } catch (err) {
            console.error(`Falha ao desenhar QR do pedido #${c.pedido_id}:`, err);
        }
    });
}

/* ======================================================================
   APRESENTAÇÃO DE ERROS
   ====================================================================== */

/**
 * Apresenta uma janela modal contendo todas
 * as mensagens de erro devolvidas pelo servidor.
 */
function mostrarErro(mensagens) {

    const modal = new tingle.modal({ footer: true, closeMethods: ['overlay', 'button', 'escape'] });
    modal.setContent(`
        <div class="modal-siupt-header erro">
            <i class="bi bi-x-circle-fill"></i>
            <h4>Erro ao processar</h4>
        </div>
        <div class="resumo-modal-lista">
            ${mensagens.map(f => `<div class="resumo-modal-item erro-item"><i class="bi bi-x-circle"></i> ${escHtml(f)}</div>`).join('')}
        </div>
    `);
    modal.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modal.close());
    modal.open();
}