/**
 * gerir_atendentes.js
 *
 * Lógica de interação da página de gestão de papéis de cantina.
 * Requer: window.CSRF_TOKEN definido inline no PHP.
 */
(function () {
    'use strict';

    const CSRF      = window.CSRF_TOKEN;
    const inputPesq = document.getElementById('inputPesquisa');
    const divResult = document.getElementById('pesquisaResultados');
    const painelAt  = document.getElementById('painelAtribuicao');

    // ID do utilizador atualmente selecionado no painel de atribuição
    let utilizadorSelecionado = null;

    // ── Utilitário: escapa HTML para prevenir XSS ──────────────────────
    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Utilitário: toast de feedback ─────────────────────────────────
    function mostrarToast(mensagem, tipo) {
        tipo = tipo || 'success';
        const antigo = document.getElementById('toast-papeis');
        if (antigo) antigo.remove();

        const t = document.createElement('div');
        t.id        = 'toast-papeis';
        t.className = 'atendentes-toast atendentes-toast--' +
            (tipo === 'success' ? 'success' : tipo === 'warning' ? 'warning' : 'danger');
        t.innerHTML =
            '<span>' + esc(mensagem) + '</span>' +
            '<button class="atendentes-toast-fechar">&times;</button>';
        t.querySelector('.atendentes-toast-fechar').addEventListener('click', function () { t.remove(); });
        document.querySelector('main').prepend(t);
        if (tipo === 'success') setTimeout(function () { t.remove(); }, 4000);
    }

    // ── Pesquisa de utilizadores com debounce ──────────────────────────
    let debounceTimer;
    inputPesq.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = inputPesq.value.trim();
        if (query.length < 2) {
            divResult.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(function () { pesquisar(query); }, 300);
    });

    async function pesquisar(query) {
        try {
            const resp = await fetch('api/gerir_papeis_pesquisar.php?q=' + encodeURIComponent(query));
            const data = await resp.json();
            renderResultados(data.utilizadores ?? []);
        } catch {
            divResult.innerHTML =
                '<div class="resultado-item" style="color:#dc2626; font-size:0.85rem;">Erro de ligação.</div>';
        }
    }

    function renderResultados(lista) {
        if (lista.length === 0) {
            divResult.innerHTML =
                '<div class="resultado-item" style="color:#8a99ad; font-size:0.85rem;">Nenhum resultado encontrado.</div>';
            return;
        }
        divResult.innerHTML = lista.map(function (u) {
            const papeis = (u.papeis || '').split(',').filter(Boolean);
            const badges = papeis.map(function (p) {
                return p === 'admin_cantina'
                    ? '<span class="badge-admin">Admin</span>'
                    : '<span class="badge-atendente">Atendente</span>';
            }).join(' ');

            return '<div class="resultado-item"' +
                   ' data-id="'    + esc(u.U_ID)   + '"' +
                   ' data-nome="'  + esc(u.U_NOME)  + '"' +
                   ' data-bicc="'  + esc(u.U_BICC)  + '"' +
                   ' data-papeis="' + esc(u.papeis || '') + '">' +
                   '<div class="resultado-avatar">' + esc(u.U_NOME.charAt(0).toUpperCase()) + '</div>' +
                   '<div class="resultado-info">' +
                   '<div class="resultado-nome">' + esc(u.U_NOME) + '</div>' +
                   '<div class="resultado-bicc">Nº ' + esc(u.U_BICC) + '</div>' +
                   '</div>' +
                   '<div class="resultado-papeis-atuais">' + badges + '</div>' +
                   '</div>';
        }).join('');

        divResult.querySelectorAll('.resultado-item[data-id]').forEach(function (el) {
            el.addEventListener('click', function () { selecionarUtilizador(el.dataset); });
        });
    }

    function selecionarUtilizador(d) {
        utilizadorSelecionado = {
            id: d.id,
            nome: d.nome,
            bicc: d.bicc,
            papeis: (d.papeis || '').split(',').filter(Boolean)
        };
        divResult.innerHTML = '';
        inputPesq.value = d.nome + ' (' + d.bicc + ')';

        document.getElementById('atribuicaoAvatar').textContent = d.nome.charAt(0).toUpperCase();
        document.getElementById('atribuicaoNome').textContent   = d.nome;
        document.getElementById('atribuicaoBicc').textContent   = 'Nº ' + d.bicc;
        atualizarLabelPapeis(utilizadorSelecionado.papeis);

        painelAt.classList.remove('painel-atribuicao-oculto');
    }

    function atualizarLabelPapeis(papeis) {
        const label = document.getElementById('labelPapeisAtuais');
        if (!papeis || papeis.length === 0) {
            label.textContent = 'Nenhum';
        } else {
            label.innerHTML = papeis.map(function (p) {
                return p === 'admin_cantina'
                    ? '<span class="badge-admin">Admin Cantina</span>'
                    : '<span class="badge-atendente">Atendente</span>';
            }).join(' ');
        }
    }

    // ── Botões de atribuição no painel de pesquisa ─────────────────────
    ['btnAtribuirAtendente', 'btnAtribuirAdmin'].forEach(function (id) {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.addEventListener('click', async function () {
            if (!utilizadorSelecionado) return;
            const papel = this.dataset.papel;
            const textoOriginal = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> A processar…';
            await chamarApi('api/gerir_papeis_atribuir.php', { user_id: utilizadorSelecionado.id, papel }, this, textoOriginal);
        });
    });

    // ── Delegação de eventos para botões na tabela ─────────────────────
    const tabela = document.getElementById('tabelaUtilizadores');
    if (tabela) {
        tabela.addEventListener('click', async function (e) {
            const btn = e.target.closest('.btn-papel');
            if (!btn) return;

            const userId = btn.dataset.userId;
            const papel  = btn.dataset.papel;
            const acao   = btn.classList.contains('btn-papel-remove')
                ? 'api/gerir_papeis_revogar.php'
                : 'api/gerir_papeis_atribuir.php';

            const confirmMsg = btn.classList.contains('btn-papel-remove')
                ? 'Tem a certeza que quer remover este papel?'
                : null;

            if (confirmMsg && !confirm(confirmMsg)) return;

            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

            await chamarApi(acao, { user_id: userId, papel }, btn, textoOriginal);
        });
    }

    // ── Fecho do dropdown ao clicar fora ──────────────────────────────
    document.addEventListener('click', function (e) {
        if (!inputPesq.contains(e.target) && !divResult.contains(e.target)) {
            divResult.innerHTML = '';
        }
    });

    // ── Chamada genérica à API ─────────────────────────────────────────
    async function chamarApi(url, params, btnOrigem, textoOriginal) {
        btnOrigem = btnOrigem || null;
        const formData = new FormData();
        formData.append('csrf_token', CSRF);
        Object.entries(params).forEach(function ([k, v]) { formData.append(k, v); });

        try {
            const resp = await fetch(url, { method: 'POST', body: formData });
            const data = await resp.json();

            const tipo = data.status === 'ok'    ? 'success'
                       : data.status === 'aviso' ? 'warning'
                       : 'danger';

            mostrarToast(data.mensagem, tipo);

            if (data.status === 'ok') {
                setTimeout(function () { window.location.reload(); }, 1000);
            } else if (btnOrigem) {
                btnOrigem.disabled = false;
                if (textoOriginal) btnOrigem.innerHTML = textoOriginal;
            }
        } catch {
            mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
            if (btnOrigem) {
                btnOrigem.disabled = false;
                if (textoOriginal) btnOrigem.innerHTML = textoOriginal;
            }
        }
    }
})();
