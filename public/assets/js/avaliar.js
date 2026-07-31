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

    modal.setContent(`
        <div class="modal-siupt-header">
            <i class="bi bi-star-half"></i>
            <h4>Avaliar refeição</h4>
        </div>
        <div class="avaliacao-modal-estrelas">
            ${[1, 2, 3, 4, 5].map(i => `<i class="bi bi-star" data-valor="${i}"></i>`).join('')}
        </div>
        <input type="hidden" id="estrelasInput" value="0">
        <textarea id="comentarioInput" class="avaliacao-comentario" placeholder="Comentário (opcional)"></textarea>
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    const btnSubmeter = modal.addFooterBtn('Submeter', 'tingle-btn tingle-btn--primary', () => {
        const estrelas = document.getElementById('estrelasInput').value;
        const comentario = document.getElementById('comentarioInput').value;
        submeterAvaliacao(pedidoId, estrelas, comentario, modal);
    });
    btnSubmeter.disabled = true;

    modal.open();

    // Lógica de seleção de estrelas dentro do modal
    const estrelasContainer = modal.modal.querySelector('.avaliacao-modal-estrelas');
    const estrelasIcons = [...estrelasContainer.querySelectorAll('i')];

    estrelasContainer.addEventListener('click', e => {
        const estrela = e.target.closest('i');
        if (!estrela) return;

        const valor = parseInt(estrela.dataset.valor, 10);
        document.getElementById('estrelasInput').value = valor;
        btnSubmeter.disabled = false;

        estrelasIcons.forEach((icon, i) => {
            icon.classList.toggle('bi-star-fill', i < valor);
            icon.classList.toggle('bi-star', i >= valor);
        });
    });
}

/**
 * Envia a avaliação para a API.
 * @param {string} pedidoId
 * @param {string} estrelas
 * @param {string} comentario
 * @param {object} modal
 */
async function submeterAvaliacao(pedidoId, estrelas, comentario, modal) {
    modal.close();

    try {
        const resposta = await fetch('api/avaliar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pedido_id: pedidoId,
                estrelas: estrelas,
                comentario: comentario,
                csrf_token: window.CSRF_TOKEN
            })
        });
        const dados = await resposta.json();

        if (dados.status === 'ok') {
            location.reload(); // Recarrega para mostrar as estrelas
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
    modalErro.setContent(`
        <div class="modal-siupt-header erro"><i class="bi bi-x-circle-fill"></i><h4>Erro na avaliação</h4></div>
        <p class="text-muted small">${mensagem}</p>
    `);
    modalErro.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modalErro.close());
    modalErro.open();
}