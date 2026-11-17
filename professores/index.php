<?php
/**
 * Lista de professores (só secretaria).
 * Mostra as especialidades, quantos alunos cada um tem e o login.
 */
require __DIR__ . '/../includes/auth.php';

exigirSecretaria();

$professores = conectar()->query(
    "SELECT u.*,
            COUNT(DISTINCT m.aluno_id) AS qtd_alunos,
            COUNT(m.id)                AS aulas_semana
     FROM usuarios u
     LEFT JOIN matriculas m ON m.professor_id = u.id AND m.ativo = 1
     WHERE u.perfil = 'professor'
     GROUP BY u.id
     ORDER BY u.ativo DESC, u.nome"
)->fetchAll();

$mensagens = ['criado' => 'Professor cadastrado! Passe o login e a senha para ele.', 'editado' => 'Professor atualizado.'];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$titulo = 'Professores';
$menu   = 'professores';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>𝄞 Professores</h1>
        <p>Cada professor tem seu próprio login e vê só os alunos dele.</p>
    </div>
    <a class="botao botao-primario" href="form.php">+ Novo professor</a>
</div>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>

<div class="grade-cartoes">
    <?php foreach ($professores as $p): ?>
        <article class="cartao cartao-professor <?= $p['ativo'] ? '' : 'inativo' ?>">
            <div class="avatar"><?= e(mb_substr($p['nome'], 0, 1)) ?></div>
            <h2><?= e($p['nome']) ?></h2>
            <div class="especialidades">
                <?php foreach (especialidades($p['instrumentos']) as $esp): ?>
                    <a class="selo selo-instrumento" href="../alunos/index.php?esp=<?= urlencode($esp) ?>"><?= e($esp) ?></a>
                <?php endforeach; ?>
            </div>
            <dl class="dados">
                <dt>Alunos</dt><dd><?= $p['qtd_alunos'] ?></dd>
                <dt>Aulas/semana</dt><dd><?= $p['aulas_semana'] ?></dd>
                <dt>Login</dt><dd><code><?= e($p['login']) ?></code></dd>
                <dt>Situação</dt><dd><?= $p['ativo'] ? 'Ativo' : 'Inativo' ?></dd>
            </dl>
            <div class="acoes">
                <a class="botao botao-pequeno" href="../agenda.php?professor=<?= $p['id'] ?>">Agenda</a>
                <a class="botao botao-pequeno" href="form.php?id=<?= $p['id'] ?>">Editar</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
