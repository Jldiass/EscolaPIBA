<?php
/**
 * Calendário semanal de aulas.
 * - Professor: vê as aulas dele.
 * - Secretaria: vê de todos, com filtro por professor.
 *
 * Como as aulas são semanais (todo "segunda às 14h", por exemplo),
 * a agenda monta a semana a partir das matrículas ativas.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();

if (ehAdmin()) {
    $professorId = (int) ($_GET['professor'] ?? 0) ?: null;
    $professores = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll();
} else {
    $professorId = (int) $usuario['id'];   // professor só vê a própria agenda
}

// Semana exibida: ?semana=0 é a atual, -1 a anterior, 1 a próxima
$deslocamento = (int) ($_GET['semana'] ?? 0);
$segunda = new DateTime('monday this week');
$segunda->modify(($deslocamento >= 0 ? '+' : '') . $deslocamento . ' week');

$semana = [];
for ($dia = 1; $dia <= 7; $dia++) {
    $data = (clone $segunda)->modify('+' . ($dia - 1) . ' day');
    $semana[$dia] = ['data' => $data, 'aulas' => aulasDoDia($dia, $professorId)];
}
// Domingo só aparece se tiver aula
if (!$semana[7]['aulas']) {
    unset($semana[7]);
}

// Uma cor para cada professor, para diferenciar na agenda geral
$cores = ['#e8b54a', '#5aa9e6', '#b2445c', '#3fbf7f', '#a78bfa', '#f97316'];

$totalAulas = array_sum(array_map(fn($d) => count($d['aulas']), $semana));
$urlSemana = fn(int $s) => '?semana=' . $s . ($professorId && ehAdmin() ? '&professor=' . $professorId : '');

$titulo = 'Agenda';
$menu   = 'agenda';
require __DIR__ . '/includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🗓 Agenda da semana</h1>
        <p><?= $segunda->format('d/m') ?> a <?= (clone $segunda)->modify('+6 day')->format('d/m/Y') ?> · <?= $totalAulas ?> aula(s)</p>
    </div>
    <div class="acoes">
        <?php if (ehAdmin()): ?>
            <form method="get">
                <input type="hidden" name="semana" value="<?= $deslocamento ?>">
                <select name="professor" onchange="this.form.submit()" class="select-simples">
                    <option value="">Todos os professores</option>
                    <?php foreach ($professores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $professorId == $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <a class="botao" href="<?= $urlSemana($deslocamento - 1) ?>">← Anterior</a>
        <a class="botao" href="<?= $urlSemana(0) ?>">Hoje</a>
        <a class="botao" href="<?= $urlSemana($deslocamento + 1) ?>">Próxima →</a>
    </div>
</div>

<div class="agenda">
    <?php foreach ($semana as $numero => $dia): ?>
        <?php $ehHoje = $dia['data']->format('Y-m-d') === date('Y-m-d'); ?>
        <section class="agenda-dia <?= $ehHoje ? 'agenda-hoje' : '' ?>">
            <header>
                <b><?= DIAS_SEMANA[$numero] ?></b>
                <span><?= $dia['data']->format('d/m') ?></span>
            </header>
            <?php if (!$dia['aulas']): ?>
                <p class="agenda-vazio">𝄽</p>
            <?php endif; ?>
            <?php foreach ($dia['aulas'] as $aula): ?>
                <a class="aula" style="--cor: <?= $cores[$aula['professor_id'] % count($cores)] ?>"
                   href="chamada/index.php?data=<?= $dia['data']->format('Y-m-d') ?>">
                    <span class="aula-hora"><?= e($aula['hora_inicio']) ?> – <?= e($aula['hora_fim']) ?></span>
                    <b><?= e($aula['aluno']) ?></b>
                    <span><?= e($aula['instrumento']) ?></span>
                    <?php if (ehAdmin()): ?><small><?= e($aula['professor']) ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
