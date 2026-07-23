<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $_POST['numero'] ?? '';
    $password = $_POST['password'] ?? '';

    $utilizador = Database::autenticar($numero, $password);

    if ($utilizador) {
        $_SESSION['user_id'] = $utilizador['id'];
        $_SESSION['user_nome'] = $utilizador['nome'];
        $_SESSION['user_tipo'] = $utilizador['tipo'];

        header('Location: ' . ($utilizador['tipo'] === 'funcionario' ? 'validar.php' : 'ementa.php'));
        exit;
    }

    $erro = 'Número ou password incorretos';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SIUPT - Login</title>
</head>
<body>
    <h2>Login</h2>
    <?php if ($erro): ?>
        <p style="color:red;"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>Número: <input type="text" name="numero" required></label><br>
        <label>Password: <input type="password" name="password" required></label><br>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>