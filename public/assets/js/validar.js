const API_URL    = window.API_URL;
const CSRF_TOKEN = window.CSRF_TOKEN;

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
            estado: 'Pedido expirado (data de refeição já passou)',
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