<?php
/**
 * Chamada do ensaio da orquestra.
 * - O regente marca quem veio e quem faltou (o ensaio é criado ao salvar).
 * - Admin e outros professores só consultam.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/orquestra.php';

exigirLogin();
$pdo = conectar();

// Data do ensaio (AAAA-MM-DD). Inválida ou no futuro -> hoje.
$data = $_GET['data'] ?? date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $data);
if (!$dt || $dt->format('Y-m-d') !== $data || $data > date('Y-m-d')) {
    $data = date('Y-m-d');
    $dt = new DateTime($data);
}

$stmt = $pdo->prepare('SELECT * FROM orquestra_ensaios WHERE data = ?');
$stmt->execute([$data]);
$ensaio = $stmt->fetch();

$membros = $pdo->query('SELECT * FROM orquestra_membros WHERE ativo = 1 ORDER BY naipe, nome')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!podeFazerChamadaOrquestra()) {
        http_response_code(403);
        exit('A chamada da orquestra é feita pelo regente.');
    }
    verificarCsrf();
    $obs = trim($_POST['observacao'] ?? '');
    // Cria o ensaio do dia (ou atualiza a observação se já existe)
    $pdo->prepare('INSERT INTO orquestra_ensaios (data, observacao) VALUES (?, ?)
                   ON CONFLICT (data) DO UPDATE SET observacao = excluded.observacao')->execute([$data, $obs ?: null]);
    $stmt = $pdo->prepare('SELECT id FROM orquestra_ensaios WHERE data = ?');
    $stmt->execute([$data]);
    $ensaioId = (int) $stmt->fetchColumn();

    $salvar = $pdo->prepare('INSERT INTO orquestra_presencas (ensaio_id, membro_id, presente) VALUES (?, ?, ?)
                             ON CONFLICT (ensaio_id, membro_id) DO UPDATE SET presente = excluded.presente');
    $ativos = array_column($membros, 'id');
    $salvas = 0;
    foreach ($_POST['presenca'] ?? [] as $membroId => $valor) {
        if (in_array($membroId, $ativos) && in_array($valor, ['1', '0'], true)) {
            $salvar->execute([$ensaioId, $membroId, (int) $valor]);
            $salvas++;
        }
    }
    header('Location: chamada.php?data=' . $data . '&salvas=' . $salvas);
    exit;
}

// Presenças já marcadas nesse ensaio
$marcadas = [];
if ($ensaio) {
    $stmt = $pdo->prepare('SELECT membro_id, presente FROM orquestra_presencas WHERE ensaio_id = ?');
    $stmt->execute([$ensaio['id']]);
    $marcadas = array_map('intval', array_column($stmt->fetchAll(), 'presente', 'membro_id'));
}
$vieram = count(array_filter($marcadas));

// Últimos ensaios, para navegar
$ultimos = $pdo->query(
    'SELECT e.data, COUNT(p.id) AS marcados, COALESCE(SUM(p.presente), 0) AS vieram
     FROM orquestra_ensaios e LEFT JOIN orquestra_presencas p ON p.ensaio_id = e.id
     GROUP BY e.id ORDER BY e.data DESC LIMIT 8'
)->fetchAll();

$podeMarcar = podeFazerChamadaOrquestra();
$anterior = (clone $dt)->modify('-1 day')->format('Y-m-d');
$proximo  = (clone $dt)->modify('+1 day')->format('Y-m-d');

$titulo = 'Chamada da orquestra';
$menu   = 'orquestra';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🎼 Chamada da orquestra</h1>
        <p><?= DIAS_SEMANA[(int) $dt->format('N')] ?>, <?= $dt->format('d/m/Y') ?> ·
           <?= $ensaio ? "$vieram de " . count($marcadas) . ' presentes' : 'sem ensaio registrado neste dia' ?>
           <?= $podeMarcar ? '' : ' · consulta: quem marca é o regente' ?></p>
    </div>
    <div class="acoes">
        <a class="botao" href="index.php">← Membros</a>
        <a class="botao" href="?data=<?= $anterior ?>">← Dia anterior</a>
        <a class="botao" href="?data=<?= date('Y-m-d') ?>">Hoje</a>
        <?php if ($data < date('Y-m-d')): ?><a class="botao" href="?data=<?= $proximo ?>">Próximo dia →</a><?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['salvas'])): ?>
    <div class="alerta alerta-sucesso">Chamada do ensaio salva (<?= (int) $_GET['salvas'] ?> membro(s)). ♪</div>
<?php endif; ?>

<nav class="filtros">
    <?php foreach ($ultimos as $u): ?>
        <a class="filtro <?= $u['data'] === $data ? 'filtro-ativo' : '' ?>" href="?data=<?= $u['data'] ?>">
            <?= date('d/m', strtotime($u['data'])) ?> · <?= $u['vieram'] ?> de <?= $u['marcados'] ?></a>
    <?php endforeach; ?>
</nav>

<section class="cartao">
    <?php if (!$membros): ?>
        <p class="vazio">Nenhum membro ativo na orquestra.</p>
    <?php elseif (!$podeMarcar): ?>
        <?php if (!$ensaio): ?>
            <p class="vazio">Sem ensaio registrado neste dia. Escolha um dos últimos ensaios acima.</p>
        <?php else: ?>
            <table class="tabela">
                <thead><tr><th>Membro</th><th>Naipe</th><th>Instrumento</th><th>Situação</th></tr></thead>
                <tbody>
                <?php foreach ($membros as $m): ?>
                    <?php $p = $marcadas[$m['id']] ?? null; ?>
                    <tr>
                        <td><?= e($m['nome']) ?></td>
                        <td><?= e($m['naipe']) ?></td>
                        <td><span class="selo selo-instrumento"><?= e($m['instrumento']) ?></span></td>
                        <td><?= $p === null ? '<span class="selo selo-inativo">Sem registro</span>'
                            : ($p ? '<span class="selo selo-ativo">Presente</span>' : '<span class="selo selo-falta">Faltou</span>') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($ensaio['observacao']): ?><p class="nota-rodape">📝 <?= e($ensaio['observacao']) ?></p><?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <form method="post">
            <?= campoCsrf() ?>
            <table class="tabela">
                <thead><tr><th>Membro</th><th>Naipe</th><th>Instrumento</th><th>Presença</th></tr></thead>
                <tbody>
                <?php foreach ($membros as $m): ?>
                    <?php $p = $marcadas[$m['id']] ?? null; ?>
                    <tr>
                        <td><?= e($m['nome']) ?></td>
                        <td><?= e($m['naipe']) ?></td>
                        <td><span class="selo selo-instrumento"><?= e($m['instrumento']) ?></span></td>
                        <td>
                            <div class="presenca">
                                <label class="presenca-sim"><input type="radio" name="presenca[<?= $m['id'] ?>]" value="1" <?= $p === 1 ? 'checked' : '' ?>><span>Presente</span></label>
                                <label class="presenca-nao"><input type="radio" name="presenca[<?= $m['id'] ?>]" value="0" <?= $p === 0 ? 'checked' : '' ?>><span>Faltou</span></label>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="campo" style="margin-top: 14px">
                <label for="observacao">Observação do ensaio <small>(opcional: repertório, recado...)</small></label>
                <input id="observacao" name="observacao" value="<?= e($ensaio['observacao'] ?? '') ?>">
            </div>
            <div class="botoes-form">
                <button class="botao botao-primario" type="submit">Salvar chamada do ensaio</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
