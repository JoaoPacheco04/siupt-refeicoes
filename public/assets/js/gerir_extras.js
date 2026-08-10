// Configurações globais
const CSRF_TOKEN = window.CSRF_TOKEN;

/* ======================================================================
   FUNÇÕES UTILITÁRIAS
   ====================================================================== */

// ── Escape de HTML (Prevenção XSS) ──────────────────────────────────────
/**
 * Escapa caracteres HTML antes de inserir texto no DOM com innerHTML,
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
   CRIAÇÃO DE NOVOS EXTRAS
   ====================================================================== */

// ── Submissão do formulário de novo extra ───────────────────────────────
const formNovoExtra = document.getElementById('formNovoExtra');

/**
 * Interceta a submissão do formulário para criar um novo extra,
 * envia os dados via API e recarrega a página em caso de sucesso.
 */
formNovoExtra.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nome = document.getElementById('novoNome').value.trim();
    const preco = document.getElementById('novoPreco').value;

    if (!nome || preco === '') return;

    // Bloqueia o botão para evitar múltiplas submissões acidentais
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
            mostrarSucesso(`"${nome}" foi criado com sucesso.`);
            setTimeout(() => location.reload(), 900);
        } else {
            mostrarErro(dados.mensagem || 'Não foi possível criar o extra.');
            btnSubmit.disabled = false;
        }
    } catch (err) {
        mostrarErro('Erro de rede ao criar o extra.');
        btnSubmit.disabled = false;
    }
});

/* ======================================================================
   EDIÇÃO DE EXTRAS EXISTENTES
   ====================================================================== */

// ── Abrir modal de edição ───────────────────────────────────────────────
/**
 * Captura o clique nos botões de edição de cada item na lista,
 * extrai os dados atuais da interface e abre a janela de edição.
 */
document.querySelectorAll('.btn-editar-extra').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.extra-item');
        const rmId = item.dataset.rmId;
        const tipoId = item.dataset.tipoId;

        const nomeAtual = item.querySelector('.extra-nome').textContent.trim();
        const precoAtualTexto = item.querySelector('.extra-preco').textContent.trim();

        // Converte o texto do preço (ex: "1,50€") num formato numérico (ex: "1.50")
        const precoAtual = precoAtualTexto.endsWith('€')
            ? precoAtualTexto.replace('€', '').replace(',', '.')
            : '';

        abrirModalEdicao(rmId, tipoId, nomeAtual, precoAtual);
    });
});

/**
 * Constrói e apresenta a janela modal com o formulário
 * pré-preenchido para alterar o nome e/ou preço do extra.
 */
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

/**
 * Processa as alterações, enviando pedidos independentes para
 * o nome e para o preço apenas se os respetivos valores tiverem mudado.
 */
async function guardarEdicao(rmId, tipoId, novoNome, novoPreco, nomeAtual, precoAtual) {
    const pedidos = [];

    // Regista o pedido de alteração de nome se for diferente do atual
    if (novoNome && novoNome !== nomeAtual) {
        pedidos.push(
            fetch('api/gerir_extras_atualizar_nome.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ rm_id: rmId, nome: novoNome, csrf_token: CSRF_TOKEN })
            })
        );
    }

    // Regista o pedido de alteração de preço se for diferente do atual
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
        // Aguarda que todas as alterações submetidas terminem
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

/* ======================================================================
   ELIMINAÇÃO DE EXTRAS
   ====================================================================== */

// ── Apagar extra existente ──────────────────────────────────────────────
/**
 * Pede confirmação ao utilizador e envia o pedido para
 * eliminar ou desativar o extra na base de dados.
 */
document.querySelectorAll('.btn-apagar-extra').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.extra-item');
        const rmId = item.dataset.rmId;
        const nome = item.querySelector('.extra-nome').textContent.trim();

        const modalConfirm = new tingle.modal({
            footer: true,
            closeMethods: ['overlay', 'button', 'escape'],
            cssClass: ['tingle-siupt'],
            onClose: function () { modalConfirm.destroy(); }
        });

        modalConfirm.setContent(`
            <div class="modal-siupt-header aviso">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h4>Eliminar extra?</h4>
            </div>
            <p class="text-muted small text-center">
                Tens a certeza que queres eliminar <strong>${escHtml(nome)}</strong>?<br>
                Esta ação não pode ser desfeita.
            </p>
        `);

        modalConfirm.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modalConfirm.close());
        modalConfirm.addFooterBtn('Sim, eliminar', 'tingle-btn tingle-btn--danger', async () => {
            modalConfirm.close();

            // Estado de loading: desativa o botão e mostra spinner
            btn.disabled = true;
            const iconeOriginal = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

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
                    btn.disabled = false;
                    btn.innerHTML = iconeOriginal;
                }
            } catch (err) {
                mostrarErro('Erro de rede ao eliminar o extra.');
                btn.disabled = false;
                btn.innerHTML = iconeOriginal;
            }
        });

        modalConfirm.open();
    });
});

/* ======================================================================
   REATIVAÇÃO DE EXTRAS
   ====================================================================== */

// ── Reativar extra descontinuado ────────────────────────────────────────
/**
 * Permite voltar a tornar disponível um extra que tinha sido
 * apagado/desativado anteriormente.
 */
document.querySelectorAll('.btn-reativar-extra').forEach(btn => {
    btn.addEventListener('click', async () => {
        const item = btn.closest('.extra-item');
        const rmId = item.dataset.rmId;

        // Estado de loading: desativa o botão e mostra spinner
        btn.disabled = true;
        const iconeOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

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
                btn.disabled = false;
                btn.innerHTML = iconeOriginal;
            }
        } catch (err) {
            mostrarErro('Erro de rede ao reativar o extra.');
            btn.disabled = false;
            btn.innerHTML = iconeOriginal;
        }
    });
});

/* ======================================================================
   APRESENTAÇÃO DE ERROS
   ====================================================================== */

// ── Modal de erro genérico ──────────────────────────────────────────────
/**
 * Apresenta uma janela modal estandardizada contendo
 * a mensagem de erro que tenha ocorrido num dos processos.
 */
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

/**
 * Apresenta um toast discreto de sucesso, que desaparece sozinho.
 */
function mostrarSucesso(mensagem) {
    const toast = document.createElement('div');
    toast.className = 'toast-sucesso';
    toast.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${escHtml(mensagem)}`;
    document.body.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('visivel'));
    setTimeout(() => {
        toast.classList.remove('visivel');
        setTimeout(() => toast.remove(), 300);
    }, 800);
}