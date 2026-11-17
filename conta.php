<?php
/**
 * Minha conta: qualquer usuário logado troca a própria senha.
 * Pede a senha atual para confirmar que é a própria pessoa.
 */
require __DIR__ . '/includes/auth.php';

$usuario = exigirLogin();
$erros = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $atual = $_POST['atual'] ?? '';
    $nova  = $_POST['nova'] ?? '';
    $conf  = $_POST['confirmacao'] ?? '';

    if (!password_verify($atual, $usuario['senha_hash'])) {
        $erros[] = 'A senha atual está errada.';
    }
    if (mb_strlen($nova) < 6) {
        $erros[] = 'A nova senha precisa ter pelo menos 6 caracteres.';
    }
    if ($nova !== $conf) {
        $erros[] = 'A confirmação não é igual à nova senha.';
    }
    if (!$erros) {
        conectar()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                  ->execute([password_hash($nova, PASSWORD_DEFAULT), $usuario['id']]);
        $ok = true;
    }
}

$titulo = 'Minha senha';
$menu   = '';
require __DIR__ . '/includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🔑 Minha senha</h1>
        <p>Login: <code><?= e($usuario['login']) ?></code></p>
    </div>
</div>

<?php if ($ok): ?><div class="alerta alerta-sucesso">Senha trocada com sucesso! ♪</div><?php endif; ?>
<?php if ($erros): ?>
    <div class="alerta alerta-erro"><?php foreach ($erros as $erro): ?><div><?= e($erro) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<section class="cartao" style="max-width: 520px">
    <form class="formulario" method="post">
        <?= campoCsrf() ?>
        <div class="campo campo-inteiro">
            <label for="atual">Senha atual</label>
            <input id="atual" name="atual" type="password" required>
        </div>
        <div class="campo">
            <label for="nova">Nova senha</label>
            <input id="nova" name="nova" type="password" required minlength="6">
        </div>
        <div class="campo">
            <label for="confirmacao">Repita a nova senha</label>
            <input id="confirmacao" name="confirmacao" type="password" required minlength="6">
        </div>
        <div class="botoes-form">
            <button class="botao botao-primario" type="submit">Trocar senha</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
