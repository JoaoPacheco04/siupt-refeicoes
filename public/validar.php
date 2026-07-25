<?php
session_start();
require_once __DIR__ . '/../src/Support/Auth.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';

$utilizador = exigirLogin('funcionario');
$validacoesHoje = Database::contarValidacoesHoje((int) $utilizador['id']);
$listaValidacoes = Database::listarValidacoesHoje((int) $utilizador['id']);
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

        <button id="btnValidarSeguinte" class="btn-validar-seguinte" style="display:none;">
            <i class="bi bi-arrow-repeat"></i> Validar seguinte
        </button>

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

    <!-- Lista de validações de hoje -->
    <h2 class="validacoes-lista-titulo">Validações de hoje</h2>
    <div id="listaValidacoes" class="lista-validacoes">
        <?php if (empty($listaValidacoes)): ?>
            <p class="lista-validacoes-vazia" id="listaVazia">
                <i class="bi bi-inbox"></i> Ainda não validaste nenhuma refeição hoje.
            </p>
        <?php else: ?>
            <?php foreach ($listaValidacoes as $v):
                $hora = date('H:i', strtotime($v['RV_DATA_VALIDACAO']));
            ?>
            <div class="validacao-item">
                <div class="validacao-hora"><?= $hora ?></div>
                <div class="validacao-nome"><?= htmlspecialchars($v['U_NOME']) ?></div>
                <div class="validacao-pedido">#<?= $v['RP_ID'] ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- html5-qrcode -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
const API_URL    = 'api/validar_qrcode.php';
const CSRF_TOKEN = '<?= gerarCsrfToken() ?>';

// ---- Estado do resultado ----
const resultadoCard   = document.getElementById('resultadoCard');
const resultadoIcone  = document.getElementById('resultadoIcone');
const resultadoEstado = document.getElementById('resultadoEstado');
const resultadoNome   = document.getElementById('resultadoNome');
const resultadoLinhas = document.getElementById('resultadoLinhas');
const contadorEl      = document.getElementById('contadorValidacoes');
const listaEl         = document.getElementById('listaValidacoes');

let cameraDisponivel = false;

// ---- Iniciar scanner de câmara ----
const html5QrCode = new Html5Qrcode("qr-reader");
const configScan  = { fps: 10, qrbox: { width: 220, height: 220 } };

html5QrCode.start(
    { facingMode: "environment" },
    configScan,
    (decodedText) => {
        // Pausa a câmara assim que lê algo — não volta a ler nada até o
        // funcionário carregar em "Validar seguinte"
        html5QrCode.pause(true);
        document.getElementById('btnValidarSeguinte').style.display = 'inline-flex';
        validarQrCode(decodedText);
    },
    () => { /* erro de leitura, ignorar */ }
).then(() => {
    cameraDisponivel = true;
}).catch(err => {
    document.getElementById('qr-reader').style.display = 'none';
    console.warn('Câmara não disponível:', err);
});

document.getElementById('btnValidarSeguinte').addEventListener('click', () => {
    if (!cameraDisponivel) return;
    html5QrCode.resume();
    document.getElementById('btnValidarSeguinte').style.display = 'none';
    resultadoCard.classList.remove('visivel');
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
            body: new URLSearchParams({ qrcode, csrf_token: CSRF_TOKEN })
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

    // Só acrescenta à lista se a validação foi mesmo bem-sucedida agora
    if (dados.status === 'valido') {
        adicionarAListaValidacoes(dados);
    }
}

// ---- Acrescentar entrada nova ao topo da lista ----
function adicionarAListaValidacoes(dados) {
    const vazia = document.getElementById('listaVazia');
    if (vazia) vazia.remove();

    const hora = new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });

    const item = document.createElement('div');
    item.className = 'validacao-item validacao-nova';
    item.innerHTML = `
        <div class="validacao-hora">${hora}</div>
        <div class="validacao-nome">${escHtml(dados.nome ?? '')}</div>
        <div class="validacao-pedido">#${dados.pedido_id ?? ''}</div>
    `;
    listaEl.prepend(item);
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