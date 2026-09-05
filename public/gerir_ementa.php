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

        <button id="btnSemanaHoje" class="semana-nav-hoje" title="Voltar à semana atual" style="display:none;">
            <i class="bi bi-arrow-return-left"></i> Voltar à semana atual
        </button>
    </nav>

    <!-- Barra de estado de publicação -->
    <div class="publicacao-barra publicacao-barra--vazia" id="publicacaoBarra" style="display:none;"></div>
    <div class="publicacao-acoes">
        <button class="btn-publicar-semana" id="btnPublicarSemana" style="display:none;">
            <i class="bi bi-send-check-fill"></i> Publicar semana
        </button>
        <button class="btn-despublicar-semana" id="btnDespublicarSemana" style="display:none;">
            <i class="bi bi-arrow-counterclockwise"></i> Despublicar
        </button>
        <button class="btn-copiar-semana" id="btnCopiarSemana" title="Copiar os pratos da semana anterior para esta semana">
            <i class="bi bi-copy"></i> Copiar semana anterior
        </button>
        <button class="btn-limpar-semana" id="btnLimparSemana" style="display:none;" title="Remover todos os pratos configurados para esta semana">
            <i class="bi bi-trash3"></i> Limpar semana
        </button>
    </div>

    <!-- Grelha de dias — preenchida via JS -->
    <div id="semanaGrid" class="semana-grid">
        <div class="semana-loading">
            <i class="bi bi-arrow-repeat"></i> A carregar…
        </div>
    </div>

</main>

<!-- Modal para copiar pratos de um dia para outro -->
<div id="modalCopiarDia" class="modal-publicar-overlay" role="dialog" aria-modal="true" aria-labelledby="modalCopiarDiaTitulo">
    <div class="modal-publicar-caixa">
        <h2 class="modal-publicar-titulo" id="modalCopiarDiaTitulo">
            <i class="bi bi-copy"></i> Copiar ementa de <span id="copiarDiaOrigemNome"></span>
        </h2>
        <p class="modal-publicar-desc">Escolhe o dia de destino para copiar os pratos configurados:</p>

        <div class="copiar-dia-opcoes" id="copiarDiaDestinosWrap">
            <!-- Opções geradas dinamicamente via JS -->
        </div>

        <div class="modal-publicar-acoes">
            <button type="button" class="btn-modal-cancelar" id="btnCancelarCopiarDia">Cancelar</button>
            <button type="button" class="btn-modal-confirmar" id="btnConfirmarCopiarDia" disabled>
                <i class="bi bi-check-lg"></i> Copiar pratos
            </button>
        </div>
    </div>
</div>

<!-- Modal de publicação -->
<div id="modalPublicar" class="modal-publicar-overlay" role="dialog" aria-modal="true" aria-labelledby="modalPublicarTitulo">
    <div class="modal-publicar-caixa">
        <h2 class="modal-publicar-titulo" id="modalPublicarTitulo">
            <i class="bi bi-send-check-fill"></i> Publicar ementa
        </h2>
        <p class="modal-publicar-desc">Escolhe quando a ementa fica visível para os alunos:</p>

        <div class="modal-opcoes">
            <label class="modal-opcao modal-opcao--selecionada" id="labelOpcaoPadrao">
                <input type="radio" name="modoAbertura" value="padrao" checked>
                <span class="modal-opcao-icone"><i class="bi bi-calendar-check"></i></span>
                <span class="modal-opcao-texto">
                    <strong>Sexta às 14h30</strong>
                    <small>Abre automaticamente na sexta-feira às 14h30 (padrão)</small>
                </span>
            </label>

            <label class="modal-opcao" id="labelOpcaoImediato">
                <input type="radio" name="modoAbertura" value="imediato">
                <span class="modal-opcao-icone"><i class="bi bi-lightning-charge-fill"></i></span>
                <span class="modal-opcao-texto">
                    <strong>Imediatamente</strong>
                    <small>Fica visível de imediato para todos os alunos</small>
                </span>
            </label>
        </div>

        <div class="modal-publicar-acoes">
            <button class="btn-modal-cancelar" id="btnCancelarPublicar">Cancelar</button>
            <button class="btn-modal-confirmar" id="btnConfirmarPublicar">
                <i class="bi bi-send-check-fill"></i> Confirmar publicação
            </button>
        </div>
    </div>
</div>

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
