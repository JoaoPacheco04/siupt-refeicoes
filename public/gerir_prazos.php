<?php
/**
 * Página de gestão de horas limite de compra (prazos).
 *
 * Permite ao admin_cantina consultar e alterar as horas limite
 * e dias de antecedência para:
 *  - Pratos Extra (ex: até às 10h do próprio dia)
 *  - Pratos da Ementa (ex: até às 14h30 do dia anterior ou por prato)
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina');

$prazoExtras          = Database::obterPrazoExtras();
$horaExtrasFormatada  = substr($prazoExtras['hora'], 0, 5);
$diasExtras           = (int) $prazoExtras['dias_antecedencia'];

$prazosEmenta         = Database::listarPrazosEmenta();
$prazoPrincipalTexto  = Database::obterDataLimitePrincipalTexto() ?? '14h30 do dia anterior';

$configPubl           = Database::obterConfiguracaoPublicacaoEmenta();
$horaPublFormatada    = $configPubl['hora_hm'];
$diasPubl             = (int) $configPubl['dias_antecedencia'];
$textoBadgePubl       = $configPubl['texto'];

// Determina valores padrão para o formulário global da ementa a partir do primeiro item (ex: Carne)
$horaEmentaGlobal = '14:30';
$diasEmentaGlobal = 1;
if (!empty($prazosEmenta)) {
    $primeiro = $prazosEmenta[0];
    $horaEmentaGlobal = substr((string) $primeiro['RDL_HORA'], 0, 5);
    $diasEmentaGlobal = (int) $primeiro['RDL_DIA_ANTECEDENCIA'];
}

// Texto para o badge dos extras
if ($diasExtras === 0) {
    $textoBadgeExtras = 'até às ' . str_replace(':', 'h', $horaExtrasFormatada) . ' do próprio dia';
} elseif ($diasExtras === 1) {
    $textoBadgeExtras = 'até às ' . str_replace(':', 'h', $horaExtrasFormatada) . ' do dia anterior';
} else {
    $textoBadgeExtras = 'até às ' . str_replace(':', 'h', $horaExtrasFormatada) . ' com ' . $diasExtras . ' dias de antecedência';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir Horas Limite</title>
    <meta name="description" content="Configuração das horas limite e prazos de corte para reservas de refeições e extras — área de administração.">
    <meta name="robots" content="noindex">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Folhas de estilo base -->
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/navbar.css') ?>" rel="stylesheet">

    <!-- CSS específico desta página -->
    <link href="<?= assetUrl('assets/css/gerir-prazos.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">

<!-- Cabeçalho da aplicação -->
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

    <a href="gerir_ementa.php" class="nav-icon-link" title="Gerir ementa semanal">
        <i class="bi bi-calendar-week"></i>
    </a>

    <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
        <i class="bi bi-egg-fried"></i>
    </a>

    <a href="gerir_precos.php" class="nav-icon-link" title="Gerir preços">
        <i class="bi bi-tag"></i>
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

    <a href="gerir_prazos.php" class="nav-icon-link nav-icon-link--ativo" title="Gerir prazos e horas limite">
        <i class="bi bi-hourglass-split"></i>
    </a>

    <a href="relatorio.php" class="nav-icon-link" title="Relatório mensal">
        <i class="bi bi-bar-chart-line"></i>
    </a>

    <!-- Área do utilizador autenticado -->
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
<main class="gerir-prazos-main">

    <h1 class="gerir-prazos-titulo">gerir horas limite e horários</h1>
    <p class="gerir-prazos-subtitulo">
        Define a hora de publicação automática da ementa aos alunos e os horários de corte para a reserva de refeições e extras.
    </p>

    <!-- ================================================================
         0. SECÇÃO: PUBLICAÇÃO AUTOMÁTICA DA EMENTA
         ================================================================ -->
    <div class="prazos-card" id="cardPublicacao">
        <div class="prazos-card-header">
            <div>
                <h2 class="prazos-card-titulo">
                    <i class="bi bi-calendar-check"></i>
                    Publicação Automática da Ementa
                </h2>
            </div>
            <span class="prazos-badge-preview badge-ativo" id="badgePreviewPublicacao">
                <i class="bi bi-broadcast"></i>
                <?= htmlspecialchars($textoBadgePubl) ?>
            </span>
        </div>

        <p class="prazos-descricao">
            Define o dia da semana e o horário em que a ementa semanal passa a estar visível aos alunos quando publicada no modo padrão. A referência é a 2.ª feira da semana da ementa.
        </p>

        <form id="formPublicacaoPadrao" class="form-prazo-linha">
            <div class="form-campo-prazo" style="max-width: 260px;">
                <label for="diasPublicacao">Dia de abertura</label>
                <select id="diasPublicacao" name="dias_antecedencia">
                    <option value="3" <?= $diasPubl === 3 ? 'selected' : '' ?>>Sexta-feira (3 dias antes)</option>
                    <option value="4" <?= $diasPubl === 4 ? 'selected' : '' ?>>Quinta-feira (4 dias antes)</option>
                    <option value="5" <?= $diasPubl === 5 ? 'selected' : '' ?>>Quarta-feira (5 dias antes)</option>
                    <option value="2" <?= $diasPubl === 2 ? 'selected' : '' ?>>Sábado (2 dias antes)</option>
                    <option value="1" <?= $diasPubl === 1 ? 'selected' : '' ?>>Domingo (1 dia antes)</option>
                    <option value="0" <?= $diasPubl === 0 ? 'selected' : '' ?>>Segunda-feira (próprio dia de início)</option>
                </select>
            </div>

            <div class="form-campo-prazo" style="max-width: 180px;">
                <label for="horaPublicacao">Hora de abertura</label>
                <input
                    type="time"
                    id="horaPublicacao"
                    name="hora"
                    value="<?= htmlspecialchars($horaPublFormatada) ?>"
                    required>
            </div>

            <button type="submit" class="btn-salvar-prazo btn-salvar-prazo--indigo">
                <i class="bi bi-check-lg"></i>
                Guardar Horário Padrão
            </button>
        </form>
    </div>

    <!-- ================================================================
         1. SECÇÃO: PRATOS EXTRA
         ================================================================ -->
    <div class="prazos-card">
        <div class="prazos-card-header">
            <div>
                <h2 class="prazos-card-titulo">
                    <i class="bi bi-egg-fried"></i>
                    Pratos Extra
                </h2>
            </div>
            <span class="prazos-badge-preview badge-ativo" id="badgePreviewExtras">
                <i class="bi bi-clock"></i>
                <?= htmlspecialchars($textoBadgeExtras) ?>
            </span>
        </div>

        <p class="prazos-descricao">
            Os alunos podem reservar pratos extra até à hora e antecedência aqui definidas. Após esse prazo, a compra para a respetiva data é bloqueada.
        </p>

        <form id="formPrazoExtras" class="form-prazo-linha">
            <div class="form-campo-prazo" style="max-width: 180px;">
                <label for="horaExtras">Hora limite</label>
                <input
                    type="time"
                    id="horaExtras"
                    name="horaExtras"
                    value="<?= htmlspecialchars($horaExtrasFormatada) ?>"
                    required>
            </div>

            <div class="form-campo-prazo" style="max-width: 260px;">
                <label for="diasExtras">Antecedência</label>
                <select id="diasExtras" name="diasExtras">
                    <option value="0" <?= $diasExtras === 0 ? 'selected' : '' ?>>0 dias de antecedência (no próprio dia)</option>
                    <option value="1" <?= $diasExtras === 1 ? 'selected' : '' ?>>1 dia de antecedência (dia anterior)</option>
                    <option value="2" <?= $diasExtras === 2 ? 'selected' : '' ?>>2 dias de antecedência</option>
                    <option value="3" <?= $diasExtras === 3 ? 'selected' : '' ?>>3 dias de antecedência</option>
                </select>
            </div>

            <button type="submit" class="btn-salvar-prazo btn-salvar-prazo--verde">
                <i class="bi bi-check-lg"></i>
                Guardar hora dos extras
            </button>
        </form>
    </div>

    <!-- ================================================================
         2. SECÇÃO: PRATOS DA EMENTA (CONFIGURAÇÃO GERAL)
         ================================================================ -->
    <div class="prazos-card">
        <div class="prazos-card-header">
            <div>
                <h2 class="prazos-card-titulo">
                    <i class="bi bi-journal-check"></i>
                    Pratos da Ementa (Definição Global)
                </h2>
            </div>
            <span class="prazos-badge-preview badge-ativo" id="badgePreviewEmenta">
                <i class="bi bi-check-all"></i>
                <?= htmlspecialchars($prazoPrincipalTexto) ?>
            </span>
        </div>

        <p class="prazos-descricao">
            Aplica uma regra uniforme a todos os pratos da ementa semanal (Carne, Peixe, Vegetariano, Sopa, Sobremesa, Bebida). Esta indicação é exibida aos alunos na página da ementa.
        </p>

        <form id="formPrazoTodosEmenta" class="form-prazo-linha">
            <div class="form-campo-prazo" style="max-width: 180px;">
                <label for="horaEmentaGlobal">Hora limite</label>
                <input
                    type="time"
                    id="horaEmentaGlobal"
                    name="horaEmentaGlobal"
                    value="<?= htmlspecialchars($horaEmentaGlobal) ?>"
                    required>
            </div>

            <div class="form-campo-prazo" style="max-width: 260px;">
                <label for="diasEmentaGlobal">Antecedência</label>
                <select id="diasEmentaGlobal" name="diasEmentaGlobal">
                    <option value="1" <?= $diasEmentaGlobal === 1 ? 'selected' : '' ?>>1 dia de antecedência (dia anterior)</option>
                    <option value="0" <?= $diasEmentaGlobal === 0 ? 'selected' : '' ?>>0 dias (no próprio dia)</option>
                    <option value="2" <?= $diasEmentaGlobal === 2 ? 'selected' : '' ?>>2 dias de antecedência</option>
                    <option value="3" <?= $diasEmentaGlobal === 3 ? 'selected' : '' ?>>3 dias de antecedência</option>
                </select>
            </div>

            <button type="submit" class="btn-salvar-prazo">
                <i class="bi bi-arrow-repeat"></i>
                Aplicar a toda a ementa
            </button>
        </form>
    </div>

    <!-- ================================================================
         3. SECÇÃO: AJUSTE INDIVIDUAL POR TIPO DE PRATO DA EMENTA
         ================================================================ -->
    <div class="ementa-tipos-card">
        <div class="ementa-tipos-header">
            <h2 class="prazos-card-titulo" style="font-size: 1rem;">
                <i class="bi bi-sliders"></i>
                Ajuste Individual dos Pratos Principais
            </h2>
            <p class="prazos-descricao" style="margin-bottom: 0.5rem;">
                Ajusta individualmente Carne, Peixe ou Vegetariano se algum deles necessitar de um prazo diferente. Sopa, sobremesa e bebida são acompanhamentos e seguem o prazo global da refeição.
            </p>
        </div>

        <div class="ementa-tipos-lista">
            <?php
            $iconesTipo = [
                'Carne'       => 'bi-fire',
                'Peixe'       => 'bi-water',
                'Vegetariano' => 'bi-flower1',
            ];
            ?>
            <?php foreach ($prazosEmenta as $p): ?>
                <?php
                $horaTipo = substr((string) $p['RDL_HORA'], 0, 5);
                $diasTipo = (int) $p['RDL_DIA_ANTECEDENCIA'];
                $icone = $iconesTipo[$p['RTP_NOME']] ?? 'bi-tag';
                ?>
                <div class="tipo-linha-item" data-tipo-id="<?= (int) $p['RTP_ID'] ?>">
                    <div class="tipo-linha-info">
                        <span class="tipo-icone-badge">
                            <i class="bi <?= $icone ?>"></i>
                        </span>
                        <span class="tipo-nome">
                            <?= htmlspecialchars($p['RTP_NOME']) ?>
                        </span>
                    </div>

                    <div class="tipo-linha-form">
                        <input
                            type="time"
                            class="input-tipo-hora"
                            value="<?= htmlspecialchars($horaTipo) ?>"
                            title="Hora limite">

                        <select class="select-tipo-dias" title="Dias de antecedência">
                            <option value="1" <?= $diasTipo === 1 ? 'selected' : '' ?>>1 dia antes</option>
                            <option value="0" <?= $diasTipo === 0 ? 'selected' : '' ?>>Próprio dia</option>
                            <option value="2" <?= $diasTipo === 2 ? 'selected' : '' ?>>2 dias antes</option>
                            <option value="3" <?= $diasTipo === 3 ? 'selected' : '' ?>>3 dias antes</option>
                        </select>

                        <button type="button" class="btn-tipo-salvar" title="Guardar este tipo">
                            <i class="bi bi-check2"></i>
                            Guardar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>
</div>

<!-- Token CSRF e Script JS -->
<script>
window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>
<script src="<?= assetUrl('assets/js/gerir_prazos.js') ?>"></script>
</body>
</html>
