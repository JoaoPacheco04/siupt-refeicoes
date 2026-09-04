<?php
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina');

$motivos = Database::listarTodosMotivosReclamacao();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir motivos de reclamação</title>
    <meta name="description" content="Gestão dos motivos de reclamação disponíveis na avaliação de refeições.">
    <meta name="robots" content="noindex">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.css" rel="stylesheet">

    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/modal.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/gerir-motivos.css') ?>" rel="stylesheet">
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

    <a href="ementa.php" class="nav-icon-link" title="Ver ementa / Reservar refeição">
        <i class="bi bi-journal-text"></i>
    </a>

    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
    </a>

    <a href="gerir_motivos.php" class="nav-icon-link nav-icon-link--ativo" title="Gerir motivos">
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

<main class="gerir-extras-main">

    <h1 class="gerir-extras-titulo">gerir motivos de reclamação</h1>

    <p class="gerir-extras-subtitulo">
        Estes motivos aparecem no formulário de avaliação quando um aluno dá 1 ou 2 estrelas.
    </p>

    <div class="extras-card">
        <h2 class="extras-card-titulo">
            <i class="bi bi-plus-circle"></i>
            Novo motivo
        </h2>

        <form id="formNovoMotivo" class="form-novo-extra">
            <div class="form-campo" style="flex: 1;">
                <label for="novoLabel">Motivo de reclamação</label>
                <input type="text" id="novoLabel" required placeholder="Ex: Embalagem danificada">
            </div>

            <button type="submit" class="btn-criar-extra">
                <i class="bi bi-check-lg"></i>
                Criar motivo
            </button>
        </form>
    </div>

    <h2 class="extras-lista-titulo">motivos existentes</h2>

    <div class="extras-existentes">
        <?php if (empty($motivos)): ?>
            <p class="extras-vazio">
                <i class="bi bi-inbox"></i>
                Ainda não há motivos criados.
            </p>
        <?php else: ?>
            <?php foreach ($motivos as $m): ?>
            <div class="extra-item<?= !$m['RMR_ATIVO'] ? ' extra-inativo' : '' ?>" data-id="<?= $m['RMR_ID'] ?>" data-label="<?= htmlspecialchars($m['RMR_LABEL']) ?>">
                <div class="extra-info">
                    <span class="extra-nome">
                        <?= htmlspecialchars($m['RMR_LABEL']) ?>
                        <?php if (!$m['RMR_ATIVO']): ?>
                            <span class="badge-inativo" style="margin-left: 0.5rem;">Desativado</span>
                        <?php endif; ?>
                    </span>
                </div>

                <button class="btn-editar-extra" title="Editar motivo" data-editar="1">
                    <i class="bi bi-pencil"></i>
                </button>

                <?php if ($m['RMR_ATIVO']): ?>
                    <button class="btn-apagar-extra" title="Desativar" data-acao="desativar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                <?php else: ?>
                    <button class="btn-reativar-extra" title="Reativar" data-acao="reativar">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button class="btn-apagar-permanente" title="Apagar permanentemente" data-apagar="1">
                        <i class="bi bi-trash3"></i>
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</main>
</div>

<script>
window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>

<script src="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.js"></script>
<script src="<?= assetUrl('assets/js/gerir_motivos.js') ?>"></script>
</body>
</html>