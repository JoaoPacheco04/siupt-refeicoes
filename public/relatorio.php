<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');

$anoMes = $_GET['mes'] ?? date('Y-m');

// Valida formato YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
    $anoMes = date('Y-m');
}

$resumo = Database::obterResumoMensal($anoMes);
$vendasPorTipo = Database::obterVendasPorTipoMensal($anoMes);
$vendasDiarias = Database::obterVendasDiariasMensal($anoMes);

$mesesNomes = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
];
[$anoSelecionado, $mesSelecionado] = explode('-', $anoMes);
$nomeMesSelecionado = $mesesNomes[$mesSelecionado] ?? $mesSelecionado;

// Comparação com o mês anterior
$diferencaMes = $resumo['total_vendido'] - $resumo['total_vendido_mes_anterior'];
$percentagemMes = $resumo['total_vendido_mes_anterior'] > 0
    ? ($diferencaMes / $resumo['total_vendido_mes_anterior']) * 100
    : null;

// Separa pratos da ementa normal dos extras
$pratosEmenta = array_values(array_filter($vendasPorTipo, fn($t) => !str_starts_with($t['RTP_NOME'], 'Extra: ')));
$extrasVendas = array_values(array_filter($vendasPorTipo, fn($t) => str_starts_with($t['RTP_NOME'], 'Extra: ')));
$totalExtras = array_sum(array_column($extrasVendas, 'total'));
$qtdExtras = array_sum(array_column($extrasVendas, 'quantidade'));
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
    <link href="assets/css/relatorio.css" rel="stylesheet">
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

<main class="relatorio-main">
    <div class="relatorio-header-acoes">
        <h1 class="relatorio-titulo">relatório mensal</h1>
        <button onclick="window.print()" class="btn-exportar-pdf" id="btnExportarPdf">
            <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
        </button>
    </div>

    <form method="get" class="relatorio-seletor-mes">
        <label for="mes">Mês</label>
        <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($anoMes) ?>" max="<?= date('Y-m') ?>">
        <button type="submit" class="btn-aplicar-mes">
            <i class="bi bi-arrow-repeat"></i> Aplicar
        </button>
    </form>

    <h2 class="relatorio-subtitulo"><?= htmlspecialchars($nomeMesSelecionado . ' ' . $anoSelecionado) ?></h2>

    <!-- Cartões de resumo -->
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
    </div>

    <!-- Vendas por tipo — ementa -->
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

    <!-- Vendas por tipo — extras -->
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

    <!-- Vendas diárias -->
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
</main>
</div>

</body>
</html>