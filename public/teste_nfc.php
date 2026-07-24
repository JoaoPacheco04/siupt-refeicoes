<?php


require_once __DIR__ . '/../src/Support/Auth.php';

exigirLogin('funcionario');
?>
<!DOCTYPE html>
<html lang="pt">
<body>
<h2>Teste NFC</h2>
<button onclick="lerCartao()">Ler cartao</button>
<div id="resultado"></div>

<script>
async function lerCartao() {
    if (!('NDEFReader' in window)) {
        document.getElementById('resultado').textContent = 'Este telemovel/browser nao suporta Web NFC';
        return;
    }
    try {
        const reader = new NDEFReader();
        await reader.scan();
        document.getElementById('resultado').textContent = 'A aguardar leitura...';
        reader.onreading = (event) => {
            const uid = event.serialNumber;
            document.getElementById('resultado').textContent = 'Lido: ' + uid;
            validar(uid);
        };
    } catch (e) {
        document.getElementById('resultado').textContent = 'Erro: ' + e;
    }
}

function validar(numero) {
    fetch('api/validar_cartao.php?numero=' + encodeURIComponent(numero))
        .then(r => r.text())
        .then(t => document.getElementById('resultado').textContent += ' | ' + t);
}
</script>
</body>
</html>
