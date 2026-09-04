'use strict';

/* ======================================================================
   GERIR EMENTA — JavaScript
   ====================================================================== */

const CSRF_TOKEN = window.CSRF_TOKEN;

// Dias da semana PT
const DIAS_SEMANA = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];
const MESES_PT    = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
                     'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/* ── Estado global ───────────────────────────────────────────────────── */
let semanaOffset = 0;   // 0 = semana atual, -1 = semana anterior, +1 = próxima
let dadosSemana  = {};  // cache dos dados da semana atual

/* ── Utilitários ─────────────────────────────────────────────────────── */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Calcula a data de Segunda-feira da semana relativa ao offset dado.
 * offset=0 → semana atual, offset=-1 → semana anterior, etc.
 */
function getSegundaDaSemana(offset = 0) {
    const hoje  = new Date();
    const diaSemana = hoje.getDay(); // 0=Dom, 1=Seg, ...
    const diffSeg   = diaSemana === 0 ? -6 : 1 - diaSemana;
    const seg  = new Date(hoje);
    seg.setDate(hoje.getDate() + diffSeg + offset * 7);
    return seg;
}

function formatarData(date) {
    return date.toISOString().split('T')[0]; // YYYY-MM-DD
}

function formatarDataCurta(dataStr) {
    const [, m, d] = dataStr.split('-');
    return `${parseInt(d)} ${MESES_PT[parseInt(m) - 1]}`;
}

/**
 * Formata uma data/hora ISO (ex: "2026-09-11 14:30:00") para
 * exibição amigável em PT, ex: "sexta, 11 set às 14:30".
 */
function formatarDataHoraPt(isoStr) {
    const dt = new Date(isoStr.replace(' ', 'T'));
    const diasSemanaCompletos = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    const diaSemana = diasSemanaCompletos[dt.getDay()];
    const dia   = dt.getDate();
    const mes   = MESES_PT[dt.getMonth()];
    const horas = String(dt.getHours()).padStart(2, '0');
    const mins  = String(dt.getMinutes()).padStart(2, '0');
    return `${diaSemana}, ${dia} ${mes} às ${horas}:${mins}`;
}

/* ── Toast de feedback ───────────────────────────────────────────────── */
const toast = document.getElementById('ementaToast');
let toastTimer = null;

function mostrarToast(msg, tipo = 'sucesso') {
    toast.textContent = msg;
    toast.className   = `ementa-toast ementa-toast--${tipo} ementa-toast--visivel`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.remove('ementa-toast--visivel');
    }, 3500);
}

/* ── Navegação semanal ───────────────────────────────────────────────── */
document.getElementById('btnSemanaAnterior').addEventListener('click', () => {
    semanaOffset--;
    carregarSemana();
});

document.getElementById('btnSemanaProxima').addEventListener('click', () => {
    semanaOffset++;
    carregarSemana();
});

document.getElementById('btnSemanaHoje').addEventListener('click', () => {
    semanaOffset = 0;
    carregarSemana();
});

/* ── Carregar semana ─────────────────────────────────────────────────── */
async function carregarSemana() {
    const seg  = getSegundaDaSemana(semanaOffset);
    const sex  = new Date(seg);
    sex.setDate(seg.getDate() + 4);

    const inicio = formatarData(seg);
    const fim    = formatarData(sex);

    // Atualiza label
    document.getElementById('semanaLabel').textContent =
        `${formatarDataCurta(inicio)} – ${formatarDataCurta(fim)} ${seg.getFullYear()}`;

    // Mostra spinner
    document.getElementById('semanaGrid').innerHTML =
        '<div class="semana-loading"><i class="bi bi-arrow-repeat"></i> A carregar…</div>';

    try {
        const resp  = await fetch(`api/gerir_ementa_semana.php?inicio=${inicio}&fim=${fim}`);
        const dados = await resp.json();

        if (dados.status !== 'ok') throw new Error(dados.mensagem);

        dadosSemana = { inicio, fim, ...dados };
        renderizarGrelha(seg, dados);
        renderizarEstadoPublicacao(dados);
    } catch (err) {
        document.getElementById('semanaGrid').innerHTML =
            `<div class="semana-loading"><i class="bi bi-exclamation-triangle"></i> Erro ao carregar ementa.</div>`;
    }
}

/* ── Renderizar grelha ───────────────────────────────────────────────── */
function renderizarGrelha(seg, dados) {
    const grid = document.getElementById('semanaGrid');
    grid.innerHTML = '';

    // Agrupar pratos por data
    const pratosPorData = {};
    for (const p of dados.pratos) {
        if (!pratosPorData[p.data]) pratosPorData[p.data] = [];
        pratosPorData[p.data].push(p);
    }

    for (let i = 0; i < 5; i++) {
        const dia = new Date(seg);
        dia.setDate(seg.getDate() + i);
        const dataStr  = formatarData(dia);
        const pratos   = pratosPorData[dataStr] || [];
        const feriado  = dados.feriados[dataStr]       || null;
        const especial = dados.dias_especiais[dataStr]  || null;

        grid.appendChild(criarColunaDia(dataStr, DIAS_SEMANA[i], pratos, feriado, especial));
    }
}

/* ── Criar coluna de um dia ──────────────────────────────────────────── */
function criarColunaDia(dataStr, nomeDia, pratos, feriado, especial) {
    const col = document.createElement('div');
    col.className = 'dia-col';

    // Header
    const header = document.createElement('div');
    let headerClass = 'dia-header';
    if (feriado)  headerClass += ' dia-header--feriado';
    if (especial) headerClass += ' dia-header--especial';
    header.className = headerClass;
    header.innerHTML = `
        <span class="dia-nome">${escHtml(nomeDia)}</span>
        <span class="dia-data">${formatarDataCurta(dataStr)}</span>
        ${feriado  ? `<span class="dia-badge-feriado"><i class="bi bi-flag-fill"></i>${escHtml(feriado)}</span>` : ''}
        ${especial && !feriado ? `<span class="dia-badge-especial"><i class="bi bi-star-fill"></i>${escHtml(especial.RDE_MOTIVO || 'Dia especial')}</span>` : ''}
    `;
    col.appendChild(header);

    // Body
    const body = document.createElement('div');
    body.className = 'dia-body';
    body.dataset.data = dataStr;

    if (pratos.length === 0) {
        body.innerHTML = `
            <div class="dia-vazio">
                <i class="bi bi-calendar-plus"></i>
                <span>Sem ementa</span>
            </div>`;
    } else {
        // Agrupar por tipo
        const porTipo = {};
        for (const p of pratos) {
            if (!porTipo[p.tipo_nome]) porTipo[p.tipo_nome] = [];
            porTipo[p.tipo_nome].push(p);
        }

        for (const [tipoNome, lista] of Object.entries(porTipo)) {
            const grupo = document.createElement('div');
            grupo.className = 'tipo-grupo';
            grupo.innerHTML = `<span class="tipo-label">${escHtml(tipoNome)}</span>`;

            for (const prato of lista) {
                grupo.appendChild(criarCardPrato(prato));
            }
            body.appendChild(grupo);
        }
    }

    // Botão "Adicionar prato" — bloqueado em dias de feriado
    if (feriado) {
        const avisoFeriado = document.createElement('div');
        avisoFeriado.className = 'dia-feriado-aviso';
        avisoFeriado.innerHTML = '<i class="bi bi-flag-fill"></i> Feriado — sem refeições';
        body.appendChild(avisoFeriado);
    } else {
        const btnAdd = document.createElement('button');
        btnAdd.className = 'btn-add-prato';
        btnAdd.innerHTML = '<i class="bi bi-plus-lg"></i> Adicionar prato';
        btnAdd.addEventListener('click', () => mostrarFormAdição(body, dataStr, btnAdd));
        body.appendChild(btnAdd);
    }

    col.appendChild(body);
    return col;
}

/* ── Card de prato ───────────────────────────────────────────────────── */
function criarCardPrato(prato) {
    const card = document.createElement('div');
    card.className = `prato-card${prato.prato_dia ? ' prato-card--principal' : ''}`;
    card.dataset.rmId = prato.rm_id;

    // Span do nome (clicável para editar)
    const nomeSpan = document.createElement('button');
    nomeSpan.className = 'prato-nome';
    nomeSpan.textContent = prato.nome;
    nomeSpan.title = 'Clique para editar o nome';
    nomeSpan.addEventListener('click', () => ativarEdicaoNome(card, prato));
    card.appendChild(nomeSpan);

    // Botão apagar
    const btnApagar = document.createElement('button');
    btnApagar.className = 'prato-btn-apagar';
    btnApagar.title = prato.tem_reservas ? 'Não pode apagar — existem reservas' : 'Remover prato';
    btnApagar.innerHTML = '<i class="bi bi-trash"></i>';
    btnApagar.disabled = prato.tem_reservas;
    btnApagar.addEventListener('click', () => apagarPrato(prato.rm_id, card));
    card.appendChild(btnApagar);

    return card;
}

/* ── Edição inline do nome ───────────────────────────────────────────── */
function ativarEdicaoNome(card, prato) {
    // Já está a editar?
    if (card.querySelector('.prato-nome-input')) return;

    const nomeSpan = card.querySelector('.prato-nome');
    const input    = document.createElement('input');
    input.type      = 'text';
    input.className = 'prato-nome-input';
    input.value     = prato.nome;

    card.replaceChild(input, nomeSpan);
    input.focus();
    input.select();

    const guardar = async () => {
        const novoNome = input.value.trim();
        if (!novoNome || novoNome === prato.nome) {
            // Cancela sem guardar
            card.replaceChild(nomeSpan, input);
            return;
        }

        try {
            const resp  = await fetch('api/gerir_ementa_atualizar.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : new URLSearchParams({ rm_id: prato.rm_id, nome: novoNome, csrf_token: CSRF_TOKEN }),
            });
            const dados = await resp.json();

            if (dados.status === 'ok') {
                prato.nome        = novoNome;
                nomeSpan.textContent = novoNome;
                card.replaceChild(nomeSpan, input);
                mostrarToast('Nome atualizado.');
            } else {
                mostrarToast(dados.mensagem || 'Erro ao guardar.', 'erro');
                card.replaceChild(nomeSpan, input);
            }
        } catch {
            mostrarToast('Erro de ligação.', 'erro');
            card.replaceChild(nomeSpan, input);
        }
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter')  guardar();
        if (e.key === 'Escape') card.replaceChild(nomeSpan, input);
    });
    input.addEventListener('blur', guardar);
}

/* ── Apagar prato ────────────────────────────────────────────────────── */
async function apagarPrato(rmId, card) {
    if (!confirm('Tem a certeza que quer remover este prato da ementa?')) return;

    try {
        const resp  = await fetch('api/gerir_ementa_apagar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({ rm_id: rmId, csrf_token: CSRF_TOKEN }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            card.style.transition = 'opacity 0.2s';
            card.style.opacity    = '0';
            setTimeout(() => {
                const body = card.closest('.dia-body');
                card.remove();
                // Se o grupo ficou vazio, remove-o também
                const grupo = card.parentElement;
                if (grupo && grupo.classList.contains('tipo-grupo') && !grupo.querySelector('.prato-card')) {
                    grupo.remove();
                }
                // Se não restam pratos, mostra estado vazio
                if (body && !body.querySelector('.prato-card')) {
                    const vazio = document.createElement('div');
                    vazio.className = 'dia-vazio';
                    vazio.innerHTML = '<i class="bi bi-calendar-plus"></i><span>Sem ementa</span>';
                    body.insertBefore(vazio, body.querySelector('.btn-add-prato, .dia-feriado-aviso'));
                }
            }, 200);
            mostrarToast('Prato removido.');
        } else if (dados.status === 'tem_pedidos') {
            mostrarToast('Não é possível remover — já existem reservas.', 'aviso');
        } else {
            mostrarToast(dados.mensagem || 'Erro ao remover.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    }
}

/* ── Formulário de adição inline ─────────────────────────────────────── */
function mostrarFormAdição(body, dataStr, btnAdd) {
    // Fecha outro form aberto nesta coluna
    const formExistente = body.querySelector('.form-add-prato');
    if (formExistente) { formExistente.remove(); return; }

    const form = document.createElement('div');
    form.className = 'form-add-prato';
    form.innerHTML = `
        <select id="selectTipo_${dataStr}">
            <option value="">— Tipo de prato —</option>
            <optgroup label="Prato principal">
                ${window.TIPOS_REFEICAO.filter(t => t.prato_dia).map(t =>
                    `<option value="${t.id}">${escHtml(t.nome)}</option>`
                ).join('')}
            </optgroup>
            <optgroup label="Acompanhamento">
                ${window.TIPOS_REFEICAO.filter(t => !t.prato_dia).map(t =>
                    `<option value="${t.id}">${escHtml(t.nome)}</option>`
                ).join('')}
            </optgroup>
        </select>
        <input type="text" id="inputNome_${dataStr}" placeholder="Nome do prato…" maxlength="150">
        <div class="form-add-acoes">
            <button class="btn-add-confirmar" id="btnConfirmar_${dataStr}">
                <i class="bi bi-check-lg"></i> Adicionar
            </button>
            <button class="btn-add-cancelar" id="btnCancelar_${dataStr}">Cancelar</button>
        </div>
    `;

    body.insertBefore(form, btnAdd);

    form.querySelector(`#inputNome_${dataStr}`).focus();

    form.querySelector(`#btnCancelar_${dataStr}`).addEventListener('click', () => form.remove());

    form.querySelector(`#btnConfirmar_${dataStr}`).addEventListener('click', () =>
        submeterNovoPrato(form, dataStr, body, btnAdd)
    );

    form.querySelector(`#inputNome_${dataStr}`).addEventListener('keydown', (e) => {
        if (e.key === 'Enter')  submeterNovoPrato(form, dataStr, body, btnAdd);
        if (e.key === 'Escape') form.remove();
    });
}

async function submeterNovoPrato(form, dataStr, body, btnAdd) {
    const tipoId = form.querySelector('select').value;
    const nome   = form.querySelector('input[type="text"]').value.trim();

    if (!tipoId || !nome) {
        mostrarToast('Preencha o tipo e o nome do prato.', 'aviso');
        return;
    }

    const btnConfirmar = form.querySelector('.btn-add-confirmar');
    btnConfirmar.disabled = true;

    try {
        const resp  = await fetch('api/gerir_ementa_criar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({ nome, tipo_id: tipoId, data: dataStr, csrf_token: CSRF_TOKEN }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            form.remove();
            mostrarToast(`"${nome}" adicionado à ementa.`);
            // Recarrega a semana para refletir o novo prato
            carregarSemana();
        } else if (dados.status === 'dia_feriado') {
            mostrarToast('Não é possível adicionar pratos num feriado.', 'aviso');
            btnConfirmar.disabled = false;
        } else {
            mostrarToast(dados.mensagem || 'Erro ao adicionar.', 'erro');
            btnConfirmar.disabled = false;
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
        btnConfirmar.disabled = false;
    }
}

/* ======================================================================
   PUBLICAÇÃO DA SEMANA
   ====================================================================== */

/**
 * Atualiza a barra de estado de publicação no topo da página, com base
 * nos dados devolvidos por gerir_ementa_semana.php (que deve incluir
 * um bloco "publicacao": { publicada, total, publicados, visivel_em }).
 */
function renderizarEstadoPublicacao(dados) {
    const barra = document.getElementById('publicacaoBarra');
    if (!barra) return; // elemento ainda não existe no HTML — nada a fazer

    const pub = dados.publicacao || { publicada: false, total: 0, publicados: 0, visivel_em: null, ja_visivel: false };

    let texto;
    let classe;

    if (pub.total === 0) {
        texto  = 'Sem pratos configurados nesta semana.';
        classe = 'publicacao-barra--vazia';
    } else if (!pub.publicada) {
        texto  = `Rascunho — ${pub.publicados}/${pub.total} pratos publicados.`;
        classe = 'publicacao-barra--rascunho';
    } else if (pub.ja_visivel) {
        texto  = 'Publicada e já visível para os alunos.';
        classe = 'publicacao-barra--visivel';
    } else {
        texto  = `Publicada — abre automaticamente ${formatarDataHoraPt(pub.visivel_em)}.`;
        classe = 'publicacao-barra--agendada';
    }

    barra.className   = `publicacao-barra ${classe}`;
    barra.textContent = texto;

    // Botão "Publicar" só faz sentido se ainda não estiver 100% publicada
    const btnPublicar = document.getElementById('btnPublicarSemana');
    if (btnPublicar) {
        btnPublicar.style.display = pub.total > 0 ? '' : 'none';
        btnPublicar.textContent   = pub.publicada ? 'Republicar semana' : 'Publicar semana';
    }

    // Botão "Despublicar" só aparece se já houver algo publicado
    const btnDespublicar = document.getElementById('btnDespublicarSemana');
    if (btnDespublicar) {
        btnDespublicar.style.display = pub.publicados > 0 ? '' : 'none';
    }
}

/**
 * Abre o modal de publicação com as duas opções de abertura.
 */
function abrirModalPublicar() {
    const modal = document.getElementById('modalPublicar');
    if (!modal) return;
    modal.classList.add('modal--visivel');

    // Garante que a opção "padrão" fica selecionada por defeito sempre que abre
    const radioPadrao = modal.querySelector('input[name="modoAbertura"][value="padrao"]');
    if (radioPadrao) radioPadrao.checked = true;
}

function fecharModalPublicar() {
    const modal = document.getElementById('modalPublicar');
    if (!modal) return;
    modal.classList.remove('modal--visivel');
}

/**
 * Confirma a publicação da semana atualmente em vista, no modo escolhido.
 */
async function confirmarPublicacao() {
    const modal = document.getElementById('modalPublicar');
    const modoSelecionado = modal.querySelector('input[name="modoAbertura"]:checked');
    const modoAbertura = modoSelecionado ? modoSelecionado.value : 'padrao';

    const btnConfirmar = document.getElementById('btnConfirmarPublicar');
    if (btnConfirmar) btnConfirmar.disabled = true;

    try {
        const resp = await fetch('api/gerir_ementa_publicar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                inicio       : dadosSemana.inicio,
                fim          : dadosSemana.fim,
                modo_abertura: modoAbertura,
                csrf_token   : CSRF_TOKEN,
            }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            fecharModalPublicar();
            mostrarToast(
                modoAbertura === 'imediato'
                    ? 'Semana publicada — já visível para os alunos.'
                    : 'Semana publicada — abre sexta às 14:30.'
            );
            carregarSemana();
        } else {
            mostrarToast(dados.mensagem || 'Erro ao publicar.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    } finally {
        if (btnConfirmar) btnConfirmar.disabled = false;
    }
}

/**
 * Despublica a semana em vista (volta a rascunho, RM_PUBLICADO = 0).
 */
async function despublicarSemana() {
    if (!confirm('Tem a certeza que quer despublicar esta semana? Os alunos deixam de a ver.')) return;

    try {
        const resp = await fetch('api/gerir_ementa_publicar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                inicio     : dadosSemana.inicio,
                fim        : dadosSemana.fim,
                acao       : 'despublicar',
                csrf_token : CSRF_TOKEN,
            }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            mostrarToast('Semana despublicada.');
            carregarSemana();
        } else {
            mostrarToast(dados.mensagem || 'Erro ao despublicar.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    }
}

/* ── Listeners dos controlos de publicação (só se existirem no HTML) ──── */
document.getElementById('btnPublicarSemana')?.addEventListener('click', abrirModalPublicar);
document.getElementById('btnDespublicarSemana')?.addEventListener('click', despublicarSemana);
document.getElementById('btnCancelarPublicar')?.addEventListener('click', fecharModalPublicar);
document.getElementById('btnConfirmarPublicar')?.addEventListener('click', confirmarPublicacao);

/* ── Inicialização ───────────────────────────────────────────────────── */
carregarSemana();