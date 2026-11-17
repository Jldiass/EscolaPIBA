<?php
/**
 * Orquestra: membros e assiduidade nos ensaios.
 * - Todos veem a lista com o status de cada membro.
 * - O admin cadastra/edita membros e escolhe o regente.
 * - O regente faz a chamada dos ensaios (orquestra/chamada.php).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/orquestra.php';

exigirLogin();
$pdo = conectar();

// Admin escolhe o regente (o professor que faz a chamada dos ensaios)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    verificarCsrf();
    $regente = (int) ($_POST['regente'] ?? 0);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = ? AND perfil = 'professor' AND ativo = 1");
    $stmt->execute([$regente]);
    if ($stmt->fetchColumn()) {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('regente_orquestra', ?)
                       ON CONFLICT (chave) DO UPDATE SET valor = excluded.valor")->execute([$regente]);
    }
    header('Location: index.php?msg=regente');
    exit;
}

$membros = membrosOrquestra();
$filtro  = array_key_exists($_GET['status'] ?? '', STATUS_ORQUESTRA) ? $_GET['status'] : '';
$contagem = array_count_values(array_column($membros, 'status'));
$visiveis = $filtro ? array_filter($membros, fn($m) => $m['status'] === $filtro) : $membros;

$ultimoEnsaio = $pdo->query('SELECT * FROM orquestra_ensaios ORDER BY data DESC LIMIT 1')->fetch();
$percentuais  = array_filter(array_column($membros, 'percentual'), fn($p) => $p !== null);
$media        = $percentuais ? round(array_sum($percentuais) / count($percentuais)) : 0;

$regenteId   = regenteOrquestra();
$professores = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll();
$nomeRegente = '';
foreach ($professores as $p) {
    if ((int) $p['id'] === $regenteId) {
        $nomeRegente = $p['nome'];
    }
}

$mensagens = ['regente' => 'Regente atualizado.', 'criado' => 'Membro cadastrado! ♪', 'editado' => 'Membro atualizado.'];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$titulo = 'Orquestra';
$menu   = 'orquestra';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🎼 Orquestra</h1>
        <p>Prática de orquestra (bancada pela igreja) · Regente: <b><?= $nomeRegente ? e($nomeRegente) : 'não definido' ?></b>
           · status pelos últimos <?= ENSAIOS_STATUS ?> ensaios</p>
    </div>
    <div class="acoes">
        <a class="botao <?= podeFazerChamadaOrquestra() ? 'botao-primario' : '' ?>" href="chamada.php">
            <?= podeFazerChamadaOrquestra() ? '✓ Fazer chamada do ensaio' : '🗓 Ver ensaios' ?></a>
        <?php if (ehAdmin()): ?>
            <a class="botao botao-primario" href="membro.php">+ Novo membro</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>

<section class="indicadores">
    <div class="cartao indicador"><span class="icone">🎻</span><div class="rotulo">Membros ativos</div><div class="valor"><?= count($membros) ?></div></div>
    <div class="cartao indicador"><span class="icone">✓</span><div class="rotulo">Frequentes</div><div class="valor"><?= $contagem['frequente'] ?? 0 ?></div></div>
    <div class="cartao indicador"><span class="icone">📊</span><div class="rotulo">Presença média</div><div class="valor"><?= $media ?>%</div></div>
    <div class="cartao indicador"><span class="icone">⚠️</span><div class="rotulo">Ausentes</div>
        <div class="valor <?= !empty($contagem['ausente']) ? 'texto-alerta' : '' ?>"><?= $contagem['ausente'] ?? 0 ?></div></div>
</section>

<nav class="filtros">
    <a class="filtro <?= $filtro === '' ? 'filtro-ativo' : '' ?>" href="index.php">Todos (<?= count($membros) ?>)</a>
    <?php foreach (STATUS_ORQUESTRA as $chave => [$rotulo]): ?>
        <a class="filtro <?= $filtro === $chave ? 'filtro-ativo' : '' ?>" href="?status=<?= $chave ?>"><?= $rotulo ?> (<?= $contagem[$chave] ?? 0 ?>)</a>
    <?php endforeach; ?>
</nav>

<section class="cartao">
    <?php if (!$visiveis): ?>
        <p class="vazio">Nenhum membro <?= $filtro ? 'com esse status' : 'cadastrado' ?>.</p>
    <?php else: ?>
        <table class="tabela">
            <thead><tr><th>Membro</th><th>Naipe</th><th>Instrumento</th><th class="ocultar-celular">Últimos ensaios</th><th>Presença</th><th>Status</th><?= ehAdmin() ? '<th></th>' : '' ?></tr></thead>
            <tbody>
            <?php foreach ($visiveis as $m): ?>
                <?php [$rotulo, $classe] = STATUS_ORQUESTRA[$m['status']]; ?>
                <tr>
                    <td><b><?= e($m['nome']) ?></b><?= $m['aluno_id'] ? ' <small class="suave">· aluno da escola</small>' : '' ?></td>
                    <td><?= e($m['naipe']) ?></td>
                    <td><span class="selo selo-instrumento"><?= e($m['instrumento']) ?></span></td>
                    <td class="ocultar-celular">
                        <span class="bolinhas">
                            <?php foreach ($m['historico'] as $h): ?>
                                <i class="<?= $h['presente'] === null ? 'b-vazio' : ($h['presente'] ? 'b-veio' : 'b-faltou') ?>"
                                   title="<?= date('d/m', strtotime($h['data'])) ?>: <?= $h['presente'] === null ? 'sem registro' : ($h['presente'] ? 'veio' : 'faltou') ?>"></i>
                            <?php endforeach; ?>
                        </span>
                    </td>
                    <td class="numero"><?= $m['percentual'] === null ? '—' : $m['percentual'] . '%' ?>
                        <small class="suave"><?= $m['ensaios'] ? '(' . $m['presencas'] . '/' . $m['ensaios'] . ')' : '' ?></small></td>
                    <td><span class="selo <?= $classe ?>"><?= $rotulo ?></span></td>
                    <?php if (ehAdmin()): ?>
                        <td><a class="botao botao-pequeno" href="membro.php?id=<?= $m['id'] ?>">Editar</a></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="nota-rodape">Frequente: 75% ou mais · De vez em quando: 40% a 74% · Ausente: menos de 40% · Novo: ainda sem ensaio registrado.
            <?= $ultimoEnsaio ? 'Último ensaio: ' . date('d/m/Y', strtotime($ultimoEnsaio['data'])) . '.' : '' ?></p>
    <?php endif; ?>
</section>

<?php if (ehAdmin()): ?>
    <section class="cartao" style="margin-top: 16px">
        <h2>Regente da orquestra</h2>
        <p class="suave">É o professor que faz a chamada dos ensaios.</p>
        <form method="post" class="filtros" style="margin: 0">
            <?= campoCsrf() ?>
            <select class="select-simples" name="regente">
                <?php foreach ($professores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $p['id'] === $regenteId ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="botao" type="submit">Salvar regente</button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
