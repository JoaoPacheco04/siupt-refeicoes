'use strict';

/* ======================================================================
   GERIR EMENTA — JavaScript
   ====================================================================== */

const CSRF_TOKEN = window.CSRF_TOKEN;

// Dias da semana PT
const DIAS_SEMANA = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];
const MESES_PT    = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
                     'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/* ── Estado global ─────────────────────────────────────────────── */
let semanaOffset = 0;   // inicializado abaixo com calcularOffsetInicial()
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
    const diaSemana = hoje.getDay(); // 0=Dom, 1=Seg, ..., 5=Sex, 6=Sáb
    const diffSeg   = diaSemana === 0 ? -6 : 1 - diaSemana;
    const seg  = new Date(hoje);
    seg.setDate(hoje.getDate() + diffSeg + offset * 7);
    return seg;
}

/**
 * Calcula o offset inicial a apresentar ao abrir a página.
 * A partir de sexta-feira às 08:00 (ou fim de semana), a semana atual
 * já está encerrada para gestão — avança automaticamente para a próxima.
 * O administrador pode sempre navegar para outra semana com as setas.
 */
function calcularOffsetInicial() {
    const hoje      = new Date();
    const diaSemana = hoje.getDay(); // 5=Sex, 6=Sáb, 0=Dom
    const hora      = hoje.getHours();
    if ((diaSemana === 5 && hora >= 8) || diaSemana === 6 || diaSemana === 0) {
        return 1;
    }
    return 0;
}

function formatarData(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
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

    // Mostra/oculta botão "Voltar à semana atual" (só aparece quando navegou para outra semana)
    const btnHoje = document.getElementById('btnSemanaHoje');
    if (btnHoje) {
        btnHoje.style.display = semanaOffset === 0 ? 'none' : 'inline-flex';
    }

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
        const feriado  = dados.feriados[dataStr]      || null;
        const especial = dados.dias_especiais[dataStr] || null;
        const reservas = dados.reservas_por_data       || {};

        grid.appendChild(criarColunaDia(dataStr, DIAS_SEMANA[i], pratos, feriado, especial, reservas));
    }
}

/* ── Criar coluna de um dia ──────────────────────────────────────────── */
function criarColunaDia(dataStr, nomeDia, pratos, feriado, especial, reservas = {}) {
    const col = document.createElement('div');
    col.className = 'dia-col';

    const hojeStr = formatarData(new Date());
    const ehPassado = dataStr < hojeStr;

    // Header
    const header = document.createElement('div');
    let headerClass = 'dia-header';
    if (feriado)   headerClass += ' dia-header--feriado';
    if (especial)  headerClass += ' dia-header--especial';
    if (ehPassado) headerClass += ' dia-header--passado';
    header.className = headerClass;
    const nReservas = reservas[dataStr] || 0;
    header.innerHTML = `
        <span class="dia-nome">${escHtml(nomeDia)}</span>
        <span class="dia-data">${formatarDataCurta(dataStr)}</span>
        ${feriado   ? `<span class="dia-badge-feriado"><i class="bi bi-flag-fill"></i>${escHtml(feriado)}</span>` : ''}
        ${ehPassado && !feriado ? `<span class="dia-badge-passado"><i class="bi bi-clock-history"></i> Encerrado</span>` : ''}
        ${especial && !feriado && !ehPassado ? `<span class="dia-badge-especial"><i class="bi bi-star-fill"></i>${escHtml(especial.RDE_MOTIVO || 'Dia especial')}</span>` : ''}
        ${nReservas > 0 ? `<span class="dia-badge-reservas"><i class="bi bi-people-fill"></i>${nReservas} reserva${nReservas !== 1 ? 's' : ''}</span>` : ''}
    `;

    // Ações do cabeçalho (apenas para dias presentes ou futuros e que não sejam feriado)
    if (!feriado && !ehPassado) {
        const acoesHeader = document.createElement('div');
        acoesHeader.className = 'dia-header-acoes';

        // Tipos de refeição válidos para o dia
        const todosTipos = (window.TIPOS_REFEICAO || []).filter(t => t.nome !== 'Menu Completo');
        const tiposComPrato = new Set(pratos.map(p => p.tipo_id));
        const todosPreenchidos = todosTipos.length > 0 && todosTipos.every(t => tiposComPrato.has(t.id));

        // Botão de guardar todos os pratos preenchidos do dia
        // Só aparece enquanto faltarem pratos por preencher! Quando todos tiverem o certo, desaparece!
        if (!todosPreenchidos) {
            const btnGuardarDia = document.createElement('button');
            btnGuardarDia.className = 'btn-guardar-dia';
            btnGuardarDia.title = `Guardar pratos preenchidos de ${nomeDia}`;
            btnGuardarDia.innerHTML = '<i class="bi bi-check2-all"></i>';
            btnGuardarDia.addEventListener('click', (e) => {
                e.stopPropagation();
                guardarTodosPratosDia(col, dataStr, nomeDia, btnGuardarDia);
            });
            acoesHeader.appendChild(btnGuardarDia);
        }

        // Botão de copiar pratos deste dia para outro dia da semana
        if (pratos.length > 0) {
            const btnCopiarDia = document.createElement('button');
            btnCopiarDia.className = 'btn-copiar-dia';
            btnCopiarDia.title = `Copiar pratos de ${nomeDia} para outro dia`;
            btnCopiarDia.innerHTML = '<i class="bi bi-copy"></i>';
            btnCopiarDia.addEventListener('click', (e) => {
                e.stopPropagation();
                abrirModalCopiarDia(dataStr, nomeDia);
            });
            acoesHeader.appendChild(btnCopiarDia);
        }

        // Botão discreto de limpar pratos do dia (só se tiver pratos)
        if (pratos.length > 0) {
            const btnLimparDia = document.createElement('button');
            btnLimparDia.className = 'btn-limpar-dia';
            btnLimparDia.title = `Limpar todos os pratos de ${nomeDia}`;
            btnLimparDia.innerHTML = '<i class="bi bi-trash3"></i>';
            btnLimparDia.addEventListener('click', (e) => {
                e.stopPropagation();
                if (confirm(`Tem a certeza que deseja remover todos os pratos de ${nomeDia} (${formatarDataCurta(dataStr)})?\nOs pratos com reservas não serão apagados.`)) {
                    limparPeriodo(dataStr, dataStr, nomeDia);
                }
            });
            acoesHeader.appendChild(btnLimparDia);
        }

        header.appendChild(acoesHeader);
    }

    col.appendChild(header);

    // Body
    const body = document.createElement('div');
    body.className = 'dia-body';
    body.dataset.data = dataStr;

    // Se for feriado, exibe aviso e bloqueia
    if (feriado) {
        const avisoFeriado = document.createElement('div');
        avisoFeriado.className = 'dia-feriado-aviso';
        avisoFeriado.innerHTML = '<i class="bi bi-flag-fill"></i> Feriado — sem refeições';
        body.appendChild(avisoFeriado);
    } else if (ehPassado) {
        // Dia anterior a hoje — bloqueado contra novas alterações
        if (pratos.length === 0) {
            const avisoPassado = document.createElement('div');
            avisoPassado.className = 'dia-passado-aviso';
            avisoPassado.innerHTML = '<i class="bi bi-clock-history"></i> Dia anterior — sem ementa';
            body.appendChild(avisoPassado);
        } else {
            const ORDEM_LOGICA = ['carne', 'peixe', 'vegetariano', 'sopa', 'sobremesa', 'bebida'];
            const todosTipos = (window.TIPOS_REFEICAO || [])
                .filter(t => t.nome !== 'Menu Completo')
                .sort((a, b) => {
                    const idxA = ORDEM_LOGICA.indexOf(a.nome.toLowerCase().trim());
                    const idxB = ORDEM_LOGICA.indexOf(b.nome.toLowerCase().trim());
                    return (idxA !== -1 ? idxA : 99) - (idxB !== -1 ? idxB : 99);
                });

            const pratosPorTipo = {};
            for (const p of pratos) {
                if (!pratosPorTipo[p.tipo_id]) pratosPorTipo[p.tipo_id] = [];
                pratosPorTipo[p.tipo_id].push(p);
            }

            const listaSlots = document.createElement('div');
            listaSlots.className = 'dia-slots-lista';

            todosTipos.forEach(tipo => {
                const lista = pratosPorTipo[tipo.id] || [];
                if (lista.length > 0) {
                    const slot = criarSlotTipo(body, dataStr, tipo, lista, true);
                    if (slot) listaSlots.appendChild(slot);
                }
            });
            body.appendChild(listaSlots);

            // Pílulas indicativas em modo só de leitura
            body.appendChild(criarAreaPillsStatus(body, dataStr, pratos, todosTipos, true));
        }
    } else {
        // Dia presente ou futuro — edição normal
        const ORDEM_LOGICA = ['carne', 'peixe', 'vegetariano', 'sopa', 'sobremesa', 'bebida'];
        const todosTipos = (window.TIPOS_REFEICAO || [])
            .filter(t => t.nome !== 'Menu Completo')
            .sort((a, b) => {
                const idxA = ORDEM_LOGICA.indexOf(a.nome.toLowerCase().trim());
                const idxB = ORDEM_LOGICA.indexOf(b.nome.toLowerCase().trim());
                return (idxA !== -1 ? idxA : 99) - (idxB !== -1 ? idxB : 99);
            });

        // Mapeia prato(s) existente(s) por tipo_id
        const pratosPorTipo = {};
        for (const p of pratos) {
            if (!pratosPorTipo[p.tipo_id]) pratosPorTipo[p.tipo_id] = [];
            pratosPorTipo[p.tipo_id].push(p);
        }

        // Slots estruturados por tipo de refeição
        const listaSlots = document.createElement('div');
        listaSlots.className = 'dia-slots-lista';

        todosTipos.forEach(tipo => {
            const lista = pratosPorTipo[tipo.id] || [];
            const slot = criarSlotTipo(body, dataStr, tipo, lista, false);
            if (slot) listaSlots.appendChild(slot);
        });
        body.appendChild(listaSlots);

        // Em baixo: pílulas de estado ("em baixo fica com o certo")
        body.appendChild(criarAreaPillsStatus(body, dataStr, pratos, todosTipos, false));
    }

    col.appendChild(body);
    return col;
}

/* ── Card de prato ───────────────────────────────────────────────────── */
function criarCardPrato(prato, readOnly = false) {
    const card = document.createElement('div');
    card.className = `prato-card${prato.prato_dia ? ' prato-card--principal' : ''}${readOnly ? ' prato-card--readonly' : ''}`;
    card.dataset.rmId   = prato.rm_id;
    card.dataset.tipoId = prato.tipo_id;

    // Ícone de estado "feito" / certo
    const iconeFeito = document.createElement('span');
    iconeFeito.className = 'prato-status-check';
    iconeFeito.title = 'Prato configurado';
    iconeFeito.innerHTML = '<i class="bi bi-check2"></i>';
    card.appendChild(iconeFeito);

    // Span do nome
    const nomeSpan = document.createElement('button');
    nomeSpan.className = 'prato-nome';
    nomeSpan.textContent = prato.nome;
    if (!readOnly) {
        nomeSpan.title = 'Clique para editar o nome';
        nomeSpan.addEventListener('click', () => ativarEdicaoNome(card, prato));
    }
    card.appendChild(nomeSpan);

    // Ações do card: Editar e Apagar (apenas para dias editáveis)
    if (!readOnly) {
        const acoes = document.createElement('div');
        acoes.className = 'prato-card-acoes';

        // Botão Editar (lápis)
        const btnEditar = document.createElement('button');
        btnEditar.className = 'prato-btn-acao prato-btn-editar';
        btnEditar.title = 'Editar nome do prato';
        btnEditar.innerHTML = '<i class="bi bi-pencil"></i>';
        btnEditar.addEventListener('click', () => ativarEdicaoNome(card, prato));
        acoes.appendChild(btnEditar);

        // Botão Apagar (lixo)
        const btnApagar = document.createElement('button');
        btnApagar.className = 'prato-btn-acao prato-btn-apagar';
        btnApagar.title = prato.tem_reservas ? 'Não pode apagar — existem reservas' : 'Remover prato';
        btnApagar.innerHTML = '<i class="bi bi-trash3"></i>';
        btnApagar.disabled = prato.tem_reservas;
        btnApagar.addEventListener('click', () => apagarPrato(prato.rm_id, card));
        acoes.appendChild(btnApagar);

        card.appendChild(acoes);
    }

    return card;
}

/* ── Edição inline do nome ───────────────────────────────────────────── */
function ativarEdicaoNome(card, prato) {
    if (card.querySelector('.prato-nome-input')) return;

    const nomeSpan = card.querySelector('.prato-nome');
    const acoes    = card.querySelector('.prato-card-acoes');
    if (acoes) acoes.style.display = 'none';

    const editWrap = document.createElement('div');
    editWrap.className = 'prato-edit-wrap';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'prato-nome-input';
    input.value = prato.nome;
    input.maxLength = 150;

    const btnOk = document.createElement('button');
    btnOk.className = 'prato-edit-ok';
    btnOk.title = 'Guardar alteração';
    btnOk.innerHTML = '<i class="bi bi-check-lg"></i>';

    const btnCancel = document.createElement('button');
    btnCancel.className = 'prato-edit-cancel';
    btnCancel.title = 'Cancelar';
    btnCancel.innerHTML = '<i class="bi bi-x-lg"></i>';

    editWrap.appendChild(input);
    editWrap.appendChild(btnOk);
    editWrap.appendChild(btnCancel);

    card.replaceChild(editWrap, nomeSpan);
    input.focus();
    input.select();

    const fecharEdicao = () => {
        if (card.contains(editWrap)) card.replaceChild(nomeSpan, editWrap);
        if (acoes) acoes.style.display = '';
    };

    const guardar = async () => {
        const novoNome = input.value.trim();
        if (!novoNome || novoNome === prato.nome) {
            fecharEdicao();
            return;
        }

        btnOk.disabled = true;
        try {
            const resp  = await fetch('api/gerir_ementa_atualizar.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : new URLSearchParams({ rm_id: prato.rm_id, nome: novoNome, csrf_token: CSRF_TOKEN }),
            });
            const dados = await resp.json();

            if (dados.status === 'ok') {
                prato.nome = novoNome;
                nomeSpan.textContent = novoNome;
                fecharEdicao();
                mostrarToast('Nome atualizado.');
            } else {
                mostrarToast(dados.mensagem || 'Erro ao guardar.', 'erro');
                fecharEdicao();
            }
        } catch {
            mostrarToast('Erro de ligação.', 'erro');
            fecharEdicao();
        }
    };

    btnOk.addEventListener('click', guardar);
    btnCancel.addEventListener('click', fecharEdicao);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter')  guardar();
        if (e.key === 'Escape') fecharEdicao();
    });
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
            // Reativa imediatamente o botão quick-add daquele tipo se estiver no mesmo dia
            const tipoId = card.dataset.tipoId;
            const body   = card.closest('.dia-body');
            if (body && tipoId) {
                const btn = body.querySelector(`.btn-quick-tipo[data-tipo-id="${tipoId}"]`);
                if (btn) {
                    btn.classList.remove('btn-quick-tipo--feito');
                    btn.classList.add('btn-quick-tipo--pendente');
                    btn.disabled  = false;
                    btn.title     = `Preencher ${btn.dataset.tipoNome || ''}`;
                    btn.innerHTML = `<i class="bi bi-plus-lg"></i> ${escHtml(btn.dataset.tipoNome || '')}`;
                }
            }

            card.style.transition = 'opacity 0.15s';
            card.style.opacity    = '0';
            mostrarToast('Prato removido.');
            setTimeout(() => {
                carregarSemana();
            }, 150);
        } else if (dados.status === 'tem_pedidos') {
            mostrarToast('Não é possível remover — já existem reservas.', 'aviso');
        } else {
            mostrarToast(dados.mensagem || 'Erro ao remover.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    }
}

/* ── Slots de tipo e botões de estado ────────────────────────────────── */

/**
 * Cria a linha/slot para um tipo específico de refeição.
 * Se já estiver preenchido, mostra o(s) card(s) com nome e ações.
 * Se estiver vazio, mostra o espaço em branco com botão de confirmação [✓].
 */
function criarSlotTipo(body, dataStr, tipo, listaPratos, readOnly = false) {
    const slot = document.createElement('div');
    slot.className = `tipo-slot${tipo.prato_dia ? ' tipo-slot--principal' : ''}`;
    slot.dataset.tipoId = tipo.id;

    const label = document.createElement('div');
    label.className = 'tipo-slot-label';
    label.innerHTML = `<span>${escHtml(tipo.nome)}</span>`;
    slot.appendChild(label);

    const temPratos = Array.isArray(listaPratos) ? listaPratos.length > 0 : !!listaPratos;

    if (temPratos) {
        if (Array.isArray(listaPratos)) {
            listaPratos.forEach(p => slot.appendChild(criarCardPrato(p, readOnly)));
        } else {
            slot.appendChild(criarCardPrato(listaPratos, readOnly));
        }
    } else if (!readOnly) {
        const inputWrap = document.createElement('div');
        inputWrap.className = 'slot-input-wrap';

        const uid = `slot_${dataStr}_${tipo.id}`;
        const nomeArticulado = tipo.nome.toLowerCase();
        let placeholder;
        if (/^(carne|sopa|sobremesa|bebida)$/i.test(nomeArticulado)) {
            placeholder = `Nome da ${nomeArticulado}…`;
        } else if (/^vegetariano$/i.test(nomeArticulado)) {
            placeholder = 'Nome do prato vegetariano…';
        } else {
            placeholder = `Nome do ${nomeArticulado}…`;
        }

        inputWrap.innerHTML = `
            <input type="text" id="${uid}" class="slot-input"
                   placeholder="${placeholder}" maxlength="150" autocomplete="off">
            <button type="button" class="slot-btn-ok" title="Guardar ${escHtml(tipo.nome)}">
                <i class="bi bi-check-lg"></i>
            </button>
        `;

        const input = inputWrap.querySelector('.slot-input');
        const btnOk = inputWrap.querySelector('.slot-btn-ok');

        input.addEventListener('input', () => {
            const preenchido = input.value.trim().length > 0;
            btnOk.classList.toggle('slot-btn-ok--visivel', preenchido);
        });

        const confirmar = () => submeterPratoSlot(input, btnOk, dataStr, tipo);

        btnOk.addEventListener('click', confirmar);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') confirmar();
        });

        slot.appendChild(inputWrap);
    } else {
        return null;
    }

    return slot;
}

/**
 * Guarda o prato inserido num slot em branco.
 */
async function submeterPratoSlot(input, btnOk, dataStr, tipo) {
    if (btnOk.disabled || input.disabled) return;

    const nome = input.value.trim();
    if (!nome) {
        input.focus();
        mostrarToast(`Escreva o nome para ${tipo.nome}.`, 'aviso');
        return;
    }

    input.disabled = true;
    btnOk.disabled = true;

    try {
        const resp  = await fetch('api/gerir_ementa_criar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({ nome, tipo_id: tipo.id, data: dataStr, csrf_token: CSRF_TOKEN }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            mostrarToast(`"${nome}" guardado.`);
            await carregarSemana();
            focarProximoSlotVazio(dataStr);
        } else if (dados.status === 'tipo_duplicado') {
            mostrarToast(`Já existe ${tipo.nome} para este dia.`, 'aviso');
            carregarSemana();
        } else if (dados.status === 'dia_feriado') {
            mostrarToast('Não é possível adicionar pratos num feriado.', 'aviso');
            input.disabled = false;
            btnOk.disabled = false;
        } else {
            mostrarToast(dados.mensagem || 'Erro ao guardar.', 'erro');
            input.disabled = false;
            btnOk.disabled = false;
            input.focus();
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
        input.disabled = false;
        btnOk.disabled = false;
    }
}

/**
 * Guarda todos os pratos preenchidos de uma só vez num dia.
 */
async function guardarTodosPratosDia(col, dataStr, nomeDia, btnGuardar) {
    if (btnGuardar.disabled) return;

    const body = col.querySelector('.dia-body');
    if (!body) return;

    const inputs = Array.from(body.querySelectorAll('.slot-input-wrap .slot-input'));
    const itensParaGuardar = [];

    inputs.forEach(input => {
        const nome = input.value.trim();
        const wrap = input.closest('.tipo-slot');
        const tipoId = wrap ? parseInt(wrap.dataset.tipoId, 10) : null;
        if (nome && tipoId) {
            itensParaGuardar.push({ nome, tipoId, input });
        }
    });

    if (itensParaGuardar.length === 0) {
        mostrarToast(`Escreva o nome de pelo menos um prato para ${nomeDia}.`, 'aviso');
        const primeiroVazio = inputs[0];
        if (primeiroVazio) primeiroVazio.focus();
        return;
    }

    btnGuardar.disabled = true;
    const iconeOriginal = btnGuardar.innerHTML;
    btnGuardar.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';

    itensParaGuardar.forEach(item => { item.input.disabled = true; });

    let guardados = 0;
    let erros = 0;

    for (const item of itensParaGuardar) {
        try {
            const resp = await fetch('api/gerir_ementa_criar.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : new URLSearchParams({ nome: item.nome, tipo_id: item.tipoId, data: dataStr, csrf_token: CSRF_TOKEN }),
            });
            const dados = await resp.json();
            if (dados.status === 'ok') {
                guardados++;
            } else {
                erros++;
                item.input.disabled = false;
            }
        } catch {
            erros++;
            item.input.disabled = false;
        }
    }

    if (guardados > 0) {
        mostrarToast(`${guardados} prato${guardados > 1 ? 's' : ''} guardado${guardados > 1 ? 's' : ''} para ${nomeDia}!`);
        carregarSemana();
    } else {
        mostrarToast('Não foi possível guardar os pratos.', 'erro');
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = iconeOriginal;
    }
}

/**
 * Cria a barra de pílulas de estado no fundo do dia.
 * Tipos preenchidos ficam com o certo verde (✓);
 * Tipos em falta ficam clicáveis para focar o espaço em branco acima.
 */
function criarAreaPillsStatus(body, dataStr, pratosExistentes, todosTipos, readOnly = false) {
    const wrap = document.createElement('div');
    wrap.className = 'quick-add-wrap';

    const tiposComPrato = new Set(pratosExistentes.map(p => p.tipo_id));

    todosTipos.forEach(tipo => {
        const jaExiste = tiposComPrato.has(tipo.id);
        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = `btn-quick-tipo${jaExiste ? ' btn-quick-tipo--feito' : ' btn-quick-tipo--pendente'}${readOnly ? ' btn-quick-tipo--readonly' : ''}`;
        pill.dataset.tipoId   = tipo.id;
        pill.dataset.tipoNome = tipo.nome;

        if (jaExiste) {
            pill.title = `${tipo.nome} configurado`;
            pill.innerHTML = `<i class="bi bi-check2"></i> ${escHtml(tipo.nome)}`;
        } else if (readOnly) {
            pill.title = `${tipo.nome} (não configurado no passado)`;
            pill.innerHTML = `<i class="bi bi-dash"></i> ${escHtml(tipo.nome)}`;
            pill.disabled = true;
        } else {
            pill.title = `Preencher ${tipo.nome}`;
            pill.innerHTML = `<i class="bi bi-plus-lg"></i> ${escHtml(tipo.nome)}`;
        }

        if (!jaExiste && !readOnly) {
            pill.addEventListener('click', () => {
                const targetInput = body.querySelector(`#slot_${dataStr}_${tipo.id}`);
                if (targetInput) {
                    targetInput.focus();
                    targetInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }
        wrap.appendChild(pill);
    });

    return wrap;
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

    // Se estiver em rascunho ou sem pratos, esconde a barra para não poluir
    if (pub.total === 0 || !pub.publicada) {
        barra.style.display = 'none';
    } else {
        barra.style.display = '';
        if (pub.ja_visivel) {
            barra.className = 'publicacao-barra publicacao-barra--visivel';
            barra.innerHTML = '<i class="bi bi-check-circle-fill"></i> Publicada e já visível para os alunos.';
        } else {
            barra.className = 'publicacao-barra publicacao-barra--agendada';
            barra.innerHTML = `<i class="bi bi-clock-history"></i> Publicada — abre automaticamente ${formatarDataHoraPt(pub.visivel_em)}.`;
        }
    }

    // "Publicar/Republicar" deve permitir publicar sempre que há pratos configurados
    // e a semana NÃO está publicada (ex: após despublicar), ou se ainda não está visível para os alunos
    const btnPublicar = document.getElementById('btnPublicarSemana');
    if (btnPublicar) {
        const mostrarPublicar = pub.total > 0 && (!pub.publicada || !pub.ja_visivel);
        btnPublicar.style.display = mostrarPublicar ? '' : 'none';
        if (mostrarPublicar) {
            btnPublicar.innerHTML = pub.publicada
                ? '<i class="bi bi-send-check-fill"></i> Republicar semana'
                : '<i class="bi bi-send-check-fill"></i> Publicar semana';
        }
    }

    // Botão "Despublicar" só aparece se já houver algo publicado
    const btnDespublicar = document.getElementById('btnDespublicarSemana');
    if (btnDespublicar) {
        btnDespublicar.style.display = pub.publicados > 0 ? '' : 'none';
    }

    // Botão "Limpar semana" só aparece se existirem pratos configurados
    const btnLimparSemana = document.getElementById('btnLimparSemana');
    if (btnLimparSemana) {
        btnLimparSemana.style.display = pub.total > 0 ? '' : 'none';
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
    if (radioPadrao) {
        radioPadrao.checked = true;
        actualizarOpcaoModal(modal);
    }

    // Atualiza destaque visual ao mudar opção
    modal.querySelectorAll('input[name="modoAbertura"]').forEach(r => {
        r.addEventListener('change', () => actualizarOpcaoModal(modal));
    });
}

function actualizarOpcaoModal(modal) {
    modal.querySelectorAll('.modal-opcao').forEach(label => {
        const radio = label.querySelector('input[type="radio"]');
        label.classList.toggle('modal-opcao--selecionada', radio?.checked ?? false);
    });
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

        if (resp.status === 401 || resp.status === 403) {
            mostrarToast('Sessão sem permissão de administrador. Inicia sessão com a conta de Administrador (55555555).', 'erro');
            return;
        }

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

        if (resp.status === 401 || resp.status === 403) {
            mostrarToast('Sessão sem permissão de administrador. Inicia sessão com a conta de Administrador (55555555).', 'erro');
            return;
        }

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

/* ── Copiar semana anterior ────────────────────────────────────────── */

/**
 * Copia os pratos da semana anterior para a semana em vista.
 * Não sobrepõe pratos que já existam (mesmo tipo no mesmo dia).
 * Não publica — fica em rascunho para o admin editar antes de publicar.
 */
async function copiarSemanaAnterior() {
    if (!dadosSemana.inicio) return;

    const [y, m, d]   = dadosSemana.inicio.split('-').map(Number);
    const dtOrigem    = new Date(y, m - 1, d - 7);
    const dtOrigemFim = new Date(y, m - 1, d - 7 + 4);

    const inicioOrigem = formatarData(dtOrigem);
    const fimOrigem    = formatarData(dtOrigemFim);

    const btn = document.getElementById('btnCopiarSemana');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> A copiar…'; }

    try {
        const resp = await fetch('api/gerir_ementa_copiar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                inicio_origem : inicioOrigem,
                fim_origem    : fimOrigem,
                inicio_destino: dadosSemana.inicio,
                fim_destino   : dadosSemana.fim,
                csrf_token    : CSRF_TOKEN,
            }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            const msg = dados.copiados === 0
                ? 'Todos os pratos já existiam — nada copiado.'
                : dados.ignorados > 0
                    ? `${dados.copiados} prato(s) copiado(s) (${dados.ignorados} já existia(m)).`
                    : `${dados.copiados} prato(s) copiado(s) com sucesso.`;
            mostrarToast(msg, dados.copiados === 0 ? 'aviso' : 'sucesso');
            carregarSemana();
        } else {
            mostrarToast(dados.mensagem || 'Erro ao copiar semana.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-copy"></i> Copiar semana anterior'; }
    }
}

/* ── Limpar período (dia ou semana) ────────────────────────────────── */
async function limparPeriodo(inicio, fim, rotulo = 'o período') {
    const btnLimpar = document.getElementById('btnLimparSemana');
    if (btnLimpar) btnLimpar.disabled = true;

    try {
        const resp = await fetch('api/gerir_ementa_limpar.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                inicio,
                fim,
                csrf_token: CSRF_TOKEN,
            }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            if (dados.apagados === 0 && dados.bloqueados > 0) {
                mostrarToast('Nenhum prato removido — já existem reservas de alunos.', 'aviso');
            } else if (dados.bloqueados > 0) {
                mostrarToast(`${dados.apagados} prato(s) removido(s). ${dados.bloqueados} não puderam ser removidos por terem reservas.`, 'aviso');
                carregarSemana();
            } else if (dados.apagados > 0) {
                mostrarToast(`${dados.apagados} prato(s) removido(s) com sucesso.`, 'sucesso');
                carregarSemana();
            } else {
                mostrarToast('Não havia pratos para remover.', 'aviso');
            }
        } else {
            mostrarToast(dados.mensagem || 'Erro ao remover pratos.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    } finally {
        if (btnLimpar) btnLimpar.disabled = false;
    }
}

/* ── Listeners ───────────────────────────────────────────────────── */
document.getElementById('btnPublicarSemana')?.addEventListener('click', abrirModalPublicar);
document.getElementById('btnDespublicarSemana')?.addEventListener('click', despublicarSemana);
document.getElementById('btnCancelarPublicar')?.addEventListener('click', fecharModalPublicar);
document.getElementById('btnConfirmarPublicar')?.addEventListener('click', confirmarPublicacao);
document.getElementById('btnCopiarSemana')?.addEventListener('click', () => {
    if (confirm('Copiar os pratos da semana anterior para esta semana?\nOs pratos já existentes não serão alterados.')) {
        copiarSemanaAnterior();
    }
});
document.getElementById('btnLimparSemana')?.addEventListener('click', () => {
    if (confirm('Tem a certeza que deseja remover todos os pratos desta semana?\nOs pratos que já tenham reservas não serão apagados.')) {
        limparPeriodo(dadosSemana.inicio, dadosSemana.fim, 'esta semana');
    }
});

/* ── Foco rápido no próximo slot livre ───────────────────────────────── */
function focarProximoSlotVazio(dataStrAtual) {
    const colAtual = document.querySelector(`.dia-body[data-data="${dataStrAtual}"]`);
    if (colAtual) {
        const proxInput = colAtual.querySelector('.slot-input');
        if (proxInput) {
            proxInput.focus();
            proxInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
    }
    const todosInputs = Array.from(document.querySelectorAll('.dia-body .slot-input'));
    if (todosInputs.length > 0) {
        todosInputs[0].focus();
        todosInputs[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

/* ── Copiar dia individual ───────────────────────────────────────────── */
let diaOrigemCopiar = null;
let diaDestinoSelecionado = null;

function abrirModalCopiarDia(dataStr, nomeDia) {
    diaOrigemCopiar = dataStr;
    diaDestinoSelecionado = null;

    const modal = document.getElementById('modalCopiarDia');
    if (!modal) return;

    document.getElementById('copiarDiaOrigemNome').textContent = `${nomeDia} (${formatarDataCurta(dataStr)})`;
    const btnConfirmar = document.getElementById('btnConfirmarCopiarDia');
    btnConfirmar.disabled = true;

    const destinosWrap = document.getElementById('copiarDiaDestinosWrap');
    destinosWrap.innerHTML = '';

    const hojeStr = formatarData(new Date());
    const seg = getSegundaDaSemana(semanaOffset);

    for (let i = 0; i < 5; i++) {
        const dia = new Date(seg);
        dia.setDate(seg.getDate() + i);
        const dStr = formatarData(dia);
        const nomeD = DIAS_SEMANA[i];

        if (dStr === dataStr) continue; // mesmo dia

        const ehFeriado = dadosSemana.feriados && dadosSemana.feriados[dStr];
        const ehPassado = dStr < hojeStr;
        const bloqueado = ehFeriado || ehPassado;

        const opcao = document.createElement('div');
        opcao.className = `copiar-dia-opcao${bloqueado ? ' copiar-dia-opcao--bloqueada' : ''}`;
        opcao.dataset.data = dStr;

        let detalhe = formatarDataCurta(dStr);
        if (ehFeriado) detalhe += ` (Feriado: ${dadosSemana.feriados[dStr]})`;
        else if (ehPassado) detalhe += ' (Encerrado — dia anterior)';

        opcao.innerHTML = `
            <i class="bi bi-${bloqueado ? 'slash-circle' : 'calendar2-check'}"></i>
            <div>
                <strong>${escHtml(nomeD)}</strong>
                <small class="text-muted" style="display:block; font-size:0.75rem;">${escHtml(detalhe)}</small>
            </div>
        `;

        if (!bloqueado) {
            opcao.addEventListener('click', () => {
                destinosWrap.querySelectorAll('.copiar-dia-opcao').forEach(el => el.classList.remove('copiar-dia-opcao--selecionada'));
                opcao.classList.add('copiar-dia-opcao--selecionada');
                diaDestinoSelecionado = dStr;
                btnConfirmar.disabled = false;
            });
        }

        destinosWrap.appendChild(opcao);
    }

    modal.classList.add('modal--visivel');
}

function fecharModalCopiarDia() {
    const modal = document.getElementById('modalCopiarDia');
    if (modal) modal.classList.remove('modal--visivel');
    diaOrigemCopiar = null;
    diaDestinoSelecionado = null;
}

async function confirmarCopiarDia() {
    if (!diaOrigemCopiar || !diaDestinoSelecionado) return;

    const btnConfirmar = document.getElementById('btnConfirmarCopiarDia');
    if (btnConfirmar) btnConfirmar.disabled = true;

    try {
        const resp = await fetch('api/gerir_ementa_copiar_dia.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                data_origem : diaOrigemCopiar,
                data_destino: diaDestinoSelecionado,
                csrf_token  : CSRF_TOKEN,
            }),
        });
        const dados = await resp.json();

        if (dados.status === 'ok') {
            const msg = dados.copiados === 0
                ? 'Todos os pratos já existiam no dia de destino.'
                : `${dados.copiados} prato(s) copiado(s)${dados.ignorados > 0 ? ` (${dados.ignorados} já existiam)` : ''}.`;
            mostrarToast(msg, dados.copiados === 0 ? 'aviso' : 'sucesso');
            fecharModalCopiarDia();
            carregarSemana();
        } else {
            mostrarToast(dados.mensagem || 'Erro ao copiar pratos.', 'erro');
        }
    } catch {
        mostrarToast('Erro de ligação.', 'erro');
    } finally {
        if (btnConfirmar) btnConfirmar.disabled = false;
    }
}

/* ── Listeners adicionais ────────────────────────────────────────── */
document.getElementById('btnCancelarCopiarDia')?.addEventListener('click', fecharModalCopiarDia);
document.getElementById('btnConfirmarCopiarDia')?.addEventListener('click', confirmarCopiarDia);

const modalCopiarDia = document.getElementById('modalCopiarDia');
if (modalCopiarDia) {
    modalCopiarDia.addEventListener('click', (e) => {
        if (e.target === modalCopiarDia) fecharModalCopiarDia();
    });
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        fecharModalPublicar();
        fecharModalCopiarDia();
    }
});

/* ── Inicialização ─────────────────────────────────────────────────── */
semanaOffset = calcularOffsetInicial();
carregarSemana();