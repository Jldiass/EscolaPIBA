<?php
/**
 * Orquestra > Maestros: regente titular, assistente, spalla, chefes de naipe...
 * - Todos veem a lista (com botão de WhatsApp para falar com cada um).
 * - O admin cadastra, edita e desativa.
 * - O maestro ligado a um login de professor pode fazer a chamada dos ensaios.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/orquestra.php';

exigirLogin();
$pdo = conectar();

$editarId = ehAdmin() && isset($_GET['id']) ? (int) $_GET['id'] : null;
$erros = [];
$form = ['nome' => '', 'funcao' => FUNCOES_MAESTRO[0], 'telefone' => '', 'usuario_id' => null, 'ativo' => 1];

if ($editarId) {
    $stmt = $pdo->prepare('SELECT * FROM orquestra_maestros WHERE id = ?');
    $stmt->execute([$editarId]);
    $form = $stmt->fetch();
    if (!$form) {
        http_response_code(404);
        exit('Maestro não encontrado.');
    }
}

$professores = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' AND ativo = 1 ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    verificarCsrf();
    $form['nome']       = trim($_POST['nome'] ?? '');
    $form['funcao']     = in_array($_POST['funcao'] ?? '', FUNCOES_MAESTRO, true) ? $_POST['funcao'] : '';
    $form['telefone']   = trim($_POST['telefone'] ?? '');
    $form['usuario_id'] = (int) ($_POST['usuario_id'] ?? 0) ?: null;
    $form['ativo']      = $editarId ? (empty($_POST['ativo']) ? 0 : 1) : 1;

    if (mb_strlen($form['nome']) < 3) {
        $erros[] = 'Informe o nome (pelo menos 3 letras).';
    }
    if ($form['funcao'] === '') {
        $erros[] = 'Escolha a função.';
    }
    if ($form['usuario_id'] && !in_array($form['usuario_id'], array_map('intval', array_column($professores, 'id')), true)) {
        $erros[] = 'Login de professor inválido.';
    }
    if ($form['telefone'] !== '' && !linkWhatsApp($form['telefone'])) {
        $erros[] = 'Telefone precisa ter DDD + número, ex.: (21) 98888-7777.';
    }

    if (!$erros) {
        $dados = [$form['nome'], $form['funcao'], $form['telefone'] ?: null, $form['usuario_id']];
        if ($editarId) {
            $pdo->prepare('UPDATE orquestra_maestros SET nome = ?, funcao = ?, telefone = ?, usuario_id = ?, ativo = ? WHERE id = ?')
                ->execute([...$dados, $form['ativo'], $editarId]);
            header('Location: maestros.php?msg=editado');
        } else {
            $pdo->prepare('INSERT INTO orquestra_maestros (nome, funcao, telefone, usuario_id) VALUES (?, ?, ?, ?)')->execute($dados);
            header('Location: maestros.php?msg=criado');
        }
        exit;
    }
}

$maestros = maestrosOrquestra(false);
$mensagens = ['criado' => 'Maestro cadastrado! ♪', 'editado' => 'Maestro atualizado.'];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$titulo = 'Maestros da orquestra';
$menu   = 'orquestra';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🎼 Orquestra</h1>
        <p>Maestros e liderança da orquestra. Quem tem login de professor ligado faz a chamada dos ensaios.</p>
    </div>
</div>

<?php $aba = 'maestros'; require __DIR__ . '/../includes/abas-orquestra.php'; ?>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>
<?php if ($erros): ?>
    <div class="alerta alerta-erro"><?php foreach ($erros as $erro): ?><div><?= e($erro) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grade-cartoes">
    <?php foreach ($maestros as $m): ?>
        <?php $whats = linkWhatsApp($m['telefone'], 'Olá, ' . explode(' ', $m['nome'])[0] . '! Aqui é da Escola de Música PIBA, sobre a orquestra.'); ?>
        <article class="cartao cartao-maestro <?= $m['ativo'] ? '' : 'inativo' ?>">
            <div class="avatar"><span><?= e(mb_substr($m['nome'], 0, 1)) ?></span></div>
            <div>
                <b><?= e($m['nome']) ?></b>
                <div><span class="selo selo-instrumento"><?= e($m['funcao']) ?></span>
                    <?= $m['ativo'] ? '' : '<span class="selo selo-inativo">Inativo</span>' ?></div>
                <small class="suave"><?= $m['usuario_id'] ? '✓ faz a chamada (login de ' . e($m['login_nome']) . ')' : 'sem login no sistema' ?></small>
            </div>
            <div class="acoes">
                <?php if ($whats): ?>
                    <a class="botao botao-pequeno botao-whats" href="<?= e($whats) ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
                <?php endif; ?>
                <?php if (ehAdmin()): ?>
                    <a class="botao botao-pequeno" href="?id=<?= $m['id'] ?>#form-maestro">Editar</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$maestros): ?><p class="vazio cartao">Nenhum maestro cadastrado.</p><?php endif; ?>
</div>

<?php if (ehAdmin()): ?>
    <section class="cartao" id="form-maestro" style="margin-top: 18px">
        <h2><?= $editarId ? 'Editar maestro' : '+ Novo maestro' ?></h2>
        <form class="formulario" method="post" action="maestros.php<?= $editarId ? '?id=' . $editarId : '' ?>">
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="nome">Nome *</label>
                <input id="nome" name="nome" required value="<?= e($form['nome']) ?>">
            </div>
            <div class="campo">
                <label for="funcao">Função *</label>
                <select id="funcao" name="funcao">
                    <?php foreach (FUNCOES_MAESTRO as $f): ?>
                        <option <?= $form['funcao'] === $f ? 'selected' : '' ?>><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="telefone">WhatsApp <small>(com DDD)</small></label>
                <input id="telefone" name="telefone" placeholder="(21) 98888-7777" value="<?= e($form['telefone'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="usuario_id">Login de professor <small>(quem tem, faz a chamada)</small></label>
                <select id="usuario_id" name="usuario_id">
                    <option value="">Sem login</option>
                    <?php foreach ($professores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int) $form['usuario_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($editarId): ?>
                <div class="campo">
                    <label>Situação</label>
                    <label class="check"><input type="checkbox" name="ativo" value="1" <?= $form['ativo'] ? 'checked' : '' ?>> Ativo na orquestra</label>
                </div>
            <?php endif; ?>
            <div class="botoes-form">
                <?php if ($editarId): ?><a class="botao" href="maestros.php">Cancelar</a><?php endif; ?>
                <button class="botao botao-primario" type="submit">Salvar maestro</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
