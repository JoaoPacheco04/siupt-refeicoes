/**
 * gerir_precos.js — Gestão assíncrona de preços das refeições
 */

const CSRF_TOKEN = window.CSRF_TOKEN || '';

function mostrarToast(mensagem, tipo = 'sucesso') {
    let toast = document.getElementById('toastPreco');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toastPreco';
        toast.className = 'toast-notificacao';
        document.body.appendChild(toast);
    }

    toast.className = `toast-notificacao toast--visivel ${tipo === 'erro' ? 'toast--erro' : 'toast--sucesso'}`;
    const icone = tipo === 'erro' ? '<i class="bi bi-exclamation-triangle"></i>' : '<i class="bi bi-check-circle"></i>';
    toast.innerHTML = `${icone} <span>${mensagem}</span>`;

    if (window._toastPrecoTimeout) {
        clearTimeout(window._toastPrecoTimeout);
    }
    window._toastPrecoTimeout = setTimeout(() => {
        toast.classList.remove('toast--visivel');
    }, 4000);
}

document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.form-atualizar-preco');

    forms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const tipoId = form.dataset.tipoId;
            const inputPreco = form.querySelector('.input-novo-preco');
            const btn = form.querySelector('.btn-salvar-preco');
            const precoVal = inputPreco ? inputPreco.value.trim() : '';

            if (!tipoId || precoVal === '') return;

            const btnTextoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> A guardar…';

            try {
                const res = await fetch('api/gerir_precos_atualizar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        tipo_id: tipoId,
                        preco: precoVal,
                        csrf_token: CSRF_TOKEN
                    })
                });

                const data = await res.json();

                if (data.status === 'ok') {
                    mostrarToast(data.mensagem || 'Preço atualizado com sucesso!');

                    // Atualiza o valor e a data em vigor no bloco visual da linha
                    const linha = form.closest('.preco-item-linha');
                    if (linha) {
                        const valorEl = linha.querySelector('.preco-vigente-valor');
                        const dataEl = linha.querySelector('.preco-vigente-data');

                        if (valorEl && data.preco_formatado) {
                            valorEl.textContent = data.preco_formatado;
                        }
                        if (dataEl && data.data_vigor) {
                            dataEl.innerHTML = `<i class="bi bi-clock-history"></i> Em vigor desde ${data.data_vigor}`;
                        }
                        // Feedback subtil de pulso visual
                        linha.style.backgroundColor = '#ecfdf5';
                        setTimeout(() => {
                            linha.style.backgroundColor = '';
                        }, 1200);
                    }
                } else {
                    mostrarToast(data.mensagem || 'Erro ao atualizar preço.', 'erro');
                }
            } catch (err) {
                mostrarToast('Erro de rede ao comunicar com o servidor.', 'erro');
            } finally {
                btn.disabled = false;
                btn.innerHTML = btnTextoOriginal;
            }
        });
    });
});
