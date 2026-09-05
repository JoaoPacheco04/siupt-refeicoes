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

/**
 * Converte 'YYYY-MM-DD' em 'DD/MM/YYYY' para apresentação ao utilizador.
 */
function formatarDataPt(iso) {
    if (!iso) return iso;
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
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

    // Atualiza o contador "por levantar hoje" com o valor real do servidor
    // (correto mesmo com vários atendentes a validar em simultâneo)
    const contadorPorLevantar = document.getElementById('contadorPorLevantar');
    if (contadorPorLevantar && typeof dados.refeicoes_por_levantar === 'number') {
        contadorPorLevantar.textContent = dados.refeicoes_por_levantar;
    }

    inputManual.value = '';
    inputManual.focus();
    inputManual.select();
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

let somAtivo = localStorage.getItem('validar_som_ativo') !== 'false';
const btnToggleSom = document.getElementById('btnToggleSom');
const iconeSom = document.getElementById('iconeSom');
const textoSom = document.getElementById('textoSom');

function actualizarEstadoSomUI() {
    if (!btnToggleSom) return;
    if (somAtivo) {
        btnToggleSom.classList.remove('btn-toggle-som--mudo');
        if (iconeSom) iconeSom.className = 'bi bi-volume-up-fill';
        if (textoSom) textoSom.textContent = 'Som ativo';
    } else {
        btnToggleSom.classList.add('btn-toggle-som--mudo');
        if (iconeSom) iconeSom.className = 'bi bi-volume-mute-fill';
        if (textoSom) textoSom.textContent = 'Som desativado';
    }
}

if (btnToggleSom) {
    btnToggleSom.addEventListener('click', () => {
        somAtivo = !somAtivo;
        localStorage.setItem('validar_som_ativo', somAtivo ? 'true' : 'false');
        actualizarEstadoSomUI();
        if (somAtivo) tocarSom(true);
    });
    actualizarEstadoSomUI();
}

/**
 * Manter o campo manual focado para pistolas leitoras USB/Bluetooth.
 */
document.addEventListener('click', (e) => {
    if (!e.target.closest('a, button, input, select, textarea, .validacao-item')) {
        inputManual?.focus();
    }
});

/**
 * Emite um sinal sonoro sintetizado com frequência diferente
 * consoante o sucesso ou o insucesso da validação.
 */
function tocarSom(sucesso) {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;

        if (sucesso) {
            // Tom ascendente elegante: Ré5 (587.33Hz) -> Lá5 (880Hz)
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';

            osc.frequency.setValueAtTime(587.33, now);
            osc.frequency.setValueAtTime(880, now + 0.08);

            gain.gain.setValueAtTime(0.18, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.28);

            osc.start(now);
            osc.stop(now + 0.28);
            osc.onended = () => ctx.close();
        } else {
            // Tom de alerta/erro mais grave
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sawtooth';

            osc.frequency.setValueAtTime(220, now);
            osc.frequency.exponentialRampToValueAtTime(130, now + 0.25);

            gain.gain.setValueAtTime(0.16, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);

            osc.start(now);
            osc.stop(now + 0.25);
            osc.onended = () => ctx.close();
        }
    } catch (e) {
        // Silencioso se restrito pelo browser
    }
}

/**
 * Altera dinamicamente os elementos visuais do cartão de resultado
 * com base no estado devolvido pela API (válido, expirado, duplicado, etc.).
 */
function mostrarResultado(status, dados = {}) {
    resultadoCard.className = 'resultado-card visivel';
    resultadoLinhas.innerHTML = '';

    if (status !== 'loading') {
        const ehSucesso = status === 'valido';
        if (somAtivo) {
            tocarSom(ehSucesso);
        }
        if (navigator.vibrate) {
            navigator.vibrate(ehSucesso ? [120] : [100, 60, 100]);
        }
    }

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
            estado: dados.mensagem || 'Pagamento não confirmado. Não é possível consumir.',
            nome: dados.nome ?? ''
        },
        utilizado: {
            classe: 'erro',
            icone: '⚠️',
            estado: dados.data_validacao
                ? `Senha já consumida em ${dados.data_validacao}${dados.validado_por ? ' (por ' + dados.validado_por + ')' : ''}. Não pode ser reutilizada!`
                : (dados.mensagem || 'Esta senha já foi consumida e não pode ser reutilizada!'),
            nome: dados.nome ?? ''
        },
        expirado: {
            classe: 'erro',
            icone: '❌',
            estado: dados.data_refeicao
                ? `Senha expirada — era para ${formatarDataPt(dados.data_refeicao)}. As senhas só podem ser consumidas no dia agendado.`
                : (dados.mensagem || 'Pedido expirado (data de refeição já passou)'),
            nome: dados.nome ?? ''
        },
        dia_errado: {
            classe: 'erro',
            icone: '📅',
            estado: dados.data_refeicao
                ? `Senha inválida para hoje! Esta refeição foi comprada para ${formatarDataPt(dados.data_refeicao)} e só pode ser consumida nesse dia.`
                : (dados.mensagem || 'Esta refeição não é para hoje!'),
            nome: dados.nome ?? ''
        },
        invalido: {
            classe: 'erro',
            icone: '🚫',
            estado: dados.mensagem || 'QR code ou código inválido',
            nome: ''
        },
        erro: {
            classe: 'erro',
            icone: '❌',
            estado: dados.mensagem || 'Erro ao processar validação',
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

    if (Array.isArray(dados.linhas) && dados.linhas.length > 0) {
        dados.linhas.forEach(linha => {
            const li = document.createElement('li');
            li.innerHTML = `
                <span class="item-nome">${escHtml(linha.RM_NOME)}</span>
                <span class="item-tipo">${escHtml(linha.RTP_NOME)}</span>
            `;
            resultadoLinhas.appendChild(li);
        });
    }

    // Executa scroll automático até ao resultado
    if (status !== 'loading') {
        resultadoCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/* ======================================================================
   NAVEGAÇÃO POR DATA NAS VALIDAÇÕES
   ====================================================================== */

const inputData       = document.getElementById('inputDataValidacoes');
const btnDataAnterior = document.getElementById('btnDataAnterior');
const btnDataSeguinte = document.getElementById('btnDataSeguinte');
const tituloLista     = document.getElementById('validacoesListaTitulo');
const btnExportarLog  = document.getElementById('btnExportarLog');
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

    if (contadorEl) contadorEl.classList.add('contador-a-atualizar');

    // Atualiza o título
    if (tituloLista) {
        tituloLista.textContent = data === hojeISO
            ? 'Validações de hoje'
            : `Validações de ${new Date(data + 'T00:00:00').toLocaleDateString('pt-PT', { day: 'numeric', month: 'long', year: 'numeric' })}`;
    }

    // Atualiza estado dos botões de navegação
    if (btnDataSeguinte) btnDataSeguinte.disabled = (data >= hojeISO);

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
        if (contadorEl) contadorEl.classList.remove('contador-a-atualizar');

    } catch (e) {
        listaEl.innerHTML = `<p class="lista-validacoes-vazia"><i class="bi bi-wifi-off"></i> Erro de ligação.</p>`;
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

