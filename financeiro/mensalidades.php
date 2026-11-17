<?php
/**
 * Financeiro > Mensalidades dos alunos: o aluno paga ao professor, e o professor informa aqui,
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
$sql = 'SELECT m.id, m.instrumento, m.mensalidade, m.bolsista, a.id AS aluno_id, a.nome AS aluno, a.telefone, a.responsavel,
               u.nome AS professor, g.pago, g.observacao
        FROM matriculas m
        JOIN alunos a   ON a.id = m.aluno_id
        JOIN usuarios u ON u.id = m.professor_id
        LEFT JOIN pagamentos g ON g.matricula_id = m.id AND g.mes = :mes
        WHERE m.ativo = 1 AND a.ativo = 1 AND ' . AULA_COM_MENSALIDADE;
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
    if (ehAdmin() && ($_POST['acao'] ?? '') === 'email_igreja') {
        verificarCsrf();
        $email = trim($_POST['email_igreja'] ?? '');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('email_igreja', ?)
                           ON CONFLICT (chave) DO UPDATE SET valor = excluded.valor")->execute([$email]);
        }
        header('Location: mensalidades.php?mes=' . $mes . '&email=1');
        exit;
    }
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
    header('Location: mensalidades.php?mes=' . $mes . '&salvas=' . $salvas);
    exit;
}

// Resumo do mês
$pagaram     = array_filter($linhas, fn($l) => $l['pago'] === 1);
$naoPagaram  = array_filter($linhas, fn($l) => $l['pago'] === 0);
$semInfo     = array_filter($linhas, fn($l) => $l['pago'] === null);
$alunosDevendo = array_filter($naoPagaram, fn($l) => !$l['bolsista']);   // o aluno paga
$bolsasDevendo = array_filter($naoPagaram, fn($l) => (bool) $l['bolsista']); // a igreja paga
$valorAberto   = array_sum(array_column($alunosDevendo, 'mensalidade'));
$valorBolsas   = array_sum(array_column($bolsasDevendo, 'mensalidade'));
$bolsasIgreja  = ehAdmin() ? bolsasEmAberto() : [];

$professores = ehAdmin()
    ? $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll()
    : [];
$filtroProf  = $professorId && ehAdmin() ? '&professor=' . $professorId : '';
$mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mesSeguinte = date('Y-m', strtotime($mes . '-01 +1 month'));

/** Botão de cobrança: WhatsApp para o aluno pagante; e-mail para a igreja no caso de bolsista. */
function botaoCobranca(array $l, string $mes): string
{
    if ($l['pago'] !== 0) {
        return '<small class="suave">—</small>';
    }
    if ($l['bolsista']) {
        return ehAdmin() ? '<a class="botao botao-pequeno botao-email" href="#bolsas-igreja">✉️ E-mail à igreja</a>'
                         : '<small class="suave">a secretaria cobra a igreja</small>';
    }
    $link = whatsappCobranca($l, [$mes]);
    return $link ? '<a class="botao botao-pequeno botao-whats" href="' . e($link) . '" target="_blank" rel="noopener" title="Abrir WhatsApp com a mensagem de cobrança pronta">💬 Cobrar</a>'
                 : '<small class="suave">sem telefone</small>';
}

$titulo = 'Mensalidades';
$menu   = 'financeiro';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1><?= ehAdmin() ? '💰 Financeiro' : '💳 Mensalidades' ?> · <?= nomeMes($mes) ?></h1>
        <p><?= ehAdmin()
            ? 'Mensalidades dos alunos (consulta): quem informa se o aluno pagou é o professor.'
            : 'Mensalidades: marque quem pagou e quem não pagou neste mês.' ?></p>
    </div>
    <div class="acoes">
        <a class="botao" href="?mes=<?= $mesAnterior . $filtroProf ?>">← <?= nomeMes($mesAnterior) ?></a>
        <?php if ($mes < date('Y-m')): ?>
            <a class="botao" href="?mes=<?= date('Y-m') . $filtroProf ?>">Mês atual</a>
            <a class="botao" href="?mes=<?= $mesSeguinte . $filtroProf ?>"><?= nomeMes($mesSeguinte) ?> →</a>
        <?php endif; ?>
    </div>
</div>

<?php $aba = 'mensalidades'; require __DIR__ . '/../includes/abas-financeiro.php'; ?>

<p class="nota-rodape" style="margin: -6px 0 14px">Aluno <b>pagante</b>: paga a própria mensalidade (cobrança pelo 💬 WhatsApp). Aluno <b>bolsista</b>: a <b>igreja paga a bolsa</b> (cobrança por ✉️ e-mail à igreja). Teoria musical e prática de orquestra são gratuitas e não aparecem aqui.</p>
<?php if (isset($_GET['email'])): ?><div class="alerta alerta-sucesso">E-mail da igreja salvo.</div><?php endif; ?>

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
    <div class="cartao indicador"><span class="icone">✗</span><div class="rotulo">Alunos que não pagaram · <?= count($alunosDevendo) ?></div>
        <div class="valor <?= $alunosDevendo ? 'texto-alerta' : '' ?>"><?= dinheiro($valorAberto) ?></div></div>
    <div class="cartao indicador"><span class="icone">🎓</span><div class="rotulo">Bolsas em aberto (igreja) · <?= count($bolsasDevendo) ?></div>
        <div class="valor <?= $bolsasDevendo ? 'texto-alerta' : '' ?>"><?= dinheiro($valorBolsas) ?></div></div>
    <div class="cartao indicador"><span class="icone">⏳</span><div class="rotulo">Sem informação</div><div class="valor"><?= count($semInfo) ?></div></div>
</section>

<section class="cartao">
    <?php if (!$linhas): ?>
        <p class="vazio">Nenhum aluno ativo.</p>
    <?php elseif (ehAdmin()): ?>
        <table class="tabela">
            <thead><tr><th>Aluno</th><th>Instrumento</th><th>Professor</th><th>Quem paga</th><th>Mensalidade</th><th>Situação</th><th>Cobrar</th><th class="ocultar-celular">Observação</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $l): ?>
                <tr>
                    <td><a href="../alunos/ver.php?id=<?= $l['aluno_id'] ?>"><?= e($l['aluno']) ?></a></td>
                    <td><span class="selo selo-instrumento"><?= e($l['instrumento']) ?></span></td>
                    <td><?= e($l['professor']) ?></td>
                    <td><?= $l['bolsista'] ? '<span class="selo selo-lembrete">🎓 Igreja (bolsa)</span>' : '<span class="selo selo-instrumento">Aluno</span>' ?></td>
                    <td class="numero"><?= dinheiro($l['mensalidade']) ?></td>
                    <td>
                        <?php if ($l['pago'] === null): ?><span class="selo selo-pendente">Sem informação</span>
                        <?php elseif ($l['pago']): ?><span class="selo selo-ativo">Pagou</span>
                        <?php else: ?><span class="selo selo-falta">Não pagou</span><?php endif; ?>
                    </td>
                    <td><?= botaoCobranca($l, $mes) ?></td>
                    <td class="ocultar-celular suave"><?= e($l['observacao'] ?? '') ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <form method="post">
            <?= campoCsrf() ?>
            <table class="tabela">
                <thead><tr><th>Aluno</th><th>Instrumento</th><th>Quem paga</th><th>Mensalidade</th><th>Pagou?</th><th>Cobrar</th><th class="ocultar-celular">Observação</th></tr></thead>
                <tbody>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td><?= e($l['aluno']) ?></td>
                        <td><span class="selo selo-instrumento"><?= e($l['instrumento']) ?></span></td>
                        <td><?= $l['bolsista'] ? '<span class="selo selo-lembrete">🎓 Igreja (bolsa)</span>' : '<span class="selo selo-instrumento">Aluno</span>' ?></td>
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
                        <td><?= botaoCobranca($l, $mes) ?></td>
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

<?php if (ehAdmin()): ?>
    <section class="cartao" id="bolsas-igreja" style="margin-top: 16px">
        <div class="topo">
            <h2>🎓 Bolsas da igreja em aberto</h2>
            <?php if ($bolsasIgreja): ?>
                <a class="botao botao-email" href="<?= e(emailBolsasIgreja($bolsasIgreja)) ?>">✉️ Enviar e-mail pronto para a igreja</a>
            <?php endif; ?>
        </div>
        <p class="suave">A igreja paga a bolsa dos alunos bolsistas de instrumento. Quando o professor marca que a bolsa do mês não chegou, ela aparece aqui.
            O botão abre o seu e-mail com a mensagem pronta, listando cada aluno, os meses e o total.</p>
        <?php if (!$bolsasIgreja): ?>
            <p class="vazio">Nenhuma bolsa em aberto. ♪</p>
        <?php else: ?>
            <table class="tabela">
                <thead><tr><th>Aluno bolsista</th><th>Instrumento</th><th>Professor</th><th>Meses</th><th>Em aberto</th></tr></thead>
                <tbody>
                <?php foreach ($bolsasIgreja as $b): ?>
                    <tr>
                        <td><?= e($b['aluno']) ?></td>
                        <td><span class="selo selo-instrumento"><?= e($b['instrumento']) ?></span></td>
                        <td><?= e($b['professor']) ?></td>
                        <td><small><?= implode(', ', array_map('nomeMes', $b['meses'])) ?></small></td>
                        <td class="numero texto-alerta"><?= dinheiro($b['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <form method="post" class="filtros" style="margin: 14px 0 0">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="email_igreja">
            <input class="select-simples" type="email" name="email_igreja" placeholder="E-mail da tesouraria da igreja"
                   value="<?= e(configuracao('email_igreja', '')) ?>" style="min-width: 280px">
            <button class="botao" type="submit">Salvar e-mail da igreja</button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
