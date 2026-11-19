<?php
/**
 * Tela de login.
 * Confere o login e a senha. Se estiverem certos, guarda o id
 * do usuário na sessão e manda para o painel.
 */
require __DIR__ . '/includes/auth.php';

$erro = null;
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

    $stmt = conectar()->prepare('SELECT * FROM usuarios WHERE login = ? AND ativo = 1');
    $stmt->execute([$login]);
    $usuario = $stmt->fetch();

    // password_verify compara a senha digitada com o hash salvo no banco
    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        session_regenerate_id(true); // novo id de sessão: evita roubo de sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        header('Location: index.php');
        exit;
    }
    // Espera 1 segundo a cada erro: deixa lento quem tenta adivinhar senhas
    sleep(1);
    // Mensagem genérica: não contamos se o erro foi no login ou na senha
    $erro = 'Login ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · Música PIBA</title>
    <link rel="icon" href="assets/img/logo-piba.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="tela-login">
    <div class="bolhas" aria-hidden="true"><i></i><i></i><i></i></div>
    <form class="cartao caixa-login" method="post">
        <img src="assets/img/logo-piba.png" alt="Logo da PIBA">
        <h1>Música<b class="destaque">PIBA</b></h1>
        <p>Escola de Música · acesso da administração e dos professores</p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= e($erro) ?></div>
        <?php endif; ?>

        <div class="campo">
            <label for="login">Login</label>
            <input id="login" name="login" required autofocus value="<?= e($login) ?>">
        </div>
        <div class="campo">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" required>
        </div>
        <button class="botao botao-primario" type="submit">Entrar ♪</button>
    </form>
</body>
</html>
