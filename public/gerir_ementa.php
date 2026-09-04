<?php
/**
 * Página de gestão da ementa semanal.
 *
 * Permite ao admin_cantina configurar os pratos disponíveis
 * para cada dia da semana (Segunda a Sexta), com navegação
 * semanal e edição inline (adicionar, editar nome, remover).
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';
require_once __DIR__ . '/../src/Support/Assets.php';

$utilizador   = exigirLogin('admin_cantina');
$tiposRefeicao = Database::listarTiposRefeicaoPratoDia();

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>SIUPT - Gerir Ementa</title>
    <meta name="description" content="Configuração da ementa semanal da cantina — área reservada a administradores.">
    <meta name="robots" content="noindex">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Folhas de estilo da aplicação -->
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">

    <!-- CSS específico desta página -->
    <link href="<?= assetUrl('assets/css/gerir-ementa.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">

<!-- Cabeçalho -->
<header>

    <a id="home" href="validar.php" title="Voltar ao início">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>

    <a href="validar.php" class="nav-icon-link" title="Validar QR code">
        <i class="bi bi-qr-code-scan"></i>
    </a>

    <a href="ementa.php" class="nav-icon-link" title="Ver ementa / Reservar refeição">
        <i class="bi bi-journal-text"></i>
    </a>

    <a href="gerir_ementa.php" class="nav-icon-link nav-icon-link--ativo" title="Gerir ementa semanal">
        <i class="bi bi-calendar-week"></i>
    </a>

    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
    </a>

    <a href="gerir_motivos.php" class="nav-icon-link" title="Gerir motivos">
        <i class="bi bi-chat-square-text"></i>
    </a>

    <a href="gerir_feriados.php" class="nav-icon-link" title="Gerir feriados">
        <i class="bi bi-calendar-x"></i>
    </a>

    <a href="gerir_atendentes.php" class="nav-icon-link" title="Gerir atendentes">
        <i class="bi bi-people"></i>
    </a>

    <a href="relatorio.php" class="nav-icon-link" title="Relatório mensal">
        <i class="bi bi-bar-chart-line"></i>
    </a>

    <!-- Área do utilizador -->
    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">
        <form method="POST" action="login.php" style="display:inline">
            <input type="hidden" name="logout" value="1">
            <input type="hidden" name="csrf_token" value="<?= gerarCsrfToken() ?>">
            <button type="submit" id="quit" title="Terminar sessão">&nbsp;</button>
        </form>
        <div id="profile-photo" class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($utilizador['nome'], 0, 1))) ?>
        </div>
    </div>
</header>

<!-- Conteúdo principal -->
<main class="gerir-ementa-main">

    <h1 class="gerir-ementa-titulo">gerir ementa semanal</h1>
    <p class="gerir-ementa-subtitulo">
        Configura os pratos disponíveis para cada dia da semana. Clica no nome de um prato para o editar.
    </p>

    <!-- Navegação semanal -->
    <nav class="semana-nav" aria-label="Navegação semanal">
        <button id="btnSemanaAnterior" class="semana-nav-btn" title="Semana anterior">
            <i class="bi bi-chevron-left"></i>
        </button>

        <span id="semanaLabel" class="semana-nav-label">A carregar…</span>

        <button id="btnSemanaProxima" class="semana-nav-btn" title="Próxima semana">
            <i class="bi bi-chevron-right"></i>
        </button>

        <button id="btnSemanaHoje" class="semana-nav-hoje" title="Ir para a semana atual">
            Esta semana
        </button>
    </nav>

    <!-- Grelha de dias — preenchida via JS -->
    <div id="semanaGrid" class="semana-grid">
        <div class="semana-loading">
            <i class="bi bi-arrow-repeat"></i> A carregar…
        </div>
    </div>

</main>

</div><!-- #bodycontainer -->

<!-- Toast de feedback -->
<div id="ementaToast" class="ementa-toast" role="alert" aria-live="polite"></div>

<!-- Passa o token CSRF e os tipos de refeição ao JavaScript -->
<script>
window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
window.TIPOS_REFEICAO = <?= json_encode(array_map(fn($t) => [
    'id'       => (int) $t['RTP_ID'],
    'nome'     => $t['RTP_NOME'],
    'prato_dia'=> (bool) $t['RM_PRATO_DIA'],
], $tiposRefeicao)) ?>;
</script>

<!-- JavaScript desta página -->
<script src="<?= assetUrl('assets/js/gerir_ementa.js') ?>"></script>

</body>
</html>
