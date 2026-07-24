// ── Filtros de estado ────────────────────────────────────────────────────
const cards     = document.querySelectorAll('.compra-card');
const semFiltro = document.querySelector('.historico-sem-filtro');

document.querySelectorAll('.btn-filtro').forEach(btn => {
    btn.addEventListener('click', () => {
        const filtro = btn.dataset.filtro;

        // Marcar botão ativo
        document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('ativo-filtro'));
        btn.classList.add('ativo-filtro');

        // Mostrar/esconder cards
        let visiveis = 0;
        cards.forEach(card => {
            const mostrar = filtro === 'todos' || card.dataset.estado === filtro;
            card.style.display = mostrar ? '' : 'none';
            if (mostrar) visiveis++;
        });

        // Mensagem de vazio
        if (semFiltro) semFiltro.style.display = visiveis === 0 ? '' : 'none';
    });
});

// ── Modal QR code ────────────────────────────────────────────────────────

document.querySelectorAll('.btn-ver-qr').forEach(btn => {
    btn.addEventListener('click', () => {
        const qrcode    = btn.dataset.qrcode;
        const data      = btn.dataset.data;
        const descricao = btn.dataset.descricao;
        mostrarQrCode(qrcode, data, descricao);
    });
});

function mostrarQrCode(qrcode, data, descricao) {
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
        </div>
        <p class="text-muted small text-center mt-1">
            <i class="bi bi-info-circle"></i>
            Apresenta este código na cantina no momento da recolha.
        </p>
    `);

    modal.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modal.close());
    modal.open();

    // Renderizar QR após o modal estar no DOM
    new QRCode(document.getElementById('qr-historico'), {
        text: qrcode,
        width: 200,
        height: 200,
        colorDark: '#1e2a3b',
        colorLight: '#ffffff'
    });
}