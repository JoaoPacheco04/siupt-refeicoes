<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('aluno');

function foraDePrazo(string $dataRefeicao): bool {
    $limite = date('Y-m-d 10:00:00', strtotime($dataRefeicao . ' -1 day'));
    return date('Y-m-d H:i:s') > $limite;
}

// Calcula segunda a sexta da semana atual
$hoje = new DateTime();
$diaSemanaHoje = (int) $hoje->format('N');
$segunda = (clone $hoje)->modify('-' . ($diaSemanaHoje - 1) . ' days');
$sexta = (clone $segunda)->modify('+4 days');

$refeicoes = Database::listarRefeicoesSemana($segunda->format('Y-m-d'), $sexta->format('Y-m-d'));

$todasForaDePrazo = !empty($refeicoes) && count(array_filter($refeicoes, fn($r) => !foraDePrazo($r['data_refeicao']))) === 0;

if (empty($refeicoes) || $todasForaDePrazo) {
    $segunda->modify('+7 days');
    $sexta->modify('+7 days');
    $refeicoes = Database::listarRefeicoesSemana($segunda->format('Y-m-d'), $sexta->format('Y-m-d'));
}

$jaCompradas = Database::refeicoesJaCompradas($utilizador['id']);

$numerosDia = [1 => '2ª', 2 => '3ª', 3 => '4ª', 4 => '5ª', 5 => '6ª'];
$meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$nomeMes = $meses[(int) $sexta->format('n')];
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
    <link href="assets/css/siupt.css" rel="stylesheet">
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
    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">
        <span><?= htmlspecialchars(explode(' ', $utilizador['nome'])[0] . ' ' . (explode(' ', $utilizador['nome'])[count(explode(' ', $utilizador['nome'])) - 1] ?? '')) ?></span>
        <img src="https://siupt.upt.pt/styles/images/metro_off.png" alt="A sua foto" id="profile-photo">
        <div>
            <a id="quit" href="login.php?submit_type=111" title="Terminar sessão">&nbsp;</a>
            <a id="help" href="http://suporte.uportu.pt/" title="Ajuda">&nbsp;</a>
            <a id="conf" href="#" title="Preferências">&nbsp;</a>
        </div>
    </div>
    <form id="form_new_user_lang" method="post" action="#">
        <input type="hidden" id="submit_confirm" name="submit_confirm" value="Tem a certeza que pretende alterar a linguagem? Irá perder as alterações não gravadas desta página.">
        <input type="hidden" id="submit_type" name="submit_type" value="switch_language">
        <input type="hidden" id="current_user_lang" name="current_user_lang" value="pt">
        <label for="new_user_lang"></label>
        <select id="new_user_lang" name="new_user_lang">
            <option value="en">Inglês</option>
            <option value="pt" selected>Português</option>
        </select>
    </form>
</header>

<main class="ementa-main container" style="padding-bottom:130px; max-width:820px;">

    <div class="ementa-cabecalho">
        <h1 class="ementa-titulo">ementa</h1>
        <p class="ementa-horario">Horário do restaurante: 12h00 - 14h30</p>
        <p class="ementa-horario">Horário do bar: 8h00 - 20h00 (Em épocas baixas das 8h00 às 18h00)</p>
        <button id="btnSelecionarSemana" class="btn-selecionar-semana" type="button">
            <i class="bi bi-check2-all"></i> Selecionar toda a semana
        </button>
    </div>

    <?php if (!empty($refeicoes)): ?>
    <h2 class="ementa-semana">
        semana de <?= $segunda->format('d') ?> a <?= $sexta->format('d') ?> de <?= $nomeMes ?>
    </h2>
    <?php else: ?>
        <p class="text-muted">Não há refeições disponíveis para esta semana.</p>
    <?php endif; ?>

    <?php foreach ($refeicoes as $r):
        $jaComprado = in_array($r['id'], $jaCompradas);
        $indisponivel = foraDePrazo($r['data_refeicao']);
        $numDia = $numerosDia[(int) date('N', strtotime($r['data_refeicao']))];
    ?>
    <div class="dia-row mb-3 <?= $indisponivel && !$jaComprado ? 'dia-indisponivel' : '' ?> <?= $jaComprado ? 'dia-comprado' : '' ?>">
        <div class="dia-checkbox-wrap">
            <div class="dia-label">
                <span class="dia-abrev"><?= $numDia ?></span>
                <span class="dia-data"><?= date('d/m', strtotime($r['data_refeicao'])) ?></span>
            </div>
            <?php if (!$indisponivel && !$jaComprado): ?>
                <input type="checkbox" class="checkbox-refeicao"
                       data-id="<?= $r['id'] ?>"
                       data-preco="<?= $r['preco'] ?>"
                       data-label="<?= htmlspecialchars($numDia . ' — ' . $r['sopa'] . ' / ' . $r['prato_principal']) ?>">
            <?php elseif ($jaComprado): ?>
                <i class="bi bi-check-circle-fill text-success" style="font-size:1.4rem;"></i>
            <?php else: ?>
                <i class="bi bi-lock-fill text-muted" style="font-size:1.2rem;"></i>
            <?php endif; ?>
        </div>
        <div class="food-card sopa">
            <svg class="food-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- tigela a fumegar -->
                <path d="M10 26h28a14 14 0 01-28 0z" fill="rgba(255,255,255,0.9)"/>
                <rect x="16" y="38" width="16" height="3" rx="1.5" fill="rgba(255,255,255,0.7)"/>
                <path d="M20 18c0 0-2 3 0 5" stroke="rgba(255,255,255,0.8)" stroke-width="2" stroke-linecap="round"/>
                <path d="M24 16c0 0-2 4 0 6" stroke="rgba(255,255,255,0.9)" stroke-width="2" stroke-linecap="round"/>
                <path d="M28 18c0 0-2 3 0 5" stroke="rgba(255,255,255,0.8)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div class="food-card-label"><?= htmlspecialchars($r['sopa']) ?></div>
        </div>
        <div class="food-card <?= $r['tipo_prato'] === 'peixe' ? 'peixe' : 'carne' ?>">
            <?php if ($r['tipo_prato'] === 'peixe'): ?>
                <svg class="food-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- espinha de peixe -->
                    <path d="M6 24 Q16 12 28 24 Q16 36 6 24Z" fill="rgba(255,255,255,0.9)"/>
                    <path d="M28 24 Q36 18 42 20" stroke="rgba(255,255,255,0.9)" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M28 24 Q36 30 42 28" stroke="rgba(255,255,255,0.9)" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="28" y1="24" x2="42" y2="24" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="32" y1="24" x2="32" y2="19" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="36" y1="24" x2="36" y2="20" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="32" y1="24" x2="32" y2="29" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="36" y1="24" x2="36" y2="28" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="11" cy="22" r="1.5" fill="rgba(255,255,255,0.9)"/>
                </svg>
            <?php else: ?>
                <svg class="food-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- coxa de frango -->
                    <ellipse cx="20" cy="28" rx="11" ry="9" fill="rgba(255,255,255,0.9)"/>
                    <path d="M28 22 Q38 12 36 8 Q32 10 30 14" stroke="rgba(255,255,255,0.85)" stroke-width="3" stroke-linecap="round" fill="none"/>
                    <ellipse cx="33" cy="8" rx="4" ry="3" fill="rgba(255,255,255,0.8)"/>
                    <line x1="30" y1="34" x2="34" y2="40" stroke="rgba(255,255,255,0.7)" stroke-width="3" stroke-linecap="round"/>
                    <ellipse cx="34" cy="41" rx="4" ry="2.5" fill="rgba(255,255,255,0.75)"/>
                </svg>
            <?php endif; ?>
            <div class="food-card-label"><?= htmlspecialchars($r['prato_principal']) ?></div>
        </div>
        <div class="dia-preco"><?= number_format($r['preco'], 2, ',', '') ?>€</div>
        <?php if ($jaComprado): ?>
            <div class="ja-comprado-badge"><i class="bi bi-check-circle-fill"></i> Adquirido</div>
        <?php elseif ($indisponivel): ?>
            <div class="ja-comprado-badge indisponivel-badge"><i class="bi bi-clock"></i> Encerrado</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</main>
</div><!-- /bodycontainer -->

<div class="resumo-fixo">
    <div class="resumo-info">
        <div class="resumo-count">
            <span id="totalSelecionadas">0</span>
            <small>refeições</small>
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

<script src="https://cdn.jsdelivr.net/npm/tingle.js@0.16.0/dist/tingle.min.js"></script>
<script src="assets/js/ementa.js"></script>

<script>
/* ── Menu SIUPT — comportamento hover fiel ao site real ── */
document.addEventListener('DOMContentLoaded', function () {
    const mainMenu = document.querySelector('#mainmenu');
    if (!mainMenu) return;

    /* Menus de primeiro nível — abrem com HOVER */
    const mainMenuItems = mainMenu.querySelectorAll(':scope > li');
    mainMenuItems.forEach(function (mainLi) {
        const mainSubmenu = mainLi.querySelector(':scope > ul');
        if (!mainSubmenu) return;

        mainLi.addEventListener('mouseenter', function () {
            /* Fecha todos os outros submenus de primeiro nível */
            mainMenuItems.forEach(function (otherMainLi) {
                const deepSubmenus = otherMainLi.querySelectorAll('ul ul');
                deepSubmenus.forEach(function (ul) { ul.style.display = 'none'; });
            });
            mainSubmenu.style.display = 'block';
            mainLi.classList.add('fake_a');
        });

        mainLi.addEventListener('mouseleave', function () {
            mainSubmenu.style.display = 'none';
            mainLi.classList.remove('fake_a');
            const deepSubmenus = mainLi.querySelectorAll('ul ul');
            deepSubmenus.forEach(function (ul) { ul.style.display = 'none'; });
        });

        /* Submenus de segundo nível — abrem com CLIQUE */
        const subItems = mainSubmenu.querySelectorAll(':scope > li');
        subItems.forEach(function (li) {
            const submenu = li.querySelector(':scope > ul');
            if (!submenu) return;

            li.querySelector('a').addEventListener('click', function (e) {
                e.stopPropagation();

                /* Fecha submenus irmãos */
                const siblings = Array.from(li.parentElement.children);
                siblings.forEach(function (sibling) {
                    if (sibling !== li && sibling.tagName === 'LI') {
                        const siblingSubmenu = sibling.querySelector(':scope > ul');
                        if (siblingSubmenu) siblingSubmenu.style.display = 'none';
                    }
                });

                /* Toggle do submenu atual */
                if (submenu.style.display === 'none' || submenu.style.display === '') {
                    submenu.style.display = 'block';
                } else {
                    submenu.style.display = 'none';
                }
            });
        });
    });

    /* Fechar todos os menus ao clicar fora */
    document.addEventListener('click', function (e) {
        if (!mainMenu.contains(e.target)) {
            const allSubmenus = mainMenu.querySelectorAll('ul');
            allSubmenus.forEach(function (ul) {
                if (ul.id !== 'mainmenu') ul.style.display = 'none';
            });
            mainMenuItems.forEach(function (li) { li.classList.remove('fake_a'); });
        }
    });
});
</script>
</body>
</html>