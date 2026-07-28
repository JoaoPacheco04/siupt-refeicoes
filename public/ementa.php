<?php
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';


$utilizador = exigirLogin(); 

$hoje = new DateTime();
$diaSemanaHoje = (int) $hoje->format('N');
$segunda = (clone $hoje)->modify('-' . ($diaSemanaHoje - 1) . ' days');
$sexta   = (clone $segunda)->modify('+4 days');

$pratos = Database::listarPratosEmentaSemana($segunda->format('Y-m-d'), $sexta->format('Y-m-d'));

$tipoIdsParaLimites = array_unique(array_column($pratos, 'RM_TP_ID'));
$dataLimitesBatch = Database::obterDataLimitesBatch($tipoIdsParaLimites);

// Se a semana atual não tem nada disponível para comprar (vazia, ou tudo já
// fora de prazo de compra), avança automaticamente para a semana seguinte
function todosPratosForaDePrazo(array $pratos, array $limitesBatch): bool {
    if (empty($pratos)) return true;
    foreach ($pratos as $p) {
        if (!Database::foraDePrazoBatch((int) $p['RM_TP_ID'], $p['RM_DATA'], $limitesBatch)) {
            return false;
        }
    }
    return true;
}

$semanaAvancada = false;
if (todosPratosForaDePrazo($pratos, $dataLimitesBatch)) {
    $semanaAvancada = true;
    $segunda->modify('+7 days');
    $sexta->modify('+7 days');
    $pratos = Database::listarPratosEmentaSemana($segunda->format('Y-m-d'), $sexta->format('Y-m-d'));
    // Recarregar limites para a nova semana (tipos podem ser diferentes)
    $tipoIdsParaLimites = array_unique(array_column($pratos, 'RM_TP_ID'));
    $dataLimitesBatch = Database::obterDataLimitesBatch($tipoIdsParaLimites);
}

// Ícones por tipo de prato principal
$iconePrato = [
    'Carne'       => '🥩',
    'Peixe'       => '🐟',
    'Vegetariano' => '🌿',
];

$extras = Database::listarPratosExtras();
$tipoMenuCompletoId = Database::obterTipoIdPorNome('Menu Completo');
$prazotexto = Database::obterDataLimitePrincipalTexto() ?? '14h30 do dia anterior';

// ── Batch de preços ───────────────────────────────────────────
$tipoIdsSemana = array_unique(array_column($pratos, 'RM_TP_ID'));
$tipoIdsExtras = array_unique(array_column($extras, 'RM_TP_ID'));
$tipoIdsTodos  = array_unique(array_merge(
    $tipoIdsSemana,
    $tipoIdsExtras,
    $tipoMenuCompletoId !== null ? [$tipoMenuCompletoId] : []
));
$hojeStr = date('Y-m-d');
$precosBatch = Database::obterPrecosVigentesBatch($tipoIdsTodos, $hojeStr);
$limitesBatch = Database::obterDataLimitesBatch($tipoIdsTodos);

// ── Agrupar pratos por dia e por tipo ────────────────────────────────────
$diasEmenta = [];
foreach ($pratos as $p) {
    $data = $p['RM_DATA'];
    $tipo = $p['RTP_NOME'];
    $diasEmenta[$data][$tipo][] = [
        'rm_id' => $p['RM_ID'],
        'nome'  => $p['RM_NOME'],
        'preco' => $precosBatch[(int) $p['RM_TP_ID']] ?? null,
        'tp_id' => (int) $p['RM_TP_ID'],
    ];
}
ksort($diasEmenta);

$precosMenuCompleto = [];
if ($tipoMenuCompletoId !== null) {
    $precoMCBase = $precosBatch[$tipoMenuCompletoId] ?? null;
    foreach (array_keys($diasEmenta) as $data) {
        $precosMenuCompleto[$data] = $precoMCBase;
    }
}

// ── Datas com pedido já ativo (aviso de duplicado — pratos principais) ───
$datasEmenta = array_keys($diasEmenta);
$datasComPedido = array_flip(
    Database::listarDatasComPedidoAtivo((int) $utilizador['id'], $datasEmenta)
);

// ── Próximos dias úteis para os extras (sem fim de semana) ───────────────
// Se já passou o horário de corte de hoje, "hoje" já não é opção válida
$hojeBloqueadoExtras = defined('EXTRA_HORA_LIMITE_HOJE') && date('H:i:s') > EXTRA_HORA_LIMITE_HOJE;
$diasUteisExtras = [];
$cursor = new DateTime();
if ($hojeBloqueadoExtras) {
    $cursor->modify('+1 day'); // pula "hoje" — já passou o horário de corte
}
while (count($diasUteisExtras) < 5) {
    if ((int) $cursor->format('N') <= 5) { // 1=Seg … 5=Sex
        $diasUteisExtras[] = $cursor->format('Y-m-d');
    }
    $cursor->modify('+1 day');
}

// Itens extra já comprados — tem de correr DEPOIS de $diasUteisExtras estar preenchido
$itensExtrasComprados = Database::listarItensExtrasComprados((int) $utilizador['id'], $diasUteisExtras);

$numerosDia = [1 => '2ª', 2 => '3ª', 3 => '4ª', 4 => '5ª', 5 => '6ª'];
$nomesCompletoDia = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];
$meses = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',
          7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
$nomeMes = $meses[(int) $sexta->format('n')];
$amanhaStr = date('Y-m-d', strtotime('+1 day'));
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Ementa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/modal.css" rel="stylesheet">
    <link href="assets/css/ementa.css" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">
<header>
    <a id="home" href="ementa.php" title="Voltar à página principal">
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

<main class="ementa-main container" style="padding-bottom:130px; max-width:900px;">

    <div class="ementa-cabecalho">
        <h1 class="ementa-titulo">ementa</h1>
        <p class="ementa-horario">Prazo de compra: <?= htmlspecialchars($prazotexto) ?></p>
    </div>

    <?php if ($semanaAvancada): ?>
    <div class="banner-semana-avancada" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        A semana atual já está fora de prazo. A mostrar a ementa da <strong>próxima semana</strong>.
    </div>
    <?php endif; ?>

    <h2 class="ementa-semana">semana de <?= $segunda->format('d') ?> a <?= $sexta->format('d') ?> de <?= $nomeMes ?></h2>

    <?php if (empty($diasEmenta)): ?>
        <p class="text-muted">Não há ementa disponível para esta semana.</p>
    <?php endif; ?>

    <?php foreach ($diasEmenta as $data => $tiposDoDia):
        $numDia = $numerosDia[(int) date('N', strtotime($data))];

        $pratosPrincipais = [];
        foreach (['Carne', 'Peixe', 'Vegetariano'] as $tipoPrincipal) {
            if (!empty($tiposDoDia[$tipoPrincipal])) {
                $pratosPrincipais[$tipoPrincipal] = $tiposDoDia[$tipoPrincipal][0];
            }
        }

        $diaBloqueado = $data < $hojeStr;
        if (!$diaBloqueado && !empty($pratosPrincipais)) {
            $primeiroPrato = reset($pratosPrincipais);
            $diaBloqueado = Database::foraDePrazoBatch($primeiroPrato['tp_id'], $data, $limitesBatch);
        }

        $componentesExtra = [];
        foreach (['Sopa', 'Sobremesa', 'Bebida'] as $tipoComponente) {
            if (!empty($tiposDoDia[$tipoComponente])) {
                $componentesExtra[$tipoComponente] = $tiposDoDia[$tipoComponente][0];
            }
        }
    ?>
    <?php $jaComprado = isset($datasComPedido[$data]); ?>
    <div class="dia-card<?= $diaBloqueado ? ' dia-passado' : ($data === $hojeStr ? ' dia-hoje' : '') ?><?= $jaComprado ? ' dia-ja-comprado' : '' ?>" data-data="<?= $data ?>">
        <div class="dia-card-header">
            <span class="dia-abrev"><?= $numDia ?></span>
            <span class="dia-data"><?= date('d/m', strtotime($data)) ?></span>
            <?php if ($diaBloqueado): ?>
            <span class="dia-passado-badge">fora de prazo</span>
            <?php elseif ($jaComprado): ?>
            <span class="dia-comprado-badge"><i class="bi bi-check-circle-fill"></i> já comprado</span>
            <?php elseif ($data === $hojeStr): ?>
            <span class="dia-hoje-badge">hoje</span>
            <?php endif; ?>
        </div>

        <?php if ($jaComprado): ?>
<p class="aviso-duplicado">
    Já tens um pedido ativo para este dia.
<a href="historico.php" style="pointer-events: auto; position: relative; z-index: 999;">Ver as minhas compras →</a></p>
<?php endif; ?>

        <div class="dia-pratos-principais">
            <?php foreach ($pratosPrincipais as $tipoNome => $prato): ?>
                <label class="prato-opcao">
                    <input type="radio" name="prato_<?= $data ?>" class="radio-prato-principal"
                           data-rm-id="<?= $prato['rm_id'] ?>"
                           data-preco="<?= $prato['preco'] ?>"
                           data-nome="<?= htmlspecialchars($tipoNome . ' — ' . $prato['nome']) ?>"
                           <?= ($diaBloqueado || $jaComprado) ? 'disabled' : '' ?>>
                    <span class="prato-tipo-icone"><?= $iconePrato[$tipoNome] ?? '' ?></span>
                    <span class="prato-opcao-label">
                        <strong><?= htmlspecialchars($tipoNome) ?></strong>
                        <small><?= htmlspecialchars($prato['nome']) ?></small>
                        <span class="prato-opcao-preco"><?= number_format($prato['preco'], 2, ',', '') ?>€</span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php
        $precoMC = $precosMenuCompleto[$data] ?? null;
        if ($precoMC !== null && !$jaComprado && !$diaBloqueado): ?>
        <label class="menu-completo-toggle">
            <input type="checkbox" class="checkbox-menu-completo"
                   data-preco-mc="<?= $precoMC ?>">
            Menu completo (sopa + sobremesa + bebida incluídas) — <?= number_format($precoMC, 2, ',', '') ?>€
        </label>
        <?php endif; ?>

        <?php if (!empty($componentesExtra) && !$jaComprado && !$diaBloqueado): ?>
        <div class="dia-componentes-wrap">
            <p class="componentes-hint"><i class="bi bi-hand-index"></i> Seleciona um prato para adicionar extras</p>
            <div class="dia-componentes">
                <?php foreach ($componentesExtra as $tipoNome => $comp): ?>
                <label class="componente-opcao">
                    <input type="checkbox" class="checkbox-componente"
                           data-rm-id="<?= $comp['rm_id'] ?>"
                           data-preco="<?= $comp['preco'] ?>"
                           data-nome="<?= htmlspecialchars($tipoNome . ' — ' . $comp['nome']) ?>">
                    <span class="nome-extra"><?= htmlspecialchars($tipoNome) ?></span>
                    <span class="preco-extra"><?= number_format($comp['preco'], 2, ',', '') ?>€</span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($extras)): ?>
    <div class="extras-secao">
        <h2 class="ementa-semana">pratos extras</h2>
        <p class="text-muted small">Disponíveis todos os dias, sem prazo de compra.</p>

        <div class="extras-data-escolha">
            <label for="dataExtras">Para quando?</label>
            <select id="dataExtras">
                <?php foreach ($diasUteisExtras as $i => $diaUtilStr):
                    $ndExtra = $nomesCompletoDia[(int) (new DateTime($diaUtilStr))->format('N')];
                    if ($diaUtilStr === $hojeStr)       $label = 'Hoje — ' . $ndExtra . ', ' . date('d', strtotime($diaUtilStr)) . ' ' . $meses[(int)(new DateTime($diaUtilStr))->format('n')];
                    elseif ($diaUtilStr === $amanhaStr) $label = 'Amanhã — ' . $ndExtra . ', ' . date('d', strtotime($diaUtilStr)) . ' ' . $meses[(int)(new DateTime($diaUtilStr))->format('n')];
                    else                                $label = $ndExtra . ', ' . date('d', strtotime($diaUtilStr)) . ' ' . $meses[(int)(new DateTime($diaUtilStr))->format('n')];
                ?>
                <option value="<?= $diaUtilStr ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="extras-lista">
            <?php foreach ($extras as $e):
                $precoExtra = $precosBatch[(int) $e['RM_TP_ID']] ?? 0;
            ?>
            <label class="componente-opcao">
                <input type="checkbox" class="checkbox-extra"
                       data-rm-id="<?= $e['RM_ID'] ?>"
                       data-preco="<?= $precoExtra ?>"
                       data-nome="<?= htmlspecialchars($e['RM_NOME']) ?>">
                <span class="nome-extra"><?= htmlspecialchars($e['RM_NOME']) ?></span>
                <span class="preco-extra"><?= number_format($precoExtra, 2, ',', '') ?>€</span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</main>
</div>

<div class="resumo-fixo">
    <div class="resumo-info">
        <div class="resumo-count">
            <span id="totalSelecionadas">0</span>
            <small>itens</small>
        </div>
        <div class="resumo-divider"></div>
        <div class="resumo-valor">
            <strong id="totalValor">0,00€</strong>
            <small>total</small>
        </div>
    </div>
    <button id="btnComprar" class="btn-comprar" disabled type="button">
        <i class="bi bi-bag-check-fill"></i>
        <span>Confirmar compra</span>
    </button>
</div>

<script>
    window.extrasJaComprados = <?= json_encode($itensExtrasComprados) ?>;
    window.CSRF_TOKEN = '<?= gerarCsrfToken() ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.js"></script>
<script src="assets/js/vendor/qrcode.min.js"></script>
<script src="assets/js/ementa.js"></script>
</body>
</html>