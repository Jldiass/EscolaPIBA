<?php
/**
 * Cadastro e edição de usuários (só admin): professores e administradores.
 * Aqui se define o tipo de acesso, o login e a senha.
 *
 * Professores não são excluídos, só desativados: assim o histórico
 * de aulas, chamadas e repasses continua guardado.
 */
require __DIR__ . '/../includes/auth.php';

$usuarioLogado = exigirAdmin();
$pdo = conectar();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$erros = [];
$prof = ['nome' => '', 'login' => '', 'telefone' => '', 'instrumentos' => '', 'ativo' => 1,
         'perfil' => ($_GET['perfil'] ?? '') === 'admin' ? 'admin' : 'professor'];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $prof = $stmt->fetch();
    if (!$prof) {
        http_response_code(404);
        exit('Usuário não encontrado.');
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
    $prof['perfil']       = ($_POST['perfil'] ?? '') === 'admin' ? 'admin' : 'professor';
    $senha                = $_POST['senha'] ?? '';

    if (mb_strlen($prof['nome']) < 3) {
        $erros[] = 'Informe o nome.';
    }
    if (!preg_match('/^[a-z0-9._]{3,}$/', $prof['login'])) {
        $erros[] = 'Login: pelo menos 3 caracteres, só letras minúsculas, números, ponto ou _.';
    }
    if ($prof['perfil'] === 'professor' && $prof['instrumentos'] === '') {
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

    // O admin não pode tirar o próprio acesso (senão ninguém mais administra o sistema)
    if ($id && $id === (int) $usuarioLogado['id'] && ($prof['perfil'] !== 'admin' || !$prof['ativo'])) {
        $erros[] = 'Você não pode tirar o seu próprio acesso de administrador.';
    }

    if (!$erros) {
        $dados = [
            ':nome' => $prof['nome'], ':login' => $prof['login'], ':telefone' => $prof['telefone'] ?: null,
            ':instrumentos' => $prof['instrumentos'] ?: null, ':ativo' => $prof['ativo'], ':perfil' => $prof['perfil'],
        ];
        if ($id) {
            $dados[':id'] = $id;
            $pdo->prepare('UPDATE usuarios SET nome = :nome, login = :login, telefone = :telefone,
                           instrumentos = :instrumentos, ativo = :ativo, perfil = :perfil WHERE id = :id')->execute($dados);
            if ($senha !== '') {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
            }
            header('Location: index.php?msg=editado');
        } else {
            $dados[':senha'] = password_hash($senha, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO usuarios (nome, login, senha_hash, perfil, telefone, instrumentos, ativo)
                           VALUES (:nome, :login, :senha, :perfil, :telefone, :instrumentos, :ativo)')->execute($dados);
            header('Location: index.php?msg=criado');
        }
        exit;
    }
}

$titulo = $id ? 'Editar usuário' : ($prof['perfil'] === 'admin' ? 'Novo administrador' : 'Novo professor');
$menu   = 'professores';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1><?= $id ? '✎ ' . e($prof['nome']) : '𝄞 ' . $titulo ?></h1>
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
            <label for="perfil">Tipo de acesso *</label>
            <select id="perfil" name="perfil">
                <option value="professor" <?= $prof['perfil'] === 'professor' ? 'selected' : '' ?>>Professor: vê só os alunos e a agenda dele</option>
                <option value="admin" <?= $prof['perfil'] === 'admin' ? 'selected' : '' ?>>Administrador: cadastra e vê tudo</option>
            </select>
        </div>
        <div class="campo campo-inteiro">
            <label for="instrumentos">Especialidades <small>(obrigatório para professor; separe por vírgula, ex.: Teoria musical, Bateria)</small></label>
            <input id="instrumentos" name="instrumentos" value="<?= e($prof['instrumentos']) ?>">
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
            <label><input type="checkbox" name="ativo" <?= $prof['ativo'] ? 'checked' : '' ?>> Acesso ativo</label>
        </div>
        <div class="botoes-form">
            <a class="botao" href="index.php">Cancelar</a>
            <button class="botao botao-primario" type="submit">Salvar</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
