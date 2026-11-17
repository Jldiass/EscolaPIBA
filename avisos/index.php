<?php
/**
 * Avisos da escola.
 * - Todos veem: lembretes ativos, próximos eventos e o histórico.
 * - Só o admin cadastra, edita e exclui.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/avisos.php';

exigirLogin();

// Excluir (só admin, só POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();
    verificarCsrf();
    conectar()->prepare('DELETE FROM avisos WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    header('Location: index.php?msg=excluido');
    exit;
}

$lembretes = lembretesAtivos();
$eventos   = proximosEventos();
$passados  = avisosPassados();

$mensagens = ['criado' => 'Aviso publicado! ♪', 'editado' => 'Aviso atualizado.', 'excluido' => 'Aviso excluído.'];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$meses = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

/** Botões Editar/Excluir de um aviso (só aparecem para o admin). */
function botoesAviso(array $a): string
{
    if (!ehAdmin()) {
        return '';
    }
    return '<div class="acoes">
        <a class="botao botao-pequeno" href="form.php?id=' . $a['id'] . '">Editar</a>
        <form method="post" onsubmit="return confirm(\'Excluir este aviso?\')">' . campoCsrf() . '
            <input type="hidden" name="id" value="' . $a['id'] . '">
            <button class="botao botao-pequeno botao-perigo" type="submit">Excluir</button>
        </form></div>';
}

$titulo = 'Avisos';
$menu   = 'avisos';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>📣 Avisos</h1>
        <p>Eventos sociais, eventos internos e lembretes da escola.</p>
    </div>
    <?php if (ehAdmin()): ?>
        <div class="acoes">
            <a class="botao" href="form.php?tipo=lembrete">📌 Novo lembrete</a>
            <a class="botao botao-primario" href="form.php">+ Novo evento</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>

<h2>📌 Lembretes</h2>
<?php if (!$lembretes): ?>
    <p class="vazio cartao">Nenhum lembrete ativo.</p>
<?php else: ?>
    <div class="grade-avisos">
        <?php foreach ($lembretes as $l): ?>
            <article class="cartao lembrete">
                <b><?= e($l['titulo']) ?></b>
                <?php if ($l['descricao']): ?><p><?= nl2br(e($l['descricao'])) ?></p><?php endif; ?>
                <small class="suave">
                    <?= $l['local'] ? '📍 ' . e($l['local']) . ' · ' : '' ?>
                    <?= $l['valido_ate'] ? 'Vale até ' . date('d/m/Y', strtotime($l['valido_ate'])) : 'Sem data para acabar' ?>
                </small>
                <?= botoesAviso($l) ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 style="margin-top: 26px">🗓 Próximos eventos</h2>
<?php if (!$eventos): ?>
    <p class="vazio cartao">Nenhum evento marcado.</p>
<?php else: ?>
    <div class="lista-eventos">
        <?php foreach ($eventos as $ev): ?>
            <?php [$nomeTipo, $icone, $classe] = TIPOS_AVISO[$ev['tipo']]; ?>
            <article class="cartao evento evento-<?= $ev['tipo'] ?>">
                <div class="evento-data">
                    <b><?= date('d', strtotime($ev['data'])) ?></b>
                    <span><?= $meses[(int) date('m', strtotime($ev['data'])) - 1] ?></span>
                </div>
                <div class="evento-info">
                    <div class="evento-linha">
                        <span class="selo <?= $classe ?>"><?= $icone ?> <?= $nomeTipo ?></span>
                        <small class="suave"><?= quandoFalta($ev['data']) ?></small>
                    </div>
                    <b><?= e($ev['titulo']) ?></b>
                    <?php if ($ev['descricao']): ?><p><?= nl2br(e($ev['descricao'])) ?></p><?php endif; ?>
                    <small class="suave">
                        <?= DIAS_SEMANA[(int) date('N', strtotime($ev['data']))] ?>, <?= date('d/m/Y', strtotime($ev['data'])) ?>
                        <?= $ev['hora'] ? ' · ' . e($ev['hora']) : '' ?>
                        <?= $ev['local'] ? ' · 📍 ' . e($ev['local']) : '' ?>
                    </small>
                </div>
                <?= botoesAviso($ev) ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($passados): ?>
    <details class="cartao historico-avisos">
        <summary>Histórico (eventos que já passaram e lembretes vencidos)</summary>
        <table class="tabela">
            <thead><tr><th>Data</th><th>Tipo</th><th>Título</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($passados as $p): ?>
                <tr>
                    <td class="numero"><?= date('d/m/Y', strtotime($p['tipo'] === 'lembrete' ? $p['valido_ate'] : $p['data'])) ?></td>
                    <td><span class="selo <?= TIPOS_AVISO[$p['tipo']][2] ?>"><?= TIPOS_AVISO[$p['tipo']][0] ?></span></td>
                    <td><?= e($p['titulo']) ?></td>
                    <td><?= botoesAviso($p) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
