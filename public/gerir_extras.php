<?php
// Inicia a sessão para permitir o acesso aos dados do utilizador autenticado.
session_start();

// Importa as funções de autenticação.
require_once __DIR__ . '/../src/Support/Auth.php';

// Importa a função de versionamento dos ficheiros CSS/JS.
require_once __DIR__ . '/../src/Support/Assets.php';

// Importa a camada de acesso à base de dados.
require_once __DIR__ . '/../src/Infrastructure/Database.php';

// Garante que apenas funcionários autenticados podem aceder a esta página.
$utilizador = exigirLogin('funcionario');

// Obtém todos os pratos extras existentes para apresentação e gestão.
$extras = Database::listarDetalhesExtrasParaGestao();

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">

    <!-- Permite que a página seja responsiva em dispositivos móveis -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>SIUPT - Gerir extras</title>
    <meta name="description" content="Gestão dos extras de refeição disponíveis na cantina — área reservada a funcionários.">
    <meta name="robots" content="noindex">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Biblioteca utilizada para as janelas modais -->
    <link href="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.css" rel="stylesheet">

    <!-- Folhas de estilo da aplicação -->
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/modal.css" rel="stylesheet">

    <!-- CSS específico desta página -->
    <link href="<?= assetUrl('assets/css/gerir-extras.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">

<!-- Cabeçalho da aplicação -->
<header>

    <!-- Logótipo com ligação para a página de validação -->
    <a id="home" href="validar.php" title="Voltar ao início">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>

    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
    </a>

    <a href="relatorio.php" class="nav-icon-link" title="Relatório mensal">
        <i class="bi bi-bar-chart-line"></i>
    </a>

    <!-- Área do utilizador autenticado -->
    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">

        <!-- Botão de terminar sessão -->
        <a id="quit" href="login.php?logout=1" title="Terminar sessão">&nbsp;</a>

        <!-- Avatar com a inicial do nome -->
        <div id="profile-photo" class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($utilizador['nome'], 0, 1))) ?>
        </div>
    </div>
</header>

<!-- Conteúdo principal -->
<main class="gerir-extras-main">

    <h1 class="gerir-extras-titulo">gerir pratos extras</h1>

    <p class="gerir-extras-subtitulo">
        Cria novos extras ou atualiza nomes e preços dos existentes.
    </p>

    <!-- ==========================
         Formulário de criação
    =========================== -->
    <div class="extras-card">

        <h2 class="extras-card-titulo">
            <i class="bi bi-plus-circle"></i>
            Novo extra
        </h2>

        <!-- Formulário processado via JavaScript -->
        <form id="formNovoExtra" class="form-novo-extra">

            <!-- Nome do novo prato -->
            <div class="form-campo">
                <label for="novoNome">Nome</label>
                <input
                    type="text"
                    id="novoNome"
                    required
                    placeholder="Ex: Hambúrguer">
            </div>

            <!-- Preço do novo prato -->
            <div class="form-campo">
                <label for="novoPreco">Preço (€)</label>
                <input
                    type="number"
                    id="novoPreco"
                    step="0.01"
                    min="0"
                    required
                    placeholder="0.00">
            </div>

            <!-- Botão de criação -->
            <button type="submit" class="btn-criar-extra">
                <i class="bi bi-check-lg"></i>
                Criar extra
            </button>

        </form>

        <!-- Informação auxiliar -->
        <p class="text-muted small mt-2">
            <i class="bi bi-info-circle"></i>
            Cada extra recebe um preço próprio, independente de outros pratos.
        </p>

    </div>

    <!-- ==========================
         Lista de extras
    =========================== -->

    <h2 class="extras-lista-titulo">extras existentes</h2>

    <div class="extras-existentes">

        <!-- Caso não existam extras -->
        <?php if (empty($extras)): ?>

            <p class="extras-vazio">
                <i class="bi bi-inbox"></i>
                Ainda não há pratos extras criados.
            </p>

        <?php else: ?>

            <!-- Percorre todos os extras existentes -->
            <?php foreach ($extras as $e): ?>

            <div
                class="extra-item<?= !$e['RM_ATIVO'] ? ' extra-inativo' : '' ?>"
                data-rm-id="<?= $e['RM_ID'] ?>"
                data-tipo-id="<?= $e['RM_TP_ID'] ?>">

                <!-- Informação do prato -->
                <div class="extra-info">

                    <!-- Nome -->
                    <span class="extra-nome">
                        <?= htmlspecialchars($e['RM_NOME']) ?>
                    </span>

                    <!-- Tipo -->
                    <span class="extra-tipo">

                        <?= htmlspecialchars($e['RTP_NOME']) ?>

                        <!-- Caso esteja descontinuado -->
                        <?php if (!$e['RM_ATIVO']): ?>
                            ·
                            <span class="badge-inativo">
                                Descontinuado
                            </span>
                        <?php endif; ?>

                    </span>

                </div>

                <!-- Preço atual -->
                <div class="extra-preco">

                    <?= $e['preco_atual'] !== null
                        ? number_format($e['preco_atual'], 2, ',', '') . '€'
                        : 'sem preço' ?>

                </div>

                <!-- Botão de edição -->
                <button
                    class="btn-editar-extra"
                    title="Editar">

                    <i class="bi bi-pencil"></i>

                </button>

                <!-- Se estiver ativo mostra botão eliminar,
                     caso contrário mostra botão reativar -->
                <?php if ($e['RM_ATIVO']): ?>

                    <button
                        class="btn-apagar-extra"
                        title="Eliminar">

                        <i class="bi bi-trash"></i>

                    </button>

                <?php else: ?>

                    <button
                        class="btn-reativar-extra"
                        title="Reativar">

                        <i class="bi bi-arrow-counterclockwise"></i>

                    </button>

                <?php endif; ?>

            </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>

</div>

<!-- Disponibiliza o token CSRF ao JavaScript -->
<script>
window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>

<!-- Biblioteca das janelas modais -->
<script src="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.js"></script>

<!-- JavaScript específico da gestão de extras -->
<script src="<?= assetUrl('assets/js/gerir_extras.js') ?>"></script>

</body>
</html>