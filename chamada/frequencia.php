<?php
/**
 * Relatório de frequência: quantas aulas cada aluno teve, quantas veio
 * e o percentual. Alunos abaixo de 75% ficam destacados (risco de desistência).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();

const FREQUENCIA_MINIMA = 75; // % abaixo do qual o aluno fica em alerta

$inicio = $_GET['inicio'] ?? date('Y-m-01');                       // padrão: começo do mês
$fim    = $_GET['fim']    ?? date('Y-m-d');                        // até hoje
if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    [$inicio, $fim] = [date('Y-m-01'), date('Y-m-d')];
}

if (ehAdmin()) {
    $professorId = (int) ($_GET['professor'] ?? 0) ?: null;
    $professores = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll();
} else {
    $professorId = (int) $usuario['id'];
}

$linhas = relatorioFrequencia($professorId, $inicio, $fim);

$totalAulas     = array_sum(array_column($linhas, 'aulas'));
$totalPresencas = array_sum(array_column($linhas, 'presencas'));
$media = $totalAulas ? round($totalPresencas * 100 / $totalAulas) : 0;
$emAlerta = array_filter($linhas, fn($l) => $l['percentual'] !== null && $l['percentual'] < FREQUENCIA_MINIMA);

$titulo = 'Frequência';
$menu   = 'frequencia';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>📊 Frequência</h1>
        <p>De <?= date('d/m/Y', strtotime($inicio)) ?> até <?= date('d/m/Y', strtotime($fim)) ?></p>
    </div>
    <form method="get" class="acoes">
        <?php if (ehAdmin()): ?>
            <select name="professor" class="select-simples">
                <option value="">Todos os professores</option>
                <?php foreach ($professores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $professorId == $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input class="select-simples" type="date" name="inicio" value="<?= e($inicio) ?>">
        <input class="select-simples" type="date" name="fim" value="<?= e($fim) ?>">
        <button class="botao" type="submit">Filtrar</button>
    </form>
</div>

<section class="indicadores">
    <div class="cartao indicador">
        <span class="icone">📊</span>
        <div class="rotulo">Frequência média</div>
        <div class="valor"><?= $media ?>%</div>
    </div>
    <div class="cartao indicador">
        <span class="icone">✓</span>
        <div class="rotulo">Presenças / aulas</div>
        <div class="valor"><?= $totalPresencas ?>/<?= $totalAulas ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">⚠</span>
        <div class="rotulo">Alunos abaixo de <?= FREQUENCIA_MINIMA ?>%</div>
        <div class="valor <?= $emAlerta ? 'texto-alerta' : '' ?>"><?= count($emAlerta) ?></div>
    </div>
</section>

<section class="cartao">
    <table class="tabela">
        <thead>
            <tr><th>Aluno</th><th>Instrumento</th><?= ehAdmin() ? '<th class="ocultar-celular">Professor</th>' : '' ?>
                <th>Aulas</th><th>Presenças</th><th>Faltas</th><th>Frequência</th></tr>
        </thead>
        <tbody>
        <?php foreach ($linhas as $l): ?>
            <?php $alerta = $l['percentual'] !== null && $l['percentual'] < FREQUENCIA_MINIMA; ?>
            <tr>
                <td><?= e($l['aluno']) ?> <?= $alerta ? '<span class="selo selo-falta">atenção</span>' : '' ?></td>
                <td><span class="selo selo-instrumento"><?= e($l['instrumento']) ?></span></td>
                <?php if (ehAdmin()): ?><td class="ocultar-celular"><?= e($l['professor']) ?></td><?php endif; ?>
                <td class="numero"><?= $l['aulas'] ?></td>
                <td class="numero"><?= $l['presencas'] ?></td>
                <td class="numero"><?= $l['faltas'] ?></td>
                <td>
                    <?php if ($l['percentual'] === null): ?>
                        <span class="suave">sem chamada</span>
                    <?php else: ?>
                        <div class="barra <?= $alerta ? 'barra-alerta' : '' ?>">
                            <span style="width: <?= $l['percentual'] ?>%"></span>
                        </div>
                        <b class="numero"><?= $l['percentual'] ?>%</b>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
