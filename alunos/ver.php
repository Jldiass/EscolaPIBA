<?php
/**
 * Ficha do aluno: dados pessoais, matrículas (professor, horário,
 * mensalidade) e frequência de cada matrícula.
 * O admin também matricula o aluno com um professor aqui.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM alunos WHERE id = ?');
$stmt->execute([$id]);
$aluno = $stmt->fetch();
if (!$aluno) {
    http_response_code(404);
    exit('Aluno não encontrado.');
}

// Matrículas do aluno + quantas aulas e presenças cada uma tem
$stmt = $pdo->prepare(
    'SELECT m.*, u.nome AS professor,
            (SELECT COUNT(*) FROM presencas p WHERE p.matricula_id = m.id) AS aulas,
            (SELECT COUNT(*) FROM presencas p WHERE p.matricula_id = m.id AND p.presente = 1) AS presencas
     FROM matriculas m JOIN usuarios u ON u.id = m.professor_id
     WHERE m.aluno_id = ?
     ORDER BY m.ativo DESC, m.dia_semana, m.hora_inicio'
);
$stmt->execute([$id]);
$matriculas = $stmt->fetchAll();

// Professor só pode ver alunos que estudam com ele
if (!ehAdmin() && !in_array($usuario['id'], array_column($matriculas, 'professor_id'))) {
    http_response_code(403);
    exit('Este aluno não está matriculado com você.');
}

$professores = $pdo->query("SELECT id, nome, instrumentos FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll();

// Últimas chamadas do aluno
$stmt = $pdo->prepare(
    'SELECT p.*, m.instrumento FROM presencas p JOIN matriculas m ON m.id = p.matricula_id
     WHERE m.aluno_id = ? ORDER BY p.data DESC LIMIT 10'
);
$stmt->execute([$id]);
$historico = $stmt->fetchAll();

$mensagens = [
    'criado'     => 'Aluno cadastrado! Agora matricule-o com um professor abaixo.',
    'editado'    => 'Dados do aluno atualizados.',
    'matriculado'=> 'Matrícula feita. A aula já aparece na agenda do professor.',
    'encerrada'  => 'Matrícula encerrada.',
    'reativada'  => 'Matrícula reativada.',
];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;
$erro = $_GET['erro'] ?? null;

$titulo = $aluno['nome'];
$menu   = 'alunos';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>♪ <?= e($aluno['nome']) ?></h1>
        <p><?= e($aluno['instrumento']) ?> · <?= $aluno['ativo'] ? 'Aluno ativo' : 'Aluno inativo' ?></p>
    </div>
    <div class="acoes">
        <a class="botao" href="index.php">← Voltar</a>
        <?php if (ehAdmin()): ?>
            <a class="botao" href="form.php?id=<?= $id ?>">Editar dados</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

<div class="grade-2">
    <section class="cartao">
        <h2>Dados</h2>
        <dl class="dados">
            <dt>Nascimento</dt><dd><?= $aluno['data_nascimento'] ? date('d/m/Y', strtotime($aluno['data_nascimento'])) : '-' ?></dd>
            <dt>Responsável</dt><dd><?= e($aluno['responsavel'] ?: '-') ?></dd>
            <dt>Telefone</dt><dd><?= e($aluno['telefone'] ?: '-') ?></dd>
            <dt>Cadastrado em</dt><dd><?= date('d/m/Y', strtotime($aluno['criado_em'])) ?></dd>
        </dl>
    </section>

    <section class="cartao">
        <h2>Últimas chamadas</h2>
        <?php if (!$historico): ?>
            <p class="vazio">Nenhuma chamada registrada ainda.</p>
        <?php else: ?>
            <div class="historico">
                <?php foreach ($historico as $h): ?>
                    <span class="selo <?= $h['presente'] ? 'selo-ativo' : 'selo-falta' ?>" title="<?= e($h['instrumento']) ?>">
                        <?= date('d/m', strtotime($h['data'])) ?> · <?= $h['presente'] ? 'Presente' : 'Faltou' ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="cartao" style="margin-top: 18px">
    <h2>Matrículas e horários</h2>
    <?php if (!$matriculas): ?>
        <p class="vazio">Ainda sem matrícula. <?= ehAdmin() ? 'Use o formulário abaixo.' : '' ?></p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th>Instrumento</th><th>Professor</th><th>Aula semanal</th>
                    <th>Mensalidade</th><th>Frequência</th><th>Situação</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matriculas as $m): ?>
                <tr>
                    <td><span class="selo selo-instrumento"><?= e($m['instrumento']) ?></span></td>
                    <td><?= e($m['professor']) ?></td>
                    <td><?= DIAS_SEMANA[$m['dia_semana']] ?>, <?= e($m['hora_inicio']) ?>–<?= e($m['hora_fim']) ?></td>
                    <td class="numero"><?= dinheiro($m['mensalidade']) ?></td>
                    <td class="numero"><?= $m['aulas'] ? round($m['presencas'] * 100 / $m['aulas']) . '%' : '-' ?>
                        <small class="suave">(<?= $m['presencas'] ?>/<?= $m['aulas'] ?>)</small></td>
                    <td><?= $m['ativo'] ? '<span class="selo selo-ativo">Ativa</span>' : '<span class="selo selo-inativo">Encerrada</span>' ?></td>
                    <td>
                        <?php if (ehAdmin()): ?>
                            <form method="post" action="matricula.php" class="acoes">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="matricula_id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="aluno_id" value="<?= $id ?>">
                                <?php if ($m['ativo']): ?>
                                    <button class="botao botao-pequeno botao-perigo" name="acao" value="encerrar">Encerrar</button>
                                <?php else: ?>
                                    <button class="botao botao-pequeno" name="acao" value="reativar">Reativar</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php if (ehAdmin()): ?>
<section class="cartao" style="margin-top: 18px">
    <h2>+ Nova matrícula</h2>
    <p class="suave">Liga o aluno a um professor, com o dia e horário fixo da aula e o valor da mensalidade.</p>
    <form class="formulario formulario-3" method="post" action="matricula.php">
        <?= campoCsrf() ?>
        <input type="hidden" name="acao" value="criar">
        <input type="hidden" name="aluno_id" value="<?= $id ?>">

        <div class="campo campo-duplo">
            <label for="professor_especialidade">Professor e especialidade *</label>
            <!-- Cada professor aparece uma vez para cada especialidade dele.
                 O valor enviado é "id|especialidade", ex.: "4|Bateria" -->
            <select id="professor_especialidade" name="professor_especialidade" required>
                <option value="">Selecione...</option>
                <?php foreach ($professores as $p): ?>
                    <?php foreach (especialidades($p['instrumentos']) as $esp): ?>
                        <option value="<?= $p['id'] ?>|<?= e($esp) ?>"
                            <?= $esp === $aluno['instrumento'] ? 'selected' : '' ?>>
                            <?= e($p['nome']) ?> — <?= e($esp) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="mensalidade">Mensalidade (R$) *</label>
            <input id="mensalidade" name="mensalidade" type="number" min="0" step="0.01" required value="80">
        </div>
        <div class="campo">
            <label for="dia_semana">Dia da aula *</label>
            <select id="dia_semana" name="dia_semana" required>
                <?php foreach (DIAS_SEMANA as $n => $dia): ?>
                    <option value="<?= $n ?>"><?= $dia ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="hora_inicio">Início *</label>
            <input id="hora_inicio" name="hora_inicio" type="time" required value="14:00">
        </div>
        <div class="campo">
            <label for="hora_fim">Fim *</label>
            <input id="hora_fim" name="hora_fim" type="time" required value="15:00">
        </div>
        <div class="botoes-form">
            <button class="botao botao-primario" type="submit">Matricular</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
