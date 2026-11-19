<?php
/**
 * Orquestra > Membros: lista com a assiduidade de cada um.
 * - Todos veem a lista com o status de cada membro.
 * - Para quem está "Ausente" ou "De vez em quando" aparece o botão do
 *   WhatsApp, que abre a conversa já com uma mensagem pronta.
 * - O admin cadastra e edita membros (orquestra/membro.php).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/orquestra.php';

exigirLogin();
$pdo = conectar();

$membros  = membrosOrquestra();
$filtro   = array_key_exists($_GET['status'] ?? '', STATUS_ORQUESTRA) ? $_GET['status'] : '';
$contagem = array_count_values(array_column($membros, 'status'));
$visiveis = $filtro ? array_filter($membros, fn($m) => $m['status'] === $filtro) : $membros;

$ultimoEnsaio = $pdo->query('SELECT * FROM orquestra_ensaios ORDER BY data DESC LIMIT 1')->fetch();
$percentuais  = array_filter(array_column($membros, 'percentual'), fn($p) => $p !== null);
$media        = $percentuais ? round(array_sum($percentuais) / count($percentuais)) : 0;
$paraContatar = ($contagem['ausente'] ?? 0) + ($contagem['as_vezes'] ?? 0);

$regentes = array_column(array_filter(maestrosOrquestra(), fn($m) => $m['funcao'] === 'Regente titular'), 'nome');

$mensagens = ['criado' => 'Membro cadastrado! ♪', 'editado' => 'Membro atualizado.'];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$titulo = 'Orquestra';
$menu   = 'orquestra';
require __DIR__ . '/../includes/header.php';
?>

<div class="topo">
    <div>
        <h1>🎼 Orquestra</h1>
        <p>Prática de orquestra (bancada pela igreja) · Regência: <b><?= $regentes ? e(implode(', ', $regentes)) : 'não definida' ?></b>
           · status pelos últimos <?= ENSAIOS_STATUS ?> ensaios</p>
    </div>
    <div class="acoes">
        <?php if (podeFazerChamadaOrquestra()): ?>
            <a class="botao botao-primario" href="chamada.php">✓ Fazer chamada do ensaio</a>
        <?php endif; ?>
        <?php if (ehAdmin()): ?>
            <a class="botao botao-primario" href="membro.php">+ Novo membro</a>
        <?php endif; ?>
    </div>
</div>

<?php $aba = 'membros'; require __DIR__ . '/../includes/abas-orquestra.php'; ?>

<?php if ($msg): ?><div class="alerta alerta-sucesso"><?= e($msg) ?></div><?php endif; ?>

<section class="indicadores">
    <div class="cartao indicador"><span class="icone">🎻</span><div class="rotulo">Membros ativos</div><div class="valor"><?= count($membros) ?></div></div>
    <div class="cartao indicador"><span class="icone">✓</span><div class="rotulo">Frequentes</div><div class="valor"><?= $contagem['frequente'] ?? 0 ?></div></div>
    <div class="cartao indicador"><span class="icone">📊</span><div class="rotulo">Presença média</div><div class="valor"><?= $media ?>%</div></div>
    <a class="cartao indicador" href="?status=ausente"><span class="icone">💬</span><div class="rotulo">Para chamar no WhatsApp</div>
        <div class="valor <?= $paraContatar ? 'texto-alerta' : '' ?>"><?= $paraContatar ?></div></a>
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
            <thead><tr><th>Membro</th><th>Naipe</th><th>Instrumento</th><th class="ocultar-celular">Últimos ensaios</th><th>Presença</th><th>Status</th><th>Contato</th><?= ehAdmin() ? '<th></th>' : '' ?></tr></thead>
            <tbody>
            <?php foreach ($visiveis as $m): ?>
                <?php
                [$rotulo, $classe] = STATUS_ORQUESTRA[$m['status']];
                // WhatsApp só para quem precisa de um contato (ausente ou de vez em quando)
                $whats = in_array($m['status'], ['ausente', 'as_vezes'], true)
                    ? linkWhatsApp($m['telefone'], mensagemContato($m['nome'], $m['status'])) : null;
                ?>
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
                    <td>
                        <?php if ($whats): ?>
                            <a class="botao botao-pequeno botao-whats" href="<?= e($whats) ?>" target="_blank" rel="noopener"
                               title="Abrir conversa no WhatsApp com mensagem pronta">💬 WhatsApp</a>
                        <?php elseif (in_array($m['status'], ['ausente', 'as_vezes'], true)): ?>
                            <small class="suave">sem telefone</small>
                        <?php else: ?>
                            <small class="suave">—</small>
                        <?php endif; ?>
                    </td>
                    <?php if (ehAdmin()): ?>
                        <td><a class="botao botao-pequeno" href="membro.php?id=<?= $m['id'] ?>">Editar</a></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="nota-rodape">Frequente: 75% ou mais · De vez em quando: 40% a 74% · Ausente: menos de 40% · Novo: ainda sem ensaio registrado.
            O botão 💬 abre o WhatsApp com uma mensagem pronta para chamar a pessoa de volta.
            <?= $ultimoEnsaio ? 'Último ensaio: ' . date('d/m/Y', strtotime($ultimoEnsaio['data'])) . '.' : '' ?></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
