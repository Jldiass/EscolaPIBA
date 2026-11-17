<?php
/**
 * Lista de alunos, com busca por nome ou instrumento.
 * - Secretaria: vê todos e pode cadastrar, editar e excluir.
 * - Professor: vê só os alunos matriculados com ele.
 */
require __DIR__ . '/../includes/auth.php';

$usuario = exigirLogin();
$pdo = conectar();

$busca = trim($_GET['busca'] ?? '');
$esp   = trim($_GET['esp'] ?? '');   // filtro por especialidade (Bateria, Teoria musical...)

// Especialidades que aparecem nos filtros: a secretaria vê todas, o professor só as dele
if (ehSecretaria()) {
    $especialidades = $pdo->query('SELECT DISTINCT instrumento FROM matriculas WHERE ativo = 1 ORDER BY instrumento')
                          ->fetchAll(PDO::FETCH_COLUMN);
} else {
    $especialidades = especialidades($usuario['instrumentos']);
}

// group_concat junta os professores do aluno em um texto só: "Carlos, Helena"
$sql = "SELECT a.*, group_concat(DISTINCT u.nome) AS professores
        FROM alunos a
        LEFT JOIN matriculas m ON m.aluno_id = a.id AND m.ativo = 1
        LEFT JOIN usuarios u   ON u.id = m.professor_id
        WHERE 1 = 1";
$params = [];

if (!ehSecretaria()) {
    // Professor: só alunos que têm matrícula ativa com ele
    $sql .= ' AND a.id IN (SELECT aluno_id FROM matriculas WHERE professor_id = :prof AND ativo = 1)';
    $params[':prof'] = $usuario['id'];
}
if ($busca !== '') {
    // Consulta preparada: o texto do usuário vai separado do SQL (evita SQL Injection)
    $sql .= ' AND (a.nome LIKE :busca OR a.instrumento LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($esp !== '') {
    // Só alunos com matrícula ativa nessa especialidade (e, se for professor, com ele)
    $sql .= ' AND a.id IN (SELECT aluno_id FROM matriculas WHERE ativo = 1 AND instrumento = :esp'
          . (ehSecretaria() ? '' : ' AND professor_id = :prof2') . ')';
    $params[':esp'] = $esp;
    if (!ehSecretaria()) {
        $params[':prof2'] = $usuario['id'];
    }
}
$sql .= ' GROUP BY a.id ORDER BY a.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alunos = $stmt->fetchAll();

$mensagens = [
    'criado'   => 'Aluno cadastrado com sucesso! Agora faça a matrícula dele com um professor.',
    'editado'  => 'Dados do aluno atualizados.',
    'excluido' => 'Aluno excluído.',
];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

/** Calcula a idade a partir da data de nascimento (AAAA-MM-DD). */
function idade(?string $nascimento): string
{
    if (!$nascimento) {
        return '-';
    }
    return (new DateTime($nascimento))->diff(new DateTime())->y . ' anos';
}

$titulo = ehSecretaria() ? 'Alunos' : 'Meus alunos';
$menu   = 'alunos';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>♫ <?= $titulo ?></h1>
        <p><?= count($alunos) ?> aluno(s) encontrado(s)</p>
    </div>
    <?php if (ehSecretaria()): ?>
        <a class="botao botao-primario" href="form.php">+ Novo aluno</a>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
    <div class="alerta alerta-sucesso"><?= e($msg) ?></div>
<?php endif; ?>

<?php if (count($especialidades) > 1 || $esp !== ''): ?>
    <nav class="filtros">
        <a class="filtro <?= $esp === '' ? 'filtro-ativo' : '' ?>" href="index.php">Todos</a>
        <?php foreach ($especialidades as $item): ?>
            <a class="filtro <?= $esp === $item ? 'filtro-ativo' : '' ?>" href="?esp=<?= urlencode($item) ?>">
                Alunos de <?= e(mb_strtolower($item)) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<section class="cartao">
    <form class="busca" method="get">
        <input type="hidden" name="esp" value="<?= e($esp) ?>">
        <input type="text" name="busca" placeholder="Buscar por nome ou instrumento..." value="<?= e($busca) ?>">
        <button class="botao" type="submit">Buscar</button>
        <?php if ($busca !== ''): ?>
            <a class="botao" href="index.php">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (count($alunos) === 0): ?>
        <p class="vazio">Nenhum aluno encontrado. 𝄽</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Instrumento</th>
                    <th class="ocultar-celular">Idade</th>
                    <th class="ocultar-celular">Responsável</th>
                    <?php if (ehSecretaria()): ?><th class="ocultar-celular">Professor(es)</th><?php endif; ?>
                    <th>Situação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alunos as $aluno): ?>
                <tr>
                    <td><a href="ver.php?id=<?= $aluno['id'] ?>"><?= e($aluno['nome']) ?></a></td>
                    <td><span class="selo selo-instrumento"><?= e($aluno['instrumento']) ?></span></td>
                    <td class="ocultar-celular"><?= idade($aluno['data_nascimento']) ?></td>
                    <td class="ocultar-celular"><?= e($aluno['responsavel'] ?: '-') ?></td>
                    <?php if (ehSecretaria()): ?>
                        <td class="ocultar-celular"><?= $aluno['professores'] ? e($aluno['professores']) : '<span class="selo selo-pendente">Sem matrícula</span>' ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($aluno['ativo']): ?>
                            <span class="selo selo-ativo">Ativo</span>
                        <?php else: ?>
                            <span class="selo selo-inativo">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="acoes">
                            <a class="botao botao-pequeno" href="ver.php?id=<?= $aluno['id'] ?>">Ver</a>
                            <?php if (ehSecretaria()): ?>
                                <a class="botao botao-pequeno" href="form.php?id=<?= $aluno['id'] ?>">Editar</a>
                                <!-- Excluir usa POST (formulário), nunca um link GET, para não apagar por acidente -->
                                <form method="post" action="excluir.php" onsubmit="return confirm('Excluir este aluno e todo o histórico dele?');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= $aluno['id'] ?>">
                                    <button class="botao botao-pequeno botao-perigo" type="submit">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
