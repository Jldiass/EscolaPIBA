<?php
/**
 * Página inicial (painel).
 * Mostra números rápidos da escola e os últimos alunos cadastrados.
 */
require __DIR__ . '/config/database.php';

$pdo = conectar();

// COUNT(*) conta quantas linhas a consulta encontrou
$totalAlunos  = $pdo->query('SELECT COUNT(*) FROM alunos')->fetchColumn();
$alunosAtivos = $pdo->query('SELECT COUNT(*) FROM alunos WHERE ativo = 1')->fetchColumn();
// DISTINCT conta cada instrumento uma vez só
$instrumentos = $pdo->query("SELECT COUNT(DISTINCT instrumento) FROM alunos WHERE instrumento <> ''")->fetchColumn();

$ultimos = $pdo->query('SELECT nome, instrumento, criado_em FROM alunos ORDER BY id DESC LIMIT 5')->fetchAll();

$titulo = 'Início';
$menu   = 'inicio';
$raiz   = '';
require __DIR__ . '/includes/header.php';
?>

<section class="banner">
    <h1>Bem-vindo à Música PIBA</h1>
    <p>Sistema de gestão da escola de música comunitária: alunos, turmas, chamada e notas em um só lugar.</p>
</section>

<section class="indicadores">
    <div class="cartao indicador">
        <span class="icone">♫</span>
        <div class="rotulo">Alunos cadastrados</div>
        <div class="valor"><?= $totalAlunos ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">♪</span>
        <div class="rotulo">Alunos ativos</div>
        <div class="valor"><?= $alunosAtivos ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">🎻</span>
        <div class="rotulo">Instrumentos ensinados</div>
        <div class="valor"><?= $instrumentos ?></div>
    </div>
</section>

<section class="cartao">
    <div class="topo">
        <h2>Últimos alunos cadastrados</h2>
        <a class="botao botao-pequeno" href="alunos/index.php">Ver todos</a>
    </div>
    <table class="tabela">
        <thead>
            <tr><th>Nome</th><th>Instrumento</th><th class="ocultar-celular">Cadastrado em</th></tr>
        </thead>
        <tbody>
        <?php foreach ($ultimos as $aluno): ?>
            <tr>
                <td><?= e($aluno['nome']) ?></td>
                <td><span class="selo selo-instrumento"><?= e($aluno['instrumento']) ?></span></td>
                <td class="ocultar-celular"><?= date('d/m/Y', strtotime($aluno['criado_em'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
