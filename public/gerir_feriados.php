<?php
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador   = exigirLogin('admin_cantina');
$feriados     = Database::listarFeriados();
$diasEspeciais = Database::listarTodosDiasEspeciais();
$csrfToken    = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Gerir Feriados</title>
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

    <main class="container mt-4" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Gerir Feriados</h1>
        </div>

        <div class="card mb-4">
            <div class="card-header">Gerar Feriados Móveis</div>
            <div class="card-body">
                <p class="card-text">Gera automaticamente os feriados que dependem da Páscoa (Carnaval, Sexta-feira Santa, Corpo de Deus) para um ano específico.</p>
                <form id="form-gerar-feriados" class="form-gerar">
                    <input type="number" class="form-control" id="ano-gerar" name="ano" value="<?= date('Y') ?>" style="width: 120px;" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-magic"></i> Gerar
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Adicionar Feriado Manualmente</div>
            <div class="card-body">
                <form id="form-criar-feriado">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="data-feriado" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data-feriado" name="data" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nome-feriado" class="form-label">Nome do Feriado</label>
                            <input type="text" class="form-control" id="nome-feriado" name="nome" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">Adicionar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <h2 class="mt-4">Feriados Registados</h2>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Nome</th>
                    <th class="table-actions"></th>
                </tr>
            </thead>
            <tbody id="lista-feriados">
                <?php foreach ($feriados as $f): ?>
                    <tr id="feriado-<?= $f['RF_ID'] ?>">
                        <td><?= (new DateTime($f['RF_DATA']))->format('d/m/Y') ?></td>
                        <td><?= htmlspecialchars($f['RF_NOME']) ?></td>
                        <td>
                            <button class="btn-apagar" data-id="<?= $f['RF_ID'] ?>" title="Apagar feriado">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($feriados)): ?>
                    <tr id="sem-feriados"><td colspan="3" class="text-center text-muted">Nenhum feriado registado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- ===== DIAS ESPECIAIS ===== -->
        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Dias Especiais</h2>
        </div>
        <p class="text-muted small">Dias em que a cantina encerra por razões não feriado (férias, greve, evento). Podes indicar se os pratos extra continuam disponíveis para compra.</p>

        <div class="card mb-4">
            <div class="card-header">Adicionar Dia Especial</div>
            <div class="card-body">
                <form id="form-criar-dia-especial">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="data-especial" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data-especial" name="data" required>
                        </div>
                        <div class="col-md-4">
                            <label for="motivo-especial" class="form-label">Motivo <span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control" id="motivo-especial" name="motivo" placeholder="ex: Férias de agosto">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="permite-extras" name="permite_extras" value="1">
                                <label class="form-check-label" for="permite-extras">
                                    Permitir venda de extras
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">Adicionar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Motivo</th>
                    <th>Extras</th>
                    <th class="table-actions"></th>
                </tr>
            </thead>
            <tbody id="lista-dias-especiais">
                <?php foreach ($diasEspeciais as $de): ?>
                    <tr id="dia-especial-<?= $de['RDE_ID'] ?>">
                        <td><?= (new DateTime($de['RDE_DATA']))->format('d/m/Y') ?></td>
                        <td><?= $de['RDE_MOTIVO'] ? htmlspecialchars($de['RDE_MOTIVO']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($de['RDE_PERMITE_EXTRAS']): ?>
                                <span class="badge bg-success"><i class="bi bi-check"></i> Permitidos</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-x"></i> Bloqueados</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-apagar btn-apagar-especial" data-id="<?= $de['RDE_ID'] ?>" title="Apagar dia especial">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($diasEspeciais)): ?>
                    <tr id="sem-dias-especiais"><td colspan="4" class="text-center text-muted">Nenhum dia especial registado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<script src="assets/js/utils.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= $csrfToken ?>';

    // ─── helper: toast inline ────────────────────────────────────────────────
    function mostrarToast(mensagem, tipo = 'success') {
        const existente = document.getElementById('toast-feriados');
        if (existente) existente.remove();
        const toast = document.createElement('div');
        toast.id = 'toast-feriados';
        toast.className = `alert alert-${tipo === 'success' ? 'success' : (tipo === 'warning' ? 'warning' : 'danger')} alert-dismissible fade show mt-3`;
        toast.role = 'alert';
    
        toast.innerHTML = escHtml(mensagem) + '<button type="button" class="btn-close"></button>';
        toast.querySelector('.btn-close').addEventListener('click', () => toast.remove());
        document.querySelector('main').prepend(toast);
        if (tipo === 'success') setTimeout(() => toast.remove(), 5000);
    }

    // ─── Gerar feriados móveis ────────────────────────────────────────────────
    document.getElementById('form-gerar-feriados').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        const ano = document.getElementById('ano-gerar').value;
        const formData = new FormData();
        formData.append('ano', ano);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_gerar_moveis.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    const tipo = data.inseridos === 0 ? 'warning' : 'success';
                    mostrarToast(data.mensagem, tipo);
                    if (data.inseridos > 0) {
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        btn.disabled = false;
                    }
                } else {
                    mostrarToast(data.mensagem || 'Erro ao gerar feriados.', 'danger');
                    btn.disabled = false;
                }
            })
            .catch(() => {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                btn.disabled = false;
            });
    });

    // ─── Criar feriado manual ─────────────────────────────────────────────────
    document.getElementById('form-criar-feriado').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        const formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_criar.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    window.location.reload();
                } else {
                    mostrarToast(data.mensagem || 'Erro ao criar feriado.', 'danger');
                    btn.disabled = false;
                }
            })
            .catch(() => {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                btn.disabled = false;
            });
    });

    // ─── Apagar feriado ───────────────────────────────────────────────────────
    document.getElementById('lista-feriados').addEventListener('click', function(e) {
        const target = e.target.closest('.btn-apagar');
        if (!target) return;
        if (!confirm('Tem a certeza que quer apagar este feriado?')) return;

        target.disabled = true;

        const id = target.dataset.id;
        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_apagar.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    document.getElementById(`feriado-${id}`).remove();
                } else {
                    mostrarToast(data.mensagem || 'Erro ao apagar feriado.', 'danger');
                    target.disabled = false;
                }
            })
            .catch(() => {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                target.disabled = false;
            });
    });

    // ─── Criar dia especial ───────────────────────────────────────────────────
    document.getElementById('form-criar-dia-especial').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        const formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_dias_especiais_criar.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    window.location.reload();
                } else {
                    mostrarToast(data.mensagem || 'Erro ao criar dia especial.', 'danger');
                    btn.disabled = false;
                }
            })
            .catch(() => {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                btn.disabled = false;
            });
    });

    // ─── Apagar dia especial ──────────────────────────────────────────────────
    document.getElementById('lista-dias-especiais').addEventListener('click', function(e) {
        const target = e.target.closest('.btn-apagar-especial');
        if (!target) return;
        if (!confirm('Tem a certeza que quer apagar este dia especial?')) return;

        target.disabled = true;

        const id = target.dataset.id;
        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_dias_especiais_apagar.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    document.getElementById(`dia-especial-${id}`).remove();
                } else {
                    mostrarToast(data.mensagem || 'Erro ao apagar dia especial.', 'danger');
                    target.disabled = false;
                }
            })
            .catch(() => {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                target.disabled = false;
            });
    });
});
</script>

</body>
</html>