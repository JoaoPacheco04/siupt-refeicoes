<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');

$extras = Database::listarDetalhesExtrasParaGestao();

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir extras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/modal.css" rel="stylesheet">
    <link href="assets/css/gerir-extras.css" rel="stylesheet">
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

<main class="gerir-extras-main">
    <h1 class="gerir-extras-titulo">gerir pratos extras</h1>
    <p class="gerir-extras-subtitulo">Cria novos extras ou atualiza nomes e preços dos existentes.</p>

    <!-- Formulário de criação -->
    <div class="extras-card">
        <h2 class="extras-card-titulo"><i class="bi bi-plus-circle"></i> Novo extra</h2>
        <form id="formNovoExtra" class="form-novo-extra">
            <div class="form-campo">
                <label for="novoNome">Nome</label>
                <input type="text" id="novoNome" required placeholder="Ex: Hambúrguer">
            </div>
            <div class="form-campo">
                <label for="novoPreco">Preço (€)</label>
                <input type="number" id="novoPreco" step="0.01" min="0" required placeholder="0.00">
            </div>
            <button type="submit" class="btn-criar-extra">
                <i class="bi bi-check-lg"></i> Criar extra
            </button>
        </form>
        <p class="text-muted small mt-2">
            <i class="bi bi-info-circle"></i>
            Cada extra recebe um preço próprio, independente de outros pratos.
        </p>
    </div>

    <!-- Lista de extras existentes -->
    <h2 class="extras-lista-titulo">extras existentes</h2>
    <div class="extras-existentes">
        <?php if (empty($extras)): ?>
        <p class="extras-vazio"><i class="bi bi-inbox"></i> Ainda não há pratos extras criados.</p>
        <?php else: ?>
        <?php foreach ($extras as $e): ?>
        <div class="extra-item<?= !$e['RM_ATIVO'] ? ' extra-inativo' : '' ?>" data-rm-id="<?= $e['RM_ID'] ?>" data-tipo-id="<?= $e['RM_TP_ID'] ?>">
            <div class="extra-info">
                <span class="extra-nome"><?= htmlspecialchars($e['RM_NOME']) ?></span>
                <span class="extra-tipo">
                    <?= htmlspecialchars($e['RTP_NOME']) ?>
                    <?php if (!$e['RM_ATIVO']): ?> · <span class="badge-inativo">Descontinuado</span><?php endif; ?>
                </span>
            </div>
            <div class="extra-preco">
                <?= $e['preco_atual'] !== null ? number_format($e['preco_atual'], 2, ',', '') . '€' : 'sem preço' ?>
            </div>
            <button class="btn-editar-extra" title="Editar">
                <i class="bi bi-pencil"></i>
            </button>
            <?php if ($e['RM_ATIVO']): ?>
            <button class="btn-apagar-extra" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
            <?php else: ?>
            <button class="btn-reativar-extra" title="Reativar">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
</div>

<script>window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';</script>
<script src="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.js"></script>
<script src="assets/js/gerir_extras.js"></script>
</body>
</html>