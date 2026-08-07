const CSRF_TOKEN = window.CSRF_TOKEN;

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function mostrarErro(mensagem) {
    const modal = new tingle.modal({ footer: true, closeMethods: ['overlay', 'button', 'escape'] });
    modal.setContent(`
        <div class="modal-siupt-header erro">
            <i class="bi bi-x-circle-fill"></i>
            <h4>Erro</h4>
        </div>
        <p class="text-muted small">${escHtml(mensagem)}</p>
    `);
    modal.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modal.close());
    modal.open();
}

document.getElementById('formNovoMotivo').addEventListener('submit', async (e) => {
    e.preventDefault();

    const codigo = document.getElementById('novoCodigo').value.trim();
    const label = document.getElementById('novoLabel').value.trim();
    if (!codigo || !label) return;

    const btnSubmit = e.target.querySelector('button[type="submit"]');
    btnSubmit.disabled = true;

    try {
        const resposta = await fetch('api/gerir_motivos_criar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ codigo, label, csrf_token: CSRF_TOKEN })
        });
        const dados = await resposta.json();

        if (dados.status === 'ok') {
            location.reload();
        } else {
            mostrarErro(dados.mensagem || 'Não foi possível criar o motivo.');
            btnSubmit.disabled = false;
        }
    } catch (err) {
        mostrarErro('Erro de rede ao criar o motivo.');
        btnSubmit.disabled = false;
    }
});

// Botões DESATIVAR / REATIVAR — usam data-acao
document.querySelectorAll('[data-acao]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const item = btn.closest('.extra-item');
        const id = item.dataset.id;
        const acao = btn.dataset.acao;

        btn.disabled = true;

        try {
            const resposta = await fetch(`api/gerir_motivos_${acao}.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id, csrf_token: CSRF_TOKEN })
            });
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                location.reload();
            } else {
                mostrarErro(dados.mensagem || 'Não foi possível concluir a operação.');
                btn.disabled = false;
            }
        } catch (err) {
            mostrarErro('Erro de rede.');
            btn.disabled = false;
        }
    });
});

// Botão EDITAR — usa data-editar, abre modal com o texto atual
document.querySelectorAll('[data-editar]').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.extra-item');
        const id = item.dataset.id;
        const labelAtual = item.dataset.label;

        const modal = new tingle.modal({
            footer: true,
            closeMethods: ['overlay', 'button', 'escape'],
            cssClass: ['tingle-siupt'],
            onClose: function () { modal.destroy(); }
        });

        modal.setContent(`
            <div class="modal-siupt-header">
                <i class="bi bi-pencil-square"></i>
                <h4>Editar motivo</h4>
            </div>
            <div class="form-campo">
                <label for="editLabel">Texto a mostrar ao aluno</label>
                <input type="text" id="editLabel" value="${escHtml(labelAtual)}">
            </div>
        `);

        modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());

        
        const btnGuardar = modal.addFooterBtn('Guardar', 'tingle-btn tingle-btn--primary', async () => {
            const novoLabel = document.getElementById('editLabel').value.trim();
            if (!novoLabel) return;

            btnGuardar.disabled = true;

            try {
                const resposta = await fetch('api/gerir_motivos_atualizar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ id, label: novoLabel, csrf_token: CSRF_TOKEN })
                });
                const dados = await resposta.json();
                modal.close();

                if (dados.status === 'ok') {
                    location.reload();
                } else {
                    mostrarErro(dados.mensagem || 'Não foi possível guardar.');
                }
            } catch (err) {
                modal.close();
                mostrarErro('Erro de rede.');
            }
        });

        modal.open();
    });
});