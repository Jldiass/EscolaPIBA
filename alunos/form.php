<?php
/**
 * Formulário de aluno: serve para CADASTRAR e para EDITAR.
 *
 * - form.php         -> formulário vazio (novo aluno)
 * - form.php?id=3    -> formulário preenchido com o aluno 3 (editar)
 *
 * Quando o usuário clica em Salvar, o formulário é enviado (POST)
 * para esta mesma página, que valida e grava no banco.
 */
require __DIR__ . '/../includes/auth.php';

exigirSecretaria(); // só a secretaria cadastra e edita alunos
$pdo = conectar();

// Instrumentos/especialidades ensinados hoje na escola
$instrumentos = ['Violino', 'Viola', 'Bateria', 'Teoria musical', 'Outro'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$erros = [];

// Valores padrão (aluno novo)
$aluno = [
    'nome' => '', 'data_nascimento' => '', 'responsavel' => '',
    'telefone' => '', 'instrumento' => '', 'ativo' => 1,
];

// Editando: busca o aluno no banco
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM alunos WHERE id = ?');
    $stmt->execute([$id]);
    $aluno = $stmt->fetch();
    if (!$aluno) {
        http_response_code(404);
        exit('Aluno não encontrado.');
    }
}

// O formulário foi enviado?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    // Pega o que foi digitado
    $aluno['nome']            = trim($_POST['nome'] ?? '');
    $aluno['data_nascimento'] = $_POST['data_nascimento'] ?? '';
    $aluno['responsavel']     = trim($_POST['responsavel'] ?? '');
    $aluno['telefone']        = trim($_POST['telefone'] ?? '');
    $aluno['instrumento']     = $_POST['instrumento'] ?? '';
    $aluno['ativo']           = isset($_POST['ativo']) ? 1 : 0;

    // Validação: regras que o dado precisa cumprir
    if (mb_strlen($aluno['nome']) < 3) {
        $erros[] = 'Informe o nome completo do aluno.';
    }
    if ($aluno['instrumento'] === '' || !in_array($aluno['instrumento'], $instrumentos, true)) {
        $erros[] = 'Escolha um instrumento.';
    }
    // Menor de 18 anos precisa de responsável
    if ($aluno['data_nascimento'] !== '') {
        $idade = (new DateTime($aluno['data_nascimento']))->diff(new DateTime())->y;
        if ($idade < 18 && $aluno['responsavel'] === '') {
            $erros[] = 'Aluno menor de idade: informe o responsável.';
        }
    }

    // Sem erros: grava no banco
    if (count($erros) === 0) {
        $dados = [
            ':nome'            => $aluno['nome'],
            ':data_nascimento' => $aluno['data_nascimento'] ?: null,
            ':responsavel'     => $aluno['responsavel'] ?: null,
            ':telefone'        => $aluno['telefone'] ?: null,
            ':instrumento'     => $aluno['instrumento'],
            ':ativo'           => $aluno['ativo'],
        ];

        if ($id) {
            $dados[':id'] = $id;
            $pdo->prepare(
                'UPDATE alunos SET nome = :nome, data_nascimento = :data_nascimento,
                 responsavel = :responsavel, telefone = :telefone,
                 instrumento = :instrumento, ativo = :ativo
                 WHERE id = :id'
            )->execute($dados);
            header('Location: ver.php?id=' . $id . '&msg=editado');
            exit;
        } else {
            $pdo->prepare(
                'INSERT INTO alunos (nome, data_nascimento, responsavel, telefone, instrumento, ativo)
                 VALUES (:nome, :data_nascimento, :responsavel, :telefone, :instrumento, :ativo)'
            )->execute($dados);
            // Vai direto para a ficha do aluno, onde a secretaria faz a matrícula
            header('Location: ver.php?id=' . $pdo->lastInsertId() . '&msg=criado');
            exit;
        }
        // Os redirecionamentos seguem o padrão Post/Redirect/Get:
        // evitam salvar duas vezes se a pessoa apertar F5.
    }
}

$titulo = $id ? 'Editar aluno' : 'Novo aluno';
$menu   = 'alunos';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1><?= $id ? '✎ Editar aluno' : '♪ Novo aluno' ?></h1>
        <p>Campos com * são obrigatórios.</p>
    </div>
    <a class="botao" href="<?= $id ? 'ver.php?id=' . $id : 'index.php' ?>">← Voltar</a>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro">
        <?php foreach ($erros as $erro): ?>
            <div><?= e($erro) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="cartao">
    <form class="formulario" method="post">
        <?= campoCsrf() ?>
        <div class="campo campo-inteiro">
            <label for="nome">Nome completo *</label>
            <input id="nome" name="nome" required value="<?= e($aluno['nome']) ?>">
        </div>

        <div class="campo">
            <label for="data_nascimento">Data de nascimento</label>
            <input id="data_nascimento" name="data_nascimento" type="date" value="<?= e($aluno['data_nascimento']) ?>">
        </div>

        <div class="campo">
            <label for="instrumento">Instrumento *</label>
            <select id="instrumento" name="instrumento" required>
                <option value="">Selecione...</option>
                <?php foreach ($instrumentos as $inst): ?>
                    <option <?= $inst === $aluno['instrumento'] ? 'selected' : '' ?>><?= e($inst) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="responsavel">Responsável (se menor de idade)</label>
            <input id="responsavel" name="responsavel" value="<?= e($aluno['responsavel']) ?>">
        </div>

        <div class="campo">
            <label for="telefone">Telefone</label>
            <input id="telefone" name="telefone" placeholder="(00) 00000-0000" value="<?= e($aluno['telefone']) ?>">
        </div>

        <div class="campo campo-inteiro">
            <label><input type="checkbox" name="ativo" <?= $aluno['ativo'] ? 'checked' : '' ?>> Aluno ativo</label>
        </div>

        <div class="botoes-form">
            <a class="botao" href="index.php">Cancelar</a>
            <button class="botao botao-primario" type="submit">Salvar</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
