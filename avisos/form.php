<?php
/**
 * Cadastrar ou editar um aviso (só admin).
 * - Evento (social ou interno): precisa de data; hora e local são opcionais.
 * - Lembrete: começa na data informada e fica visível até "válido até"
 *   (em branco = fica até alguém apagar).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/avisos.php';

$usuario = exigirAdmin();
$pdo = conectar();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$erros = [];
$aviso = [
    'tipo' => ($_GET['tipo'] ?? '') === 'lembrete' ? 'lembrete' : 'social',
    'titulo' => '', 'descricao' => '', 'data' => date('Y-m-d'), 'hora' => '', 'local' => '', 'valido_ate' => '',
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM avisos WHERE id = ?');
    $stmt->execute([$id]);
    $aviso = $stmt->fetch();
    if (!$aviso) {
        http_response_code(404);
        exit('Aviso não encontrado.');
    }
}

/** Confere se o texto é uma data AAAA-MM-DD válida. */
function dataValida(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $aviso['tipo']       = array_key_exists($_POST['tipo'] ?? '', TIPOS_AVISO) ? $_POST['tipo'] : '';
    $aviso['titulo']     = trim($_POST['titulo'] ?? '');
    $aviso['descricao']  = trim($_POST['descricao'] ?? '');
    $aviso['data']       = $_POST['data'] ?? '';
    $aviso['hora']       = $_POST['hora'] ?? '';
    $aviso['local']      = trim($_POST['local'] ?? '');
    $aviso['valido_ate'] = $aviso['tipo'] === 'lembrete' ? ($_POST['valido_ate'] ?? '') : '';

    if ($aviso['tipo'] === '') {
        $erros[] = 'Escolha o tipo do aviso.';
    }
    if (mb_strlen($aviso['titulo']) < 3) {
        $erros[] = 'Escreva um título (pelo menos 3 letras).';
    }
    if (!dataValida($aviso['data'])) {
        $erros[] = 'Informe uma data válida.';
    }
    if ($aviso['hora'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $aviso['hora'])) {
        $erros[] = 'Hora inválida.';
    }
    if ($aviso['valido_ate'] !== '') {
        if (!dataValida($aviso['valido_ate'])) {
            $erros[] = '"Válido até" precisa ser uma data válida.';
        } elseif ($aviso['valido_ate'] < $aviso['data']) {
            $erros[] = '"Válido até" não pode ser antes da data de início.';
        }
    }

    if (!$erros) {
        $dados = [
            ':tipo' => $aviso['tipo'], ':titulo' => $aviso['titulo'], ':descricao' => $aviso['descricao'] ?: null,
            ':data' => $aviso['data'], ':hora' => $aviso['hora'] ?: null, ':local' => $aviso['local'] ?: null,
            ':valido_ate' => $aviso['valido_ate'] ?: null,
        ];
        if ($id) {
            $dados[':id'] = $id;
            $pdo->prepare('UPDATE avisos SET tipo = :tipo, titulo = :titulo, descricao = :descricao, data = :data,
                           hora = :hora, local = :local, valido_ate = :valido_ate WHERE id = :id')->execute($dados);
            header('Location: index.php?msg=editado');
        } else {
            $dados[':criado_por'] = $usuario['id'];
            $pdo->prepare('INSERT INTO avisos (tipo, titulo, descricao, data, hora, local, valido_ate, criado_por)
                           VALUES (:tipo, :titulo, :descricao, :data, :hora, :local, :valido_ate, :criado_por)')->execute($dados);
            header('Location: index.php?msg=criado');
        }
        exit;
    }
}

$titulo = $id ? 'Editar aviso' : 'Novo aviso';
$menu   = 'avisos';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>📣 <?= $titulo ?></h1>
        <p>Campos com * são obrigatórios.</p>
    </div>
    <a class="botao" href="index.php">← Voltar</a>
</div>

<?php if ($erros): ?>
    <div class="alerta alerta-erro"><?php foreach ($erros as $erro): ?><div><?= e($erro) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<section class="cartao">
    <form class="formulario" method="post">
        <?= campoCsrf() ?>
        <div class="campo campo-inteiro">
            <label>Tipo *</label>
            <div class="filtros" style="margin: 0">
                <?php foreach (TIPOS_AVISO as $chave => [$nome, $icone]): ?>
                    <label class="opcao-tipo">
                        <input type="radio" name="tipo" value="<?= $chave ?>" <?= $aviso['tipo'] === $chave ? 'checked' : '' ?>
                               onchange="document.getElementById('campo-validade').hidden = this.value !== 'lembrete'">
                        <span><?= $icone ?> <?= $nome ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="campo campo-inteiro">
            <label for="titulo">Título *</label>
            <input id="titulo" name="titulo" required placeholder="Ex.: Recital de Natal ou Não usar a sala 101" value="<?= e($aviso['titulo']) ?>">
        </div>
        <div class="campo campo-inteiro">
            <label for="descricao">Detalhes</label>
            <textarea id="descricao" name="descricao" rows="3" placeholder="Opcional"><?= e($aviso['descricao']) ?></textarea>
        </div>
        <div class="campo">
            <label for="data">Data * <small>(do evento, ou início do lembrete)</small></label>
            <input id="data" name="data" type="date" required value="<?= e($aviso['data']) ?>">
        </div>
        <div class="campo">
            <label for="hora">Hora</label>
            <input id="hora" name="hora" type="time" value="<?= e($aviso['hora']) ?>">
        </div>
        <div class="campo">
            <label for="local">Local</label>
            <input id="local" name="local" placeholder="Ex.: Templo principal, Sala 101" value="<?= e($aviso['local']) ?>">
        </div>
        <div class="campo" id="campo-validade" <?= $aviso['tipo'] === 'lembrete' ? '' : 'hidden' ?>>
            <label for="valido_ate">Lembrete válido até <small>(em branco = até apagar)</small></label>
            <input id="valido_ate" name="valido_ate" type="date" value="<?= e($aviso['valido_ate']) ?>">
        </div>
        <div class="botoes-form">
            <a class="botao" href="index.php">Cancelar</a>
            <button class="botao botao-primario" type="submit">Publicar aviso</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
