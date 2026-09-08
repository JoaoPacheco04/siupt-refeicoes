/**
 * gerir_prazos.js — Interações e chamadas assíncronas para gestão de prazos
 */

const CSRF_TOKEN = window.CSRF_TOKEN || '';

function mostrarToast(mensagem, tipo = 'sucesso') {
    let toast = document.getElementById('toastPrazo');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toastPrazo';
        toast.className = 'toast-notificacao';
        document.body.appendChild(toast);
    }

    toast.className = `toast-notificacao toast--visivel ${tipo === 'erro' ? 'toast--erro' : 'toast--sucesso'}`;
    const icone = tipo === 'erro' ? '<i class="bi bi-exclamation-triangle"></i>' : '<i class="bi bi-check-circle"></i>';
    toast.innerHTML = `${icone} <span>${mensagem}</span>`;

    if (window._toastTimeout) {
        clearTimeout(window._toastTimeout);
    }
    window._toastTimeout = setTimeout(() => {
        toast.classList.remove('toast--visivel');
    }, 4000);
}

function formatarTextoPrazo(hora, dias) {
    const horaFormatada = hora.substring(0, 5).replace(':', 'h');
    const diasNum = parseInt(dias, 10);
    if (diasNum === 0) {
        return `até às ${horaFormatada} do próprio dia`;
    } else if (diasNum === 1) {
        return `até às ${horaFormatada} do dia anterior`;
    } else {
        return `até às ${horaFormatada} com ${diasNum} dias de antecedência`;
    }
}

function formatarTextoPublicacao(hora, dias) {
    const horaFormatada = hora.substring(0, 5).replace(':', 'h');
    const diasNum = parseInt(dias, 10);
    const diasNomes = {
        0: 'Segunda',
        1: 'Domingo',
        2: 'Sábado',
        3: 'Sexta',
        4: 'Quinta',
        5: 'Quarta',
        6: 'Terça'
    };
    const nome = diasNomes[diasNum] || `${diasNum} dias antes`;
    return `${nome} às ${horaFormatada}`;
}

// 0. Atualizar Publicação Automática Padrão da Ementa
const formPublicacao = document.getElementById('formPublicacaoPadrao');
if (formPublicacao) {
    const horaInput = document.getElementById('horaPublicacao');
    const diasSelect = document.getElementById('diasPublicacao');
    const badgePubl = document.getElementById('badgePreviewPublicacao');

    function atualizarBadgePublLive() {
        if (!badgePubl || !horaInput || !diasSelect) return;
        const h = horaInput.value.trim();
        const d = diasSelect.value;
        if (h) {
            badgePubl.innerHTML = `<i class="bi bi-broadcast"></i> ${formatarTextoPublicacao(h, d)}`;
        }
    }
    diasSelect?.addEventListener('change', atualizarBadgePublLive);
    horaInput?.addEventListener('input', atualizarBadgePublLive);
    formPublicacao.addEventListener('submit', async (e) => {
        e.preventDefault();
        const horaInput = document.getElementById('horaPublicacao');
        const diasSelect = document.getElementById('diasPublicacao');
        const hora = horaInput ? horaInput.value.trim() : '';
        const dias = diasSelect ? diasSelect.value : '3';
        const btn = formPublicacao.querySelector('button[type="submit"]');

        if (!hora) return;

        btn.disabled = true;
        try {
            const res = await fetch('api/gerir_publicacao_config.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    hora: hora,
                    dias_antecedencia: dias,
                    csrf_token: CSRF_TOKEN
                })
            });

            const data = await res.json();
            if (data.status === 'ok') {
                mostrarToast(data.mensagem || 'Horário padrão de publicação guardado com sucesso!');
                const badge = document.getElementById('badgePreviewPublicacao');
                if (badge && data.config) {
                    badge.innerHTML = `<i class="bi bi-broadcast"></i> ${data.config.texto}`;
                }
            } else {
                mostrarToast(data.mensagem || 'Erro ao guardar horário de publicação.', 'erro');
            }
        } catch (err) {
            mostrarToast('Erro de rede ao comunicar com o servidor.', 'erro');
        } finally {
            btn.disabled = false;
        }
    });
}

// 1. Atualizar Hora Limite dos Extras
const formExtras = document.getElementById('formPrazoExtras');
if (formExtras) {
    const horaInput = document.getElementById('horaExtras');
    const diasInput = document.getElementById('diasExtras');
    const badgeExtras = document.getElementById('badgePreviewExtras');

    function atualizarBadgeExtrasLive() {
        if (!badgeExtras || !horaInput || !diasInput) return;
        const h = horaInput.value.trim();
        const d = diasInput.value;
        if (h) {
            badgeExtras.innerHTML = `<i class="bi bi-clock"></i> ${formatarTextoPrazo(h, d)}`;
        }
    }
    diasInput?.addEventListener('change', atualizarBadgeExtrasLive);
    horaInput?.addEventListener('input', atualizarBadgeExtrasLive);

    formExtras.addEventListener('submit', async (e) => {
        e.preventDefault();
        const horaInput = document.getElementById('horaExtras');
        const diasInput = document.getElementById('diasExtras');
        const hora = horaInput ? horaInput.value.trim() : '';
        const dias = diasInput ? diasInput.value : '0';
        const btn = formExtras.querySelector('button[type="submit"]');

        if (!hora) return;

        btn.disabled = true;
        try {
            const res = await fetch('api/gerir_prazos_atualizar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    acao: 'atualizar_extras',
                    hora: hora,
                    dias_antecedencia: dias,
                    csrf_token: CSRF_TOKEN
                })
            });

            const data = await res.json();
            if (data.status === 'ok') {
                mostrarToast(data.mensagem || 'Hora dos extras guardada com sucesso!');
                const badge = document.getElementById('badgePreviewExtras');
                if (badge) {
                    badge.innerHTML = `<i class="bi bi-clock"></i> ${formatarTextoPrazo(hora, dias)}`;
                }
            } else {
                mostrarToast(data.mensagem || 'Erro ao guardar hora dos extras.', 'erro');
            }
        } catch (err) {
            mostrarToast('Erro de rede ao comunicar com o servidor.', 'erro');
        } finally {
            btn.disabled = false;
        }
    });
}

// 2. Atualizar Todos os Pratos da Ementa
const formTodosEmenta = document.getElementById('formPrazoTodosEmenta');
if (formTodosEmenta) {
    const horaInput = document.getElementById('horaEmentaGlobal');
    const diasInput = document.getElementById('diasEmentaGlobal');
    const badgeEmenta = document.getElementById('badgePreviewEmenta');

    function atualizarBadgeEmentaLive() {
        if (!badgeEmenta || !horaInput || !diasInput) return;
        const h = horaInput.value.trim();
        const d = diasInput.value;
        if (h) {
            badgeEmenta.innerHTML = `<i class="bi bi-clock"></i> ${formatarTextoPrazo(h, d)}`;
        }
    }
    diasInput?.addEventListener('change', atualizarBadgeEmentaLive);
    horaInput?.addEventListener('input', atualizarBadgeEmentaLive);

    formTodosEmenta.addEventListener('submit', async (e) => {
        e.preventDefault();
        const horaInput = document.getElementById('horaEmentaGlobal');
        const diasInput = document.getElementById('diasEmentaGlobal');
        const hora = horaInput.value.trim();
        const dias = diasInput.value;
        const btn = formTodosEmenta.querySelector('button[type="submit"]');

        if (!hora) return;

        btn.disabled = true;
        try {
            const res = await fetch('api/gerir_prazos_atualizar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    acao: 'atualizar_todos_ementa',
                    hora: hora,
                    dias_antecedencia: dias,
                    csrf_token: CSRF_TOKEN
                })
            });

            const data = await res.json();
            if (data.status === 'ok') {
                mostrarToast(data.mensagem || 'Prazos da ementa atualizados com sucesso!');
                const badge = document.getElementById('badgePreviewEmenta');
                if (badge) {
                    badge.innerHTML = `<i class="bi bi-check-all"></i> ${formatarTextoPrazo(hora, dias)}`;
                }

                // Sincronizar os inputs individuais de cada linha
                document.querySelectorAll('.tipo-linha-item').forEach(linha => {
                    const inputHora = linha.querySelector('.input-tipo-hora');
                    const selectDias = linha.querySelector('.select-tipo-dias');
                    if (inputHora) inputHora.value = hora.substring(0, 5);
                    if (selectDias) selectDias.value = dias;
                });
            } else {
                mostrarToast(data.mensagem || 'Erro ao atualizar prazos da ementa.', 'erro');
            }
        } catch (err) {
            mostrarToast('Erro de rede ao comunicar com o servidor.', 'erro');
        } finally {
            btn.disabled = false;
        }
    });
}

// 3. Atualizar Tipo Individual da Ementa
document.querySelectorAll('.btn-tipo-salvar').forEach(btn => {
    btn.addEventListener('click', async () => {
        const linha = btn.closest('.tipo-linha-item');
        if (!linha) return;

        const tipoId = linha.dataset.tipoId;
        const inputHora = linha.querySelector('.input-tipo-hora');
        const selectDias = linha.querySelector('.select-tipo-dias');
        const tipoNome = linha.querySelector('.tipo-nome')?.textContent.trim() || 'Prato';

        const hora = inputHora ? inputHora.value.trim() : '';
        const dias = selectDias ? selectDias.value : '1';

        if (!tipoId || !hora) return;

        const conteudoOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

        try {
            const res = await fetch('api/gerir_prazos_atualizar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    acao: 'atualizar_tipo',
                    tipo_id: tipoId,
                    hora: hora,
                    dias_antecedencia: dias,
                    csrf_token: CSRF_TOKEN
                })
            });

            const data = await res.json();
            if (data.status === 'ok') {
                mostrarToast(`${tipoNome}: prazo atualizado (${formatarTextoPrazo(hora, dias)})`);
            } else {
                mostrarToast(data.mensagem || `Erro ao atualizar prazo de ${tipoNome}.`, 'erro');
            }
        } catch (err) {
            mostrarToast('Erro de rede ao comunicar com o servidor.', 'erro');
        } finally {
            btn.disabled = false;
            btn.innerHTML = conteudoOriginal;
        }
    });
});
