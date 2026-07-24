<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');
$validacoesHoje = Database::contarValidacoesHoje((int) $utilizador['id']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIUPT - Validar refeição</title>
    <meta name="description" content="Validação de refeições por QR code — área do funcionário.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/base.css" rel="stylesheet">
    <link href="assets/css/navbar.css" rel="stylesheet">
    <link href="assets/css/validar.css" rel="stylesheet">
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
    <div id="profile" title="<?= htmlspecialchars($utilizador['nome']) ?>">
        <a id="quit" href="login.php?logout=1" title="Terminar sessão">&nbsp;</a>
        <div id="profile-photo" class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($utilizador['nome'], 0, 1))) ?>
        </div>
    </div>
</header>

<main class="validar-main">
    <h1 class="validar-titulo">validar refeição</h1>
    <p class="validar-subtitulo">Aponte a câmara para o QR code do utilizador ou introduza o código manualmente.</p>

    <div class="validacoes-contador">
        <i class="bi bi-check2-circle"></i>
        <span class="num" id="contadorValidacoes"><?= $validacoesHoje ?></span>
        validações hoje
    </div>

    <!-- Scanner de câmara -->
    <div class="scan-card">
        <div class="scan-card-titulo">
            <i class="bi bi-camera"></i> Scan por câmara
        </div>
        <div id="qr-reader"></div>

        <div class="scan-separador">ou</div>

        <!-- Input manual -->
        <div class="scan-input-group">
            <input type="text" id="inputQrManual" class="scan-input"
                   placeholder="Cole ou escreva o código QR aqui…"
                   autocomplete="off" spellcheck="false">
            <button id="btnValidarManual" class="btn-validar-manual">
                <i class="bi bi-search"></i> Validar
            </button>
        </div>
    </div>

    <!-- Área de resultado -->
    <div id="resultadoCard" class="resultado-card">
        <span class="resultado-icone" id="resultadoIcone"></span>
        <div class="resultado-estado" id="resultadoEstado"></div>
        <div class="resultado-nome" id="resultadoNome"></div>
        <ul class="resultado-linhas" id="resultadoLinhas"></ul>
    </div>
</main>
</div>

<!-- html5-qrcode -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
const API_URL = 'api/validar_qrcode.php';

// ---- Estado do resultado ----
const resultadoCard   = document.getElementById('resultadoCard');
const resultadoIcone  = document.getElementById('resultadoIcone');
const resultadoEstado = document.getElementById('resultadoEstado');
const resultadoNome   = document.getElementById('resultadoNome');
const resultadoLinhas = document.getElementById('resultadoLinhas');
const contadorEl      = document.getElementById('contadorValidacoes');

let scanAtivo = true; // evitar chamadas duplicadas enquanto a câmara está a ler

// ---- Iniciar scanner de câmara ----
const html5QrCode = new Html5Qrcode("qr-reader");
const configScan  = { fps: 10, qrbox: { width: 220, height: 220 } };

html5QrCode.start(
    { facingMode: "environment" },
    configScan,
    (decodedText) => {
        if (!scanAtivo) return;
        scanAtivo = false;
        validarQrCode(decodedText);
        // Reativar scan após 3 segundos
        setTimeout(() => { scanAtivo = true; }, 3000);
    },
    () => { /* erro de leitura, ignorar */ }
).catch(err => {
    // Câmara não disponível — esconder o reader, o input manual ainda funciona
    document.getElementById('qr-reader').style.display = 'none';
    console.warn('Câmara não disponível:', err);
});

// ---- Input manual ----
document.getElementById('btnValidarManual').addEventListener('click', () => {
    const codigo = document.getElementById('inputQrManual').value.trim();
    if (!codigo) return;
    validarQrCode(codigo);
    document.getElementById('inputQrManual').value = '';
});

document.getElementById('inputQrManual').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('btnValidarManual').click();
});

// ---- Chamada à API ----
async function validarQrCode(qrcode) {
    mostrarResultado('loading');

    let dados;
    try {
        const resp = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ qrcode })
        });
        dados = await resp.json();
    } catch (e) {
        mostrarResultado('erro_rede');
        return;
    }

    mostrarResultado(dados.status, dados);

    if (typeof dados.validacoes_hoje === 'number') {
        contadorEl.textContent = dados.validacoes_hoje;
    }
}

// ---- Mostrar resultado ----
function mostrarResultado(status, dados = {}) {
    resultadoCard.className = 'resultado-card visivel';
    resultadoLinhas.innerHTML = '';

    const configs = {
        loading: {
            classe: 'aviso',
            icone: '⏳',
            estado: 'A validar…',
            nome: ''
        },
        valido: {
            classe: 'sucesso',
            icone: '✅',
            estado: 'Refeição validada com sucesso!',
            nome: dados.nome ?? ''
        },
        nao_pago: {
            classe: 'erro',
            icone: '💳',
            estado: 'Pagamento não confirmado',
            nome: dados.nome ?? ''
        },
        utilizado: {
            classe: 'aviso',
            icone: '⚠️',
            estado: 'QR code já utilizado',
            nome: dados.nome ?? ''
        },
        vencido: {
            classe: 'erro',
            icone: '❌',
            estado: 'Pedido vencido (data de refeição já passou)',
            nome: dados.nome ?? ''
        },
        invalido: {
            classe: 'erro',
            icone: '🚫',
            estado: 'QR code inválido',
            nome: ''
        },
        erro: {
            classe: 'erro',
            icone: '❌',
            estado: 'Erro ao processar',
            nome: ''
        },
        erro_rede: {
            classe: 'erro',
            icone: '📡',
            estado: 'Erro de ligação. Tente novamente.',
            nome: ''
        }
    };

    const cfg = configs[status] ?? configs['erro'];
    resultadoCard.classList.add(cfg.classe);
    resultadoIcone.textContent  = cfg.icone;
    resultadoEstado.textContent = cfg.estado;
    resultadoNome.textContent   = cfg.nome;

    // Linhas da compra (apenas em validações bem-sucedidas)
    if (status === 'valido' && Array.isArray(dados.linhas) && dados.linhas.length > 0) {
        dados.linhas.forEach(linha => {
            const li = document.createElement('li');
            li.innerHTML = `
                <span class="item-nome">${escHtml(linha.RM_NOME)}</span>
                <span class="item-tipo">${escHtml(linha.RTP_NOME)}</span>
            `;
            resultadoLinhas.appendChild(li);
        });
    }
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
</body>
</html>