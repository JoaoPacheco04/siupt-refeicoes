// Configurações globais e referências da API
const API_URL    = window.API_URL;
const CSRF_TOKEN = window.CSRF_TOKEN;

// ── Escape de HTML (Prevenção XSS) ──────────────────────────────────────
/**
 * Escapa caracteres HTML antes de inserir texto no DOM com innerHTML,
 * prevenindo ataques de injeção de código (XSS).
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Elementos principais da interface de validação ──────────────────────
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

/* ======================================================================
   GESTÃO DO SCANNER DE CÂMARA
   ====================================================================== */

// ── Inicialização do leitor ótico por câmara ────────────────────────────
const html5QrCode = new Html5Qrcode("qr-reader");
const configScan  = { fps: 10, qrbox: { width: 220, height: 220 } };

/**
 * Inicia o fluxo de captura de vídeo para leitura automática de QR Codes,
 * gerindo pausas temporizadas e o retomar automático após cada leitura bem-sucedida.
 */
html5QrCode.start(
    { facingMode: "environment" },
    configScan,
    (decodedText) => {
        html5QrCode.pause(true);
        document.getElementById('btnValidarSeguinte').style.display = 'inline-flex';
        validarQrCode(decodedText);

        clearTimeout(timeoutRetomarAutomatico);
        timeoutRetomarAutomatico = setTimeout(() => {
            if (!cameraDisponivel) return;
            html5QrCode.resume();
            document.getElementById('btnValidarSeguinte').style.display = 'none';
        }, 2500);
    },
    () => { /* Erro de leitura em tempo real ignorado para evitar poluição da consola */ }
).then(() => {
    cameraDisponivel = true;
}).catch(err => {
    console.warn('Câmara não disponível:', err);
    mostrarAvisoSemCamara();
});

/**
 * Apresenta uma mensagem de aviso no elemento do leitor caso
 * o dispositivo não suporte ou não conceda permissão de câmara.
 */
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

/**
 * Permite ao operador retomar manualmente a câmara de forma imediata
 * sem aguardar pelo temporizador de salto automático.
 */
document.getElementById('btnValidarSeguinte').addEventListener('click', () => {
    if (!cameraDisponivel) return;
    clearTimeout(timeoutRetomarAutomatico);
    html5QrCode.resume();
    document.getElementById('btnValidarSeguinte').style.display = 'none';
    resultadoCard.classList.remove('visivel');
});

/* ======================================================================
   INPUT MANUAL DE CÓDIGOS
   ====================================================================== */

// ── Submissão através do campo de texto ─────────────────────────────────
/**
 * Trata o clique no botão para validar o código introduzido manualmente.
 */
document.getElementById('btnValidarManual').addEventListener('click', () => {
    const codigo = inputManual.value.trim();
    if (!codigo) return;
    validarQrCode(codigo);
    inputManual.value = '';
});

/**
 * Permite submeter o código premindo a tecla "Enter" no teclado físico ou pistola laser.
 */
inputManual.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('btnValidarManual').click();
});

inputManual.focus();

/* ======================================================================
   COMUNICAÇÃO COM A API DE VALIDAÇÃO
   ====================================================================== */

// ── Processamento do QR Code ────────────────────────────────────────────
/**
 * Envia o código capturado ou inserido para validação no servidor,
 * atualizando o estado visual e o contador diário consoante a resposta.
 */
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

/* ======================================================================
   ATUALIZAÇÃO DA INTERFACE DE RESULTADOS E HISTÓRICO
   ====================================================================== */

/**
 * Insere uma nova validação bem-sucedida no topo da lista em tempo real.
 */
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

/**
 * Emite um sinal sonoro sintetizado (beep) com frequência diferente
 * consoante o sucesso ou o insucesso da validação.
 */
function tocarSom(sucesso) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = sucesso ? 880 : 220; // Tom agudo para sucesso, grave para erro
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch (e) {
        // Ignora falhas de autoplay ou restrições de contexto de áudio do browser
    }
}

/**
 * Altera dinamicamente os elementos visuais do cartão de resultado
 * com base no estado devolvido pela API (válido, expirado, duplicado, etc.).
 */
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

    // Executa scroll automático até ao resultado e reproduz o alerta sonoro
    if (status !== 'loading') {
        resultadoCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        tocarSom(status === 'valido');
    }
}

/* ======================================================================
   N2 — NAVEGAÇÃO POR DATA NAS VALIDAÇÕES
   ====================================================================== */

const inputData       = document.getElementById('inputDataValidacoes');
const btnDataAnterior = document.getElementById('btnDataAnterior');
const btnDataSeguinte = document.getElementById('btnDataSeguinte');
const tituloLista     = document.getElementById('validacoesListaTitulo');
const btnExportarLog  = document.getElementById('btnExportarLog'); // NOVO — item 4
const hojeISO         = new Date().toISOString().split('T')[0];

/**
 * Carrega via fetch as validações para a data indicada e re-renderiza a lista.
 */
async function carregarValidacoesPorData(data) {
    if (!listaEl) return;

    // Feedback de loading
    listaEl.innerHTML = `
        <p class="lista-validacoes-vazia">
            <i class="bi bi-hourglass-split"></i> A carregar…
        </p>`;

    // NOVO: feedback visual no contador enquanto a nova data carrega
    if (contadorEl) contadorEl.classList.add('contador-a-atualizar');

    // Atualiza o título
    if (tituloLista) {
        tituloLista.textContent = data === hojeISO
            ? 'Validações de hoje'
            : `Validações de ${new Date(data + 'T00:00:00').toLocaleDateString('pt-PT', { day: 'numeric', month: 'long', year: 'numeric' })}`;
    }

    // Atualiza estado dos botões de navegação
    if (btnDataSeguinte) btnDataSeguinte.disabled = (data >= hojeISO);

    // NOVO — item 4: mantém o link de exportar sincronizado com a data selecionada
    if (btnExportarLog) {
        btnExportarLog.href = `api/exportar_validacoes.php?data=${data}`;
    }

    try {
        const resposta = await fetch('api/listar_validacoes_por_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ data, csrf_token: CSRF_TOKEN })
        });
        const dados = await resposta.json();

        if (dados.status !== 'ok') {
            listaEl.innerHTML = `<p class="lista-validacoes-vazia"><i class="bi bi-exclamation-circle"></i> Erro ao carregar validações.</p>`;
            return;
        }

        if (dados.validacoes.length === 0) {
            listaEl.innerHTML = `<p class="lista-validacoes-vazia" id="listaVazia"><i class="bi bi-inbox"></i> Nenhuma validação neste dia.</p>`;
            return;
        }

        listaEl.innerHTML = '';
        dados.validacoes.forEach(v => {
            const hora = new Date(v.RV_DATA_VALIDACAO).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
            const item = document.createElement('div');
            item.className = 'validacao-item';
            item.innerHTML = `
                <div class="validacao-hora">${hora}</div>
                <div class="validacao-info">
                    <span class="validacao-nome">${escHtml(v.U_NOME ?? '')}</span>
                    <span class="validacao-numero">Nº ${escHtml(v.U_BICC ?? '')}</span>
                    <span class="validacao-refeicao">${escHtml(v.itens ?? 'Sem itens registados')}</span>
                </div>
                <div class="validacao-pedido">#${v.RP_ID ?? ''}</div>
            `;
            listaEl.appendChild(item);
        });

        // Atualiza o contador (só para "hoje")
        if (data === hojeISO && contadorEl) {
            contadorEl.textContent = dados.total;
        }
        // NOVO: remove o estado de "a atualizar" independentemente do resultado
        if (contadorEl) contadorEl.classList.remove('contador-a-atualizar');

    } catch (e) {
        listaEl.innerHTML = `<p class="lista-validacoes-vazia"><i class="bi bi-wifi-off"></i> Erro de ligação.</p>`;
        // NOVO: garante que o contador não fica preso em estado de loading
        if (contadorEl) contadorEl.classList.remove('contador-a-atualizar');
    }
}

// ── Eventos de navegação por data ─────────────────────────────────────────
if (inputData) {
    inputData.addEventListener('change', () => {
        const data = inputData.value;
        if (data && data <= hojeISO) {
            carregarValidacoesPorData(data);
        }
    });
}

if (btnDataAnterior) {
    btnDataAnterior.addEventListener('click', () => {
        if (!inputData) return;
        const atual = new Date(inputData.value + 'T00:00:00');
        atual.setDate(atual.getDate() - 1);
        inputData.value = atual.toISOString().split('T')[0];
        carregarValidacoesPorData(inputData.value);
    });
}

if (btnDataSeguinte) {
    btnDataSeguinte.addEventListener('click', () => {
        if (!inputData) return;
        const atual = new Date(inputData.value + 'T00:00:00');
        atual.setDate(atual.getDate() + 1);
        const nova = atual.toISOString().split('T')[0];
        if (nova <= hojeISO) {
            inputData.value = nova;
            carregarValidacoesPorData(nova);
        }
    });
}

/* escHtml — já declarada no início deste ficheiro */