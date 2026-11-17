<?php
/**
 * Painel inicial.
 * A secretaria vê números da escola toda; o professor vê os dele.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();

$hoje = (int) date('N');               // 1 = segunda ... 7 = domingo
$profFiltro = ehSecretaria() ? null : (int) $usuario['id'];
$aulasHoje = aulasDoDia($hoje, $profFiltro);
$mesAtual = date('Y-m');

if (ehSecretaria()) {
    $alunosAtivos = $pdo->query('SELECT COUNT(*) FROM alunos WHERE ativo = 1')->fetchColumn();
    $professores  = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'professor' AND ativo = 1")->fetchColumn();
    $aulasSemana  = $pdo->query('SELECT COUNT(*) FROM matriculas WHERE ativo = 1')->fetchColumn();
    $financeiro   = resumoFinanceiro($mesAtual);
    $pendentes    = array_filter($financeiro, fn($l) => !$l['repassado'] && $l['parte_escola'] > 0);
} else {
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT aluno_id) FROM matriculas WHERE professor_id = ? AND ativo = 1');
    $stmt->execute([$usuario['id']]);
    $alunosAtivos = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM matriculas WHERE professor_id = ? AND ativo = 1');
    $stmt->execute([$usuario['id']]);
    $aulasSemana = $stmt->fetchColumn();

    // Frequência média dos alunos do professor nos últimos 30 dias
    $freq = relatorioFrequencia((int) $usuario['id'], date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    $aulas = array_sum(array_column($freq, 'aulas'));
    $freqMedia = $aulas ? round(array_sum(array_column($freq, 'presencas')) * 100 / $aulas) : 0;
}

$titulo = 'Início';
$menu   = 'inicio';
require __DIR__ . '/includes/header.php';
?>

<section class="banner">
    <h1>Olá, <?= e(explode(' ', $usuario['nome'])[0]) ?>!</h1>
    <p>Hoje é <?= DIAS_SEMANA[$hoje] ?>, <?= date('d/m/Y') ?>.
       <?= count($aulasHoje) ? 'Há ' . count($aulasHoje) . ' aula(s) marcada(s) para hoje.' : 'Nenhuma aula marcada para hoje.' ?></p>
</section>

<section class="indicadores">
    <div class="cartao indicador">
        <span class="icone">♫</span>
        <div class="rotulo"><?= ehSecretaria() ? 'Alunos ativos' : 'Meus alunos' ?></div>
        <div class="valor"><?= $alunosAtivos ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">🗓</span>
        <div class="rotulo">Aulas por semana</div>
        <div class="valor"><?= $aulasSemana ?></div>
    </div>
    <?php if (ehSecretaria()): ?>
        <div class="cartao indicador">
            <span class="icone">𝄞</span>
            <div class="rotulo">Professores</div>
            <div class="valor"><?= $professores ?></div>
        </div>
        <a class="cartao indicador" href="financeiro/index.php">
            <span class="icone">💰</span>
            <div class="rotulo">Repasses pendentes (<?= nomeMes($mesAtual) ?>)</div>
            <div class="valor <?= $pendentes ? 'texto-alerta' : '' ?>"><?= count($pendentes) ?></div>
        </a>
    <?php else: ?>
        <div class="cartao indicador">
            <span class="icone">📊</span>
            <div class="rotulo">Frequência (30 dias)</div>
            <div class="valor"><?= $freqMedia ?>%</div>
        </div>
    <?php endif; ?>
</section>

<section class="cartao">
    <div class="topo">
        <h2>Aulas de hoje</h2>
        <a class="botao botao-pequeno" href="chamada/index.php">Fazer chamada</a>
    </div>
    <?php if (!$aulasHoje): ?>
        <p class="vazio">Sem aulas hoje. Bom descanso! 𝄽</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th>Horário</th><th>Aluno</th><th>Instrumento</th><?= ehSecretaria() ? '<th>Professor</th>' : '' ?></tr>
            </thead>
            <tbody>
            <?php foreach ($aulasHoje as $aula): ?>
                <tr>
                    <td class="numero"><?= e($aula['hora_inicio']) ?> – <?= e($aula['hora_fim']) ?></td>
                    <td><?= e($aula['aluno']) ?></td>
                    <td><span class="selo selo-instrumento"><?= e($aula['instrumento']) ?></span></td>
                    <?php if (ehSecretaria()): ?><td><?= e($aula['professor']) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
