const API_URL    = window.API_URL;
const CSRF_TOKEN = window.CSRF_TOKEN;

// ---- Estado do resultado ----
const resultadoCard   = document.getElementById('resultadoCard');
const resultadoIcone  = document.getElementById('resultadoIcone');
const resultadoEstado = document.getElementById('resultadoEstado');
const resultadoNome   = document.getElementById('resultadoNome');
const resultadoNumero = document.getElementById('resultadoNumero');
const resultadoLinhas = document.getElementById('resultadoLinhas');
const contadorEl      = document.getElementById('contadorValidacoes');
const listaEl         = document.getElementById('listaValidacoes');
const inputManual     = document.getElementById('inputQrManual');

let cameraDisponivel = false;
let timeoutRetomarAutomatico = null;

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

        // Melhoria #5 — retoma automaticamente ao fim de 2.5s, sem precisar de toque.
        // O botão manual "Validar seguinte" continua disponível para quem quiser mais tempo.
        clearTimeout(timeoutRetomarAutomatico);
        timeoutRetomarAutomatico = setTimeout(() => {
            if (!cameraDisponivel) return;
            html5QrCode.resume();
            document.getElementById('btnValidarSeguinte').style.display = 'none';
        }, 2500);
    },
    () => { /* erro de leitura, ignorar */ }
).then(() => {
    cameraDisponivel = true;
}).catch(err => {
    console.warn('Câmara não disponível:', err);
    mostrarAvisoSemCamara();
});

function mostrarAvisoSemCamara() {
    const reader = document.getElementById('qr-reader');
    reader.innerHTML = `
        <div class="sem-camara-aviso">
            <i class="bi bi-camera-video-off"></i>
            <p>Câmara não disponível neste dispositivo/ligação.<br>
               Usa o campo abaixo ou um leitor físico.</p>
        </div>
    `;
}

document.getElementById('btnValidarSeguinte').addEventListener('click', () => {
    if (!cameraDisponivel) return;
    clearTimeout(timeoutRetomarAutomatico); // cancela o auto-resume se o funcionário já clicou manualmente
    html5QrCode.resume();
    document.getElementById('btnValidarSeguinte').style.display = 'none';
    resultadoCard.classList.remove('visivel');
});

// ---- Input manual ----
document.getElementById('btnValidarManual').addEventListener('click', () => {
    const codigo = inputManual.value.trim();
    if (!codigo) return;
    validarQrCode(codigo);
    inputManual.value = '';
});

inputManual.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('btnValidarManual').click();
});

// ---- Foco automático — essencial para scanners físicos tipo emulador de teclado ----
inputManual.focus();

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

    inputManual.focus();
}

// ---- Acrescentar entrada nova ao topo da lista ----
function adicionarAListaValidacoes(dados) {
    const vazia = document.getElementById('listaVazia');
    if (vazia) vazia.remove();

    const hora = new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });

    const nomesItens = Array.isArray(dados.linhas) && dados.linhas.length > 0
        ? dados.linhas.map(l => l.RM_NOME).join(', ')
        : 'Sem itens registados';

    const item = document.createElement('div');
    item.className = 'validacao-item validacao-nova';
    item.innerHTML = `
        <div class="validacao-hora">${hora}</div>
        <div class="validacao-info">
            <span class="validacao-nome">${escHtml(dados.nome ?? '')}</span>
            <span class="validacao-numero">Nº ${escHtml(dados.numero ?? '')}</span>
            <span class="validacao-refeicao">${escHtml(nomesItens)}</span>
        </div>
        <div class="validacao-pedido">#${dados.pedido_id ?? ''}</div>
    `;
    listaEl.prepend(item);
}
// ---- Sons de feedback ----
function tocarSom(sucesso) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = sucesso ? 880 : 220;
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch (e) {
        // browsers que bloqueiam áudio sem interação prévia — ignora silenciosamente
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
        expirado: {
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
    resultadoNumero.textContent = dados.numero ? `Nº ${dados.numero}` : '';

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

    // Melhoria #1 — scroll automático até ao resultado (evita a fila ter de rolar o ecrã)
    if (status !== 'loading') {
        resultadoCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        tocarSom(status === 'valido');
    }
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}