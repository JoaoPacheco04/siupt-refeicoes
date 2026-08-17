/**
 * gerir_feriados.js
 *
 * Lógica de interação da página de gestão de feriados e dias especiais.
 * Requer: assets/js/utils.js (escHtml)
 * Requer: window.CSRF_TOKEN definido inline no PHP.
 */

document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = window.CSRF_TOKEN;

    // ── Helper: toast de feedback ──────────────────────────────────────────
    function mostrarToast(mensagem, tipo) {
        tipo = tipo || 'success';
        const existente = document.getElementById('toast-feriados');
        if (existente) existente.remove();

        const toast = document.createElement('div');
        toast.id = 'toast-feriados';
        toast.className = 'feriados-toast feriados-toast--' + tipo;
        toast.innerHTML =
            '<span>' + escHtml(mensagem) + '</span>' +
            '<button class="feriados-toast-fechar" aria-label="Fechar">&times;</button>';
        toast.querySelector('.feriados-toast-fechar')
            .addEventListener('click', function () { toast.remove(); });
        document.querySelector('main').prepend(toast);
        if (tipo === 'success') setTimeout(function () { toast.remove(); }, 5000);
    }

    // ── Gerar feriados móveis ──────────────────────────────────────────────
    document.getElementById('form-gerar-feriados').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        var ano = document.getElementById('ano-gerar').value;
        var formData = new FormData();
        formData.append('ano', ano);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_gerar_moveis.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'ok') {
                    var tipo = data.inseridos === 0 ? 'warning' : 'success';
                    mostrarToast(data.mensagem, tipo);
                    if (data.inseridos > 0) {
                        setTimeout(function () { window.location.reload(); }, 1500);
                    } else {
                        btn.disabled = false;
                    }
                } else {
                    mostrarToast(data.mensagem || 'Erro ao gerar feriados.', 'danger');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                btn.disabled = false;
            });
    });

    // ── Criar feriado manual ───────────────────────────────────────────────
    document.getElementById('form-criar-feriado').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        var formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_feriados_criar.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'ok') {
                    window.location.reload();
                } else {
                    mostrarToast(data.mensagem || 'Erro ao criar feriado.', 'danger');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                btn.disabled = false;
            });
    });

    // ── Helper: modal de confirmação elegante (Tingle) ─────────────────────
    function confirmarAcao(titulo, mensagem, onConfirm) {
        if (typeof tingle === 'undefined') {
            if (confirm(mensagem)) onConfirm();
            return;
        }

        const modal = new tingle.modal({
            footer: true,
            closeMethods: ['overlay', 'button', 'escape'],
            cssClass: ['tingle-siupt'],
            onClose: function () { modal.destroy(); }
        });

        modal.setContent(`
            <div class="modal-siupt-header erro">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h4>${escHtml(titulo)}</h4>
            </div>
            <p class="text-muted small">${escHtml(mensagem)}</p>
        `);

        modal.addFooterBtn('Cancelar', 'tingle-btn tingle-btn--default', () => modal.close());
        modal.addFooterBtn('Apagar', 'tingle-btn tingle-btn--danger', () => {
            modal.close();
            onConfirm();
        });

        modal.open();
    }

    // ── Apagar feriado (delegação de eventos) ─────────────────────────────
    document.getElementById('lista-feriados').addEventListener('click', function (e) {
        var target = e.target.closest('.btn-feriados-apagar');
        if (!target || target.classList.contains('btn-apagar-especial')) return;

        confirmarAcao('Apagar feriado', 'Tens a certeza de que pretendes apagar este feriado?', function () {
            target.disabled = true;
            var id = target.dataset.id;
            var formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', csrfToken);

            fetch('api/gerir_feriados_apagar.php', { method: 'POST', body: formData })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.status === 'ok') {
                        var row = document.getElementById('feriado-' + id);
                        if (row) row.remove();
                        mostrarToast('Feriado apagado com sucesso.', 'success');
                    } else {
                        mostrarToast(data.mensagem || 'Erro ao apagar feriado.', 'danger');
                        target.disabled = false;
                    }
                })
                .catch(function () {
                    mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                    target.disabled = false;
                });
        });
    });

    // ── Criar dia especial ─────────────────────────────────────────────────
    document.getElementById('form-criar-dia-especial').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        var formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        fetch('api/gerir_dias_especiais_criar.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'ok') {
                    window.location.reload();
                } else {
                    mostrarToast(data.mensagem || 'Erro ao criar dia especial.', 'danger');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                btn.disabled = false;
            });
    });

    // ── Apagar dia especial (delegação de eventos) ─────────────────────────
    document.getElementById('lista-dias-especiais').addEventListener('click', function (e) {
        var target = e.target.closest('.btn-apagar-especial');
        if (!target) return;

        confirmarAcao('Apagar dia especial', 'Tens a certeza de que pretendes apagar este dia especial?', function () {
            target.disabled = true;
            var id = target.dataset.id;
            var formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', csrfToken);

            fetch('api/gerir_dias_especiais_apagar.php', { method: 'POST', body: formData })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.status === 'ok') {
                        var row = document.getElementById('dia-especial-' + id);
                        if (row) row.remove();
                        mostrarToast('Dia especial apagado com sucesso.', 'success');
                    } else {
                        mostrarToast(data.mensagem || 'Erro ao apagar dia especial.', 'danger');
                        target.disabled = false;
                    }
                })
                .catch(function () {
                    mostrarToast('Erro de ligação. Tenta novamente.', 'danger');
                    target.disabled = false;
                });
        });
    });
});
