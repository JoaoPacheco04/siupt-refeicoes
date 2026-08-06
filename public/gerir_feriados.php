<?php
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Support/Assets.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');
$feriados = Database::listarFeriados();
$csrfToken = gerarCsrfToken();
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
    <style>
        .table-actions { width: 1%; }
        .btn-apagar {
            color: #dc3545;
            background: none;
            border: none;
            padding: 0.25rem 0.5rem;
        }
        .btn-apagar:hover {
            color: #fff;
            background-color: #dc3545;
            border-radius: 4px;
        }
        .form-gerar {
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>
</head>
<body>

<div id="bodycontainer">
    <header>
        <a id="home" href="validar.php" title="Voltar à página principal">
            <img src="https://siupt.upt.pt/styles/images/siupt.png" alt="SIUPT" id="siupt-logo">
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
            <a href="validar.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
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
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= $csrfToken ?>';

    document.getElementById('form-gerar-feriados').addEventListener('submit', function(e) {
        e.preventDefault();
        const ano = document.getElementById('ano-gerar').value;
        const formData = new FormData();
        formData.append('ano', ano);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_gerar_moveis.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                alert(data.mensagem || 'Operação concluída.');
                if (data.status === 'ok') window.location.reload();
            });
    });

    document.getElementById('form-criar-feriado').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_criar.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') window.location.reload();
                else alert(data.mensagem || 'Erro ao criar feriado.');
            });
    });

    document.getElementById('lista-feriados').addEventListener('click', function(e) {
        const target = e.target.closest('.btn-apagar');
        if (!target) return;

        if (!confirm('Tem a certeza que quer apagar este feriado?')) return;

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
                    alert(data.mensagem || 'Erro ao apagar feriado.');
                }
            });
    });
});
</script>

</body>
</html>