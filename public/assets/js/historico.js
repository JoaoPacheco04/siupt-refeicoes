/* ======================================================================
   FUNÇÕES UTILITÁRIAS
   ====================================================================== */

// ── Escape de HTML (Prevenção XSS) ──────────────────────────────────────
/**
 * Escapa caracteres HTML antes de inserir texto no DOM,
 * prevenindo ataques de injeção de código (XSS).
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ======================================================================
   PAGINAÇÃO + FILTROS DE ESTADO (HISTÓRICO)
   ====================================================================== */

const cards     = document.querySelectorAll('.compra-card');
const semFiltro = document.querySelector('.historico-sem-filtro');
const paginacao = document.getElementById('paginacao');
const btnAnterior = document.getElementById('btnPagAnterior');
const btnSeguinte = document.getElementById('btnPagSeguinte');
const pagInfo     = document.getElementById('pagInfo');

const POR_PAGINA = 10;
let paginaAtual = 1;
let cardsFiltrados = [...cards]; // todos visíveis por defeito

/**
 * Atualiza a visibilidade dos cartões conforme a página atual
 * e actualiza os botões de navegação.
 */
function renderizarPagina() {
    const inicio = (paginaAtual - 1) * POR_PAGINA;
    const fim    = inicio + POR_PAGINA;
    const total  = cardsFiltrados.length;
    const totalPaginas = Math.ceil(total / POR_PAGINA);

    // Mostra só os cartões da página atual
    cardsFiltrados.forEach((card, i) => {
        card.style.display = (i >= inicio && i < fim) ? '' : 'none';
    });

    // Atualiza informação e estado dos botões
    if (pagInfo)     pagInfo.textContent = total > 0 ? `${paginaAtual} / ${totalPaginas}` : '';
    if (btnAnterior) btnAnterior.disabled = paginaAtual <= 1;
    if (btnSeguinte) btnSeguinte.disabled = paginaAtual >= totalPaginas;

    // Mostra/oculta paginação
    if (paginacao) paginacao.style.display = total > POR_PAGINA ? '' : 'none';

    // Mensagem de sem resultados
    if (semFiltro) semFiltro.style.display = total === 0 ? '' : 'none';
}

/**
 * Aplica o filtro e reinicia na página 1.
 */
function aplicarFiltro(filtro) {
    cardsFiltrados = [...cards].filter(card =>
        filtro === 'todos' || card.dataset.estado === filtro
    );
    // Esconde todos os cards inicialmente
    cards.forEach(c => c.style.display = 'none');
    paginaAtual = 1;
    renderizarPagina();
}

/**
 * Filtra os cartões de histórico com base no estado selecionado
 * e reinicia a paginação na página 1.
 */
document.querySelectorAll('.btn-filtro').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('ativo-filtro'));
        btn.classList.add('ativo-filtro');
        aplicarFiltro(btn.dataset.filtro);
    });
});

// Navegação de páginas
btnAnterior?.addEventListener('click', () => { paginaAtual--; renderizarPagina(); });
btnSeguinte?.addEventListener('click', () => { paginaAtual++; renderizarPagina(); });

// Inicialização — renderiza a primeira página com todos os cartões
aplicarFiltro('todos');


/* ======================================================================
   VISUALIZAÇÃO DE QR CODES
   ====================================================================== */

// ── Abrir modal do QR Code ──────────────────────────────────────────────
let qrModalCounter = 0; // Garante a criação de IDs únicos para a biblioteca QRCode.js

/**
 * Associa o evento de clique aos botões de visualização, extraindo
 * os dados necessários (data attributes) do próprio botão HTML.
 */
document.querySelectorAll('.btn-ver-qr').forEach(btn => {
    btn.addEventListener('click', () => {
        const qrcode      = btn.dataset.qrcode;
        const data        = btn.dataset.data;
        const descricao   = btn.dataset.descricao;
        const codigoCurto = btn.dataset.codigoCurto;
        mostrarQrCode(qrcode, data, descricao, codigoCurto);
    });
});

/**
 * Constrói uma janela modal e utiliza a biblioteca QRCode.js
 * para desenhar visualmente o código de levantamento na cantina.
 */
function mostrarQrCode(qrcode, data, descricao, codigoCurto) {
    const idUnico = `qr-historico-${++qrModalCounter}`;

    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        closeLabel: 'Fechar',
        cssClass: ['tingle-siupt'],
        onClose: function () { modal.destroy(); }
    });

    modal.setContent(`
        <div class="modal-siupt-header sucesso">
            <i class="bi bi-qr-code-scan"></i>
            <h4>QR code — ${escHtml(data)}</h4>
        </div>
        <p class="text-muted small text-center mb-1">${escHtml(descricao)}</p>
        <div class="text-center py-2">
            <div id="${idUnico}" style="display:inline-block;"></div>
            <p class="codigo-curto">${escHtml(codigoCurto ?? '')}</p>
        </div>
        <p class="text-muted small text-center mt-1">
            <i class="bi bi-info-circle"></i>
            Apresenta este código na cantina no momento da recolha.
        </p>
    `);

    modal.open();

    const elQr = document.getElementById(idUnico);
    if (elQr) {
        try {
            new QRCode(elQr, {
                text: qrcode,
                width: 200,
                height: 200,
                colorDark: '#1e2a3b',
                colorLight: '#ffffff'
            });
        } catch (err) {
            console.error('Falha ao desenhar QR code:', err);
        }
    } else {
        console.warn('Elemento do QR não encontrado.');
    }
}

/* ======================================================================
   RETOMAR PAGAMENTOS PENDENTES
   ====================================================================== */

// ── Iniciar fluxo de pagamento ──────────────────────────────────────────
/**
 * Permite ao utilizador retomar o fluxo de pagamento para um
 * pedido que tenha ficado esquecido ou falhado no estado pendente.
 */
document.querySelectorAll('.btn-pagar-agora').forEach(btn => {
    btn.addEventListener('click', () => {
        const pedidoId = btn.dataset.pedidoId;
        mostrarEcraPagamentoHistorico(pedidoId);
    });
});

/**
 * Apresenta o ecrã de simulação de pagamento MB WAY.
 */
function mostrarEcraPagamentoHistorico(pedidoId) {
    const modalPagamento = new tingle.modal({
        footer: false,
        closeMethods: [],
        onClose: function () { modalPagamento.destroy(); }
    });
    
    modalPagamento.setContent(`
        <div class="text-center py-3">
            <i class="bi bi-phone" style="font-size:2.5rem;color:#3d8bb5;"></i>
            <h4 class="mt-2">A aguardar confirmação</h4>
            <p class="text-muted small">Aceita o pedido de pagamento MB WAY no teu telemóvel.</p>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button id="simSucessoHist" class="btn btn-success btn-sm">Simular aceite</button>
                <button id="simFalhaHist" class="btn btn-outline-danger btn-sm">Simular recusa</button>
            </div>
        </div>
    `);
    modalPagamento.open();

    document.getElementById('simSucessoHist').onclick = () => confirmarPagamentoHistorico(pedidoId, true, modalPagamento);
    document.getElementById('simFalhaHist').onclick = () => confirmarPagamentoHistorico(pedidoId, false, modalPagamento);
}

let qrPagoCounter = 0;

/**
 * Processa o resultado da simulação na API. Em caso de sucesso,
 * apresenta o novo QR Code validado; em caso de falha, exibe aviso.
 */
async function confirmarPagamentoHistorico(pedidoId, sucesso, modalPagamento) {
    modalPagamento.close();

    let dados;
    try {
        const resposta = await fetch('api/confirmar_pagamento_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_ids: pedidoId,
                resultado: sucesso ? 'sucesso' : 'falha',
                csrf_token: window.CSRF_TOKEN
            })
        });
        dados = await resposta.json();
    } catch (e) {
        dados = { status: 'erro', detalhe: [] };
    }

    const confirmado = (dados.detalhe || []).find(d => d.status === 'confirmado');
    const idUnicoPago = `qr-hist-pago-${++qrPagoCounter}`;

    const modalResultado = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt'],
        onClose: function () { modalResultado.destroy(); }
    });

    if (confirmado) {
        modalResultado.setContent(`
            <div class="modal-siupt-header sucesso">
                <i class="bi bi-check-circle-fill"></i>
                <h4>Pagamento confirmado!</h4>
            </div>
            <div class="text-center py-2">
                <div id="${idUnicoPago}" style="display:inline-block;"></div>
                <p class="codigo-curto">${escHtml(confirmado.codigo_curto ?? '')}</p>
            </div>
            <p class="text-muted small text-center">
                <i class="bi bi-info-circle"></i>
                Apresenta este código na cantina no momento da recolha.
            </p>
        `);
    } else {
        modalResultado.setContent(`
            <div class="modal-siupt-header erro">
                <i class="bi bi-x-circle-fill"></i>
                <h4>Pagamento não confirmado</h4>
            </div>
            <p class="text-muted small">Podes tentar novamente quando quiseres.</p>
        `);
    }

    // Ao fechar e continuar, a página recarrega para atualizar o estado dos botões
    modalResultado.addFooterBtn('Continuar', 'tingle-btn tingle-btn--primary', () => location.reload());
    modalResultado.open();

    // Renderiza o QR apenas se o estado for confirmado e após o modal estar aberto no DOM
    if (confirmado) {
        const elQrPago = document.getElementById(idUnicoPago);
        if (elQrPago) {
            try {
                new QRCode(elQrPago, {
                    text: confirmado.qrcode,
                    width: 180,
                    height: 180,
                    colorDark: '#1e2a3b',
                    colorLight: '#ffffff'
                });
            } catch (err) {
                console.error('Falha ao desenhar QR após pagamento:', err);
            }
        }
    }
}

/* ======================================================================
   CANCELAMENTO DE PEDIDOS
   ====================================================================== */

// ── Iniciar fluxo de cancelamento ───────────────────────────────────────
/**
 * Captura o clique e inicia a janela de confirmação de perigo 
 * antes de eliminar um pedido pendente (para evitar cliques acidentais).
 */
document.querySelectorAll('.btn-cancelar-pendente').forEach(btn => {
    btn.addEventListener('click', () => {
        const pedidoId = btn.dataset.pedidoId;
        mostrarConfirmacaoCancelar(pedidoId);
    });
});

/**
 * Exibe o modal para confirmar a intenção de cancelar.
 */
function mostrarConfirmacaoCancelar(pedidoId) {
    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt'],
        onClose: function () { modal.destroy(); }
    });

    modal.setContent(`
        <div class="modal-siupt-header aviso">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <h4>Cancelar pedido?</h4>
        </div>
        <p class="text-muted small text-center">
            Esta ação não pode ser revertida. Tens a certeza que queres cancelar este pedido pendente?
        </p>
    `);

    modal.addFooterBtn('Não, manter', 'tingle-btn tingle-btn--default', () => modal.close());
    modal.addFooterBtn('Sim, cancelar', 'tingle-btn tingle-btn--danger', () => {
        cancelarPedido(pedidoId, modal);
    });

    modal.open();
}

/**
 * Envia o pedido de cancelamento à API.
 * Em caso de sucesso, remove visualmente o cartão do DOM sem necessidade
 * de recarregar a página inteira, tornando a interface mais fluida.
 */
async function cancelarPedido(pedidoId, modal) {
    modal.close();

    try {
        const resposta = await fetch('api/cancelar_pedido_pendente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_id: pedidoId,
                csrf_token: window.CSRF_TOKEN
            })
        });
        const dados = await resposta.json();

        if (dados.status === 'ok') {
            // Remove o elemento visualmente de imediato
            document.querySelector(`.compra-card[data-id='${pedidoId}']`)?.remove();
        } else {
            alert(dados.mensagem || 'Ocorreu um erro ao cancelar o pedido.');
        }
    } catch (e) {
        alert('Ocorreu um erro de comunicação. Tenta novamente.');
    }
}

document.querySelectorAll('.btn-avaliar').forEach(btn => {
    btn.addEventListener('click', () => {
        const pedidoId = btn.dataset.pedidoId;
        mostrarModalAvaliacao(pedidoId);
    });
});

/**
 * Constrói e abre o modal para o utilizador introduzir a avaliação da refeição.
 * @param {string} pedidoId ID do pedido a ser avaliado.
 */
function mostrarModalAvaliacao(pedidoId) {
    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt'],
        onClose: function () { modal.destroy(); }
    });

    let estrelaSelecionada = 0;

    modal.setContent(`
        <div class="modal-siupt-header">
            <i class="bi bi-star-half"></i>
            <h4>Avaliar refeição</h4>
        </div>
        <div class="avaliacao-estrelas-input" id="estrelasInput">
            ${[1,2,3,4,5].map(n => `<i class="bi bi-star" data-valor="${n}"></i>`).join('')}
        </div>
        <div class="avaliacao-motivo-wrap" id="motivoWrap" style="display:none;">
            <label for="motivoSelect" class="avaliacao-motivo-label">O que correu mal? (opcional)</label>
            <select id="motivoSelect" class="avaliacao-motivo-select">
                <option value="">Selecionar motivo…</option>
                <option value="comida_fria">Comida fria</option>
                <option value="porcao_pequena">Porção pequena</option>
                <option value="qualidade_abaixo">Qualidade abaixo do esperado</option>
                <option value="erro_pedido">Refeição errada</option>
                <option value="demora_entrega">Demora na entrega</option>
            </select>
        </div>
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    const btnSubmeter = modal.addFooterBtn('Enviar avaliação', 'tingle-btn tingle-btn--primary', async () => {
        if (estrelaSelecionada === 0) { alert('Escolhe pelo menos 1 estrela.'); return; }
        const motivo = document.getElementById('motivoSelect')?.value || '';
        await enviarAvaliacao(pedidoId, estrelaSelecionada, motivo, modal);
    });
    btnSubmeter.disabled = true;

    modal.open();

    const icones = modal.modal.querySelectorAll('#estrelasInput i');
    const motivoWrap = modal.modal.querySelector('#motivoWrap');

    icones.forEach(icone => {
        icone.addEventListener('click', () => {
            estrelaSelecionada = parseInt(icone.dataset.valor);
            btnSubmeter.disabled = false;
            icones.forEach(i => i.className = parseInt(i.dataset.valor) <= estrelaSelecionada ? 'bi bi-star-fill' : 'bi bi-star');

            // Mostra o dropdown com 1 ou 2 estrelas
            if (motivoWrap) motivoWrap.style.display = estrelaSelecionada <= 2 ? 'block' : 'none';
            if (estrelaSelecionada > 2) {
                const motivoSelect = document.getElementById('motivoSelect');
                if (motivoSelect) motivoSelect.value = '';
            }
        });
    });
}

/**
 * Envia a avaliação (estrelas e motivo opcional) para a API.
 * @param {string} pedidoId
 * @param {number} estrelas
 * @param {string} motivo
 * @param {object} modal
 */
async function enviarAvaliacao(pedidoId, estrelas, motivo, modal) {
    try {
        const resposta = await fetch('api/avaliar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_id: pedidoId,
                estrelas,
                motivo,
                csrf_token: window.CSRF_TOKEN
            })
        });
        const dados = await resposta.json();
        modal.close();
        if (dados.status === 'ok') {
            location.reload();
        } else {
            alert(dados.mensagem || 'Não foi possível enviar a avaliação.');
        }
    } catch (e) {
        modal.close();
        alert('Erro de rede ao enviar a avaliação.');
    }
}

document.querySelectorAll('.btn-transferir').forEach(btn => {
    btn.addEventListener('click', () => {
        mostrarModalTransferir(btn.dataset.pedidoId);
    });
});

function mostrarModalTransferir(pedidoId) {
    const modal = new tingle.modal({ footer: true, closeMethods: ['overlay', 'button', 'escape'], cssClass: ['tingle-siupt'] });

    modal.setContent(`
        <div class="modal-siupt-header">
            <i class="bi bi-send"></i>
            <h4>Transferir refeição</h4>
        </div>
        <p class="text-muted small">Introduz o número de estudante/colaborador de quem vai receber.</p>
        <input type="text" id="biccDestinoInput" class="form-control" placeholder="Número">
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    modal.addFooterBtn('Transferir', 'tingle-btn tingle-btn--primary', async () => {
        const bicc = document.getElementById('biccDestinoInput').value.trim();
        if (!bicc) return;
        await transferirPedido(pedidoId, bicc, modal);
    });

    modal.open();
}

async function transferirPedido(pedidoId, biccDestino, modal) {
    try {
        const resposta = await fetch('api/transferir_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ pedido_id: pedidoId, bicc_destino: biccDestino, csrf_token: window.CSRF_TOKEN })
        });
        const dados = await resposta.json();
        modal.close();
        if (dados.status === 'ok') {
            location.reload();
        } else {
            alert(dados.mensagem || 'Não foi possível transferir.');
        }
    } catch (e) {
        modal.close();
        alert('Erro de rede ao transferir.');
    }
}