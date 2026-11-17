<?php
/**
 * Mensalidades: o aluno paga ao professor, e o professor informa aqui,
 * mês a mês, quem pagou e quem não pagou.
 * - Professor: marca os alunos dele.
 * - Admin: só consulta (vê quem não pagou e quem ainda não foi informado).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/consultas.php';

$usuario = exigirLogin();
$pdo = conectar();

// Mês (AAAA-MM). Inválido ou no futuro -> mês atual.
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) || $mes > date('Y-m')) {
    $mes = date('Y-m');
}

$professorId = ehAdmin() ? ((int) ($_GET['professor'] ?? 0) ?: null) : (int) $usuario['id'];

// Alunos (matrículas ativas) com a informação do mês, se houver
$sql = 'SELECT m.id, m.instrumento, m.mensalidade, a.id AS aluno_id, a.nome AS aluno, u.nome AS professor,
               g.pago, g.observacao
        FROM matriculas m
        JOIN alunos a   ON a.id = m.aluno_id
        JOIN usuarios u ON u.id = m.professor_id
        LEFT JOIN pagamentos g ON g.matricula_id = m.id AND g.mes = :mes
        WHERE m.ativo = 1 AND a.ativo = 1';
$params = [':mes' => $mes];
if ($professorId) {
    $sql .= ' AND m.professor_id = :prof';
    $params[':prof'] = $professorId;
}
$stmt = $pdo->prepare($sql . ' ORDER BY u.nome, a.nome');
$stmt->execute($params);
$linhas = $stmt->fetchAll();
foreach ($linhas as &$l) {
    $l['pago'] = $l['pago'] === null ? null : (int) $l['pago'];   // null = professor ainda não informou
}
unset($l);

// Salvar (só o professor, só os alunos dele)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ehAdmin()) {
        http_response_code(403);
        exit('Quem informa o pagamento é o professor. O administrador só consulta.');
    }
    verificarCsrf();
    $permitidos = array_column($linhas, 'id');
    $salvar = $pdo->prepare(
        'INSERT INTO pagamentos (matricula_id, mes, pago, observacao) VALUES (?, ?, ?, ?)
         ON CONFLICT (matricula_id, mes) DO UPDATE SET pago = excluded.pago, observacao = excluded.observacao,
                                                       informado_em = datetime(\'now\', \'localtime\')'
    );
    $salvas = 0;
    foreach ($_POST['pago'] ?? [] as $matriculaId => $valor) {
        if (!in_array($matriculaId, $permitidos) || !in_array($valor, ['1', '0'], true)) {
            continue;
        }
        $obs = trim($_POST['observacao'][$matriculaId] ?? '');
        $salvar->execute([$matriculaId, $mes, (int) $valor, $obs ?: null]);
        $salvas++;
    }
    header('Location: index.php?mes=' . $mes . '&salvas=' . $salvas);
    exit;
}

// Resumo do mês
$pagaram     = array_filter($linhas, fn($l) => $l['pago'] === 1);
$naoPagaram  = array_filter($linhas, fn($l) => $l['pago'] === 0);
$semInfo     = array_filter($linhas, fn($l) => $l['pago'] === null);
$valorAberto = array_sum(array_column($naoPagaram, 'mensalidade'));

$professores = ehAdmin()
    ? $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll()
    : [];
$filtroProf  = $professorId && ehAdmin() ? '&professor=' . $professorId : '';
$mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mesSeguinte = date('Y-m', strtotime($mes . '-01 +1 month'));

$titulo = 'Mensalidades';
$menu   = 'mensalidades';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>💳 Mensalidades · <?= nomeMes($mes) ?></h1>
        <p><?= ehAdmin()
            ? 'Consulta: quem informa se o aluno pagou é o professor.'
            : 'Marque quem pagou e quem não pagou a mensalidade neste mês.' ?></p>
    </div>
    <div class="acoes">
        <a class="botao" href="?mes=<?= $mesAnterior . $filtroProf ?>">← <?= nomeMes($mesAnterior) ?></a>
        <?php if ($mes < date('Y-m')): ?>
            <a class="botao" href="?mes=<?= date('Y-m') . $filtroProf ?>">Mês atual</a>
            <a class="botao" href="?mes=<?= $mesSeguinte . $filtroProf ?>"><?= nomeMes($mesSeguinte) ?> →</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['salvas'])): ?>
    <div class="alerta alerta-sucesso">Mensalidades salvas (<?= (int) $_GET['salvas'] ?> aluno(s)).</div>
<?php endif; ?>

<?php if (ehAdmin()): ?>
<form class="filtros" method="get">
    <input type="hidden" name="mes" value="<?= e($mes) ?>">
    <select class="select-simples" name="professor" onchange="this.form.submit()">
        <option value="">Todos os professores</option>
        <?php foreach ($professores as $prof): ?>
            <option value="<?= $prof['id'] ?>" <?= $professorId === (int) $prof['id'] ? 'selected' : '' ?>><?= e($prof['nome']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="botao" type="submit">Filtrar</button></noscript>
</form>
<?php endif; ?>

<section class="indicadores">
    <div class="cartao indicador"><span class="icone">✓</span><div class="rotulo">Pagaram</div><div class="valor"><?= count($pagaram) ?></div></div>
    <div class="cartao indicador"><span class="icone">✗</span><div class="rotulo">Não pagaram</div>
        <div class="valor <?= $naoPagaram ? 'texto-alerta' : '' ?>"><?= count($naoPagaram) ?></div></div>
    <div class="cartao indicador"><span class="icone">⏳</span><div class="rotulo">Sem informação</div><div class="valor"><?= count($semInfo) ?></div></div>
    <div class="cartao indicador"><span class="icone">💰</span><div class="rotulo">Valor em aberto</div><div class="valor"><?= dinheiro($valorAberto) ?></div></div>
</section>

<section class="cartao">
    <?php if (!$linhas): ?>
        <p class="vazio">Nenhum aluno ativo.</p>
    <?php elseif (ehAdmin()): ?>
        <table class="tabela">
            <thead><tr><th>Aluno</th><th>Instrumento</th><th>Professor</th><th>Mensalidade</th><th>Situação</th><th class="ocultar-celular">Observação</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $l): ?>
                <tr>
                    <td><a href="../alunos/ver.php?id=<?= $l['aluno_id'] ?>"><?= e($l['aluno']) ?></a></td>
                    <td><span class="selo selo-instrumento"><?= e($l['instrumento']) ?></span></td>
                    <td><?= e($l['professor']) ?></td>
                    <td class="numero"><?= dinheiro($l['mensalidade']) ?></td>
                    <td>
                        <?php if ($l['pago'] === null): ?><span class="selo selo-pendente">Sem informação</span>
                        <?php elseif ($l['pago']): ?><span class="selo selo-ativo">Pagou</span>
                        <?php else: ?><span class="selo selo-falta">Não pagou</span><?php endif; ?>
                    </td>
                    <td class="ocultar-celular suave"><?= e($l['observacao'] ?? '') ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <form method="post">
            <?= campoCsrf() ?>
            <table class="tabela">
                <thead><tr><th>Aluno</th><th>Instrumento</th><th>Mensalidade</th><th>Pagou?</th><th class="ocultar-celular">Observação</th></tr></thead>
                <tbody>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td><?= e($l['aluno']) ?></td>
                        <td><span class="selo selo-instrumento"><?= e($l['instrumento']) ?></span></td>
                        <td class="numero"><?= dinheiro($l['mensalidade']) ?></td>
                        <td>
                            <div class="presenca">
                                <label class="presenca-sim">
                                    <input type="radio" name="pago[<?= $l['id'] ?>]" value="1" <?= $l['pago'] === 1 ? 'checked' : '' ?>>
                                    <span>Pagou</span>
                                </label>
                                <label class="presenca-nao">
                                    <input type="radio" name="pago[<?= $l['id'] ?>]" value="0" <?= $l['pago'] === 0 ? 'checked' : '' ?>>
                                    <span>Não pagou</span>
                                </label>
                            </div>
                        </td>
                        <td class="ocultar-celular">
                            <input class="campo-tabela" name="observacao[<?= $l['id'] ?>]" placeholder="Opcional (ex.: paga dia 20)"
                                   value="<?= e($l['observacao'] ?? '') ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="botoes-form">
                <button class="botao botao-primario" type="submit">Salvar mensalidades</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
