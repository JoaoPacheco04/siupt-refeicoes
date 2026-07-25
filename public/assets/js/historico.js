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
    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        closeLabel: 'Fechar',
        cssClass: ['tingle-siupt']
    });

    modal.setContent(`
        <div class="modal-siupt-header sucesso">
            <i class="bi bi-qr-code-scan"></i>
            <h4>QR code — ${data}</h4>
        </div>
        <p class="text-muted small text-center mb-1">${descricao}</p>
        <div class="text-center py-2">
            <div id="qr-historico" style="display:inline-block;"></div>
            <p class="codigo-curto">${codigoCurto ?? ''}</p>
        </div>
        <p class="text-muted small text-center mt-1">
            <i class="bi bi-info-circle"></i>
            Apresenta este código na cantina no momento da recolha.
        </p>
    `);

    modal.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modal.close());
    modal.open();

    new QRCode(document.getElementById('qr-historico'), {
        text: qrcode,
        width: 200,
        height: 200,
        colorDark: '#1e2a3b',
        colorLight: '#ffffff'
    });
}
// ── Retomar pagamento de um pedido pendente ────────────────────────────────
document.querySelectorAll('.btn-pagar-agora').forEach(btn => {
    btn.addEventListener('click', () => {
        const pedidoId = btn.dataset.pedidoId;
        mostrarEcraPagamentoHistorico(pedidoId);
    });
});

function mostrarEcraPagamentoHistorico(pedidoId) {
    const modalPagamento = new tingle.modal({ footer: false, closeMethods: [] });
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

async function confirmarPagamentoHistorico(pedidoId, sucesso, modalPagamento) {
    modalPagamento.close();

    let dados;
    try {
        const resposta = await fetch('api/confirmar_pagamento_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_ids: pedidoId,
                resultado: sucesso ? 'sucesso' : 'falha'
            })
        });
        dados = await resposta.json();
    } catch (e) {
        dados = { status: 'erro', detalhe: [] };
    }

    const confirmado = (dados.detalhe || []).some(d => d.status === 'confirmado');

    const modalResultado = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt']
    });

    modalResultado.setContent(confirmado ? `
        <div class="modal-siupt-header sucesso">
            <i class="bi bi-check-circle-fill"></i>
            <h4>Pagamento confirmado!</h4>
        </div>
        <p class="text-muted small">O QR code já está disponível.</p>
    ` : `
        <div class="modal-siupt-header erro">
            <i class="bi bi-x-circle-fill"></i>
            <h4>Pagamento não confirmado</h4>
        </div>
        <p class="text-muted small">Podes tentar novamente quando quiseres.</p>
    `);
    modalResultado.addFooterBtn('Continuar', 'tingle-btn tingle-btn--primary', () => location.reload());
    modalResultado.open();
}