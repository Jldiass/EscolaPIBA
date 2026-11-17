<?php
/**
 * Cadastrar ou editar um membro da orquestra (só admin).
 * O membro pode ser aluno da escola (liga ao cadastro) ou alguém de fora.
 * Para tirar alguém da orquestra, desmarque "Ativo": o histórico de ensaios fica guardado.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/orquestra.php';

exigirAdmin();
$pdo = conectar();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$erros = [];
$membro = ['nome' => '', 'naipe' => 'Cordas', 'instrumento' => '', 'telefone' => '', 'aluno_id' => null, 'ativo' => 1];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM orquestra_membros WHERE id = ?');
    $stmt->execute([$id]);
    $membro = $stmt->fetch();
    if (!$membro) {
        http_response_code(404);
        exit('Membro não encontrado.');
    }
}

$alunos = $pdo->query('SELECT id, nome FROM alunos WHERE ativo = 1 ORDER BY nome')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $membro['nome']        = trim($_POST['nome'] ?? '');
    $membro['naipe']       = in_array($_POST['naipe'] ?? '', NAIPES, true) ? $_POST['naipe'] : '';
    $membro['instrumento'] = trim($_POST['instrumento'] ?? '');
    $membro['telefone']    = trim($_POST['telefone'] ?? '');
    $membro['aluno_id']    = (int) ($_POST['aluno_id'] ?? 0) ?: null;
    $membro['ativo']       = $id ? (empty($_POST['ativo']) ? 0 : 1) : 1;

    // Escolheu um aluno e não digitou o nome? Usa o nome do cadastro.
    if ($membro['aluno_id'] && $membro['nome'] === '') {
        foreach ($alunos as $a) {
            if ((int) $a['id'] === $membro['aluno_id']) {
                $membro['nome'] = $a['nome'];
            }
        }
    }
    if (mb_strlen($membro['nome']) < 3) {
        $erros[] = 'Informe o nome (pelo menos 3 letras).';
    }
    if ($membro['naipe'] === '') {
        $erros[] = 'Escolha o naipe.';
    }
    if ($membro['instrumento'] === '') {
        $erros[] = 'Informe o instrumento.';
    }

    if (!$erros) {
        $dados = [$membro['nome'], $membro['naipe'], $membro['instrumento'], $membro['telefone'] ?: null, $membro['aluno_id']];
        if ($id) {
            $pdo->prepare('UPDATE orquestra_membros SET nome = ?, naipe = ?, instrumento = ?, telefone = ?, aluno_id = ?, ativo = ? WHERE id = ?')
                ->execute([...$dados, $membro['ativo'], $id]);
            header('Location: index.php?msg=editado');
        } else {
            $pdo->prepare('INSERT INTO orquestra_membros (nome, naipe, instrumento, telefone, aluno_id) VALUES (?, ?, ?, ?, ?)')
                ->execute($dados);
            header('Location: index.php?msg=criado');
        }
        exit;
    }
}

$titulo = $id ? 'Editar membro' : 'Novo membro da orquestra';
$menu   = 'orquestra';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🎼 <?= $titulo ?></h1>
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
            <label for="aluno_id">É aluno da escola? <small>(opcional)</small></label>
            <select id="aluno_id" name="aluno_id">
                <option value="">Não, é de fora (ou não quero ligar)</option>
                <?php foreach ($alunos as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= (int) $membro['aluno_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo campo-inteiro">
            <label for="nome">Nome * <small>(em branco = usa o nome do aluno escolhido)</small></label>
            <input id="nome" name="nome" value="<?= e($membro['nome']) ?>">
        </div>
        <div class="campo">
            <label for="naipe">Naipe *</label>
            <select id="naipe" name="naipe" required>
                <?php foreach (NAIPES as $n): ?>
                    <option <?= $membro['naipe'] === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="instrumento">Instrumento *</label>
            <input id="instrumento" name="instrumento" required placeholder="Ex.: Violoncelo" value="<?= e($membro['instrumento']) ?>">
        </div>
        <div class="campo">
            <label for="telefone">Telefone</label>
            <input id="telefone" name="telefone" value="<?= e($membro['telefone'] ?? '') ?>">
        </div>
        <?php if ($id): ?>
            <div class="campo">
                <label>Situação</label>
                <label class="check"><input type="checkbox" name="ativo" value="1" <?= $membro['ativo'] ? 'checked' : '' ?>> Ativo na orquestra</label>
            </div>
        <?php endif; ?>
        <div class="botoes-form">
            <a class="botao" href="index.php">Cancelar</a>
            <button class="botao botao-primario" type="submit">Salvar</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
