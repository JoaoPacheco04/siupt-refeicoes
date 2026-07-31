/**
 * Lógica para o modal de avaliação de pedidos.
 * Reutilizável em qualquer página que tenha o botão .btn-avaliar
 */
document.addEventListener('DOMContentLoaded', () => {

    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-avaliar');
        if (!btn) return;

        const pedidoId = btn.dataset.pedidoId;
        if (!pedidoId) return;

        abrirModalAvaliacao(pedidoId);
    });

});

// Escape de HTML (previne XSS ao inserir texto em innerHTML)
function escHtmlAvaliar(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Abre o modal para o utilizador introduzir a avaliação.
 * @param {string} pedidoId ID do pedido a avaliar.
 */
function abrirModalAvaliacao(pedidoId) {
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
        // BUG 1 FIX: campo correto é "motivo", não "comentario"
        const motivo = document.getElementById('motivoSelect')?.value || '';
        await submeterAvaliacao(pedidoId, estrelaSelecionada, motivo, modal);
    });
    btnSubmeter.disabled = true;

    modal.open();

    const icones = modal.modal.querySelectorAll('#estrelasInput i');
    const motivoWrap = modal.modal.querySelector('#motivoWrap');

    icones.forEach(icone => {
        icone.addEventListener('click', () => {
            estrelaSelecionada = parseInt(icone.dataset.valor, 10);
            btnSubmeter.disabled = false;
            icones.forEach(i =>
                i.className = parseInt(i.dataset.valor) <= estrelaSelecionada ? 'bi bi-star-fill' : 'bi bi-star'
            );

            // Mostra o dropdown de motivo apenas com nota baixa (1-2 estrelas)
            if (motivoWrap) motivoWrap.style.display = estrelaSelecionada <= 2 ? 'block' : 'none';
            if (estrelaSelecionada > 2) {
                const motivoSelect = document.getElementById('motivoSelect');
                if (motivoSelect) motivoSelect.value = '';
            }
        });
    });
}

/**
 * Envia a avaliação para a API.
 * @param {string} pedidoId
 * @param {number} estrelas
 * @param {string} motivo
 * @param {object} modal
 */
async function submeterAvaliacao(pedidoId, estrelas, motivo, modal) {
    modal.close();

    try {
        const resposta = await fetch('api/avaliar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_id: pedidoId,
                estrelas: estrelas,
                // BUG 1 FIX: campo "motivo" (antes enviava "comentario" — API ignorava)
                motivo: motivo,
                csrf_token: window.CSRF_TOKEN
            })
        });
        const dados = await resposta.json();

        if (dados.status === 'ok') {
            location.reload();
        } else {
            mostrarErroAvaliacao(dados.mensagem || 'Ocorreu um erro ao submeter a avaliação.');
        }
    } catch (e) {
        mostrarErroAvaliacao('Ocorreu um erro de comunicação. Tenta novamente.');
    }
}

/**
 * Mostra um modal de erro específico para a avaliação.
 * @param {string} mensagem
 */
function mostrarErroAvaliacao(mensagem) {
    const modalErro = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        cssClass: ['tingle-siupt']
    });
    // SEC 2 FIX: escapar mensagem antes de inserir em innerHTML (previne XSS)
    modalErro.setContent(`
        <div class="modal-siupt-header erro"><i class="bi bi-x-circle-fill"></i><h4>Erro na avaliação</h4></div>
        <p class="text-muted small">${escHtmlAvaliar(mensagem)}</p>
    `);
    modalErro.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modalErro.close());
    modalErro.open();
}