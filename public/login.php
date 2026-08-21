<?php
/**
 * Página de autenticação da aplicação.
 *
 * Permite autenticar estudantes e funcionários,
 * criar a sessão de utilizador e encaminhar cada
 * perfil para a respetiva área da aplicação.
 */

require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = '';

/**
 * Termina a sessão do utilizador.
 * Requer método POST para prevenir CSRF (ex: <img src="login.php?logout=1">).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    verificarCsrfToken();
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Rate limiting simples: máx 10 tentativas por IP em 15 minutos ────────
$ipCliente  = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$chaveRateKey = 'login_tentativas_' . md5($ipCliente);
$janelaTempo  = 900;  // 15 minutos em segundos
$maxTentativas = 10;

if (!isset($_SESSION[$chaveRateKey])) {
    $_SESSION[$chaveRateKey] = ['total' => 0, 'desde' => time()];
}

// Reinicia a janela se já passou o tempo limite
if (time() - $_SESSION[$chaveRateKey]['desde'] > $janelaTempo) {
    $_SESSION[$chaveRateKey] = ['total' => 0, 'desde' => time()];
}

$bloqueado = $_SESSION[$chaveRateKey]['total'] >= $maxTentativas;

/**
 * Processa o pedido de autenticação.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {

    $bicc     = trim($_POST['numero'] ?? '');
    $password = $_POST['password'] ?? '';

    // S3: Valida comprimento máximo antes de enviar à base de dados
    if (strlen($bicc) > 50 || strlen($password) > 256) {
        $erro = 'Dados de acesso inválidos.';
    } else {
        $utilizador = Database::autenticar($bicc, $password);

        if ($utilizador) {
            // Sucesso — zera o contador de tentativas
            $_SESSION[$chaveRateKey] = ['total' => 0, 'desde' => time()];

            $tipo = Database::perfilParaTipo((int) $utilizador['U_PERFIL']);

            session_regenerate_id(true);

            $_SESSION['user_id']     = $utilizador['U_ID'];
            $_SESSION['user_nome']   = $utilizador['U_NOME'];
            $_SESSION['user_tipo']   = $tipo;
            // Carrega os papéis de cantina (atendente / admin_cantina).
            // Alunos e colaboradores sem papel ficam com array vazio.
            $_SESSION['user_papeis'] = Database::obterPapeisUtilizador((int) $utilizador['U_ID']);

            // Redireciona para a página de origem (passada via ?next=) se for segura.
            // Proteção contra open redirect: só aceita caminhos relativos (sem "://").
            $destino = $_GET['next'] ?? '';
            $destinoPadrao = !empty($_SESSION['user_papeis']) ? 'validar.php' : 'ementa.php';
            if ($destino !== '' && !str_contains($destino, '://') && !str_starts_with($destino, '//')) {
                $destino = ltrim(basename(parse_url($destino, PHP_URL_PATH)), '/');
            } else {
                $destino = $destinoPadrao;
            }

            header('Location: ' . ($destino ?: $destinoPadrao));
            exit;
        }

        // Falha — incrementa o contador
        $_SESSION[$chaveRateKey]['total']++;
        $restantes = max(0, $maxTentativas - $_SESSION[$chaveRateKey]['total']);

        $erro = $restantes > 0
            ? "Número ou palavra-passe incorretos. Tentativas restantes: {$restantes}."
            : 'Demasiadas tentativas. Por favor aguarda 15 minutos e tenta novamente.';
    }

} elseif ($bloqueado) {
    $erro = 'Demasiadas tentativas. Por favor aguarda antes de tentar novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body>

<div class="login-page">

    <div class="login-left">
        <div class="login-brand">
            <div class="login-brand-badge">
                <img src="https://siupt.upt.pt/styles/images/Logoinstitucional-PT-HE-branco-negativo.png" alt="UPT">
            </div>
            <div class="login-brand-text">
                UNIVERSIDADE<br>PORTUCALENSE
            </div>
        </div>

        <div>
            <div class="login-left-title">SIUPT</div>
            <div class="login-left-subtitle">
                Sistema de Informação da Universidade Portucalense
            </div>
        </div>
    </div>

    <div class="login-right position-relative">

        <div class="login-right-top">
            <i class="bi bi-globe"></i> Português
            <i class="bi bi-question-circle"></i>
        </div>

        <div class="login-welcome">Bem-vindo</div>
        <div class="login-subtext">
            Aceda à sua área reservada do Portal Académico.
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger py-2 small">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Seleção do tipo de utilizador -->
        <div class="login-field-label">Tipo de utilizador</div>

        <div class="user-type-toggle" id="tipoToggle">
            <div class="user-type-option active" data-tipo="estudante">
                <i class="bi bi-mortarboard"></i> Estudante
            </div>

            <div class="user-type-option" data-tipo="colaborador">
                <i class="bi bi-briefcase"></i> Colaborador
            </div>
        </div>
        <p class="text-muted small mt-1" style="font-size:0.78rem; text-align:center;">
            O sistema deteta automaticamente o teu perfil de acesso.
        </p>

        <!-- Formulário de autenticação -->
        <form method="POST">

            <input
                type="hidden"
                name="tipo_selecionado"
                id="tipoSelecionado"
                value="estudante"
            >

            <div class="login-field-label" id="labelNumero">
                Número de estudante
            </div>

            <div class="input-icon-group">
                <i class="bi bi-person-badge"></i>
                <input
                    type="text"
                    name="numero"
                    class="form-control"
                    required
                    autofocus
                >
            </div>

            <div class="login-field-label">
                Palavra-passe
                <span class="forgot-link" title="Recuperação de password gerida pela Universidade Portucalense."
                      onclick="alert('Para recuperar a password, contacta os Serviços Académicos da Universidade Portucalense.')"
                      style="cursor:pointer;">
                    Esqueceu-se?
                </span>
            </div>

            <div class="input-icon-group">
                <i class="bi bi-lock"></i>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    required
                >
            </div>

            <button type="submit" class="btn btn-siupt w-100 text-white mt-2">
                ENTRAR <i class="bi bi-box-arrow-in-right"></i>
            </button>

        </form>

    </div>

</div>

<script>
/**
 * Atualiza a interface de acordo com o tipo
 * de utilizador selecionado.
 */
document.querySelectorAll('.user-type-option').forEach(opt => {

    opt.addEventListener('click', () => {

        document.querySelectorAll('.user-type-option')
            .forEach(o => o.classList.remove('active'));

        opt.classList.add('active');

        const tipo = opt.dataset.tipo;

        document.getElementById('labelNumero').textContent =
            tipo === 'estudante'
                ? 'Número de estudante'
                : 'Número de colaborador';

        document.getElementById('tipoSelecionado').value = tipo;
    });

});
</script>

</body>
</html>