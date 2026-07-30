// Escape de HTML para usar em innerHTML (previne XSS)
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Filtros de estado ────────────────────────────────────────────────────
const cards     = document.querySelectorAll('.compra-card');
const semFiltro = document.querySelector('.historico-sem-filtro');

document.querySelectorAll('.btn-filtro').forEach(btn => {
    btn.addEventListener('click', () => {
        const filtro = btn.dataset.filtro;

        document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('ativo-filtro'));
        btn.classList.add('ativo-filtro');

        let visiveis = 0;
        cards.forEach(card => {
            const mostrar = filtro === 'todos' || card.dataset.estado === filtro;
            card.style.display = mostrar ? '' : 'none';
            if (mostrar) visiveis++;
        });

        if (semFiltro) semFiltro.style.display = visiveis === 0 ? '' : 'none';
    });
});

// ── Modal QR code ────────────────────────────────────────────────────────
let qrModalCounter = 0;

document.querySelectorAll('.btn-ver-qr').forEach(btn => {
    btn.addEventListener('click', () => {
        const qrcode      = btn.dataset.qrcode;
        const data        = btn.dataset.data;
        const descricao   = btn.dataset.descricao;
        const codigoCurto = btn.dataset.codigoCurto;
        mostrarQrCode(qrcode, data, descricao, codigoCurto);
    });
});

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

// ── Retomar pagamento de um pedido pendente ────────────────────────────────
document.querySelectorAll('.btn-pagar-agora').forEach(btn => {
    btn.addEventListener('click', () => {
        const pedidoId = btn.dataset.pedidoId;
        mostrarEcraPagamentoHistorico(pedidoId);
    });
});

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

    modalResultado.addFooterBtn('Continuar', 'tingle-btn tingle-btn--primary', () => location.reload());
    modalResultado.open();

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

// ── Cancelar pedido pendente ─────────────────────────────────────────────
document.querySelectorAll('.btn-cancelar-pendente').forEach(btn => {
    btn.addEventListener('click', () => {
        const pedidoId = btn.dataset.pedidoId;
        mostrarConfirmacaoCancelar(pedidoId);
    });
});

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

async function cancelarPedido(pedidoId, modal) {
    modal.close();

    let dados;
    try {
        const resposta = await fetch('api/cancelar_pedido_pendente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_id: pedidoId,
                csrf_token: window.CSRF_TOKEN
            })
        });
        dados = await resposta.json();

        if (dados.status === 'ok') {
            document.querySelector(`.compra-card[data-id='${pedidoId}']`)?.remove();
        } else {
            alert(dados.mensagem || 'Ocorreu um erro ao cancelar o pedido.');
        }
    } catch (e) {
        alert('Ocorreu um erro de comunicação. Tenta novamente.');
    }
}