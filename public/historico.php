<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin(); // qualquer utilizador autenticado

$pedidos = Database::listarPedidosDoUtilizador($utilizador['id']);

// Batch: buscar todas as linhas de todos os pedidos numa só query
$pedidoIds = array_column($pedidos, 'RP_ID');
$todasLinhas = Database::listarLinhasDePedidos($pedidoIds);
foreach ($pedidos as &$p) {
    $p['linhas'] = $todasLinhas[(int) $p['RP_ID']] ?? [];
}
unset($p);

// Ordenar: ativos primeiro (data ascendente — mais urgente no topo),
// depois os restantes por data descendente
usort($pedidos, function($a, $b) {
    $ordemEstado = ['nao_pago' => 0, 'ativo' => 1, 'utilizado' => 2, 'vencido' => 3];
    $oA = $ordemEstado[$a['estado']] ?? 4;
    $oB = $ordemEstado[$b['estado']] ?? 4;
    if ($oA !== $oB) return $oA - $oB;
    return $oA <= 1
        ? strcmp($a['RP_DATA_REFEICAO'], $b['RP_DATA_REFEICAO'])
        : strcmp($b['RP_DATA_REFEICAO'], $a['RP_DATA_REFEICAO']);
});

$estados = [
    'nao_pago'  => ['label' => 'Pagamento pendente', 'class' => 'estado-nao-pago'],
    'ativo'     => ['label' => 'Ativo',     'class' => 'estado-ativo'],
    'utilizado' => ['label' => 'Levantado', 'class' => 'estado-utilizado'],
    'vencido'   => ['label' => 'Vencido',   'class' => 'estado-vencido'],
];

$numerosDia = [1 => '2ª', 2 => '3ª', 3 => '4ª', 4 => '5ª', 5 => '6ª', 6 => 'Sáb', 7 => 'Dom'];

// Contagens para os filtros
$contagens = ['todos' => count($pedidos), 'nao_pago' => 0, 'ativo' => 0, 'utilizado' => 0, 'vencido' => 0];
foreach ($pedidos as $p) {
    if (isset($contagens[$p['estado']])) $contagens[$p['estado']]++;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - As minhas compras</title>
    <meta name="description" content="Consulte os seus pedidos de refeição: ativos, levantados e vencidos.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/modal.css" rel="stylesheet">
    <link href="assets/css/historico.css" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">
<header>
    <a id="home" href="ementa.php" title="Voltar à ementa">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>
    <nav>
        <ul id="mainmenu">
            <li id="menu_id_10" class=""><a href="#">Portais</a></li>
            <li id="menu_id_5"  class=""><a href="#">Ingresso</a></li>
            <li id="menu_id_7"  class=""><a href="#">Estudante</a></li>
            <li id="menu_id_8"  class="selected"><a href="ementa.php">Suporte</a></li>
            <li id="menu_id_16" class=""><a href="#">Decisão</a></li>
        </ul>
    </nav>
    <a href="historico.php" class="nav-icon-link" title="As minhas compras">
        <i class="bi bi-clock-history"></i>
    </a>
    <form id="form_new_user_lang" method="post" action="#">
        <label for="new_user_lang"></label>
        <select id="new_user_lang" name="new_user_lang">
            <option value="en">Inglês</option>
            <option value="pt" selected>Português</option>
        </select>
    </form>
    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">
        <a id="quit" href="login.php?logout=1" title="Terminar sessão">&nbsp;</a>
        <div id="profile-photo" class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($utilizador['nome'], 0, 1))) ?>
        </div>
    </div>
</header>

<main class="historico-main">
    <h1 class="historico-titulo">as minhas compras</h1>

    <!-- Filtros -->
    <?php if (!empty($pedidos)): ?>
    <div class="historico-filtros" role="group" aria-label="Filtrar pedidos">
        <button class="btn-filtro ativo-filtro" data-filtro="todos" id="filtro-todos">
            Todos <span class="filtro-conta"><?= $contagens['todos'] ?></span>
        </button>
        <button class="btn-filtro<?= $contagens['nao_pago'] === 0 ? ' filtro-vazio' : '' ?>" data-filtro="nao_pago" id="filtro-nao_pago">
            Pendentes <span class="filtro-conta"><?= $contagens['nao_pago'] ?></span>
        </button>
        <button class="btn-filtro<?= $contagens['ativo'] === 0 ? ' filtro-vazio' : '' ?>" data-filtro="ativo" id="filtro-ativo">
            Ativos <span class="filtro-conta"><?= $contagens['ativo'] ?></span>
        </button>
        <button class="btn-filtro<?= $contagens['utilizado'] === 0 ? ' filtro-vazio' : '' ?>" data-filtro="utilizado" id="filtro-utilizado">
            Levantados <span class="filtro-conta"><?= $contagens['utilizado'] ?></span>
        </button>
        <button class="btn-filtro<?= $contagens['vencido'] === 0 ? ' filtro-vazio' : '' ?>" data-filtro="vencido" id="filtro-vencido">
            Vencidos <span class="filtro-conta"><?= $contagens['vencido'] ?></span>
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($pedidos)): ?>
        <p class="historico-vazio">
            <i class="bi bi-inbox"></i>
            Ainda não fizeste nenhuma compra.
        </p>
    <?php else: ?>
        <?php foreach ($pedidos as $p):
            $estado  = $p['estado'];
            $info    = $estados[$estado] ?? ['label' => $estado, 'class' => 'estado-vencido'];
            $numDia  = $numerosDia[(int) date('N', strtotime($p['RP_DATA_REFEICAO']))] ?? '?';
            $linhas  = $p['linhas'];

            // Construir descrição resumida dos itens
            $nomesItens = array_map(function($l) {
                $prefixo = $l['RC_MENU_COMPLETO'] ? 'Menu completo — ' : '';
                return $prefixo . $l['RM_NOME'];
            }, $linhas);
            $descricao = implode(', ', $nomesItens);
        ?>
        <div class="compra-card" data-id="<?= $p['RP_ID'] ?>" data-estado="<?= $estado ?>">
            <div class="compra-info">
                <div class="compra-dia-badge<?= $p['RP_DATA_REFEICAO'] === date('Y-m-d') ? ' dia-badge-hoje' : '' ?>">
                    <span class="dia-abrev"><?= $numDia ?></span>
                    <span class="dia-data"><?= date('d/m', strtotime($p['RP_DATA_REFEICAO'])) ?></span>
                    <?php if ($p['RP_DATA_REFEICAO'] === date('Y-m-d')): ?>
                    <span class="dia-hoje-mini">hoje</span>
                    <?php endif; ?>
                </div>
                <div class="compra-detalhe">
                    <div class="compra-prato"><?= htmlspecialchars($descricao ?: 'Sem itens registados') ?></div>
                    <div class="compra-preco"><?= number_format((float)$p['RP_PRECO_TOTAL'], 2, ',', '') ?>€</div>
                </div>
            </div>
            <div class="compra-lado-direito">
                <?php if ($estado === 'ativo'): ?>
                   <button class="btn-ver-qr"
        id="btn-qr-<?= $p['RP_ID'] ?>"
        data-qrcode="<?= htmlspecialchars($p['RP_QRCODE']) ?>"
        data-codigo-curto="<?= htmlspecialchars($p['RP_CODIGO_CURTO'] ?? '') ?>"
        data-data="<?= date('d/m/Y', strtotime($p['RP_DATA_REFEICAO'])) ?>"
        data-descricao="<?= htmlspecialchars($descricao) ?>"
        title="Ver QR code">
    <i class="bi bi-qr-code"></i>
    QR code
</button>
                <<?php elseif ($estado === 'nao_pago'): ?>
    <button class="btn-pagar-agora" data-pedido-id="<?= $p['RP_ID'] ?>">
        <i class="bi bi-credit-card"></i> Pagar agora
    </button>
<?php endif; ?>
                <span class="estado-badge <?= $info['class'] ?>"><?= $info['label'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>

        <p class="historico-sem-filtro" style="display:none;">
            <i class="bi bi-funnel"></i> Nenhum pedido nesta categoria.
        </p>
    <?php endif; ?>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.js"></script>
<script src="assets/js/vendor/qrcode.min.js"></script>
<script src="assets/js/historico.js"></script>
</body>
</html>