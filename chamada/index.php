<?php
/**
 * Chamada: o professor marca quem veio e quem faltou nas aulas de um dia.
 * - Por padrão abre o dia de hoje; dá para voltar dias para lançar chamada atrasada.
 * - O admin só CONSULTA: vê quem veio, quem faltou e quais chamadas ainda não
 *   foram feitas, mas não marca nada (quem faz a chamada é o professor).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();

// Data da chamada (AAAA-MM-DD). Se vier inválida ou no futuro, usa hoje.
$data = $_GET['data'] ?? date('Y-m-d');
if (!dataValida($data) || $data > date('Y-m-d')) {
    $data = date('Y-m-d');
}
$dt = new DateTime($data);
$diaSemana = (int) $dt->format('N');

$professorId = ehAdmin() ? ((int) ($_GET['professor'] ?? 0) ?: null) : (int) $usuario['id'];
$aulas = aulasDoDia($diaSemana, $professorId);

// Salvar chamada (só o professor)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ehAdmin()) {
        http_response_code(403);
        exit('A chamada é feita pelo professor. O administrador só consulta.');
    }
    verificarCsrf();
    $idsPermitidos = array_column($aulas, 'id');   // só as aulas que esta pessoa pode ver

    // "UPSERT": insere; se já existe chamada dessa aula nesse dia, atualiza
    $salvar = $pdo->prepare(
        'INSERT INTO presencas (matricula_id, data, presente, observacao) VALUES (?, ?, ?, ?)
         ON CONFLICT (matricula_id, data) DO UPDATE SET presente = excluded.presente, observacao = excluded.observacao'
    );
    $salvas = 0;
    foreach ($_POST['presenca'] ?? [] as $matriculaId => $valor) {
        if (!in_array($matriculaId, $idsPermitidos) || !in_array($valor, ['1', '0'], true)) {
            continue;
        }
        $obs = trim($_POST['observacao'][$matriculaId] ?? '');
        $salvar->execute([$matriculaId, $data, (int) $valor, $obs ?: null]);
        $salvas++;
    }
    header('Location: index.php?data=' . $data . ($professorId && ehAdmin() ? '&professor=' . $professorId : '') . '&salvas=' . $salvas);
    exit;
}

// Chamadas já feitas nesse dia, para deixar o formulário marcado
$feitas = [];
if ($aulas) {
    $marcadores = implode(',', array_fill(0, count($aulas), '?'));
    $stmt = $pdo->prepare("SELECT * FROM presencas WHERE data = ? AND matricula_id IN ($marcadores)");
    $stmt->execute(array_merge([$data], array_column($aulas, 'id')));
    foreach ($stmt->fetchAll() as $p) {
        $feitas[$p['matricula_id']] = $p;
    }
}

$anterior = (clone $dt)->modify('-1 day')->format('Y-m-d');
$proximo  = (clone $dt)->modify('+1 day')->format('Y-m-d');
$filtroProf = $professorId && ehAdmin() ? '&professor=' . $professorId : '';

// Resumo do dia (para o admin): presentes, faltas e chamadas que o professor ainda não fez
$presentes = count(array_filter($feitas, fn($p) => $p['presente']));
$faltas    = count($feitas) - $presentes;
$pendentes = count($aulas) - count($feitas);
$professores = ehAdmin()
    ? $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll()
    : [];

$titulo = 'Chamada';
$menu   = 'chamada';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>✓ Chamada</h1>
        <p><?= DIAS_SEMANA[$diaSemana] ?>, <?= $dt->format('d/m/Y') ?> · <?= count($aulas) ?> aula(s)
            <?= ehAdmin() ? ' · consulta: quem marca a chamada é o professor' : '' ?></p>
    </div>
    <div class="acoes">
        <a class="botao" href="?data=<?= $anterior . $filtroProf ?>">← Dia anterior</a>
        <a class="botao" href="?data=<?= date('Y-m-d') . $filtroProf ?>">Hoje</a>
        <?php if ($data < date('Y-m-d')): ?>
            <a class="botao" href="?data=<?= $proximo . $filtroProf ?>">Próximo dia →</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['salvas'])): ?>
    <div class="alerta alerta-sucesso">Chamada salva (<?= (int) $_GET['salvas'] ?> aluno(s)). ♪</div>
<?php endif; ?>

<?php if (ehAdmin()): ?>
<form class="filtros" method="get">
    <input type="hidden" name="data" value="<?= e($data) ?>">
    <select class="select-simples" name="professor" onchange="this.form.submit()">
        <option value="">Todos os professores</option>
        <?php foreach ($professores as $prof): ?>
            <option value="<?= $prof['id'] ?>" <?= $professorId === (int) $prof['id'] ? 'selected' : '' ?>><?= e($prof['nome']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="botao" type="submit">Filtrar</button></noscript>
</form>

<section class="indicadores">
    <div class="indicador"><span class="icone">✓</span><div class="rotulo">Presentes</div><div class="valor"><?= $presentes ?></div></div>
    <div class="indicador"><span class="icone">✗</span><div class="rotulo">Faltas</div><div class="valor"><?= $faltas ?></div></div>
    <div class="indicador"><span class="icone">⏳</span><div class="rotulo">Chamadas não feitas</div><div class="valor"><?= $pendentes ?></div></div>
</section>
<?php endif; ?>

<section class="cartao">
    <?php if (!$aulas): ?>
        <p class="vazio">Nenhuma aula neste dia. 𝄽</p>
    <?php elseif (ehAdmin()): ?>
        <!-- Admin: só leitura -->
        <table class="tabela">
            <thead>
                <tr><th>Horário</th><th>Aluno</th><th>Instrumento</th><th>Professor</th><th>Situação</th><th class="ocultar-celular">Observação</th></tr>
            </thead>
            <tbody>
            <?php foreach ($aulas as $aula): ?>
                <?php $p = $feitas[$aula['id']] ?? null; ?>
                <tr>
                    <td class="numero"><?= e($aula['hora_inicio']) ?></td>
                    <td><a href="../alunos/ver.php?id=<?= $aula['aluno_id'] ?>"><?= e($aula['aluno']) ?></a></td>
                    <td><span class="selo selo-instrumento"><?= e($aula['instrumento']) ?></span></td>
                    <td><?= e($aula['professor']) ?></td>
                    <td>
                        <?php if (!$p): ?><span class="selo selo-pendente">Chamada não feita</span>
                        <?php elseif ($p['presente']): ?><span class="selo selo-ativo">Presente</span>
                        <?php else: ?><span class="selo selo-falta">Faltou</span><?php endif; ?>
                    </td>
                    <td class="ocultar-celular suave"><?= e($p['observacao'] ?? '') ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <form method="post">
            <?= campoCsrf() ?>
            <table class="tabela">
                <thead>
                    <tr><th>Horário</th><th>Aluno</th><th>Instrumento</th>
                        <th>Presença</th><th class="ocultar-celular">Observação</th></tr>
                </thead>
                <tbody>
                <?php foreach ($aulas as $aula): ?>
                    <?php $p = $feitas[$aula['id']] ?? null; ?>
                    <tr>
                        <td class="numero"><?= e($aula['hora_inicio']) ?></td>
                        <td><?= e($aula['aluno']) ?></td>
                        <td><span class="selo selo-instrumento"><?= e($aula['instrumento']) ?></span></td>
                        <td>
                            <!-- Dois "botões" de rádio: só um pode ficar marcado -->
                            <div class="presenca">
                                <label class="presenca-sim">
                                    <input type="radio" name="presenca[<?= $aula['id'] ?>]" value="1" <?= $p && $p['presente'] ? 'checked' : '' ?>>
                                    <span>Presente</span>
                                </label>
                                <label class="presenca-nao">
                                    <input type="radio" name="presenca[<?= $aula['id'] ?>]" value="0" <?= $p && !$p['presente'] ? 'checked' : '' ?>>
                                    <span>Faltou</span>
                                </label>
                            </div>
                        </td>
                        <td class="ocultar-celular">
                            <input class="campo-tabela" name="observacao[<?= $aula['id'] ?>]" placeholder="Opcional"
                                   value="<?= e($p['observacao'] ?? '') ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="botoes-form">
                <button class="botao botao-primario" type="submit">Salvar chamada</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
