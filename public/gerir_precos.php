<?php
/**
 * Página de gestão de preços de refeições.
 *
 * Permite ao admin_cantina consultar e atualizar os preços vigentes
 * para pratos principais (Carne, Peixe, Vegetariano), Menu Completo,
 * complementos (Sopa, Sobremesa, Bebida) e pratos extra padrão.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina');

$precos = Database::listarPrecosVigentesTodosTipos();

// Listar pratos extra individuais para exibição completa na tabela e exportação
$extrasIndividuais = [];
try {
    $extrasIndividuais = Database::listarDetalhesExtrasParaGestao();
} catch (Exception $e) {
    $extrasIndividuais = [];
}

// Categorias organizadas para renderização clara
$categorias = [
    'principais' => [
        'titulo'    => 'Pratos Principais da Ementa',
        'subtitulo' => 'Carne, Peixe e Vegetariano',
        'icone'     => 'bi-journal-check',
        'tipos'     => ['Carne', 'Peixe', 'Vegetariano'],
    ],
    'menu_completo' => [
        'titulo'    => 'Menu Completo de Refeição',
        'subtitulo' => 'Fórmula global com prato, sopa, sobremesa e bebida',
        'icone'     => 'bi-stars',
        'tipos'     => ['Menu Completo'],
    ],
    'complementos' => [
        'titulo'    => 'Itens Complementares & Bebidas',
        'subtitulo' => 'Vendidos isoladamente ou componentes do menu',
        'icone'     => 'bi-cup-hot',
        'tipos'     => ['Sopa', 'Sobremesa', 'Bebida'],
    ],
    'extras' => [
        'titulo'    => 'Pratos Extra (Preço Base Padrão)',
        'subtitulo' => 'Preço de referência para opções extra da cantina',
        'icone'     => 'bi-egg-fried',
        'tipos'     => ['Prato extra'],
    ],
];

// Metadados informativos para cada tipo
$metaTipos = [
    'Carne' => [
        'icone' => 'bi-fire',
        'desc'  => 'Prato principal à base de carne da ementa diária',
    ],
    'Peixe' => [
        'icone' => 'bi-water',
        'desc'  => 'Prato principal de pescado da ementa diária',
    ],
    'Vegetariano' => [
        'icone' => 'bi-flower1',
        'desc'  => 'Prato principal vegetariano da ementa diária',
    ],
    'Menu Completo' => [
        'icone' => 'bi-stars',
        'desc'  => 'Fórmula com prato principal + sopa + sobremesa + bebida',
    ],
    'Sopa' => [
        'icone' => 'bi-cup-hot',
        'desc'  => 'Sopa do dia (incluída no Menu Completo ou à parte)',
    ],
    'Sobremesa' => [
        'icone' => 'bi-pie-chart',
        'desc'  => 'Sobremesa / Fruta da época (incluída no Menu Completo ou à parte)',
    ],
    'Bebida' => [
        'icone' => 'bi-cup-straw',
        'desc'  => 'Água ou sumo (incluída no Menu Completo ou à parte)',
    ],
    'Prato extra' => [
        'icone' => 'bi-egg-fried',
        'desc'  => 'Preço padrão base para novos pratos extra adicionados',
    ],
];

// Mapeia preços por nome para consulta rápida
$precosPorNome = [];
foreach ($precos as $p) {
    $precosPorNome[$p['RTP_NOME']] = $p;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir Preços</title>
    <meta name="description" content="Gestão dos preços vigentes das refeições e menus da cantina — área de administração.">
    <meta name="robots" content="noindex">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Folhas de estilo base -->
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/navbar.css') ?>" rel="stylesheet">

    <!-- CSS específico desta página -->
    <link href="<?= assetUrl('assets/css/gerir-precos.css') ?>" rel="stylesheet">
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

    <a href="gerir_precos.php" class="nav-icon-link nav-icon-link--ativo" title="Gerir preços">
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

    <a href="gerir_prazos.php" class="nav-icon-link" title="Gerir prazos e horas limite">
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
<main class="gerir-precos-main">

    <!-- Cabeçalho Oficial exclusivo para Impressão / PDF (Afixação Física) -->
    <div class="print-header-oficial">
        <div class="print-instituicao">Universidade Portucalense</div>
        <div class="print-subinstituicao">Serviços de Cantina e Alimentação</div>
        <h1 class="print-titulo">TABELA OFICIAL DE PREÇOS DAS REFEIÇÕES</h1>
        <p class="print-data-emissao">
            Documento emitido em <?= date('d/m/Y \à\s H:i') ?> &bull; Preços em vigor
        </p>
    </div>

    <div class="gerir-precos-header">
        <div class="gerir-precos-header-texto">
            <h1 class="gerir-precos-titulo">tabela de preços das refeições</h1>
            <p class="gerir-precos-subtitulo">
                Consulta e atualiza os preços das refeições, menus e itens da cantina universitária.
            </p>
        </div>
        <div class="gerir-precos-acoes-topo">
            <button type="button" class="btn-acao-exportar btn-imprimir-pdf" onclick="window.print()" title="Imprimir tabela ou guardar em PDF para afixação física">
                <i class="bi bi-printer"></i>
                <span>Imprimir / PDF</span>
            </button>
            <a href="api/exportar_precos_csv.php" class="btn-acao-exportar btn-exportar-csv" title="Exportar tabela completa de preços em formato CSV (Excel)">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Exportar CSV</span>
            </a>
        </div>
    </div>

    <!-- Aviso sobre Versionamento e Histórico de Preços -->
    <div class="precos-aviso-historico">
        <i class="bi bi-shield-check"></i>
        <div>
            <strong>Preservação do Histórico de Compras:</strong> A alteração de um preço aplica-se apenas a reservas efetuadas a partir de hoje. Todos os pedidos e relatórios anteriores mantêm exatamente os valores cobrados na respetiva data.
        </div>
    </div>

    <!-- Secções de Preços por Categoria -->
    <?php foreach ($categorias as $catId => $cat): ?>
        <div class="precos-card">
            <div class="precos-card-header">
                <h2 class="precos-card-titulo">
                    <i class="bi <?= $cat['icone'] ?>"></i>
                    <?= htmlspecialchars($cat['titulo']) ?>
                </h2>
                <span class="precos-badge-categoria">
                    <?= htmlspecialchars($cat['subtitulo']) ?>
                </span>
            </div>

            <div class="precos-itens-lista">
                <?php foreach ($cat['tipos'] as $nomeTipo): ?>
                    <?php
                    $item = $precosPorNome[$nomeTipo] ?? null;
                    if (!$item) continue;

                    $tipoId = (int) $item['RTP_ID'];
                    $precoAtual = $item['preco_atual'] !== null ? (float) $item['preco_atual'] : null;
                    $precoFormatado = $precoAtual !== null ? number_format($precoAtual, 2, ',', '') . ' €' : 'Sem preço';
                    $precoInputValor = $precoAtual !== null ? number_format($precoAtual, 2, '.', '') : '';

                    $dataInicio = $item['data_inicio_preco'] ?? null;
                    $textoData = 'Preço de catálogo';
                    if ($dataInicio) {
                        $dt = new DateTime($dataInicio);
                        $textoData = 'Em vigor desde ' . $dt->format('d/m/Y');
                    }

                    $meta = $metaTipos[$nomeTipo] ?? [
                        'icone' => 'bi-tag',
                        'desc'  => 'Refeição da cantina'
                    ];
                    ?>
                    <div class="preco-item-linha" data-tipo="<?= htmlspecialchars($nomeTipo) ?>">
                        <div class="preco-item-info">
                            <div class="preco-tipo-icone">
                                <i class="bi <?= $meta['icone'] ?>"></i>
                            </div>
                            <div class="preco-tipo-detalhes">
                                <span class="preco-tipo-nome"><?= htmlspecialchars($nomeTipo) ?></span>
                                <span class="preco-tipo-desc"><?= htmlspecialchars($meta['desc']) ?></span>
                            </div>
                        </div>

                        <div class="preco-item-acoes">
                            <div class="preco-item-vigente-bloco">
                                <span class="preco-vigente-valor"><?= htmlspecialchars($precoFormatado) ?></span>
                                <span class="preco-vigente-data">
                                    <i class="bi bi-clock-history"></i> <?= htmlspecialchars($textoData) ?>
                                </span>
                            </div>

                            <form class="preco-item-form form-atualizar-preco" data-tipo-id="<?= $tipoId ?>">
                                <div class="input-preco-wrap">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="999.99"
                                        class="input-novo-preco"
                                        value="<?= htmlspecialchars($precoInputValor) ?>"
                                        placeholder="0.00"
                                        required
                                        title="Novo preço em euros">
                                    <span class="input-preco-simbolo">€</span>
                                </div>
                                <button type="submit" class="btn-salvar-preco" title="Guardar novo preço para <?= htmlspecialchars($nomeTipo) ?>">
                                    <i class="bi bi-check2"></i> Guardar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($catId === 'extras' && !empty($extrasIndividuais)): ?>
                <div class="precos-extras-individuais-seccao">
                    <div class="precos-extras-subtitulo-bloco">
                        <span class="precos-extras-subtitulo-texto">Pratos Extra Específicos Ativos</span>
                    </div>
                    <?php foreach ($extrasIndividuais as $extra): ?>
                        <?php
                        if (empty($extra['RM_ATIVO'])) continue;
                        $precoExtra = $extra['preco_atual'] !== null ? (float) $extra['preco_atual'] : null;
                        $precoExtraFormatado = $precoExtra !== null ? number_format($precoExtra, 2, ',', '') . ' €' : 'Sem preço';
                        ?>
                        <div class="preco-item-linha preco-item-linha--extra-individual" data-tipo="<?= htmlspecialchars($extra['RM_NOME']) ?>">
                            <div class="preco-item-info">
                                <div class="preco-tipo-icone">
                                    <i class="bi bi-star"></i>
                                </div>
                                <div class="preco-tipo-detalhes">
                                    <span class="preco-tipo-nome"><?= htmlspecialchars($extra['RM_NOME']) ?></span>
                                    <span class="preco-tipo-desc">Prato extra individual disponível para reserva</span>
                                </div>
                            </div>

                            <div class="preco-item-acoes">
                                <div class="preco-item-vigente-bloco">
                                    <span class="preco-vigente-valor"><?= htmlspecialchars($precoExtraFormatado) ?></span>
                                    <span class="preco-vigente-data">
                                        <i class="bi bi-check-circle"></i> Ativo
                                    </span>
                                </div>
                                <div class="preco-item-link-edicao">
                                    <a href="gerir_extras.php" class="btn-editar-extra-atalho" title="Alterar preço em Gerir Extras">
                                        <i class="bi bi-pencil-square"></i> Alterar
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($catId === 'extras'): ?>
                <div class="precos-rodape-atalho">
                    <span>
                        <i class="bi bi-info-circle"></i> Os extras com preços diferenciados (ex.: Hambúrguer, Francesinha) podem ser geridos individualmente.
                    </span>
                    <a href="gerir_extras.php" class="btn-link-extras">
                        Gerir pratos extras específicos <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Rodapé Oficial exclusivo para Impressão / PDF (Afixação Física) -->
    <div class="print-footer-oficial">
        <div class="print-footer-notas">
            <p><strong>Condições Gerais e Composição dos Menus:</strong></p>
            <p>&bull; <strong>Menu Completo:</strong> Inclui 1 Prato Principal (Carne, Peixe ou Vegetariano), 1 Sopa, 1 Sobremesa/Fruta da época e 1 Bebida (Água ou Sumo).</p>
            <p>&bull; <strong>Itens Avulso:</strong> A sopa, sobremesa e bebida podem ser adquiridas individualmente ao preço de tabela indicado.</p>
            <p>&bull; <strong>Pratos Extra:</strong> Opções especiais disponíveis diariamente conforme disponibilidade e prazo de reserva em vigor.</p>
            <p>&bull; Todos os preços apresentados incluem IVA à taxa legal em vigor. Tabela afixada em cumprimento da legislação aplicável.</p>
        </div>
        <div class="print-footer-assinatura">
            <div>
                Data de afixação: ____ / ____ / ________
            </div>
            <div class="print-assinatura-linha">
                A Direção dos Serviços de Cantina
            </div>
        </div>
    </div>

</main>
</div>

<!-- Token CSRF e Script JS -->
<script>
window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>
<script src="<?= assetUrl('assets/js/gerir_precos.js') ?>"></script>
</body>
</html>
