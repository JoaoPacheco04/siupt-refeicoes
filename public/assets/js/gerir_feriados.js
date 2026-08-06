const CSRF_TOKEN = window.CSRF_TOKEN;

function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function mostrarErro(mensagem) {
    const modal = new tingle.modal({ footer: true, closeMethods: ['overlay', 'button', 'escape'] });
    modal.setContent(`
        <div class="modal-siupt-header erro"><i class="bi bi-x-circle-fill"></i><h4>Erro</h4></div>
        <p class="text-muted small">${escHtml(mensagem)}</p>
    `);
    modal.addFooterBtn('Fechar', 'tingle-btn tingle-btn--primary', () => modal.close());
    modal.open();
}

document.getElementById('formGerarMoveis').addEventListener('submit', async (e) => {
    e.preventDefault();
    const ano = document.getElementById('anoGerar').value;
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
        const resposta = await fetch('api/gerir_feriados_gerar_moveis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ ano, csrf_token: CSRF_TOKEN })
        });
        const dados = await resposta.json();
        if (dados.status === 'ok') {
            location.reload();
        } else {
            mostrarErro(dados.mensagem || 'Não foi possível gerar os feriados.');
            btn.disabled = false;
        }
    } catch (err) {
        mostrarErro('Erro de rede.');
        btn.disabled = false;
    }
});

document.getElementById('formNovoFeriado').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = document.getElementById('novaData').value;
    const nome = document.getElementById('novoNome').value.trim();
    if (!data || !nome) return;

    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
        const resposta = await fetch('api/gerir_feriados_criar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ data, nome, csrf_token: CSRF_TOKEN })
        });
        const dados = await resposta.json();
        if (dados.status === 'ok') {
            location.reload();
        } else {
            mostrarErro(dados.mensagem || 'Não foi possível criar o feriado.');
            btn.disabled = false;
        }
    } catch (err) {
        mostrarErro('Erro de rede.');
        btn.disabled = false;
    }
});

document.querySelectorAll('[data-apagar]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const item = btn.closest('.extra-item');
        const id = item.dataset.id;
        btn.disabled = true;

        try {
            const resposta = await fetch('api/gerir_feriados_apagar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id, csrf_token: CSRF_TOKEN })
            });
            const dados = await resposta.json();
            if (dados.status === 'ok') {
                location.reload();
            } else {
                mostrarErro(dados.mensagem || 'Não foi possível apagar.');
                btn.disabled = false;
            }
        } catch (err) {
            mostrarErro('Erro de rede.');
            btn.disabled = false;
        }
    });
});