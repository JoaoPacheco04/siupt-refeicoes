<?php
/**
 * Página de gestão de papéis de cantina.
 *
 * Permite ao administrador da cantina (admin_cantina) atribuir e revogar
 * papéis de acesso sem precisar de aceder diretamente à base de dados:
 *  - Atendente (atendente): valida QR codes e exporta registos
 *  - Administrador de cantina (admin_cantina): acesso à gestão completa
 *
 * Fluxo de utilização:
 *  1. Pesquisar um utilizador por nome ou número BICC
 *  2. Selecionar o utilizador na lista de resultados
 *  3. Atribuir ou revogar papel com um clique
 *
 * A tabela em baixo lista todos os utilizadores com pelo menos um papel,
 * com botões para revogar papéis individualmente.
 *
 * Proteção: o último admin_cantina não pode ser removido, para garantir
 * que o sistema tem sempre pelo menos um administrador.
 *
 * Requer papel: admin_cantina
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador         = exigirLogin('admin_cantina');
$utilizadoresComPapeis = Database::listarUtilizadoresComPapeis();
$csrfToken          = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir Atendentes</title>
    <meta name="description" content="Gestão de papéis de acesso à cantina — área reservada ao administrador.">
    <meta name="robots" content="noindex">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/base.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/navbar.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/gerir-atendentes.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">

<header>
    <a id="home" href="validar.php" title="Voltar ao início">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>

    <a href="validar.php" class="nav-icon-link" title="Validar QR code">
        <i class="bi bi-qr-code-scan"></i>
    </a>

    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
    </a>

    <a href="gerir_motivos.php" class="nav-icon-link" title="Gerir motivos">
        <i class="bi bi-chat-square-text"></i>
    </a>

    <a href="gerir_feriados.php" class="nav-icon-link" title="Gerir feriados e dias especiais">
        <i class="bi bi-calendar-x"></i>
    </a>

    <a href="gerir_atendentes.php" class="nav-icon-link nav-icon-link--ativo" title="Gerir atendentes">
        <i class="bi bi-people"></i>
    </a>

    <a href="relatorio.php" class="nav-icon-link" title="Relatório mensal">
        <i class="bi bi-bar-chart-line"></i>
    </a>

    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">
        <a id="quit" href="login.php?logout=1" title="Terminar sessão">&nbsp;</a>
        <div id="profile-photo" class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($utilizador['nome'], 0, 1))) ?>
        </div>
    </div>
</header>

<main class="container mt-4" style="max-width: 860px;">

    <div class="d-flex justify-content-between align-items-start mb-1">
        <h1 class="mb-0">Gerir Atendentes</h1>
    </div>
    <p class="text-muted small mb-4">
        Atribui ou revoga papéis de acesso à cantina. As alterações têm efeito imediato na próxima sessão do utilizador.
    </p>

    <!-- ── Área de pesquisa e atribuição ──────────────────────────────── -->
    <div class="painel-pesquisa">
        <label class="form-label fw-semibold mb-2">
            <i class="bi bi-search me-1"></i> Pesquisar utilizador para atribuir papel
        </label>
        <div class="pesquisa-wrapper">
            <input
                type="text"
                id="inputPesquisa"
                class="form-control"
                placeholder="Nome ou número BICC (ex: 12345678 ou João...)"
                autocomplete="off"
            >
            <div class="pesquisa-resultados" id="pesquisaResultados"></div>
        </div>

        <!-- Painel de atribuição — aparece após selecionar utilizador -->
        <div id="painelAtribuicao" class="mt-3 d-none">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="user-cell">
                    <div class="perfil-avatar-sm" id="atribuicaoAvatar"></div>
                    <div>
                        <div class="user-nome" id="atribuicaoNome"></div>
                        <div class="user-bicc" id="atribuicaoBicc"></div>
                    </div>
                </div>

                <div class="d-flex gap-2 ms-auto flex-wrap">
                    <button
                        id="btnAtribuirAtendente"
                        class="btn btn-sm btn-outline-primary"
                        data-papel="atendente"
                    >
                        <i class="bi bi-plus-circle me-1"></i>Atribuir Atendente
                    </button>
                    <button
                        id="btnAtribuirAdmin"
                        class="btn btn-sm btn-outline-success"
                        data-papel="admin_cantina"
                    >
                        <i class="bi bi-plus-circle me-1"></i>Atribuir Admin
                    </button>
                </div>
            </div>

            <div id="painelPapeisAtuais" class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                <span class="text-muted small">Papéis atuais:</span>
                <span id="labelPapeisAtuais" class="text-muted small fst-italic">Nenhum</span>
            </div>
        </div>
    </div>

    <!-- ── Tabela de utilizadores com papéis ───────────────────────────── -->
    <h2 class="h5 mb-3">Utilizadores com Papéis de Cantina</h2>

    <?php if (empty($utilizadoresComPapeis)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Nenhum utilizador tem ainda um papel de cantina. Usa a pesquisa acima para atribuir o primeiro.
        </div>
    <?php else: ?>
    <table class="table table-hover align-middle" id="tabelaUtilizadores">
        <thead class="table-light">
            <tr>
                <th>Utilizador</th>
                <th>Papéis</th>
                <th class="table-actions"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($utilizadoresComPapeis as $u): ?>
            <?php
                $papeis    = array_filter(array_map('trim', explode(',', $u['papeis'] ?? '')));
                $temAtend  = in_array('atendente', $papeis);
                $temAdmin  = in_array('admin_cantina', $papeis);
                $inicial   = strtoupper(substr($u['U_NOME'], 0, 1));
            ?>
            <tr id="user-row-<?= $u['U_ID'] ?>">
                <td>
                    <div class="user-cell">
                        <div class="perfil-avatar-sm"><?= htmlspecialchars($inicial) ?></div>
                        <div>
                            <div class="user-nome"><?= htmlspecialchars($u['U_NOME']) ?></div>
                            <div class="user-bicc"><?= htmlspecialchars($u['U_BICC']) ?></div>
                        </div>
                    </div>
                </td>
                <td id="badges-<?= $u['U_ID'] ?>">
                    <?php if ($temAtend): ?>
                        <span class="badge-atendente me-1"><i class="bi bi-qr-code-scan me-1"></i>Atendente</span>
                    <?php endif; ?>
                    <?php if ($temAdmin): ?>
                        <span class="badge-admin"><i class="bi bi-shield-check me-1"></i>Admin Cantina</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap justify-content-end">
                        <?php if ($temAtend): ?>
                            <button
                                class="btn-papel btn-papel-remove"
                                data-user-id="<?= $u['U_ID'] ?>"
                                data-papel="atendente"
                                title="Remover papel de Atendente"
                            >
                                <i class="bi bi-dash-circle"></i> Atendente
                            </button>
                        <?php else: ?>
                            <button
                                class="btn-papel btn-papel-add"
                                data-user-id="<?= $u['U_ID'] ?>"
                                data-papel="atendente"
                                title="Atribuir papel de Atendente"
                            >
                                <i class="bi bi-plus-circle"></i> Atendente
                            </button>
                        <?php endif; ?>

                        <?php if ($temAdmin): ?>
                            <button
                                class="btn-papel btn-papel-remove"
                                data-user-id="<?= $u['U_ID'] ?>"
                                data-papel="admin_cantina"
                                title="Remover papel de Administrador"
                            >
                                <i class="bi bi-dash-circle"></i> Admin
                            </button>
                        <?php else: ?>
                            <button
                                class="btn-papel btn-papel-add"
                                data-user-id="<?= $u['U_ID'] ?>"
                                data-papel="admin_cantina"
                                title="Atribuir papel de Administrador"
                            >
                                <i class="bi bi-plus-circle"></i> Admin
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</main>
</div><!-- #bodycontainer -->

<script>
(function () {
    'use strict';

    const CSRF      = <?= json_encode($csrfToken) ?>;
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
    function mostrarToast(mensagem, tipo = 'success') {
        const antigo = document.getElementById('toast-papeis');
        if (antigo) antigo.remove();

        const t = document.createElement('div');
        t.id        = 'toast-papeis';
        t.className = `alert alert-${tipo === 'success' ? 'success' : tipo === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show mt-3`;
        t.role      = 'alert';
        t.innerHTML = esc(mensagem) + '<button type="button" class="btn-close"></button>';
        t.querySelector('.btn-close').addEventListener('click', () => t.remove());
        document.querySelector('main').prepend(t);
        if (tipo === 'success') setTimeout(() => t.remove(), 4000);
    }

    // ── Pesquisa de utilizadores com debounce ──────────────────────────
    let debounceTimer;
    inputPesq.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = inputPesq.value.trim();
        if (query.length < 2) {
            divResult.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(() => pesquisar(query), 300);
    });

    async function pesquisar(query) {
        try {
            const resp = await fetch('api/gerir_papeis_pesquisar.php?q=' + encodeURIComponent(query));
            const data = await resp.json();
            renderResultados(data.utilizadores ?? []);
        } catch {
            divResult.innerHTML = '<div class="resultado-item text-danger small">Erro de ligação.</div>';
        }
    }

    function renderResultados(lista) {
        if (lista.length === 0) {
            divResult.innerHTML = '<div class="resultado-item text-muted small">Nenhum resultado encontrado.</div>';
            return;
        }
        divResult.innerHTML = lista.map(u => {
            const papeis = (u.papeis || '').split(',').filter(Boolean);
            const badges = papeis.map(p =>
                p === 'admin_cantina'
                    ? `<span class="badge-admin">Admin</span>`
                    : `<span class="badge-atendente">Atendente</span>`
            ).join(' ');

            return `<div class="resultado-item"
                         data-id="${esc(u.U_ID)}"
                         data-nome="${esc(u.U_NOME)}"
                         data-bicc="${esc(u.U_BICC)}"
                         data-papeis="${esc(u.papeis || '')}">
                <div class="resultado-avatar">${esc(u.U_NOME.charAt(0).toUpperCase())}</div>
                <div class="resultado-info">
                    <div class="resultado-nome">${esc(u.U_NOME)}</div>
                    <div class="resultado-bicc">Nº ${esc(u.U_BICC)}</div>
                </div>
                <div class="resultado-papeis-atuais">${badges}</div>
            </div>`;
        }).join('');

        // Clique num resultado → selecionar utilizador
        divResult.querySelectorAll('.resultado-item[data-id]').forEach(el => {
            el.addEventListener('click', () => selecionarUtilizador(el.dataset));
        });
    }

    function selecionarUtilizador(d) {
        utilizadorSelecionado = { id: d.id, nome: d.nome, bicc: d.bicc, papeis: (d.papeis || '').split(',').filter(Boolean) };
        divResult.innerHTML = '';
        inputPesq.value     = d.nome + ' (' + d.bicc + ')';

        document.getElementById('atribuicaoAvatar').textContent = d.nome.charAt(0).toUpperCase();
        document.getElementById('atribuicaoNome').textContent   = d.nome;
        document.getElementById('atribuicaoBicc').textContent   = 'Nº ' + d.bicc;
        atualizarLabelPapeis(utilizadorSelecionado.papeis);

        painelAt.classList.remove('d-none');
    }

    function atualizarLabelPapeis(papeis) {
        const label = document.getElementById('labelPapeisAtuais');
        if (!papeis || papeis.length === 0) {
            label.textContent = 'Nenhum';
            label.className   = 'text-muted small fst-italic';
        } else {
            label.innerHTML = papeis.map(p =>
                p === 'admin_cantina'
                    ? `<span class="badge-admin me-1">Admin Cantina</span>`
                    : `<span class="badge-atendente me-1">Atendente</span>`
            ).join('');
        }
    }

    // ── Botões de atribuição no painel de pesquisa ─────────────────────
    ['btnAtribuirAtendente', 'btnAtribuirAdmin'].forEach(id => {
        document.getElementById(id).addEventListener('click', async function () {
            if (!utilizadorSelecionado) return;
            const papel = this.dataset.papel;
            await chamarApi('api/gerir_papeis_atribuir.php', { user_id: utilizadorSelecionado.id, papel });
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
            btn.disabled = true;

            await chamarApi(acao, { user_id: userId, papel }, btn);
        });
    }

    // ── Fecho do dropdown ao clicar fora ──────────────────────────────
    document.addEventListener('click', e => {
        if (!inputPesq.contains(e.target) && !divResult.contains(e.target)) {
            divResult.innerHTML = '';
        }
    });

    // ── Chamada genérica à API ─────────────────────────────────────────
    async function chamarApi(url, params, btnOrigem = null) {
        const formData = new FormData();
        formData.append('csrf_token', CSRF);
        Object.entries(params).forEach(([k, v]) => formData.append(k, v));

        try {
            const resp = await fetch(url, { method: 'POST', body: formData });
            const data = await resp.json();

            const tipo = data.status === 'ok' ? 'success'
                       : data.status === 'aviso' ? 'warning' : 'danger';

            mostrarToast(data.mensagem, tipo);

            if (data.status === 'ok') {
                // Recarregar a página para atualizar tabela e painel
                setTimeout(() => window.location.reload(), 1200);
            } else if (btnOrigem) {
                btnOrigem.disabled = false;
            }
        } catch {
            mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
            if (btnOrigem) btnOrigem.disabled = false;
        }
    }
})();
</script>

</body>
</html>
