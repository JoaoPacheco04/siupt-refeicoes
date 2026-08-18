<?php
/**
 * Página de validação de refeições.
 *
 * Permite aos funcionários validar refeições através
 * de QR Code ou introdução manual do código, bem como
 * consultar e exportar as validações realizadas no dia.
 */

// Auth.php inicia a sessão internamente se ainda não estiver ativa.
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('atendente');
$validacoesHoje = Database::contarValidacoesHoje((int) $utilizador['id']);
$refeicoesPorLevantar = Database::contarRefeicoesAtivasHoje(); // NOVO — item 2
$vejoTudo = temPapelSessao('admin_cantina');
$listaValidacoes = $vejoTudo
    ? Database::listarValidacoesHojeTodos()
    : Database::listarValidacoesHoje((int) $utilizador['id']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Validar refeição</title>
    <meta name="description" content="Validação de refeições por QR code — área do funcionário.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/validar.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">

<!-- Cabeçalho -->
<header>
    <a id="home" href="validar.php" title="Voltar ao início">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>

    <a href="validar.php" class="nav-icon-link nav-icon-link--ativo" title="Validar QR code">
        <i class="bi bi-qr-code-scan"></i>
    </a>

    <a href="ementa.php" class="nav-icon-link" title="Ver ementa / Reservar refeição">
        <i class="bi bi-journal-text"></i>
    </a>

    <?php if (temPapelSessao('admin_cantina')): ?>
    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
    </a>

    <a href="gerir_motivos.php" class="nav-icon-link" title="Gerir motivos">
        <i class="bi bi-chat-square-text"></i>
    </a>

    <a href="gerir_feriados.php" class="nav-icon-link" title="Gerir feriados e dias especiais">
        <i class="bi bi-calendar-x"></i>
    </a>

    <a href="gerir_atendentes.php" class="nav-icon-link" title="Gerir atendentes">
        <i class="bi bi-people"></i>
    </a>

    <a href="relatorio.php" class="nav-icon-link" title="Relatório mensal">
        <i class="bi bi-bar-chart-line"></i>
    </a>
    <?php endif; ?>

    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">
        <a id="quit" href="login.php?logout=1" title="Terminar sessão">&nbsp;</a>

        <div id="profile-photo" class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($utilizador['nome'], 0, 1))) ?>
        </div>
    </div>
</header>

<!-- Conteúdo principal -->
<main class="validar-main">

    <h1 class="validar-titulo">validar refeição</h1>

    <p class="validar-subtitulo">
        Aponte a câmara para o QR code do utilizador ou introduza o código manualmente.
    </p>

    <!-- Contador de validações -->
    <div class="validacoes-contador">
        <i class="bi bi-check2-circle"></i>
        <span class="num" id="contadorValidacoes"><?= $validacoesHoje ?></span>
        validações hoje
    </div>

    <!-- NOVO — item 2: refeições pagas por levantar hoje -->
    <?php if ($refeicoesPorLevantar > 0): ?>
    <div class="validacoes-contador validacoes-contador--aviso">
        <i class="bi bi-hourglass-split"></i>
        <span class="num" id="contadorPorLevantar"><?= $refeicoesPorLevantar ?></span>
        por levantar hoje
    </div>
    <?php endif; ?>


    <!-- Área de validação -->
    <div class="scan-card">

        <div class="scan-card-titulo">
            <i class="bi bi-camera"></i> Scan por câmara
        </div>

        <div id="qr-reader"></div>

        <button id="btnValidarSeguinte" class="btn-validar-seguinte" style="display:none;">
            <i class="bi bi-arrow-repeat"></i> Validar seguinte
        </button>

        <div class="scan-separador">ou</div>

        <div class="scan-input-group">
            <input
                type="text"
                id="inputQrManual"
                class="scan-input"
                placeholder="Código…"
                autocomplete="off"
                spellcheck="false">

            <button id="btnValidarManual" class="btn-validar-manual">
                <i class="bi bi-search"></i> Validar
            </button>
        </div>
    </div>

    <!-- Resultado da validação -->
    <div id="resultadoCard" class="resultado-card">
        <span class="resultado-icone" id="resultadoIcone"></span>
        <div class="resultado-estado" id="resultadoEstado"></div>
        <div class="resultado-nome" id="resultadoNome"></div>
        <div class="resultado-numero" id="resultadoNumero"></div>
        <ul class="resultado-linhas" id="resultadoLinhas"></ul>
    </div>

    <!-- Histórico de validações com navegação por data -->
    <div class="validacoes-lista-header">
        <h2 class="validacoes-lista-titulo" id="validacoesListaTitulo">Validações de hoje</h2>

        <div class="validacoes-data-nav">
            <button class="btn-nav-data" id="btnDataAnterior" title="Dia anterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <input type="date" id="inputDataValidacoes"
                   value="<?= date('Y-m-d') ?>"
                   max="<?= date('Y-m-d') ?>"
                   class="input-data-validacoes">
            <button class="btn-nav-data" id="btnDataSeguinte" title="Dia seguinte" disabled>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <!-- item 4: id + href já inclui a data de hoje; validar.js atualiza dinamicamente -->
        <a href="api/exportar_validacoes.php?data=<?= date('Y-m-d') ?>" class="btn-exportar-log" id="btnExportarLog">
            <i class="bi bi-download"></i> Exportar
        </a>

        <a href="api/exportar_codigos_contingencia.php" target="_blank" class="btn-exportar-log" title="Imprimir lista de contingência">
            <i class="bi bi-printer"></i> Lista de contingência
        </a>
    </div>

    <div id="listaValidacoes" class="lista-validacoes">

        <?php if (empty($listaValidacoes)): ?>

            <p class="lista-validacoes-vazia" id="listaVazia">
                <i class="bi bi-inbox"></i>
                <?= $vejoTudo
                    ? 'Ainda não foram registadas validações hoje.'
                    : 'Ainda não validaste nenhuma refeição hoje.' ?>
            </p>

        <?php else: ?>

            <?php foreach ($listaValidacoes as $v):
                $hora = date('H:i', strtotime($v['RV_DATA_VALIDACAO']));
            ?>

                <div class="validacao-item">
                    <div class="validacao-hora"><?= $hora ?></div>

                    <div class="validacao-info">
                        <span class="validacao-nome"><?= htmlspecialchars($v['U_NOME']) ?></span>
                        <span class="validacao-numero">Nº <?= htmlspecialchars($v['U_BICC']) ?></span>
                        <span class="validacao-refeicao">
                            <?= htmlspecialchars($v['itens'] ?? 'Sem itens registados') ?>
                        </span>
                    </div>

                    <div class="validacao-pedido">
                        #<?= $v['RP_ID'] ?>
                        <?php if ($vejoTudo && !empty($v['funcionario_nome'])): ?>
                            <br><small class="text-muted" style="font-size: 0.7em;">Val: <?= htmlspecialchars($v['funcionario_nome']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>
</div>

<!-- Bibliotecas e scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

<script>
    window.API_URL = 'api/validar_qrcode.php';
    window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>

<script src="<?= assetUrl('assets/js/validar.js') ?>"></script>

</body>
</html>