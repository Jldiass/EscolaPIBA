<?php
/**
 * Banco de partituras: o acervo musical da escola.
 * - Pastas padrão: Orquestra e Métodos de ensino. Qualquer usuário pode criar outras
 *   (ex.: Apresentação de fim de ano, Recital dos alunos).
 * - Cada partitura é um PDF com título, autor/compositor, instrumento e nível.
 * - Admin e professores enviam e consultam; exclui quem enviou ou o admin.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/partituras.php';

exigirLogin();
$pdo = conectar();
$usuario = usuarioAtual();
$erros = [];

$pastas   = pastasPartituras();
$idsPasta = array_map('intval', array_column($pastas, 'id'));
$pastaId  = in_array((int) ($_GET['pasta'] ?? 0), $idsPasta, true) ? (int) $_GET['pasta'] : null;
$nivel    = array_key_exists($_GET['nivel'] ?? '', NIVEIS_PARTITURA) ? $_GET['nivel'] : '';
$busca    = trim($_GET['busca'] ?? '');
$form     = ['titulo' => '', 'autor' => '', 'instrumento' => '', 'nivel' => 'basico', 'pasta_id' => $pastaId ?? ($idsPasta[0] ?? 0)];
$voltar   = 'index.php?' . http_build_query(array_filter(['pasta' => $pastaId, 'nivel' => $nivel]));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'nova_pasta') {
        $nome = trim($_POST['nome_pasta'] ?? '');
        $descricao = trim($_POST['descricao_pasta'] ?? '');
        if (mb_strlen($nome) < 3) {
            $erros[] = 'Dê um nome para a pasta (pelo menos 3 letras).';
        } elseif (in_array(mb_strtolower($nome), array_map('mb_strtolower', array_column($pastas, 'nome')), true)) {
            $erros[] = 'Já existe uma pasta com esse nome.';
        } else {
            $pdo->prepare('INSERT INTO partituras_pastas (nome, descricao, criado_por) VALUES (?, ?, ?)')
                ->execute([$nome, $descricao ?: null, $usuario['id']]);
            header('Location: index.php?pasta=' . $pdo->lastInsertId() . '&msg=pasta');
            exit;
        }
    }

    if ($acao === 'enviar') {
        $form = [
            'titulo'      => trim($_POST['titulo'] ?? ''),
            'autor'       => trim($_POST['autor'] ?? ''),
            'instrumento' => trim($_POST['instrumento'] ?? ''),
            'nivel'       => array_key_exists($_POST['nivel'] ?? '', NIVEIS_PARTITURA) ? $_POST['nivel'] : '',
            'pasta_id'    => (int) ($_POST['pasta_id'] ?? 0),
        ];
        if (mb_strlen($form['titulo']) < 2) {
            $erros[] = 'Informe o título da partitura ou do método.';
        }
        if ($form['nivel'] === '') {
            $erros[] = 'Escolha o nível.';
        }
        if (!in_array($form['pasta_id'], $idsPasta, true)) {
            $erros[] = 'Escolha a pasta.';
        }
        if (!$erros) {
            try {
                [$arquivo, $tamanho] = salvarPdfEnviado($_FILES['arquivo'] ?? null);
                $pdo->prepare('INSERT INTO partituras (pasta_id, titulo, autor, instrumento, nivel, arquivo, tamanho, enviado_por)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$form['pasta_id'], $form['titulo'], $form['autor'] ?: null, $form['instrumento'] ?: null,
                               $form['nivel'], $arquivo, $tamanho, $usuario['id']]);
                header('Location: index.php?pasta=' . $form['pasta_id'] . '&msg=enviada');
                exit;
            } catch (RuntimeException $ex) {
                $erros[] = $ex->getMessage();
            }
        }
    }

    if ($acao === 'excluir') {
        $stmt = $pdo->prepare('SELECT * FROM partituras WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        $p = $stmt->fetch();
        if (!$p || !podeExcluirPartitura($p)) {
            http_response_code(403);
            exit('Só quem enviou a partitura (ou o admin) pode excluir.');
        }
        $pdo->prepare('DELETE FROM partituras WHERE id = ?')->execute([$p['id']]);
        @unlink(pastaArquivosPartituras() . '/' . basename($p['arquivo']));
        header('Location: ' . $voltar . '&msg=excluida');
        exit;
    }

    if ($acao === 'excluir_pasta') {
        $stmt = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM partituras WHERE pasta_id = p.id) AS total FROM partituras_pastas p WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        $pasta = $stmt->fetch();
        if (!$pasta || $pasta['fixa'] || !(ehAdmin() || (int) $pasta['criado_por'] === (int) $usuario['id'])) {
            http_response_code(403);
            exit('Essa pasta não pode ser excluída por você.');
        }
        if ($pasta['total'] > 0) {
            $erros[] = 'Esvazie a pasta antes de excluir (ela ainda tem partituras).';
        } else {
            $pdo->prepare('DELETE FROM partituras_pastas WHERE id = ?')->execute([$pasta['id']]);
            header('Location: index.php?msg=pasta_excluida');
            exit;
        }
    }
}

// Lista filtrada
$where = [];
$params = [];
if ($pastaId) { $where[] = 't.pasta_id = ?'; $params[] = $pastaId; }
if ($nivel)   { $where[] = 't.nivel = ?';    $params[] = $nivel; }
if ($busca !== '') {
    $where[] = '(t.titulo LIKE ? OR t.autor LIKE ? OR t.instrumento LIKE ?)';
    array_push($params, "%$busca%", "%$busca%", "%$busca%");
}
$stmt = $pdo->prepare(
    'SELECT t.*, p.nome AS pasta, p.icone, u.nome AS enviado_nome FROM partituras t
     JOIN partituras_pastas p ON p.id = t.pasta_id LEFT JOIN usuarios u ON u.id = t.enviado_por'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.fixa DESC, p.nome, t.titulo'
);
$stmt->execute($params);
$partituras = $stmt->fetchAll();

$porNivel = array_column($pdo->query('SELECT nivel, COUNT(*) AS n FROM partituras GROUP BY nivel')->fetchAll(), 'n', 'nivel');
$totalAcervo = array_sum($porNivel);
$pastaAtual = $pastaId ? current(array_filter($pastas, fn($p) => (int) $p['id'] === $pastaId)) : null;

$mensagens = ['pasta' => 'Pasta criada! ♪', 'enviada' => 'Partitura guardada no acervo! ♪', 'excluida' => 'Partitura excluída.', 'pasta_excluida' => 'Pasta excluída.'];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$titulo = 'Banco de partituras';
$menu   = 'partituras';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>📚 Banco de partituras</h1>
        <p>O acervo musical da escola: partituras da orquestra, métodos de ensino e repertório das apresentações, em PDF.</p>
    </div>
    <a class="botao botao-primario" href="#enviar">+ Enviar partitura</a>
</div>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>
<?php if ($erros): ?>
    <div class="alerta alerta-erro"><?php foreach ($erros as $erro): ?><div><?= e($erro) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<section class="indicadores">
    <div class="cartao indicador"><span class="icone">📚</span><div class="rotulo">Partituras no acervo</div><div class="valor"><?= $totalAcervo ?></div></div>
    <?php foreach (NIVEIS_PARTITURA as $chave => [$rotulo]): ?>
        <a class="cartao indicador" href="?<?= http_build_query(array_filter(['pasta' => $pastaId, 'nivel' => $chave])) ?>">
            <span class="icone">♪</span><div class="rotulo">Nível <?= mb_strtolower($rotulo) ?></div><div class="valor"><?= (int) ($porNivel[$chave] ?? 0) ?></div></a>
    <?php endforeach; ?>
</section>

<div class="grade-pastas">
    <a class="cartao cartao-pasta <?= $pastaId ? '' : 'pasta-ativa' ?>" href="?<?= http_build_query(array_filter(['nivel' => $nivel])) ?>">
        <span class="icone-pasta">🗂️</span><b>Todas as pastas</b><small class="suave"><?= $totalAcervo ?> partitura(s)</small></a>
    <?php foreach ($pastas as $p): ?>
        <a class="cartao cartao-pasta <?= $pastaId === (int) $p['id'] ? 'pasta-ativa' : '' ?>" href="?<?= http_build_query(array_filter(['pasta' => $p['id'], 'nivel' => $nivel])) ?>">
            <span class="icone-pasta"><?= e($p['icone']) ?></span><b><?= e($p['nome']) ?></b>
            <small class="suave"><?= (int) $p['total'] ?> partitura(s)<?= $p['fixa'] ? ' · pasta padrão' : '' ?></small></a>
    <?php endforeach; ?>
</div>

<section class="cartao">
    <div class="topo">
        <div>
            <h2><?= $pastaAtual ? e($pastaAtual['icone'] . ' ' . $pastaAtual['nome']) : '🗂️ Todo o acervo' ?></h2>
            <?php if ($pastaAtual && $pastaAtual['descricao']): ?><p class="suave"><?= e($pastaAtual['descricao']) ?></p><?php endif; ?>
        </div>
        <?php if ($pastaAtual && !$pastaAtual['fixa'] && (ehAdmin() || (int) $pastaAtual['criado_por'] === (int) $usuario['id'])): ?>
            <form method="post" onsubmit="return confirm('Excluir a pasta <?= e(addslashes($pastaAtual['nome'])) ?>?')">
                <?= campoCsrf() ?><input type="hidden" name="acao" value="excluir_pasta"><input type="hidden" name="id" value="<?= $pastaAtual['id'] ?>">
                <button class="botao botao-pequeno" type="submit">Excluir pasta</button>
            </form>
        <?php endif; ?>
    </div>

    <form class="filtros" method="get">
        <?php if ($pastaId): ?><input type="hidden" name="pasta" value="<?= $pastaId ?>"><?php endif; ?>
        <input class="select-simples" type="search" name="busca" placeholder="Buscar título, compositor ou instrumento" value="<?= e($busca) ?>" style="min-width: 260px">
        <select class="select-simples" name="nivel" aria-label="Nível">
            <option value="">Todos os níveis</option>
            <?php foreach (NIVEIS_PARTITURA as $chave => [$rotulo]): ?>
                <option value="<?= $chave ?>" <?= $nivel === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
            <?php endforeach; ?>
        </select>
        <button class="botao" type="submit">Filtrar</button>
    </form>

    <?php if (!$partituras): ?>
        <p class="vazio">Nenhuma partitura aqui ainda. Envie a primeira! ♪</p>
    <?php else: ?>
        <table class="tabela">
            <thead><tr><th>Título</th><th>Compositor / autor</th><th>Instrumento</th><th>Nível</th><th>Pasta</th><th class="ocultar-celular">Enviado por</th><th>PDF</th></tr></thead>
            <tbody>
            <?php foreach ($partituras as $t): ?>
                <?php [$rotuloNivel, $classeNivel] = NIVEIS_PARTITURA[$t['nivel']] ?? ['—', 'selo-inativo']; ?>
                <tr>
                    <td><b><?= e($t['titulo']) ?></b></td>
                    <td><?= e($t['autor'] ?? '—') ?></td>
                    <td><?= $t['instrumento'] ? '<span class="selo selo-instrumento">' . e($t['instrumento']) . '</span>' : '—' ?></td>
                    <td><span class="selo <?= $classeNivel ?>"><?= $rotuloNivel ?></span></td>
                    <td><?= e($t['icone'] . ' ' . $t['pasta']) ?></td>
                    <td class="ocultar-celular"><small><?= e($t['enviado_nome'] ?? '—') ?> · <?= date('d/m/Y', strtotime($t['criado_em'])) ?></small></td>
                    <td class="sem-quebra">
                        <a class="botao botao-pequeno botao-primario" href="arquivo.php?id=<?= $t['id'] ?>" target="_blank" rel="noopener">📄 Abrir</a>
                        <a class="botao botao-pequeno" href="arquivo.php?id=<?= $t['id'] ?>&baixar=1" title="Baixar <?= tamanhoArquivo((int) $t['tamanho']) ?>">⬇</a>
                        <?php if (podeExcluirPartitura($t)): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Excluir esta partitura?')">
                                <?= campoCsrf() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button class="botao botao-pequeno" type="submit" title="Excluir">🗑</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<div class="painel-grade" style="margin-top: 16px">
    <section class="cartao" id="enviar">
        <h2>+ Enviar partitura ou método (PDF)</h2>
        <form class="formulario" method="post" enctype="multipart/form-data" action="<?= e($voltar) ?>">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="enviar">
            <div class="campo campo-inteiro">
                <label for="titulo">Título *</label>
                <input id="titulo" name="titulo" required placeholder="Ex.: Ode à Alegria" value="<?= e($form['titulo']) ?>">
            </div>
            <div class="campo">
                <label for="autor">Compositor / autor</label>
                <input id="autor" name="autor" placeholder="Ex.: L. van Beethoven" value="<?= e($form['autor']) ?>">
            </div>
            <div class="campo">
                <label for="instrumento">Instrumento</label>
                <input id="instrumento" name="instrumento" placeholder="Ex.: Violino ou Orquestra completa" value="<?= e($form['instrumento']) ?>">
            </div>
            <div class="campo">
                <label for="pasta_id">Pasta *</label>
                <select id="pasta_id" name="pasta_id">
                    <?php foreach ($pastas as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int) $form['pasta_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['icone'] . ' ' . $p['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="nivel">Nível *</label>
                <select id="nivel" name="nivel">
                    <?php foreach (NIVEIS_PARTITURA as $chave => [$rotulo]): ?>
                        <option value="<?= $chave ?>" <?= $form['nivel'] === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo campo-inteiro">
                <label for="arquivo">Arquivo PDF * <small>(até <?= tamanhoArquivo(TAMANHO_MAX_PDF) ?>)</small></label>
                <input id="arquivo" name="arquivo" type="file" accept="application/pdf,.pdf" required>
            </div>
            <div class="botoes-form">
                <button class="botao botao-primario" type="submit">Guardar no acervo</button>
            </div>
        </form>
    </section>

    <section class="cartao">
        <h2>📁 Nova pasta</h2>
        <p class="suave">Crie pastas para organizar o repertório: apresentação de fim de ano, recital dos alunos, festival, músicas por nível...</p>
        <form class="formulario" method="post" action="index.php">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="nova_pasta">
            <div class="campo campo-inteiro">
                <label for="nome_pasta">Nome da pasta *</label>
                <input id="nome_pasta" name="nome_pasta" required placeholder="Ex.: Recital de fim de ano">
            </div>
            <div class="campo campo-inteiro">
                <label for="descricao_pasta">Descrição <small>(opcional)</small></label>
                <input id="descricao_pasta" name="descricao_pasta" placeholder="Para que serve esta pasta">
            </div>
            <div class="botoes-form">
                <button class="botao" type="submit">Criar pasta</button>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
