const CSRF_TOKEN = window.CSRF_TOKEN;

// Escape de HTML para usar em innerHTML (previne XSS)
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Criar novo extra ────────────────────────────────────────────────────
const formNovoExtra = document.getElementById('formNovoExtra');

formNovoExtra.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nome = document.getElementById('novoNome').value.trim();
    const preco = document.getElementById('novoPreco').value;

    if (!nome || preco === '') return;

    const btnSubmit = formNovoExtra.querySelector('button[type="submit"]');
    btnSubmit.disabled = true;

    try {
        const resposta = await fetch('api/gerir_extras_criar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                nome,
                preco,
                csrf_token: CSRF_TOKEN
            })
        });
        const dados = await resposta.json();

        if (dados.status === 'ok') {
            location.reload();
        } else {
            mostrarErro(dados.mensagem || 'Não foi possível criar o extra.');
            btnSubmit.disabled = false;
        }
    } catch (err) {
        mostrarErro('Erro de rede ao criar o extra.');
        btnSubmit.disabled = false;
    }
});

// ── Editar extra existente (nome e/ou preço) ────────────────────────────
document.querySelectorAll('.btn-editar-extra').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.extra-item');
        const rmId = item.dataset.rmId;
        const tipoId = item.dataset.tipoId;
        const nomeAtual = item.querySelector('.extra-nome').textContent.trim();
        const precoAtualTexto = item.querySelector('.extra-preco').textContent.trim();
        const precoAtual = precoAtualTexto.endsWith('€')
            ? precoAtualTexto.replace('€', '').replace(',', '.')
            : '';

        abrirModalEdicao(rmId, tipoId, nomeAtual, precoAtual);
    });
});

function abrirModalEdicao(rmId, tipoId, nomeAtual, precoAtual) {
    const modal = new tingle.modal({
        footer: true,
        closeMethods: ['overlay', 'button', 'escape'],
        closeLabel: 'Fechar',
        cssClass: ['tingle-siupt']
    });

    modal.setContent(`
        <div class="modal-siupt-header">
            <i class="bi bi-pencil-square"></i>
            <h4>Editar extra</h4>
        </div>
        <div class="form-campo">
            <label for="editNome">Nome</label>
            <input type="text" id="editNome" value="${escHtml(nomeAtual)}">
        </div>
        <div class="form-campo">
            <label for="editPreco">Novo preço (€)</label>
            <input type="number" id="editPreco" step="0.01" min="0" value="${escHtml(precoAtual)}">
        </div>
        <p class="text-muted small mt-2">
            <i class="bi bi-info-circle"></i>
            Alterar o preço aplica-se a partir de hoje.
        </p>
    `);

    modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
    modal.addFooterBtn('Guardar', 'tingle-btn tingle-btn--primary', async () => {
        const novoNome = document.getElementById('editNome').value.trim();
        const novoPreco = document.getElementById('editPreco').value;

        await guardarEdicao(rmId, tipoId, novoNome, novoPreco, nomeAtual, precoAtual);
        modal.close();
    });

    modal.open();
}

async function guardarEdicao(rmId, tipoId, novoNome, novoPreco, nomeAtual, precoAtual) {
    const pedidos = [];

    if (novoNome && novoNome !== nomeAtual) {
        pedidos.push(
            fetch('api/gerir_extras_atualizar_nome.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ rm_id: rmId, nome: novoNome, csrf_token: CSRF_TOKEN })
            })
        );
    }

    if (novoPreco !== '' && novoPreco !== precoAtual) {
        pedidos.push(
            fetch('api/gerir_extras_atualizar_preco.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ tipo_id: tipoId, preco: novoPreco, csrf_token: CSRF_TOKEN })
            })
        );
    }

    if (pedidos.length === 0) return;

    try {
        const respostas = await Promise.all(pedidos);
        const resultados = await Promise.all(respostas.map(r => r.json()));
        const falhou = resultados.some(r => r.status !== 'ok');

        if (falhou) {
            mostrarErro('Algumas alterações não foram guardadas. Tenta novamente.');
        } else {
            location.reload();
        }
    } catch (err) {
        mostrarErro('Erro de rede ao guardar as alterações.');
    }
}

// ── Apagar extra existente ──────────────────────────────────────────────
document.querySelectorAll('.btn-apagar-extra').forEach(btn => {
    btn.addEventListener('click', async () => {
        const item = btn.closest('.extra-item');
        const rmId = item.dataset.rmId;
        const nome = item.querySelector('.extra-nome').textContent.trim();

        if (!confirm(`Eliminar "${nome}"? Esta ação não pode ser desfeita.`)) return;

        try {
            const resposta = await fetch('api/gerir_extras_apagar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ rm_id: rmId, csrf_token: CSRF_TOKEN })
            });
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                location.reload();
            } else {
                mostrarErro(dados.mensagem || 'Não foi possível eliminar o extra.');
            }
        } catch (err) {
            mostrarErro('Erro de rede ao eliminar o extra.');
        }
    });
});

// ── Reativar extra descontinuado ────────────────────────────────────────
document.querySelectorAll('.btn-reativar-extra').forEach(btn => {
    btn.addEventListener('click', async () => {
        const item = btn.closest('.extra-item');
        const rmId = item.dataset.rmId;

        try {
            const resposta = await fetch('api/gerir_extras_reativar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ rm_id: rmId, csrf_token: CSRF_TOKEN })
            });
            const dados = await resposta.json();

            if (dados.status === 'ok') {
                location.reload();
            } else {
                mostrarErro(dados.mensagem || 'Não foi possível reativar o extra.');
            }
        } catch (err) {
            mostrarErro('Erro de rede ao reativar o extra.');
        }
    });
});

// ── Modal de erro genérico ──────────────────────────────────────────────
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