<?php
/**
 * Página da ementa semanal.
 *
 * Apresenta a ementa da semana corrente (ou da próxima, se a atual
 * já não tiver pratos disponíveis), permitindo ao utilizador selecionar
 * refeições e extras para compra.
 */

// Inclui os ficheiros de suporte e infraestrutura necessários.
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

// Garante que apenas utilizadores autenticados podem aceder.
$utilizador = exigirLogin();

// Calcula as datas de início e fim da semana corrente.
$hoje = new DateTime();
$diaSemanaHoje = (int) $hoje->format('N');
$segunda = (clone $hoje)->modify('-' . ($diaSemanaHoje - 1) . ' days');
$sexta   = (clone $segunda)->modify('+4 days');

// Obtém os pratos da ementa para a semana calculada.
$pratos = Database::listarPratosEmentaSemana($segunda->format('Y-m-d'), $sexta->format('Y-m-d'));

// Carrega em lote os limites de compra para os tipos de prato da semana.
$tipoIdsParaLimites = array_unique(array_column($pratos, 'RM_TP_ID'));
$dataLimitesBatch = Database::obterDataLimitesBatch($tipoIdsParaLimites);

/**
 * Verifica se todos os pratos de uma lista já estão fora do prazo de compra.
 */
function todosPratosForaDePrazo(array $pratos, array $limitesBatch): bool {
    if (empty($pratos)) return true;
    foreach ($pratos as $p) {
        if (!Database::foraDePrazoBatch((int) $p['RM_TP_ID'], $p['RM_DATA'], $limitesBatch)) {
            return false;
        }
    }
    return true;
}

/**
 * Se a semana atual não tem pratos disponíveis ou todos estão fora de prazo,
 * avança automaticamente para a semana seguinte.
 */
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

// Configuração dos ícones para cada tipo de prato principal.
$iconePrato = [
    'Carne'       => '🥩',
    'Peixe'       => '🐟',
    'Vegetariano' => '🌿',
];

// Carrega os pratos extras, o ID do tipo "Menu Completo" e o texto do prazo.
$extras = Database::listarPratosExtras();
$tipoMenuCompletoId = Database::obterTipoIdPorNome('Menu Completo');
$prazotexto = Database::obterDataLimitePrincipalTexto() ?? '14h30 do dia anterior';

// Carrega todos os preços e limites de uma só vez para evitar múltiplas queries (N+1).
$tipoIdsSemana = array_unique(array_column($pratos, 'RM_TP_ID'));
$tipoIdsExtras = array_unique(array_column($extras, 'RM_TP_ID'));
// Junta todos os IDs de tipo de refeição (semana, extras, menu completo).
$tipoIdsTodos  = array_unique(array_merge(
    $tipoIdsSemana,
    $tipoIdsExtras,
    $tipoMenuCompletoId !== null ? [$tipoMenuCompletoId] : []
));
$hojeStr = date('Y-m-d');
$precosBatch = Database::obterPrecosVigentesBatch($tipoIdsTodos, $hojeStr);
$limitesBatch = Database::obterDataLimitesBatch($tipoIdsTodos);

// Agrupa os pratos da ementa por data e, dentro de cada data, por tipo.
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

// Define o preço do menu completo para cada dia da ementa.
$precosMenuCompleto = [];
if ($tipoMenuCompletoId !== null) {
    $precoMCBase = $precosBatch[$tipoMenuCompletoId] ?? null;
    foreach (array_keys($diasEmenta) as $data) {
        $precosMenuCompleto[$data] = $precoMCBase;
    }
}

// Identifica as datas para as quais o utilizador já tem um pedido ativo.
$datasEmenta = array_keys($diasEmenta);
$datasComPedido = array_flip(
    Database::listarDatasComPedidoAtivo((int) $utilizador['id'], $datasEmenta)
);

/**
 * Calcula os próximos 5 dias úteis para a compra de extras.
 * Se o horário de corte para o dia de hoje já passou, começa a contar
 * a partir de amanhã.
 */
$hojeBloqueadoExtras = date('H:i:s') > '10:00:00';
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

// Verifica quais itens extra o utilizador já comprou para os dias disponíveis.
$itensExtrasComprados = Database::listarItensExtrasComprados((int) $utilizador['id'], $diasUteisExtras);

// Filtra os extras que não têm um preço definido para não os mostrar na interface.
$extrasComPreco = [];
foreach ($extras as $e) {
    $preco = $precosBatch[(int) $e['RM_TP_ID']] ?? null;
    if ($preco === null) continue;
    $extrasComPreco[] = $e + ['preco' => $preco];
}

// ── Médias de avaliação por nome do prato (histórico) ────────────────────
$nomesParaAvaliar = [];
foreach ($diasEmenta as $tiposDoDia) {
    foreach ($tiposDoDia as $itens) {
        foreach ($itens as $item) {
            $nomesParaAvaliar[] = $item['nome'];
        }
    }
}
foreach ($extrasComPreco as $e) {
    $nomesParaAvaliar[] = $e['RM_NOME'];
}
$mediasAvaliacoes = Database::obterMediaAvaliacoesPorNomes($nomesParaAvaliar);

// ── Aviso de prazo a aproximar-se (só para o dia de hoje) ─────────────────
// P1 FIX: Constrói mapa nome→id a partir dos pratos já carregados (sem queries extra)
$mapaTipoNomeParaId = [];
foreach ($pratos as $p) {
    $mapaTipoNomeParaId[$p['RTP_NOME']] = (int) $p['RM_TP_ID'];
}
$avisoPrazo = null;
foreach (['Carne', 'Peixe', 'Vegetariano'] as $tipoPrincipal) {
    $tipoId = $mapaTipoNomeParaId[$tipoPrincipal] ?? null;
    if ($tipoId === null || !isset($limitesBatch[$tipoId])) continue;

    $limite = $limitesBatch[$tipoId];
    $dataLimiteHoje = date(
        'Y-m-d ' . $limite['RDL_HORA'],
        strtotime($hojeStr . ' -' . $limite['RDL_DIA_ANTECEDENCIA'] . ' days')
    );

    $agora = new DateTime();
    $limiteObj = new DateTime($dataLimiteHoje);
    $diffSegundos = $limiteObj->getTimestamp() - $agora->getTimestamp();

    // Só mostra se faltar entre 0 e 2 horas (não mostra se já passou, nem se falta muito)
    if ($diffSegundos > 0 && $diffSegundos <= 2 * 3600) {
        $horasRestantes = floor($diffSegundos / 3600);
        $minutosRestantes = floor(($diffSegundos % 3600) / 60);
        $avisoPrazo = [
            'horas' => $horasRestantes,
            'minutos' => $minutosRestantes,
            'hora_limite' => date('H:i', strtotime($limite['RDL_HORA'])),
        ];
        break; // já encontrámos o próximo prazo a expirar, não precisamos de continuar
    }
}
// Variáveis auxiliares para a apresentação da data na interface.
$numerosDia = [1 => '2ª', 2 => '3ª', 3 => '4ª', 4 => '5ª', 5 => '6ª'];
$nomesCompletoDia = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];
$meses = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',
          7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
$nomeMes = $meses[(int) $sexta->format('n')];
$amanhaStr = date('Y-m-d', strtotime('+1 day'));

$pedidosPorAvaliar = Database::contarPedidosPorAvaliar((int) $utilizador['id']);
$refeicoesPerdidas = Database::contarRefeicoesNaoLevantadasRecentes((int) $utilizador['id']);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Ementa</title>
    <meta name="description" content="Consulte e reserve a ementa semanal da cantina da Universidade Portucalense.">
    <meta name="robots" content="noindex">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/modal.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/ementa.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">
<header>
    <a id="home" href="ementa.php" title="Voltar à página principal">
        <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
    </a>
    <a href="historico.php" class="nav-icon-link" title="As minhas compras">
        <i class="bi bi-clock-history"></i>
    </a>
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

    <?php if ($pedidosPorAvaliar > 0): ?>
    <div class="banner-avaliar-pendente" id="bannerAvaliar" data-total="<?= $pedidosPorAvaliar ?>">
        <i class="bi bi-star"></i>
        Tens <?= $pedidosPorAvaliar ?> refeição(ões) por avaliar.
        <a href="historico.php">Avaliar agora →</a>
        <button type="button" class="btn-fechar-banner" id="btnFecharBanner" title="Fechar">
            <i class="bi bi-x"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($semanaAvancada): ?>
    <div class="banner-semana-avancada" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        A semana atual já está fora de prazo. A mostrar a ementa da <strong>próxima semana</strong>.
    </div>
    <?php endif; ?>

    <?php if ($refeicoesPerdidas > 0): ?>
    <div class="banner-nao-levantado" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i>
        Tens <?= $refeicoesPerdidas ?> refeição(ões) paga(s) que não foram levantadas a tempo.
        <a href="historico.php">Ver histórico →</a>
    </div>
    <?php endif; ?>

    <?php if ($avisoPrazo !== null): ?>
    <div class="banner-prazo-proximo" role="alert">
        <i class="bi bi-clock-fill"></i>
        <?php if ($avisoPrazo['horas'] > 0): ?>
        Faltam <?= $avisoPrazo['horas'] ?>h<?= $avisoPrazo['minutos'] > 0 ? $avisoPrazo['minutos'] . 'min' : '' ?> para o prazo de compra de hoje (<?= $avisoPrazo['hora_limite'] ?>).
        <?php else: ?>
        Faltam apenas <?= $avisoPrazo['minutos'] ?> minutos para o prazo de compra de hoje (<?= $avisoPrazo['hora_limite'] ?>)!
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <h2 class="ementa-semana">semana de <?= $segunda->format('d') ?> a <?= $sexta->format('d') ?> de <?= $nomeMes ?></h2>

    <?php if (empty($diasEmenta)): ?>
        <p class="text-muted">Não há ementa disponível para esta semana.</p>
    <?php endif; ?>

    <?php foreach ($diasEmenta as $data => $tiposDoDia):
        $numDia = $numerosDia[(int) date('N', strtotime($data))];

        // Pratos principais — ignora quem não tem preço configurado
        $pratosPrincipais = [];
        $pratosSemPreco = [];
        foreach (['Carne', 'Peixe', 'Vegetariano'] as $tipoPrincipal) {
            if (!empty($tiposDoDia[$tipoPrincipal])) {
                $prato = $tiposDoDia[$tipoPrincipal][0];
                if ($prato['preco'] === null) {
                    $pratosSemPreco[] = $tipoPrincipal;
                    continue;
                }
                $pratosPrincipais[$tipoPrincipal] = $prato;
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
            <a href="historico.php" style="pointer-events: auto; position: relative; z-index: 999;">Ver as minhas compras →</a>
        </p>
        <?php endif; ?>

        <?php if (!empty($pratosSemPreco)): ?>
        <p class="text-muted small">
            <i class="bi bi-exclamation-circle"></i>
            <?= htmlspecialchars(implode(', ', $pratosSemPreco)) ?> indisponível(is) — preço por configurar.
        </p>
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
                        <?php if (isset($mediasAvaliacoes[$prato['nome']])):
                            $av = $mediasAvaliacoes[$prato['nome']];
                        ?>
                        <span class="prato-avaliacao">
                            <?= str_repeat('★', round($av['media'])) . str_repeat('☆', 5 - round($av['media'])) ?>
                            <span class="prato-avaliacao-total">(<?= $av['total'] ?>)</span>
                        </span>
                        <?php endif; ?>
                        <span class="prato-opcao-preco"><?= number_format($prato['preco'], 2, ',', '') ?>€</span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php
$precoMC = $precosMenuCompleto[$data] ?? null;
if ($precoMC !== null && !$jaComprado && !$diaBloqueado && !empty($pratosPrincipais)): ?>
<label class="menu-completo-toggle">
    <input type="checkbox" class="checkbox-menu-completo"
           data-preco-mc="<?= $precoMC ?>">
    Menu completo (sopa + sobremesa + bebida incluídas) — <?= number_format($precoMC, 2, ',', '') ?>€
</label>
<div class="menu-completo-resumo">
    <?php foreach ($componentesExtra as $tipoNome => $comp): ?>
    <span class="menu-completo-item-incluido">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($tipoNome) ?>
    </span>
    <?php endforeach; ?>
</div>
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

    <?php if (!empty($extrasComPreco)): ?>
    <div class="extras-secao">
        <h2 class="ementa-semana">pratos extras</h2>
        <p class="text-muted small">Disponíveis todos os dias, sem prazo de compra.</p>

        <div class="extras-data-escolha">
            <label for="dataExtras">Para quando?</label>
            <select id="dataExtras">
                <?php foreach ($diasUteisExtras as $diaUtilStr):
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
            <?php foreach ($extrasComPreco as $e): ?>
            <label class="componente-opcao">
                <input type="checkbox" class="checkbox-extra"
                       data-rm-id="<?= $e['RM_ID'] ?>"
                       data-preco="<?= $e['preco'] ?>"
                       data-nome="<?= htmlspecialchars($e['RM_NOME']) ?>">
                <span class="nome-extra"><?= htmlspecialchars($e['RM_NOME']) ?></span>
                <?php if (isset($mediasAvaliacoes[$e['RM_NOME']])):
                    $av = $mediasAvaliacoes[$e['RM_NOME']];
                ?>
                <span class="prato-avaliacao">
                    <?= str_repeat('★', round($av['media'])) . str_repeat('☆', 5 - round($av['media'])) ?>
                    <span class="prato-avaliacao-total">(<?= $av['total'] ?>)</span>
                </span>
                <?php endif; ?>
                <span class="preco-extra"><?= number_format($e['preco'], 2, ',', '') ?>€</span>
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
<script src="<?= assetUrl('assets/js/ementa.js') ?>"></script>
</body>
</html>