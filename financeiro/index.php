<?php
/**
 * Financeiro (só secretaria).
 *
 * Para o mês escolhido, mostra professor por professor:
 *   - quantos alunos ele tem e a soma das mensalidades;
 *   - quanto fica com o professor e quanto deve ser repassado à escola;
 *   - se ele JÁ REPASSOU ou se está PENDENTE.
 *
 * A secretaria registra o repasse quando o professor entrega o valor,
 * e pode ajustar o percentual da escola.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/consultas.php';

$usuario = exigirSecretaria();
$pdo = conectar();

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'repassar') {
        $pdo->prepare(
            'INSERT INTO repasses (professor_id, mes, valor, data_pagamento, observacao, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT (professor_id, mes) DO UPDATE SET valor = excluded.valor,
                 data_pagamento = excluded.data_pagamento, observacao = excluded.observacao'
        )->execute([
            (int) $_POST['professor_id'], $mes, (float) $_POST['valor'],
            $_POST['data_pagamento'] ?: date('Y-m-d'), trim($_POST['observacao'] ?? '') ?: null, $usuario['id'],
        ]);
        $msg = 'Repasse registrado.';
    } elseif ($acao === 'desfazer') {
        $pdo->prepare('DELETE FROM repasses WHERE professor_id = ? AND mes = ?')
            ->execute([(int) $_POST['professor_id'], $mes]);
        $msg = 'Repasse desfeito: o professor voltou para pendente.';
    } elseif ($acao === 'percentual') {
        $percentual = max(0, min(100, (float) $_POST['percentual']));
        $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('percentual_escola', ?)
                       ON CONFLICT (chave) DO UPDATE SET valor = excluded.valor")->execute([$percentual]);
        $msg = 'Percentual da escola atualizado para ' . $percentual . '%.';
    }
    header('Location: index.php?mes=' . $mes . '&msg=' . urlencode($msg ?? ''));
    exit;
}

$linhas     = resumoFinanceiro($mes);
$percentual = (float) configuracao('percentual_escola', '30');

$totalMensalidades = array_sum(array_column($linhas, 'total_mensalidades'));
$totalEscola       = array_sum(array_column($linhas, 'parte_escola'));
$totalRecebido     = array_sum(array_map(fn($l) => $l['repassado'] ? $l['valor_repassado'] : 0, $linhas));
$totalPendente     = array_sum(array_map(fn($l) => $l['repassado'] ? 0 : $l['parte_escola'], $linhas));

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

<?php if (!empty($_GET['msg'])): ?>
    <div class="alerta alerta-sucesso"><?= e($_GET['msg']) ?></div>
<?php endif; ?>

<section class="indicadores">
    <div class="cartao indicador">
        <span class="icone">♫</span>
        <div class="rotulo">Mensalidades do mês</div>
        <div class="valor"><?= dinheiro($totalMensalidades) ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">🏛</span>
        <div class="rotulo">Parte da escola (<?= $percentual ?>%)</div>
        <div class="valor"><?= dinheiro($totalEscola) ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">✓</span>
        <div class="rotulo">Já repassado</div>
        <div class="valor texto-ok"><?= dinheiro($totalRecebido) ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">⏳</span>
        <div class="rotulo">Pendente</div>
        <div class="valor <?= $totalPendente > 0 ? 'texto-alerta' : '' ?>"><?= dinheiro($totalPendente) ?></div>
    </div>
</section>

<section class="cartao">
    <h2>Professores</h2>
    <table class="tabela">
        <thead>
            <tr>
                <th>Professor</th><th>Alunos</th><th>Mensalidades</th>
                <th>Professor recebe</th><th>Repasse à escola</th><th>Situação</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($linhas as $l): ?>
            <tr>
                <td><b><?= e($l['nome']) ?></b></td>
                <td class="numero"><?= $l['qtd_alunos'] ?></td>
                <td class="numero"><?= dinheiro($l['total_mensalidades']) ?></td>
                <td class="numero"><?= dinheiro($l['parte_professor']) ?></td>
                <td class="numero"><b><?= dinheiro($l['parte_escola']) ?></b></td>
                <td>
                    <?php if ($l['repassado']): ?>
                        <span class="selo selo-ativo">Repassado</span>
                        <small class="suave"><?= date('d/m', strtotime($l['data_pagamento'])) ?> · <?= dinheiro($l['valor_repassado']) ?></small>
                    <?php elseif ($l['parte_escola'] > 0): ?>
                        <span class="selo selo-pendente">Pendente</span>
                    <?php else: ?>
                        <span class="suave">sem alunos</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" class="acoes">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="professor_id" value="<?= $l['id'] ?>">
                        <?php if ($l['repassado']): ?>
                            <button class="botao botao-pequeno botao-perigo" name="acao" value="desfazer"
                                    onclick="return confirm('Desfazer o repasse deste professor?')">Desfazer</button>
                        <?php elseif ($l['parte_escola'] > 0): ?>
                            <input type="hidden" name="valor" value="<?= $l['parte_escola'] ?>">
                            <input type="hidden" name="data_pagamento" value="<?= date('Y-m-d') ?>">
                            <button class="botao botao-pequeno botao-primario" name="acao" value="repassar">Registrar repasse</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="cartao" style="margin-top: 18px">
    <h2>Percentual da escola</h2>
    <p class="suave">Quanto da mensalidade de cada aluno o professor repassa à escola. O restante fica com o professor.</p>
    <form method="post" class="acoes" style="justify-content: flex-start">
        <?= campoCsrf() ?>
        <input type="hidden" name="acao" value="percentual">
        <input class="select-simples" type="number" name="percentual" min="0" max="100" step="0.5" value="<?= $percentual ?>"> %
        <button class="botao" type="submit">Salvar</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
