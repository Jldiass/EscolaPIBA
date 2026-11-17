<?php
/**
 * Financeiro > Resumo do mês (só admin).
 * Junta as duas pontas do dinheiro da escola:
 *   1) Mensalidades: o aluno paga o professor (o professor informa quem pagou).
 *   2) Repasses: o professor repassa à escola o percentual da escola.
 * O professor que abrir esta página vai direto para Mensalidades.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/consultas.php';

exigirLogin();
if (!ehAdmin()) {
    header('Location: mensalidades.php');
    exit;
}
$pdo = conectar();

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
    $mes = date('Y-m');
}

// 1) Mensalidades do mês, conforme o professor informou
$stmt = $pdo->prepare(
    'SELECT m.mensalidade, g.pago
     FROM matriculas m
     JOIN alunos a ON a.id = m.aluno_id
     LEFT JOIN pagamentos g ON g.matricula_id = m.id AND g.mes = ?
     WHERE m.ativo = 1 AND a.ativo = 1 AND ' . AULA_COBRADA
);
$stmt->execute([$mes]);
$mensalidades = $stmt->fetchAll();
$esperado   = array_sum(array_column($mensalidades, 'mensalidade'));
$pago       = 0; $naoPago = 0; $qtdNaoPago = 0; $qtdSemInfo = 0;
foreach ($mensalidades as $l) {
    if ($l['pago'] === null) {
        $qtdSemInfo++;
    } elseif ((int) $l['pago'] === 1) {
        $pago += $l['mensalidade'];
    } else {
        $naoPago += $l['mensalidade'];
        $qtdNaoPago++;
    }
}

// Aulas sem cobrança: gratuitas (teoria, orquestra) e bolsistas da igreja
$semCobranca = $pdo->query(
    'SELECT SUM(m.mensalidade = 0) AS gratuitas, SUM(m.mensalidade > 0 AND m.bolsista = 1) AS bolsistas
     FROM matriculas m JOIN alunos a ON a.id = m.aluno_id WHERE m.ativo = 1 AND a.ativo = 1'
)->fetch();

// 2) Repasses do mês, professor por professor
$percentual   = (float) configuracao('percentual_escola', '30');
$repasses     = resumoFinanceiro($mes);
$parteEscola  = array_sum(array_column($repasses, 'parte_escola'));
$recebido     = array_sum(array_map(fn($r) => $r['repassado'] ? $r['valor_repassado'] : 0, $repasses));
$aReceber     = array_sum(array_map(fn($r) => $r['repassado'] ? 0 : $r['parte_escola'], $repasses));

// Quem está devendo (aluno e professor)
$inadimplentes = alunosInadimplentes();
$atrasados     = array_filter(repassesPendentes(), fn($r) => $r['mes'] < date('Y-m'));

$mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mesSeguinte = date('Y-m', strtotime($mes . '-01 +1 month'));

$titulo = 'Financeiro';
$menu   = 'financeiro';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>💰 Financeiro · <?= nomeMes($mes) ?></h1>
        <p>O aluno paga a mensalidade ao professor; o professor repassa <b><?= $percentual ?>%</b> para a escola.</p>
    </div>
    <div class="acoes">
        <a class="botao" href="?mes=<?= $mesAnterior ?>">← <?= nomeMes($mesAnterior) ?></a>
        <a class="botao" href="?mes=<?= date('Y-m') ?>">Mês atual</a>
        <a class="botao" href="?mes=<?= $mesSeguinte ?>"><?= nomeMes($mesSeguinte) ?> →</a>
    </div>
</div>

<?php $aba = 'resumo'; require __DIR__ . '/../includes/abas-financeiro.php'; ?>

<h2>💳 Mensalidades dos alunos</h2>
<section class="indicadores">
    <div class="cartao indicador"><span class="icone">♫</span><div class="rotulo">Mensalidades do mês</div><div class="valor"><?= dinheiro($esperado) ?></div></div>
    <div class="cartao indicador"><span class="icone">✓</span><div class="rotulo">Pagas (informado)</div><div class="valor"><?= dinheiro($pago) ?></div></div>
    <a class="cartao indicador" href="mensalidades.php?mes=<?= $mes ?>"><span class="icone">✗</span><div class="rotulo">Não pagas · <?= $qtdNaoPago ?> aluno(s)</div>
        <div class="valor <?= $naoPago ? 'texto-alerta' : '' ?>"><?= dinheiro($naoPago) ?></div></a>
    <a class="cartao indicador" href="mensalidades.php?mes=<?= $mes ?>"><span class="icone">⏳</span><div class="rotulo">Sem informação</div><div class="valor"><?= $qtdSemInfo ?> aluno(s)</div></a>
</section>

<p class="nota-rodape" style="margin: -4px 0 18px">🎁 Sem cobrança, bancadas pela igreja: <b><?= (int) $semCobranca['gratuitas'] ?></b> aula(s) gratuita(s) (teoria musical e prática de orquestra) e <b><?= (int) $semCobranca['bolsistas'] ?></b> aluno(s) bolsista(s) de instrumento.</p>

<h2>🏦 Repasses dos professores</h2>
<section class="indicadores">
    <div class="cartao indicador"><span class="icone">🏫</span><div class="rotulo">Parte da escola (<?= $percentual ?>%)</div><div class="valor"><?= dinheiro($parteEscola) ?></div></div>
    <div class="cartao indicador"><span class="icone">✓</span><div class="rotulo">Já repassado</div><div class="valor"><?= dinheiro($recebido) ?></div></div>
    <a class="cartao indicador" href="repasses.php?mes=<?= $mes ?>"><span class="icone">⏳</span><div class="rotulo">Falta repassar</div>
        <div class="valor <?= $aReceber ? 'texto-alerta' : '' ?>"><?= dinheiro($aReceber) ?></div></a>
    <div class="cartao indicador"><span class="icone">𝄞</span><div class="rotulo">Professores que repassaram</div>
        <div class="valor"><?= count(array_filter($repasses, fn($r) => $r['repassado'])) ?>/<?= count($repasses) ?></div></div>
</section>

<h2>⚠️ Inadimplência</h2>
<div class="painel-grade">
    <section class="cartao">
        <div class="topo"><h2>Alunos que não pagaram</h2><a class="botao botao-pequeno" href="mensalidades.php">Ver mensalidades</a></div>
        <?php if (!$inadimplentes): ?>
            <p class="vazio">Ninguém com mensalidade em aberto. ♪</p>
        <?php else: ?>
            <table class="tabela">
                <thead><tr><th>Aluno</th><th>Professor</th><th>Meses</th><th>Em aberto</th></tr></thead>
                <tbody>
                <?php foreach ($inadimplentes as $i): ?>
                    <tr>
                        <td><?= e($i['aluno']) ?></td>
                        <td><?= e($i['professor']) ?></td>
                        <td><small><?= implode(', ', array_map('nomeMes', $i['meses'])) ?></small></td>
                        <td class="numero texto-alerta"><?= dinheiro($i['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <section class="cartao">
        <div class="topo"><h2>Professores com repasse atrasado</h2><a class="botao botao-pequeno" href="repasses.php">Ver repasses</a></div>
        <?php if (!$atrasados): ?>
            <p class="vazio">Nenhum repasse atrasado. ♪</p>
        <?php else: ?>
            <table class="tabela">
                <thead><tr><th>Professor</th><th>Mês</th><th>Deve repassar</th></tr></thead>
                <tbody>
                <?php foreach ($atrasados as $r): ?>
                    <tr>
                        <td><?= e($r['nome']) ?></td>
                        <td><?= nomeMes($r['mes']) ?></td>
                        <td class="numero texto-alerta"><?= dinheiro($r['parte_escola']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
