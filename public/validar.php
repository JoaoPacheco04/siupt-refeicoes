<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');
$validacoesHoje = Database::contarValidacoesHoje((int) $utilizador['id']);
$listaValidacoes = Database::listarValidacoesHoje((int) $utilizador['id']);
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
    <link href="assets/css/validar.css" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">
<header>
    <a id="home" href="validar.php" title="Voltar ao início">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>
    <nav>
        <ul id="mainmenu">
            <li id="menu_id_10" class=""><a href="#">Portais</a></li>
            <li id="menu_id_5"  class=""><a href="#">Ingresso</a></li>
            <li id="menu_id_7"  class=""><a href="#">Funcionário</a></li>
            <li id="menu_id_8"  class="selected"><a href="validar.php">Cantina</a></li>
            <li id="menu_id_16" class=""><a href="#">Decisão</a></li>
        </ul>
    </nav>
    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
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

<main class="validar-main">
    <h1 class="validar-titulo">validar refeição</h1>
    <p class="validar-subtitulo">Aponte a câmara para o QR code do utilizador ou introduza o código manualmente.</p>

    <div class="validacoes-contador">
        <i class="bi bi-check2-circle"></i>
        <span class="num" id="contadorValidacoes"><?= $validacoesHoje ?></span>
        validações hoje
    </div>

    <!-- Scanner de câmara -->
    <div class="scan-card">
        <div class="scan-card-titulo">
            <i class="bi bi-camera"></i> Scan por câmara
        </div>
        <div id="qr-reader"></div>

        <button id="btnValidarSeguinte" class="btn-validar-seguinte" style="display:none;">
            <i class="bi bi-arrow-repeat"></i> Validar seguinte
        </button>

        <div class="scan-separador">ou</div>

        <!-- Input manual -->
        <div class="scan-input-group">
           <input type="text" id="inputQrManual" class="scan-input"
       placeholder="Código…"
       autocomplete="off" spellcheck="false">
            <button id="btnValidarManual" class="btn-validar-manual">
                <i class="bi bi-search"></i> Validar
            </button>
        </div>
    </div>

    <!-- Área de resultado -->
<div id="resultadoCard" class="resultado-card">
    <span class="resultado-icone" id="resultadoIcone"></span>
    <div class="resultado-estado" id="resultadoEstado"></div>
    <div class="resultado-nome" id="resultadoNome"></div>
    <div class="resultado-numero" id="resultadoNumero"></div>
    <ul class="resultado-linhas" id="resultadoLinhas"></ul>
</div>

    <!-- Lista de validações de hoje -->
   <div class="validacoes-lista-header">
    <h2 class="validacoes-lista-titulo">Validações de hoje</h2>
    <a href="api/exportar_validacoes.php" class="btn-exportar-log">
        <i class="bi bi-download"></i> Exportar
    </a>
</div>
    <div id="listaValidacoes" class="lista-validacoes">
        <?php if (empty($listaValidacoes)): ?>
            <p class="lista-validacoes-vazia" id="listaVazia">
                <i class="bi bi-inbox"></i> Ainda não validaste nenhuma refeição hoje.
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
                    <span class="validacao-refeicao"><?= htmlspecialchars($v['itens'] ?? 'Sem itens registados') ?></span>
                </div>
                <div class="validacao-pedido">#<?= $v['RP_ID'] ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- html5-qrcode -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
    window.API_URL   = 'api/validar_qrcode.php';
    window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>
<script src="assets/js/validar.js"></script>
</body>
</html>