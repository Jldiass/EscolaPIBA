<?php
/**
 * Cadastro e edição de professor (só secretaria).
 * Aqui também se define o login e a senha do professor.
 *
 * Professores não são excluídos, só desativados: assim o histórico
 * de aulas, chamadas e repasses continua guardado.
 */
require __DIR__ . '/../includes/auth.php';

exigirSecretaria();
$pdo = conectar();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$erros = [];
$prof = ['nome' => '', 'login' => '', 'telefone' => '', 'instrumentos' => '', 'ativo' => 1];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND perfil = 'professor'");
    $stmt->execute([$id]);
    $prof = $stmt->fetch();
    if (!$prof) {
        http_response_code(404);
        exit('Professor não encontrado.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $prof['nome']         = trim($_POST['nome'] ?? '');
    $prof['login']        = mb_strtolower(trim($_POST['login'] ?? ''));
    $prof['telefone']     = trim($_POST['telefone'] ?? '');
    // As especialidades vêm separadas por vírgula: "Teoria musical, Bateria"
    $prof['instrumentos'] = implode(', ', especialidades($_POST['instrumentos'] ?? ''));
    $prof['ativo']        = isset($_POST['ativo']) ? 1 : 0;
    $senha                = $_POST['senha'] ?? '';

    if (mb_strlen($prof['nome']) < 3) {
        $erros[] = 'Informe o nome do professor.';
    }
    if (!preg_match('/^[a-z0-9._]{3,}$/', $prof['login'])) {
        $erros[] = 'Login: pelo menos 3 caracteres, só letras minúsculas, números, ponto ou _.';
    }
    if ($prof['instrumentos'] === '') {
        $erros[] = 'Informe pelo menos uma especialidade.';
    }
    // Senha obrigatória ao cadastrar; ao editar, em branco = manter a atual
    if ((!$id || $senha !== '') && mb_strlen($senha) < 6) {
        $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
    }
    // Login não pode repetir
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE login = ? AND id <> ?');
    $stmt->execute([$prof['login'], $id ?? 0]);
    if ($stmt->fetchColumn() > 0) {
        $erros[] = 'Esse login já está em uso.';
    }

    if (!$erros) {
        $dados = [
            ':nome' => $prof['nome'], ':login' => $prof['login'], ':telefone' => $prof['telefone'] ?: null,
            ':instrumentos' => $prof['instrumentos'], ':ativo' => $prof['ativo'],
        ];
        if ($id) {
            $dados[':id'] = $id;
            $pdo->prepare('UPDATE usuarios SET nome = :nome, login = :login, telefone = :telefone,
                           instrumentos = :instrumentos, ativo = :ativo WHERE id = :id')->execute($dados);
            if ($senha !== '') {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
            }
            header('Location: index.php?msg=editado');
        } else {
            $dados[':senha'] = password_hash($senha, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios (nome, login, senha_hash, perfil, telefone, instrumentos, ativo)
                           VALUES (:nome, :login, :senha, 'professor', :telefone, :instrumentos, :ativo)")->execute($dados);
            header('Location: index.php?msg=criado');
        }
        exit;
    }
}

$titulo = $id ? 'Editar professor' : 'Novo professor';
$menu   = 'professores';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1><?= $id ? '✎ Editar professor' : '𝄞 Novo professor' ?></h1>
        <p>Campos com * são obrigatórios.</p>
    </div>
    <a class="botao" href="index.php">← Voltar</a>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro"><?php foreach ($erros as $erro): ?><div><?= e($erro) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<section class="cartao">
    <form class="formulario" method="post">
        <?= campoCsrf() ?>
        <div class="campo campo-inteiro">
            <label for="nome">Nome *</label>
            <input id="nome" name="nome" required value="<?= e($prof['nome']) ?>">
        </div>
        <div class="campo campo-inteiro">
            <label for="instrumentos">Especialidades * <small>(separe por vírgula, ex.: Teoria musical, Bateria)</small></label>
            <input id="instrumentos" name="instrumentos" required value="<?= e($prof['instrumentos']) ?>">
        </div>
        <div class="campo">
            <label for="telefone">Telefone</label>
            <input id="telefone" name="telefone" value="<?= e($prof['telefone']) ?>">
        </div>
        <div class="campo">
            <label for="login">Login *</label>
            <input id="login" name="login" required value="<?= e($prof['login']) ?>">
        </div>
        <div class="campo">
            <label for="senha">Senha <?= $id ? '<small>(deixe em branco para manter)</small>' : '*' ?></label>
            <input id="senha" name="senha" type="password" <?= $id ? '' : 'required' ?> minlength="6">
        </div>
        <div class="campo">
            <label>&nbsp;</label>
            <label><input type="checkbox" name="ativo" <?= $prof['ativo'] ? 'checked' : '' ?>> Professor ativo</label>
        </div>
        <div class="botoes-form">
            <a class="botao" href="index.php">Cancelar</a>
            <button class="botao botao-primario" type="submit">Salvar</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
