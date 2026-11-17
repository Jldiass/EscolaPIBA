<?php
/**
 * Painel inicial.
 * O admin vê números da escola toda; o professor vê os dele.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();

$hoje = (int) date('N');               // 1 = segunda ... 7 = domingo
$profFiltro = ehAdmin() ? null : (int) $usuario['id'];
$aulasHoje = aulasDoDia($hoje, $profFiltro);
$mesAtual = date('Y-m');

if (ehAdmin()) {
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
    <div class="bolhas" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="banner-texto">
        <span class="etiqueta"><b></b><?= DIAS_SEMANA[$hoje] ?>, <?= date('d/m/Y') ?></span>
        <h1>Olá, <?= e(explode(' ', $usuario['nome'])[0]) ?>! <span class="destaque">Vamos fazer música</span></h1>
        <p><?= count($aulasHoje) ? 'Há ' . count($aulasHoje) . ' aula(s) marcada(s) para hoje.' : 'Nenhuma aula marcada para hoje.' ?>
           <?= ehAdmin() ? 'Acompanhe alunos, professores e repasses por aqui.' : 'Sua agenda e a chamada estão a um clique.' ?></p>
    </div>
    <!-- Nota musical 3D girando (Three.js). Sem internet, aparece uma nota simples. -->
    <div class="palco" id="palco" role="img" aria-label="Nota musical 3D girando">
        <?php if ($aulasHoje): ?>
            <div class="cartao-flutuante">
                <span class="ao-vivo">Próxima aula</span>
                <b><?= e($aulasHoje[0]['hora_inicio']) ?> · <?= e($aulasHoje[0]['aluno']) ?></b>
                <span class="suave"><?= e($aulasHoje[0]['instrumento']) ?></span>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="faixa" aria-hidden="true">
    <div class="trilho">
        <?php for ($i = 0; $i < 2; $i++): // repete 2x para a rolagem ficar contínua ?>
            <span class="tag" style="--c: var(--p-lavanda)">🎻 Violino</span>
            <span class="tag" style="--c: var(--p-rosa)">🎻 Viola</span>
            <span class="tag" style="--c: var(--p-amarelo)">🥁 Bateria</span>
            <span class="tag" style="--c: var(--p-ceu)">𝄞 Teoria musical</span>
            <span class="tag" style="--c: var(--p-menta)">♪ Aulas gratuitas para a comunidade</span>
            <span class="tag" style="--c: var(--p-pessego)">🎶 Escola de Música PIBA</span>
        <?php endfor; ?>
    </div>
</div>

<section class="indicadores">
    <div class="cartao indicador">
        <span class="icone">♫</span>
        <div class="rotulo"><?= ehAdmin() ? 'Alunos ativos' : 'Meus alunos' ?></div>
        <div class="valor"><?= $alunosAtivos ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">🗓</span>
        <div class="rotulo">Aulas por semana</div>
        <div class="valor"><?= $aulasSemana ?></div>
    </div>
    <?php if (ehAdmin()): ?>
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
                <tr><th>Horário</th><th>Aluno</th><th>Instrumento</th><?= ehAdmin() ? '<th>Professor</th>' : '' ?></tr>
            </thead>
            <tbody>
            <?php foreach ($aulasHoje as $aula): ?>
                <tr>
                    <td class="numero"><?= e($aula['hora_inicio']) ?> – <?= e($aula['hora_fim']) ?></td>
                    <td><?= e($aula['aluno']) ?></td>
                    <td><span class="selo selo-instrumento"><?= e($aula['instrumento']) ?></span></td>
                    <?php if (ehAdmin()): ?><td><?= e($aula['professor']) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- Three.js r128 guardado no próprio projeto: funciona mesmo sem internet -->
<script src="assets/js/vendor/three.min.js"></script>
<script src="assets/js/nota3d.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
