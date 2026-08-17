<?php
/**
 * Página de gestão de feriados e dias especiais.
 *
 * Permite ao administrador da cantina (admin_cantina):
 *  - Gerar automaticamente os feriados nacionais + móveis para um dado ano
 *    (via Database::gerarTodosFeriadosDoAno, que usa easter_date() para os móveis)
 *  - Adicionar feriados manualmente (legislação nova ou suspensão de um feriado)
 *  - Remover feriados existentes
 *  - Registar dias especiais (encerramentos por férias, greve ou evento interno),
 *    com controlo individual sobre se os pratos extra continuam disponíveis nesses dias
 *
 * Distinção importante:
 *  - Feriado (restaurante_feriado): data simbólica/legal — bloqueia a compra
 *    tanto de pratos da ementa como de pratos extra (cantina totalmente encerrada).
 *  - Dia especial (restaurante_dia_especial): encerramento operacional por outro motivo — pode
 *    bloquear também os extras (RDE_PERMITE_EXTRAS = 0) ou permitir apenas extras (RDE_PERMITE_EXTRAS = 1).
 *
 * Requer papel: admin_cantina
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador    = exigirLogin('admin_cantina');
$feriados      = Database::listarFeriados();
$diasEspeciais = Database::listarTodosDiasEspeciais();
$csrfToken     = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir Feriados</title>
    <meta name="description" content="Gestão de feriados e dias especiais da cantina — área reservada ao administrador.">
    <meta name="robots" content="noindex">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/base.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/navbar.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('assets/css/gerir-feriados.css') ?>" rel="stylesheet">
</head>
<body>

<div id="bodycontainer">

    <header>
        <a id="home" href="validar.php" title="Voltar ao início">
            <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
        </a>

        <a href="validar.php" class="nav-icon-link" title="Validar QR code">
            <i class="bi bi-qr-code-scan"></i>
        </a>

        <a href="gerir_extras.php" class="nav-icon-link" title="Gerir extras">
            <i class="bi bi-egg-fried"></i>
        </a>

        <a href="gerir_motivos.php" class="nav-icon-link" title="Gerir motivos">
            <i class="bi bi-chat-square-text"></i>
        </a>

        <a href="gerir_feriados.php" class="nav-icon-link nav-icon-link--ativo" title="Gerir feriados e dias especiais">
            <i class="bi bi-calendar-x"></i>
        </a>

        <a href="gerir_atendentes.php" class="nav-icon-link" title="Gerir atendentes">
            <i class="bi bi-people"></i>
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

    <main class="gerir-feriados-main">

        <h1 class="gerir-feriados-titulo">gerir feriados</h1>
        <p class="gerir-feriados-subtitulo">
            Configura os feriados nacionais e dias de encerramento especial da cantina.
        </p>

        <!-- ── Gerar feriados móveis ──────────────────────────────────── -->
        <div class="feriados-card">
            <h2 class="feriados-card-titulo">
                <i class="bi bi-magic"></i>
                Gerar feriados móveis
            </h2>
            <p>Gera automaticamente os feriados que dependem da Páscoa (Carnaval, Sexta-feira Santa, Corpo de Deus) para um ano específico.</p>
            <form id="form-gerar-feriados" class="feriados-form-linha">
                <div class="feriados-form-campo">
                    <label for="ano-gerar">Ano</label>
                    <input type="number" id="ano-gerar" name="ano" value="<?= date('Y') ?>" min="2020" max="2040" required>
                </div>
                <button type="submit" class="btn-feriados-acao">
                    <i class="bi bi-magic"></i> Gerar
                </button>
            </form>
        </div>

        <!-- ── Adicionar feriado manual ───────────────────────────────── -->
        <div class="feriados-card">
            <h2 class="feriados-card-titulo">
                <i class="bi bi-plus-circle"></i>
                Adicionar feriado manualmente
            </h2>
            <form id="form-criar-feriado" class="feriados-form-linha">
                <div class="feriados-form-campo">
                    <label for="data-feriado">Data</label>
                    <input type="date" id="data-feriado" name="data" required>
                </div>
                <div class="feriados-form-campo" style="flex:2; min-width:200px;">
                    <label for="nome-feriado">Nome do feriado</label>
                    <input type="text" id="nome-feriado" name="nome" placeholder="Ex: Feriado municipal" required>
                </div>
                <button type="submit" class="btn-feriados-acao btn-feriados-acao--verde">
                    <i class="bi bi-check-lg"></i> Adicionar
                </button>
            </form>
        </div>

        <!-- ── Lista de feriados ──────────────────────────────────────── -->
        <h2 class="feriados-secao-titulo">feriados registados</h2>
        <div class="feriados-tabela">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nome</th>
                        <th class="col-acoes"></th>
                    </tr>
                </thead>
                <tbody id="lista-feriados">
                    <?php foreach ($feriados as $f): ?>
                        <tr id="feriado-<?= $f['RF_ID'] ?>">
                            <td><?= (new DateTime($f['RF_DATA']))->format('d/m/Y') ?></td>
                            <td><?= htmlspecialchars($f['RF_NOME']) ?></td>
                            <td>
                                <button class="btn-feriados-apagar" data-id="<?= $f['RF_ID'] ?>" title="Apagar feriado">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($feriados)): ?>
                        <tr id="sem-feriados">
                            <td colspan="3" class="feriados-vazio">
                                <i class="bi bi-inbox"></i> Nenhum feriado registado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <hr class="feriados-divisor">

        <!-- ── Dias especiais ─────────────────────────────────────────── -->
        <h2 class="gerir-feriados-titulo" style="font-size:1.3rem;">dias especiais</h2>
        <p class="gerir-feriados-subtitulo">
            Dias em que a cantina encerra por razões não feriado (férias, greve, evento). Podes indicar se os pratos extra continuam disponíveis.
        </p>

        <div class="feriados-card">
            <h2 class="feriados-card-titulo">
                <i class="bi bi-plus-circle"></i>
                Adicionar dia especial
            </h2>
            <form id="form-criar-dia-especial" class="feriados-form-linha">
                <div class="feriados-form-campo">
                    <label for="data-especial">Data</label>
                    <input type="date" id="data-especial" name="data" required>
                </div>
                <div class="feriados-form-campo" style="flex:2; min-width:180px;">
                    <label for="motivo-especial">Motivo <span style="font-weight:400;">(opcional)</span></label>
                    <input type="text" id="motivo-especial" name="motivo" placeholder="Ex: Férias de agosto">
                </div>
                <div class="feriados-form-campo" style="flex:0; min-width:auto; justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <label class="feriados-check-label">
                        <input type="checkbox" id="permite-extras" name="permite_extras" value="1">
                        Permitir extras
                    </label>
                </div>
                <button type="submit" class="btn-feriados-acao btn-feriados-acao--verde">
                    <i class="bi bi-check-lg"></i> Adicionar
                </button>
            </form>
        </div>

        <!-- ── Lista de dias especiais ────────────────────────────────── -->
        <h2 class="feriados-secao-titulo">dias especiais registados</h2>
        <div class="feriados-tabela">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Motivo</th>
                        <th>Extras</th>
                        <th class="col-acoes"></th>
                    </tr>
                </thead>
                <tbody id="lista-dias-especiais">
                    <?php foreach ($diasEspeciais as $de): ?>
                        <tr id="dia-especial-<?= $de['RDE_ID'] ?>">
                            <td><?= (new DateTime($de['RDE_DATA']))->format('d/m/Y') ?></td>
                            <td><?= $de['RDE_MOTIVO'] ? htmlspecialchars($de['RDE_MOTIVO']) : '<span style="color:#8a99ad;">—</span>' ?></td>
                            <td>
                                <?php if ($de['RDE_PERMITE_EXTRAS']): ?>
                                    <span class="badge-extras-sim"><i class="bi bi-check"></i> Permitidos</span>
                                <?php else: ?>
                                    <span class="badge-extras-nao"><i class="bi bi-x"></i> Bloqueados</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-feriados-apagar btn-apagar-especial" data-id="<?= $de['RDE_ID'] ?>" title="Apagar dia especial">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($diasEspeciais)): ?>
                        <tr id="sem-dias-especiais">
                            <td colspan="4" class="feriados-vazio">
                                <i class="bi bi-inbox"></i> Nenhum dia especial registado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
window.CSRF_TOKEN = '<?= $csrfToken ?>';
</script>
<script src="<?= assetUrl('assets/js/utils.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gerir_feriados.js') ?>"></script>

</body>
</html>