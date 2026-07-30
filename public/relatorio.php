<?php
/**
 * Página de relatório mensal.
 *
 * Apresenta um resumo estatístico das vendas da cantina
 * para o mês selecionado, incluindo indicadores gerais,
 * vendas por tipo de refeição e vendas diárias.
 */

session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';
require_once __DIR__ . '/../src/Support/Assets.php';

$utilizador = exigirLogin('funcionario');
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

$mesesNomes = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
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
 * Separa as vendas entre pratos da ementa e pratos extra.
 */
$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => !str_starts_with($t['RTP_NOME'], 'Extra: ')));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => str_starts_with($t['RTP_NOME'], 'Extra: ')));
$totalExtras = array_sum(array_column($extrasVendas, 'total'));
$qtdExtras = array_sum(array_column($extrasVendas, 'quantidade'));

$LIMITE_LINHAS = 10;
$pratosEmentaMostrar = array_slice($pratosEmenta, 0, $LIMITE_LINHAS);
$extrasMostrar = array_slice($extrasVendas, 0, $LIMITE_LINHAS);

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
</head>
<body>

<div id="bodycontainer">
<!-- Cabeçalho da aplicação -->
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

<main class="relatorio-main">
    <div class="relatorio-header-acoes">
        <h1 class="relatorio-titulo">relatório mensal</h1>
        <div class="relatorio-acoes-grupo">
            <a href="api/exportar_relatorio.php?mes=<?= htmlspecialchars($anoMes) ?>" class="btn-exportar-csv">
                <i class="bi bi-filetype-csv"></i> CSV
            </a>
            <a href="api/exportar_relatorio_pdf.php?mes=<?= htmlspecialchars($anoMes) ?>" class="btn-exportar-pdf">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </a>
        </div>
    </div>

    <!-- Seleção do mês do relatório -->
    <?php
    $mesAnteriorStr = date('Y-m', strtotime($anoMes . '-01 -1 month'));
    $mesSeguinteStr = date('Y-m', strtotime($anoMes . '-01 +1 month'));
    $temMesSeguinte = $mesSeguinteStr <= date('Y-m'); // não permite ir além do mês atual
    ?>
    
    <div class="relatorio-seletor-mes">
        <a href="?mes=<?= $mesAnteriorStr ?>" class="btn-nav-mes" title="Mês anterior">
            <i class="bi bi-chevron-left"></i>
        </a>
        <form method="get" style="display:contents;">
            <label for="mes">Mês</label>
            <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($anoMes) ?>" max="<?= date('Y-m') ?>"
                   onchange="this.form.submit()">
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

    <!-- Vendas por tipo de refeição da ementa -->
    <h2 class="relatorio-secao-titulo">vendas por tipo — ementa</h2>
    <?php if (empty($pratosEmenta)): ?>
    <p class="relatorio-vazio"><i class="bi bi-inbox"></i> Sem vendas de pratos da ementa neste mês.</p>
    <?php else: ?>
    <div class="relatorio-tabela">
        <?php foreach ($pratosEmenta as $i => $t): ?>
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
        <span class="relatorio-subtotal">(total: <?= number_format($totalExtras, 2, ',', '.') ?>€, <?= $qtdExtras ?>x)</span>
        <?php endif; ?>
    </h2>
    <?php if (empty($extrasVendas)): ?>
    <p class="relatorio-vazio"><i class="bi bi-inbox"></i> Sem vendas de extras neste mês.</p>
    <?php else: ?>
    <div class="relatorio-tabela">
        <?php foreach ($extrasVendas as $i => $t): ?>
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

    <!-- Resumo diário de vendas -->
    <h2 class="relatorio-secao-titulo">vendas diárias</h2>
    <?php if (empty($vendasDiarias)): ?>
    <p class="relatorio-vazio"><i class="bi bi-inbox"></i> Sem vendas registadas neste mês.</p>
    <?php else: ?>
    <div class="relatorio-tabela">
        <?php foreach ($vendasDiarias as $d): ?>
        <div class="relatorio-tabela-linha">
            <span class="relatorio-tabela-nome"><?= date('d/m/Y', strtotime($d['RP_DATA_REFEICAO'])) ?></span>
            <span class="relatorio-tabela-qtd"><?= $d['total_pedidos'] ?> pedido(s)</span>
            <span class="relatorio-tabela-total"><?= number_format((float) $d['total_vendido'], 2, ',', '.') ?>€</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Avaliações dos alunos -->
    <h2 class="relatorio-secao-titulo">avaliações dos alunos</h2>

    <?php if ($mediaAvaliacoes['total'] === 0): ?>
    <p class="relatorio-vazio"><i class="bi bi-star"></i> Sem avaliações registadas neste mês.</p>
    <?php else: ?>
    <div class="avaliacao-resumo-card">
        <div class="avaliacao-media-estrelas">
            <?= str_repeat('★', round($mediaAvaliacoes['media'])) . str_repeat('☆', 5 - round($mediaAvaliacoes['media'])) ?>
        </div>
        <div class="avaliacao-media-numero"><?= number_format($mediaAvaliacoes['media'], 1, ',', '.') ?> / 5</div>
        <div class="avaliacao-media-total"><?= $mediaAvaliacoes['total'] ?> pessoa(s) avaliaram</div>
    </div>
    <?php endif; ?>
</main>
</div>

</body>
</html>