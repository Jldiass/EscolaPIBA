<?php
/**
 * Lista de alunos, com busca por nome ou instrumento.
 */
require __DIR__ . '/../config/database.php';

$pdo = conectar();

// O que foi digitado na busca (?busca=...). trim() tira espaços sobrando.
$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    // Consulta preparada: o texto do usuário vai separado do SQL,
    // o que impede ataques de SQL Injection.
    $stmt = $pdo->prepare(
        'SELECT * FROM alunos
         WHERE nome LIKE :busca OR instrumento LIKE :busca
         ORDER BY nome'
    );
    $stmt->execute([':busca' => '%' . $busca . '%']);
} else {
    $stmt = $pdo->query('SELECT * FROM alunos ORDER BY nome');
}
$alunos = $stmt->fetchAll();

// Mensagem que vem depois de salvar ou excluir (?msg=...)
$mensagens = [
    'criado'    => 'Aluno cadastrado com sucesso!',
    'editado'   => 'Dados do aluno atualizados.',
    'excluido'  => 'Aluno excluído.',
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

$titulo = 'Alunos';
$menu   = 'alunos';
$raiz   = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>♫ Alunos</h1>
        <p><?= count($alunos) ?> aluno(s) encontrado(s)</p>
    </div>
    <a class="botao botao-primario" href="form.php">+ Novo aluno</a>
</div>

<?php if ($msg): ?>
    <div class="alerta alerta-sucesso"><?= e($msg) ?></div>
<?php endif; ?>

<section class="cartao">
    <form class="busca" method="get">
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
                    <th>Situação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alunos as $aluno): ?>
                <tr>
                    <td><?= e($aluno['nome']) ?></td>
                    <td><span class="selo selo-instrumento"><?= e($aluno['instrumento']) ?></span></td>
                    <td class="ocultar-celular"><?= idade($aluno['data_nascimento']) ?></td>
                    <td class="ocultar-celular"><?= e($aluno['responsavel'] ?: '-') ?></td>
                    <td>
                        <?php if ($aluno['ativo']): ?>
                            <span class="selo selo-ativo">Ativo</span>
                        <?php else: ?>
                            <span class="selo selo-inativo">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="acoes">
                        <a class="botao botao-pequeno" href="form.php?id=<?= $aluno['id'] ?>">Editar</a>
                        <!-- Excluir usa POST (formulário), nunca um link GET, para não apagar por acidente -->
                        <form method="post" action="excluir.php" onsubmit="return confirm('Excluir este aluno?');">
                            <input type="hidden" name="id" value="<?= $aluno['id'] ?>">
                            <button class="botao botao-pequeno botao-perigo" type="submit">Excluir</button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
