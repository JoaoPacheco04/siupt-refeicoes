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

$utilizador           = exigirLogin('admin_cantina');
$utilizadoresComPapeis = Database::listarUtilizadoresComPapeis();
$csrfToken            = gerarCsrfToken();
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

<main class="gerir-atendentes-main">

    <h1 class="gerir-atendentes-titulo">gerir atendentes</h1>
    <p class="gerir-atendentes-subtitulo">
        Atribui ou revoga papéis de acesso à cantina. As alterações têm efeito imediato na próxima sessão do utilizador.
    </p>

    <!-- ── Área de pesquisa e atribuição ──────────────────────────── -->
    <div class="painel-pesquisa">
        <h2 class="painel-pesquisa-titulo">
            <i class="bi bi-search"></i>
            Pesquisar utilizador
        </h2>
        <div class="pesquisa-wrapper">
            <input
                type="text"
                id="inputPesquisa"
                class="pesquisa-input"
                placeholder="Nome ou número BICC (ex: 12345678 ou João...)"
                autocomplete="off"
            >
            <div class="pesquisa-resultados" id="pesquisaResultados"></div>
        </div>

        <!-- Painel de atribuição — aparece após selecionar utilizador -->
        <div id="painelAtribuicao" class="painel-atribuicao painel-atribuicao-oculto">
            <div class="user-cell">
                <div class="perfil-avatar-sm" id="atribuicaoAvatar"></div>
                <div>
                    <div class="user-nome" id="atribuicaoNome"></div>
                    <div class="user-bicc" id="atribuicaoBicc"></div>
                </div>
            </div>

            <div class="btn-atribuir-grupo">
                <button
                    id="btnAtribuirAtendente"
                    class="btn-atribuir"
                    data-papel="atendente"
                >
                    <i class="bi bi-plus-circle"></i> Atribuir Atendente
                </button>
                <button
                    id="btnAtribuirAdmin"
                    class="btn-atribuir btn-atribuir--admin"
                    data-papel="admin_cantina"
                >
                    <i class="bi bi-plus-circle"></i> Atribuir Admin
                </button>
            </div>

            <div class="papeis-atuais-linha" id="painelPapeisAtuais">
                <span>Papéis atuais:</span>
                <span id="labelPapeisAtuais" style="font-style:italic;">Nenhum</span>
            </div>
        </div>
    </div>

    <!-- ── Tabela de utilizadores com papéis ──────────────────────── -->
    <h2 class="atendentes-lista-titulo">utilizadores com papéis de cantina</h2>

    <?php if (empty($utilizadoresComPapeis)): ?>
        <p class="atendentes-vazio">
            <i class="bi bi-info-circle"></i>
            Nenhum utilizador tem ainda um papel de cantina. Usa a pesquisa acima para atribuir o primeiro.
        </p>
    <?php else: ?>
    <div class="atendentes-tabela">
        <table id="tabelaUtilizadores">
            <thead>
                <tr>
                    <th>Utilizador</th>
                    <th>Papéis</th>
                    <th class="col-acoes"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($utilizadoresComPapeis as $u): ?>
                <?php
                    $papeis   = array_filter(array_map('trim', explode(',', $u['papeis'] ?? '')));
                    $temAtend = in_array('atendente', $papeis);
                    $temAdmin = in_array('admin_cantina', $papeis);
                    $inicial  = strtoupper(substr($u['U_NOME'], 0, 1));
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
                            <span class="badge-atendente"><i class="bi bi-qr-code-scan"></i> Atendente</span>
                        <?php endif; ?>
                        <?php if ($temAdmin): ?>
                            <span class="badge-admin"><i class="bi bi-shield-check"></i> Admin Cantina</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:0.35rem; flex-wrap:wrap; justify-content:flex-end;">
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
    </div>
    <?php endif; ?>

</main>
</div><!-- #bodycontainer -->

<script>
window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
</script>
<script src="<?= assetUrl('assets/js/gerir_atendentes.js') ?>"></script>

</body>
</html>
