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
   FILTROS DE ESTADO (HISTÓRICO)
   ====================================================================== */

// ── Alternar visualização por estado do pedido ──────────────────────────
const cards     = document.querySelectorAll('.compra-card');
const semFiltro = document.querySelector('.historico-sem-filtro');

/**
 * Filtra os cartões de histórico com base no estado selecionado
 * (Todos, Pendente, Confirmado, Cancelado) e exibe uma mensagem
 * de aviso caso não existam resultados para o filtro aplicado.
 */
document.querySelectorAll('.btn-filtro').forEach(btn => {
    btn.addEventListener('click', () => {
        const filtro = btn.dataset.filtro;

        // Atualiza a interface visual dos botões de filtro
        document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('ativo-filtro'));
        btn.classList.add('ativo-filtro');

        let visiveis = 0;
        
        // Percorre todos os cartões e mostra/esconde consoante o estado
        cards.forEach(card => {
            const mostrar = filtro === 'todos' || card.dataset.estado === filtro;
            card.style.display = mostrar ? '' : 'none';
            if (mostrar) visiveis++;
        });

        // Mostra o "empty state" (mensagem de sem resultados) se tudo for filtrado
        if (semFiltro) semFiltro.style.display = visiveis === 0 ? '' : 'none';
    });
});

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