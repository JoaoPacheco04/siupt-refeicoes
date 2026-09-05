<?php
/**
 * Página de relatório mensal.
 *
 * Apresenta um resumo estatístico das vendas da cantina
 * para o mês selecionado, incluindo indicadores gerais,
 * vendas por tipo de refeição, gráfico de vendas diárias
 * e avaliações por prato (filtradas pelo mês selecionado).
 */

// Auth.php inicia a sessão internamente se ainda não estiver ativa.
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';
require_once __DIR__ . '/../src/Support/Assets.php';

$utilizador = exigirLogin('admin_cantina');

/**
 * Obtém o mês pretendido através da query string.
 * Caso o formato seja inválido, utiliza o mês atual.
 */
$anoMes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
    $anoMes = date('Y-m');
}

/**
 * Carrega toda a informação necessária para o relatório.
 */
$resumo = Database::obterResumoMensal($anoMes);
$vendasPorTipo = Database::obterVendasPorTipoMensal($anoMes);
$vendasDiarias = Database::obterVendasDiariasMensal($anoMes);
$mediaAvaliacoes = Database::obterMediaAvaliacoesMensal($anoMes);
$avaliacoesPorPrato = Database::obterMediaAvaliacoesPorPrato(1, $anoMes);
// Motivos de reclamação das avaliações com 1-2 estrelas
$motivosProblemas = Database::obterMotivosProblemasMensal($anoMes);

// Labels vêm da BD (editáveis via gerir_motivos.php)
$motivosLabels = [];
foreach (Database::listarTodosMotivosReclamacao() as $m) {
    $motivosLabels[$m['RMR_CODIGO']] = $m['RMR_LABEL'];
}




$mesesNomes = [
    '01' => 'Janeiro',
    '02' => 'Fevereiro',
    '03' => 'Março',
    '04' => 'Abril',
    '05' => 'Maio',
    '06' => 'Junho',
    '07' => 'Julho',
    '08' => 'Agosto',
    '09' => 'Setembro',
    '10' => 'Outubro',
    '11' => 'Novembro',
    '12' => 'Dezembro',
];
[$anoSelecionado, $mesSelecionado] = explode('-', $anoMes);
$nomeMesSelecionado = $mesesNomes[$mesSelecionado] ?? $mesSelecionado;

/**
 * Calcula a variação do valor vendido relativamente ao mês anterior.
 */
$diferencaMes = $resumo['total_vendido'] - $resumo['total_vendido_mes_anterior'];
$percentagemMes = $resumo['total_vendido_mes_anterior'] > 0
    ? ($diferencaMes / $resumo['total_vendido_mes_anterior']) * 100
    : null;

/**
 * Separa as vendas entre pratos da ementa e pratos extra utilizando RM_PRATO_DIA.
 */
$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => (int) $t['RM_PRATO_DIA'] !== 0 || $t['RTP_NOME'] === 'Menu Completo'));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => (int) $t['RM_PRATO_DIA'] === 0 && $t['RTP_NOME'] !== 'Menu Completo'));
$totalExtras = array_sum(array_column($extrasVendas, 'total'));
$qtdExtras = array_sum(array_column($extrasVendas, 'quantidade'));

$LIMITE_LINHAS = 10;
$pratosEmentaMostrar = array_slice($pratosEmenta, 0, $LIMITE_LINHAS);
$extrasMostrar = array_slice($extrasVendas, 0, $LIMITE_LINHAS);

/**
 * Calcula distribuição percentual por tipo de refeição.
 */
$totalQtdGeral = array_sum(array_column($vendasPorTipo, 'quantidade'));
$distribuicaoTipos = [];
if ($totalQtdGeral > 0) {
    foreach ($vendasPorTipo as $vt) {
        $nome = trim($vt['RTP_NOME']);
        $nomeLower = mb_strtolower($nome);
        $cor = '#64748b';
        if (str_contains($nomeLower, 'carne')) {
            $cor = '#ef4444';
        } elseif (str_contains($nomeLower, 'peixe')) {
            $cor = '#0284c7';
        } elseif (str_contains($nomeLower, 'veg')) {
            $cor = '#16a34a';
        } elseif (str_contains($nomeLower, 'dieta')) {
            $cor = '#f59e0b';
        } elseif (str_contains($nomeLower, 'menu')) {
            $cor = '#8b5cf6';
        } elseif (str_contains($nomeLower, 'extra')) {
            $cor = '#ec4899';
        }
        $qtd = (int) $vt['quantidade'];
        $pct = round(($qtd / $totalQtdGeral) * 100, 1);
        $distribuicaoTipos[] = [
            'nome' => $nome,
            'quantidade' => $qtd,
            'percentagem' => $pct,
            'cor' => $cor,
        ];
    }
}

/**
 * Prepara os dados do gráfico de vendas diárias para Chart.js.
 */
$graficoLabels = [];
$graficoVendas = [];
$graficoPedidos = [];
foreach ($vendasDiarias as $d) {
    $graficoLabels[] = date('d/m', strtotime($d['RP_DATA_REFEICAO']));
    $graficoVendas[] = round((float) $d['total_vendido'], 2);
    $graficoPedidos[] = (int) $d['total_pedidos'];
}

?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Relatório mensal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/relatorio.css') ?>" rel="stylesheet">
    <!-- Chart.js para o gráfico de vendas diárias -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body>

    <div id="bodycontainer">
        <header>
            <a id="home" href="<?= temPapelSessao('atendente') || temPapelSessao('admin_cantina') ? 'validar.php' : 'ementa.php' ?>" title="Voltar ao início">
                <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
            </a>

            <?php if (temPapelSessao('atendente') || temPapelSessao('admin_cantina')): ?>
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

                <a href="gerir_motivos.php" class="nav-icon-link" title="Gerir motivos">
                    <i class="bi bi-chat-square-text"></i>
                </a>

                <a href="gerir_feriados.php" class="nav-icon-link" title="Gerir feriados">
                    <i class="bi bi-calendar-x"></i>
                </a>

                <a href="gerir_atendentes.php" class="nav-icon-link" title="Gerir atendentes">
                    <i class="bi bi-people"></i>
                </a>

                <a href="relatorio.php" class="nav-icon-link nav-icon-link--ativo" title="Relatório mensal">
                    <i class="bi bi-bar-chart-line"></i>
                </a>
            <?php endif; ?>

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

        <main class="relatorio-main">
            <div class="relatorio-header-acoes">
                <h1 class="relatorio-titulo">relatório mensal</h1>
                <div class="relatorio-acoes-grupo">
                    <a href="api/exportar_relatorio_mensal.php?mes=<?= htmlspecialchars($anoMes) ?>" class="btn-exportar-csv" title="Exportar lista detalhada de refeições em CSV">
                        <i class="bi bi-file-earmark-spreadsheet"></i> CSV Detalhado
                    </a>
                    <a href="api/exportar_relatorio.php?mes=<?= htmlspecialchars($anoMes) ?>" class="btn-exportar-csv" title="Exportar resumo de vendas em CSV">
                        <i class="bi bi-filetype-csv"></i> CSV Resumo
                    </a>
                    <a href="api/exportar_relatorio_pdf.php?mes=<?= htmlspecialchars($anoMes) ?>"
                        class="btn-exportar-pdf">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </a>
                </div>
            </div>

            <!-- Seleção do mês do relatório -->
            <?php
            $mesAnteriorStr = date('Y-m', strtotime($anoMes . '-01 -1 month'));
            $mesSeguinteStr = date('Y-m', strtotime($anoMes . '-01 +1 month'));
            $temMesSeguinte = $mesSeguinteStr <= date('Y-m');
            ?>

            <div class="relatorio-seletor-mes">
                <a href="?mes=<?= $mesAnteriorStr ?>" class="btn-nav-mes" title="Mês anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <form method="get" style="display:contents;">
                    <label for="mes">Mês</label>
                    <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($anoMes) ?>"
                        max="<?= date('Y-m') ?>" onchange="this.form.submit()">
                </form>
                <?php if ($temMesSeguinte): ?>
                    <a href="?mes=<?= $mesSeguinteStr ?>" class="btn-nav-mes" title="Mês seguinte">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="btn-nav-mes btn-nav-mes-desativado" title="Não é possível avançar para o futuro">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>

            <h2 class="relatorio-subtitulo"><?= htmlspecialchars($nomeMesSelecionado . ' ' . $anoSelecionado) ?></h2>

            <!-- Indicadores principais do mês -->
            <div class="relatorio-cartoes">
                <div class="cartao-resumo">
                    <div class="cartao-icone"><i class="bi bi-bag-check-fill"></i></div>
                    <div class="cartao-valor"><?= number_format($resumo['total_vendido'], 2, ',', '.') ?>€</div>
                    <div class="cartao-label">total vendido</div>
                    <?php if ($percentagemMes !== null): ?>
                        <div class="cartao-comparacao <?= $percentagemMes >= 0 ? 'positiva' : 'negativa' ?>">
                            <i class="bi bi-arrow-<?= $percentagemMes >= 0 ? 'up' : 'down' ?>-short"></i>
                            <?= number_format(abs($percentagemMes), 1, ',', '.') ?>% vs. mês anterior
                        </div>
                    <?php endif; ?>
                </div>
                <div class="cartao-resumo">
                    <div class="cartao-icone"><i class="bi bi-receipt"></i></div>
                    <div class="cartao-valor"><?= $resumo['total_pedidos'] ?></div>
                    <div class="cartao-label">pedidos pagos</div>
                </div>
                <div class="cartao-resumo">
                    <div class="cartao-icone"><i class="bi bi-check2-circle"></i></div>
                    <div class="cartao-valor"><?= $resumo['total_levantados'] ?></div>
                    <div class="cartao-label">refeições levantadas</div>
                </div>
                <div class="cartao-resumo <?= $resumo['total_nao_levantados'] > 0 ? 'cartao-aviso' : '' ?>">
                    <div class="cartao-icone"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="cartao-valor"><?= $resumo['total_nao_levantados'] ?></div>
                    <div class="cartao-label">não levantadas</div>
                </div>
                <div class="cartao-resumo">
                    <div class="cartao-icone"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="cartao-valor"><?= number_format($resumo['preco_medio'], 2, ',', '.') ?>€</div>
                    <div class="cartao-label">preço médio/pedido</div>
                </div>
            </div>

            <?php if (!empty($distribuicaoTipos)): ?>
                <div class="relatorio-distribuicao-card">
                    <div class="distribuicao-header">
                        <h3 class="distribuicao-titulo"><i class="bi bi-pie-chart-fill"></i> Distribuição das Escolhas</h3>
                        <span class="distribuicao-subtitulo"><?= $totalQtdGeral ?> refeições consumidas</span>
                    </div>
                    <div class="distribuicao-barra-segmentada" role="progressbar" aria-label="Distribuição de escolhas">
                        <?php foreach ($distribuicaoTipos as $seg): ?>
                            <?php if ($seg['percentagem'] > 0): ?>
                                <div class="segmento-barra" 
                                     style="width: <?= $seg['percentagem'] ?>%; background-color: <?= $seg['cor'] ?>;" 
                                     title="<?= htmlspecialchars($seg['nome']) ?>: <?= $seg['quantidade'] ?>x (<?= $seg['percentagem'] ?>%)">
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="distribuicao-legendas">
                        <?php foreach ($distribuicaoTipos as $seg): ?>
                            <div class="distribuicao-legenda-item">
                                <span class="legenda-ponto" style="background-color: <?= $seg['cor'] ?>;"></span>
                                <span class="legenda-nome"><?= htmlspecialchars($seg['nome']) ?></span>
                                <strong class="legenda-pct"><?= $seg['percentagem'] ?>%</strong>
                                <span class="legenda-qtd">(<?= $seg['quantidade'] ?>x)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($vendasDiarias)): ?>
                <h2 class="relatorio-secao-titulo">evolução de vendas diárias</h2>
                <div class="relatorio-grafico-wrap">
                    <?php if (count($vendasDiarias) < 3): ?>
                        <p class="text-muted small" style="text-align:center; margin-bottom:0.5rem;">
                            <i class="bi bi-info-circle"></i> Poucos dias de dados este mês — o gráfico fica mais útil com o mês
                            completo.
                        </p>
                    <?php endif; ?>
                    <canvas id="graficoVendas" height="90" aria-label="Gráfico de vendas diárias" role="img"></canvas>
                </div>
            <?php endif; ?>

            <!-- Vendas por tipo de refeição da ementa -->
            <h2 class="relatorio-secao-titulo">
                vendas por tipo — ementa
                <?php if (count($pratosEmenta) > $LIMITE_LINHAS): ?>
                    <span class="relatorio-subtotal">(top <?= $LIMITE_LINHAS ?> de <?= count($pratosEmenta) ?>)</span>
                <?php endif; ?>
            </h2>
            <?php if (empty($pratosEmentaMostrar)): ?>
                <p class="relatorio-vazio"><i class="bi bi-inbox"></i> Sem vendas de pratos da ementa neste mês.</p>
            <?php else: ?>
                <div class="relatorio-tabela">
                    <?php foreach ($pratosEmentaMostrar as $i => $t): ?>
                        <div class="relatorio-tabela-linha<?= $i === 0 ? ' relatorio-tabela-destaque' : '' ?>">
                            <span class="relatorio-tabela-nome">
                                <?= htmlspecialchars($t['RTP_NOME']) ?>
                                <?php if ($i === 0): ?><i class="bi bi-star-fill relatorio-icone-destaque"></i><?php endif; ?>
                            </span>
                            <span class="relatorio-tabela-qtd"><?= $t['quantidade'] ?>x</span>
                            <span class="relatorio-tabela-total"><?= number_format((float) $t['total'], 2, ',', '.') ?>€</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Vendas de pratos extra -->
            <h2 class="relatorio-secao-titulo">
                vendas por tipo — extras
                <?php if (!empty($extrasVendas)): ?>
                    <span class="relatorio-subtotal">
                        (total: <?= number_format($totalExtras, 2, ',', '.') ?>€, <?= $qtdExtras ?>x)
                        <?php if (count($extrasVendas) > $LIMITE_LINHAS): ?>
                            — mostrando top <?= $LIMITE_LINHAS ?> de <?= count($extrasVendas) ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </h2>
            <?php if (empty($extrasMostrar)): ?>
                <p class="relatorio-vazio"><i class="bi bi-inbox"></i> Sem vendas de extras neste mês.</p>
            <?php else: ?>
                <div class="relatorio-tabela">
                    <?php foreach ($extrasMostrar as $i => $t): ?>
                        <div class="relatorio-tabela-linha<?= $i === 0 ? ' relatorio-tabela-destaque' : '' ?>">
                            <span class="relatorio-tabela-nome">
                                <?= htmlspecialchars(str_replace('Extra: ', '', $t['RTP_NOME'])) ?>
                                <?php if ($i === 0): ?><i class="bi bi-star-fill relatorio-icone-destaque"></i><?php endif; ?>
                            </span>
                            <span class="relatorio-tabela-qtd"><?= $t['quantidade'] ?>x</span>
                            <span class="relatorio-tabela-total"><?= number_format((float) $t['total'], 2, ',', '.') ?>€</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Resumo diário de vendas (tabela compacta abaixo do gráfico) -->
            <h2 class="relatorio-secao-titulo">vendas diárias — detalhe</h2>
            <?php if (empty($vendasDiarias)): ?>
                <p class="relatorio-vazio"><i class="bi bi-inbox"></i> Sem vendas registadas neste mês.</p>
            <?php else: ?>
                <div class="relatorio-tabela">
                    <?php foreach ($vendasDiarias as $d): ?>
                        <div class="relatorio-tabela-linha">
                            <span class="relatorio-tabela-nome"><?= date('d/m/Y', strtotime($d['RP_DATA_REFEICAO'])) ?></span>
                            <span class="relatorio-tabela-qtd"><?= $d['total_pedidos'] ?> pedido(s)</span>
                            <span
                                class="relatorio-tabela-total"><?= number_format((float) $d['total_vendido'], 2, ',', '.') ?>€</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Avaliações dos alunos por prato (filtradas pelo mês selecionado) -->
            <h2 class="relatorio-secao-titulo">avaliações por prato — <?= htmlspecialchars($nomeMesSelecionado) ?></h2>

            <?php if (empty($avaliacoesPorPrato) && $mediaAvaliacoes['total'] === 0): ?>
                <p class="relatorio-vazio"><i class="bi bi-star"></i> Sem avaliações registadas neste mês.</p>
            <?php else: ?>

                <?php if ($mediaAvaliacoes['total'] > 0): ?>
                    <div class="avaliacao-resumo-geral">
                        <span
                            class="avaliacao-geral-estrelas"><?= str_repeat('★', round($mediaAvaliacoes['media'])) . str_repeat('☆', 5 - round($mediaAvaliacoes['media'])) ?></span>
                        <span class="avaliacao-geral-numero"><?= number_format($mediaAvaliacoes['media'], 1, ',', '.') ?> /
                            5</span>
                        <span class="avaliacao-geral-total">(<?= $mediaAvaliacoes['total'] ?>
                            avaliação<?= $mediaAvaliacoes['total'] !== 1 ? 'ões' : '' ?> neste mês)</span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($avaliacoesPorPrato)): ?>
                    <div class="relatorio-tabela avaliacao-por-prato-tabela">
                        <?php foreach ($avaliacoesPorPrato as $prato):
                            $media = (float) $prato['media'];
                            $total = (int) $prato['total'];
                            $estrelasInt = (int) round($media);
                            $corClasse = $media >= 4 ? 'barra-boa' : ($media >= 3 ? 'barra-media' : 'barra-ma');
                            // DESIGN D2: barra de progresso proporcional (0–5 escala → 0–100%)
                            $larguraBarra = round(($media / 5) * 100);
                            ?>
                            <div class="relatorio-tabela-linha avaliacao-prato-linha">
                                <span class="relatorio-tabela-nome avaliacao-prato-nome">
                                    <?= htmlspecialchars($prato['RM_NOME']) ?>
                                    <span class="avaliacao-prato-contagem"><?= $total ?>
                                        <?= $total === 1 ? 'avaliação' : 'avaliações' ?></span>
                                </span>
                                <!-- DESIGN D2: barra visual proporcional à nota -->
                                <span class="avaliacao-prato-barra-wrap">
                                    <span class="avaliacao-prato-barra <?= $corClasse ?>"
                                        style="width:<?= $larguraBarra ?>%"></span>
                                </span>
                                <span class="avaliacao-prato-estrelas">
                                    <?= str_repeat('★', $estrelasInt) . str_repeat('☆', 5 - $estrelasInt) ?>
                                </span>
                                <span class="avaliacao-prato-nota <?= $corClasse ?>">
                                    <?= number_format($media, 1, ',', '.') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="relatorio-vazio"><i class="bi bi-star"></i> Nenhum prato com avaliações suficientes para mostrar.
                    </p>
                <?php endif; ?>

            <?php endif; ?>

            <!-- Motivos de reclamação das avaliações negativas -->
            <?php
            $totalMotivos = array_sum(array_column($motivosProblemas, 'total'));
            $maxMotivo = !empty($motivosProblemas) ? max(array_column($motivosProblemas, 'total')) : 1;
            ?>
            <h2 class="relatorio-secao-titulo">motivos de reclamação
                <?php if ($totalMotivos > 0): ?>
                    <span class="relatorio-subtotal">(<?= $totalMotivos ?> no total)</span>
                <?php endif; ?>
            </h2>

            <?php if (empty($motivosProblemas)): ?>
                <p class="relatorio-vazio"><i class="bi bi-emoji-smile"></i> Sem reclamações registadas neste mês.</p>
            <?php else: ?>
                <div class="relatorio-tabela motivos-tabela">
                    <?php foreach ($motivosProblemas as $m):
                        $label = $motivosLabels[$m['RAV_MOTIVO']] ?? $m['RAV_MOTIVO'];
                        $icone = 'bi-chat-square-text';
                        $largura = round(($m['total'] / $maxMotivo) * 100);
                        $pratosComData = [];
                        if (!empty($m['pratos_associados'])) {
                            $entradas = array_filter(array_map('trim', explode(';;', $m['pratos_associados'])));
                            foreach ($entradas as $entrada) {
                                $partes = explode('|', $entrada, 2);
                                $nomePrato = trim($partes[0]);
                                $dataPrato = isset($partes[1]) ? trim($partes[1]) : '';
                                if ($nomePrato !== '') {
                                    $chave = $nomePrato . '|' . $dataPrato;
                                    $pratosComData[$chave] = ($pratosComData[$chave] ?? 0) + 1;
                                }
                            }
                            arsort($pratosComData);
                        }
                        $temPratos = !empty($pratosComData);
                        ?>
                        <div class="motivo-item <?= $temPratos ? 'motivo-expansivel' : '' ?>">
                            <div class="relatorio-tabela-linha motivo-linha" <?= $temPratos ? 'role="button" tabindex="0" title="Clique para ver os pratos"' : '' ?>>
                                <span class="relatorio-tabela-nome motivo-nome">
                                    <i class="bi <?= $icone ?> motivo-icone"></i>
                                    <?= htmlspecialchars($label) ?>
                                </span>
                                <span class="motivo-barra-wrap">
                                    <span class="motivo-barra" style="width:<?= $largura ?>%"></span>
                                </span>
                                <span class="relatorio-tabela-qtd motivo-contagem">
                                    <?= $m['total'] ?>×
                                    <?php if ($temPratos): ?>
                                        <i class="bi bi-chevron-down motivo-chevron" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($temPratos): ?>
                                <div class="motivo-detalhes" style="display: none;">
                                    <div class="motivo-detalhes-conteudo">
                                        <span class="motivo-detalhes-label">Pratos reportados nesta categoria:</span>
                                        <div class="motivo-pratos-chips">
                                            <?php foreach ($pratosComData as $chave => $qtdPrato):
                                                [$nomePrato, $dataPrato] = explode('|', $chave, 2);
                                                // Formata data YYYY-MM-DD → dd/mm
                                                $dataFormatada = '';
                                                if ($dataPrato) {
                                                    $ts = strtotime($dataPrato);
                                                    $dataFormatada = $ts ? date('d/m', $ts) : $dataPrato;
                                                }
                                                ?>
                                                <span class="motivo-prato-chip">
                                                    <?= htmlspecialchars($nomePrato) ?>
                                                    <?php if ($dataFormatada): ?>
                                                        <span class="motivo-chip-data"><?= htmlspecialchars($dataFormatada) ?></span>
                                                    <?php endif; ?>
                                                    <span class="motivo-chip-qtd"><?= $qtdPrato ?>×</span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <?php if (!empty($vendasDiarias)): ?>
        <script>
            (function () {
                const labels = <?= json_encode($graficoLabels) ?>;
                const vendas = <?= json_encode($graficoVendas) ?>;
                const pedidos = <?= json_encode($graficoPedidos) ?>;

                const ctx = document.getElementById('graficoVendas');
                if (!ctx) return;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Vendas (€)',
                                data: vendas,
                                backgroundColor: 'rgba(26, 58, 99, 0.75)',
                                borderColor: '#1a3a63',
                                borderWidth: 0,
                                borderRadius: 5,
                                borderSkipped: false,
                                barPercentage: 0.5,
                                categoryPercentage: 0.6,
                                yAxisID: 'yVendas',
                            },
                            {
                                label: 'Pedidos',
                                data: pedidos,
                                type: 'line',
                                borderColor: '#73c5d8',
                                backgroundColor: 'rgba(115, 197, 216, 0.08)',
                                pointBackgroundColor: '#73c5d8',
                                pointRadius: 3,
                                tension: 0.35,
                                fill: true,
                                yAxisID: 'yPedidos',
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 12 }, boxWidth: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ctx.dataset.label === 'Vendas (€)'
                                        ? ` ${ctx.raw.toFixed(2).replace('.', ',')}€`
                                        : ` ${ctx.raw} pedido(s)`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11, family: 'Plus Jakarta Sans, sans-serif' } }
                            },
                            yVendas: {
                                position: 'left',
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    font: { size: 11, family: 'Plus Jakarta Sans, sans-serif' },
                                    callback: (v) => v.toFixed(0) + '€'
                                }
                            },
                            yPedidos: {
                                position: 'right',
                                grid: { display: false },
                                ticks: {
                                    font: { size: 11, family: 'Plus Jakarta Sans, sans-serif' },
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            })();
        </script>
    <?php endif; ?>

    <script>
        // Interação de expansão dos motivos de reclamação
        document.querySelectorAll('.motivo-expansivel .motivo-linha').forEach(function (linha) {
            function alternarDetalhes() {
                const item = linha.closest('.motivo-item');
                if (!item) return;
                const detalhes = item.querySelector('.motivo-detalhes');
                if (!detalhes) return;

                const expandido = item.classList.toggle('is-expanded');
                detalhes.style.display = expandido ? 'block' : 'none';
                linha.setAttribute('aria-expanded', expandido ? 'true' : 'false');

                const chevron = item.querySelector('.motivo-chevron');
                if (chevron) {
                    chevron.classList.toggle('bi-chevron-down', !expandido);
                    chevron.classList.toggle('bi-chevron-up', expandido);
                }
            }

            linha.addEventListener('click', alternarDetalhes);
            linha.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    alternarDetalhes();
                }
            });
        });
    </script>

</body>

</html>