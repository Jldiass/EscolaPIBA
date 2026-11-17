<?php
/**
 * Painel inicial: um painel diferente para cada perfil.
 *
 * Admin: próximas aulas de todos os professores, avisos e lembretes,
 *        alunos que não pagaram (informado pelo professor) e
 *        professores que ainda não repassaram a parte da escola.
 * Professor: as próximas aulas dele, avisos, mensalidades em aberto
 *        dos alunos dele e quem está com frequência baixa.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/consultas.php';
require __DIR__ . '/includes/avisos.php';

$usuario = exigirLogin();
$pdo = conectar();

$hoje       = (int) date('N');               // 1 = segunda ... 7 = domingo
$profFiltro = ehAdmin() ? null : (int) $usuario['id'];
$aulasHoje  = aulasDoDia($hoje, $profFiltro);
$mesAtual   = date('Y-m');

// Blocos que os dois perfis têm
$proximas      = proximasAulas($profFiltro, 6);
$lembretes     = lembretesAtivos();
$eventos       = proximosEventos(3);
$inadimplentes = alunosInadimplentes($profFiltro);
$semInfo       = mensalidadesSemInformacao($mesAtual, $profFiltro);

if (ehAdmin()) {
    $alunosAtivos = $pdo->query('SELECT COUNT(*) FROM alunos WHERE ativo = 1')->fetchColumn();
    $aulasSemana  = $pdo->query('SELECT COUNT(*) FROM matriculas WHERE ativo = 1')->fetchColumn();
    // Repasse atrasado = mês que já fechou e o professor não repassou.
    // No mês atual o professor ainda está no prazo, então só mostramos quantos faltam.
    $todosPendentes   = repassesPendentes();
    $repasses         = array_filter($todosPendentes, fn($r) => $r['mes'] < $mesAtual);
    $pendentesDoMes   = count($todosPendentes) - count($repasses);
} else {
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT aluno_id) FROM matriculas WHERE professor_id = ? AND ativo = 1');
    $stmt->execute([$usuario['id']]);
    $alunosAtivos = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM matriculas WHERE professor_id = ? AND ativo = 1');
    $stmt->execute([$usuario['id']]);
    $aulasSemana = $stmt->fetchColumn();

    // Frequência dos alunos do professor nos últimos 30 dias
    $freq = relatorioFrequencia((int) $usuario['id'], date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    $aulas = array_sum(array_column($freq, 'aulas'));
    $freqMedia = $aulas ? round(array_sum(array_column($freq, 'presencas')) * 100 / $aulas) : 0;
    $freqBaixa = array_filter($freq, fn($l) => $l['percentual'] !== null && $l['percentual'] < 75);
}

/** "Hoje", "Amanhã" ou "Qui, 19/11" */
function quando(string $data): string
{
    if ($data === date('Y-m-d')) {
        return 'Hoje';
    }
    if ($data === date('Y-m-d', strtotime('+1 day'))) {
        return 'Amanhã';
    }
    return mb_substr(DIAS_SEMANA[(int) date('N', strtotime($data))], 0, 3) . ', ' . date('d/m', strtotime($data));
}

/** Lista de meses "Out/2026, Nov/2026" */
function mesesCurtos(array $meses): string
{
    $nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return implode(', ', array_map(fn($m) => $nomes[(int) substr($m, 5, 2) - 1] . '/' . substr($m, 0, 4), $meses));
}

$titulo = 'Início';
$menu   = 'inicio';
require __DIR__ . '/includes/header.php';
?>

<section class="banner">
    <div class="bolhas" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="banner-texto">
        <span class="etiqueta"><b></b><?= DIAS_SEMANA[$hoje] ?>, <?= date('d/m/Y') ?></span>
        <h1>Olá, <?= e(explode(' ', $usuario['nome'])[0]) ?>! <span class="destaque">Vamos fazer música</span></h1>
        <p><?= count($aulasHoje) ? 'Há ' . count($aulasHoje) . ' aula(s) marcada(s) para hoje.' : 'Nenhuma aula marcada para hoje.' ?>
           <?= ehAdmin() ? 'Aqui está o resumo da escola.' : 'Aqui está o resumo das suas aulas.' ?></p>
    </div>
    <!-- Nota musical 3D girando (Three.js). Sem internet, aparece uma nota simples. -->
    <div class="palco" id="palco" role="img" aria-label="Nota musical 3D girando">
        <?php if ($proximas): ?>
            <div class="cartao-flutuante">
                <span class="ao-vivo">Próxima aula</span>
                <b><?= quando($proximas[0]['data']) ?> · <?= e($proximas[0]['hora_inicio']) ?></b>
                <span class="suave"><?= e($proximas[0]['aluno']) ?> · <?= e($proximas[0]['instrumento']) ?></span>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="indicadores">
    <div class="cartao indicador">
        <span class="icone">♫</span>
        <div class="rotulo"><?= ehAdmin() ? 'Alunos ativos' : 'Meus alunos' ?></div>
        <div class="valor"><?= $alunosAtivos ?></div>
    </div>
    <div class="cartao indicador">
        <span class="icone">🗓</span>
        <div class="rotulo">Aulas por semana</div>
        <div class="valor"><?= $aulasSemana ?></div>
    </div>
    <?php if (ehAdmin()): ?>
        <a class="cartao indicador" href="mensalidades/index.php">
            <span class="icone">💳</span>
            <div class="rotulo">Alunos que não pagaram</div>
            <div class="valor <?= $inadimplentes ? 'texto-alerta' : '' ?>"><?= count($inadimplentes) ?></div>
        </a>
        <a class="cartao indicador" href="financeiro/index.php">
            <span class="icone">💰</span>
            <div class="rotulo">Repasses atrasados</div>
            <div class="valor <?= $repasses ? 'texto-alerta' : '' ?>"><?= count($repasses) ?></div>
        </a>
    <?php else: ?>
        <div class="cartao indicador">
            <span class="icone">📊</span>
            <div class="rotulo">Frequência (30 dias)</div>
            <div class="valor"><?= $freqMedia ?>%</div>
        </div>
        <a class="cartao indicador" href="mensalidades/index.php">
            <span class="icone">💳</span>
            <div class="rotulo">Mensalidades em aberto</div>
            <div class="valor <?= $inadimplentes ? 'texto-alerta' : '' ?>"><?= count($inadimplentes) ?></div>
        </a>
    <?php endif; ?>
</section>

<div class="painel-grade">
    <!-- Próximas aulas -->
    <section class="cartao">
        <div class="topo">
            <h2>🗓 <?= ehAdmin() ? 'Próximas aulas dos professores' : 'Minhas próximas aulas' ?></h2>
            <a class="botao botao-pequeno" href="<?= ehAdmin() ? 'agenda.php' : 'chamada/index.php' ?>"><?= ehAdmin() ? 'Ver agenda' : 'Fazer chamada' ?></a>
        </div>
        <?php if (!$proximas): ?>
            <p class="vazio">Nenhuma aula nos próximos 7 dias. 𝄽</p>
        <?php else: ?>
            <table class="tabela">
                <thead><tr><th>Quando</th><th>Horário</th><th>Aluno</th><?= ehAdmin() ? '<th>Professor</th>' : '<th>Instrumento</th>' ?></tr></thead>
                <tbody>
                <?php foreach ($proximas as $aula): ?>
                    <tr>
                        <td class="sem-quebra"><b><?= quando($aula['data']) ?></b></td>
                        <td class="numero"><?= e($aula['hora_inicio']) ?></td>
                        <td><?= e($aula['aluno']) ?></td>
                        <?php if (ehAdmin()): ?>
                            <td><?= e($aula['professor']) ?> <small class="suave">· <?= e($aula['instrumento']) ?></small></td>
                        <?php else: ?>
                            <td><span class="selo selo-instrumento"><?= e($aula['instrumento']) ?></span></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Avisos e lembretes (para todos) -->
    <section class="cartao">
        <div class="topo">
            <h2>📣 Avisos e lembretes</h2>
            <a class="botao botao-pequeno" href="avisos/index.php">Ver todos</a>
        </div>
        <?php if (!$lembretes && !$eventos): ?>
            <p class="vazio">Nenhum aviso no momento.</p>
        <?php else: ?>
            <ul class="avisos-painel">
                <?php foreach ($lembretes as $l): ?>
                    <li class="aviso-lembrete">
                        <span class="selo selo-lembrete">📌 Lembrete</span>
                        <div><b><?= e($l['titulo']) ?></b>
                            <small class="suave"><?= $l['valido_ate'] ? 'até ' . date('d/m', strtotime($l['valido_ate'])) : 'sem data para acabar' ?></small></div>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($eventos as $ev): ?>
                    <?php [$nomeTipo, $icone, $classe] = TIPOS_AVISO[$ev['tipo']]; ?>
                    <li>
                        <span class="selo <?= $classe ?>"><?= $icone ?> <?= $nomeTipo ?></span>
                        <div><b><?= e($ev['titulo']) ?></b>
                            <small class="suave"><?= date('d/m', strtotime($ev['data'])) ?><?= $ev['hora'] ? ' · ' . e($ev['hora']) : '' ?> · <?= quandoFalta($ev['data']) ?></small></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Alunos que não pagaram (o professor é quem informa) -->
    <section class="cartao">
        <div class="topo">
            <h2>💳 <?= ehAdmin() ? 'Alunos que não pagaram' : 'Mensalidades em aberto' ?></h2>
            <a class="botao botao-pequeno" href="mensalidades/index.php"><?= ehAdmin() ? 'Ver mensalidades' : 'Informar pagamentos' ?></a>
        </div>
        <?php if (!$inadimplentes): ?>
            <p class="vazio">Ninguém com mensalidade em aberto. ♪</p>
        <?php else: ?>
            <table class="tabela">
                <thead><tr><th>Aluno</th><?= ehAdmin() ? '<th>Professor</th>' : '' ?><th>Meses</th><th>Em aberto</th></tr></thead>
                <tbody>
                <?php foreach ($inadimplentes as $i): ?>
                    <tr>
                        <td><?= e($i['aluno']) ?></td>
                        <?php if (ehAdmin()): ?><td><?= e($i['professor']) ?></td><?php endif; ?>
                        <td><small><?= mesesCurtos($i['meses']) ?></small></td>
                        <td class="numero texto-alerta"><?= dinheiro($i['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if ($semInfo): ?>
            <p class="nota-rodape">⏳ <?= $semInfo ?> aluno(s) ainda sem informação de pagamento em <?= nomeMes($mesAtual) ?>.</p>
        <?php endif; ?>
    </section>

    <?php if (ehAdmin()): ?>
        <!-- Professores que não repassaram a parte da escola -->
        <section class="cartao">
            <div class="topo">
                <h2>💰 Professores sem repasse</h2>
                <a class="botao botao-pequeno" href="financeiro/index.php">Ver financeiro</a>
            </div>
            <?php if (!$repasses): ?>
                <p class="vazio">Nenhum repasse atrasado. ♪</p>
            <?php else: ?>
                <table class="tabela">
                    <thead><tr><th>Professor</th><th>Mês</th><th>Deve repassar</th></tr></thead>
                    <tbody>
                    <?php foreach ($repasses as $r): ?>
                        <tr>
                            <td><?= e($r['nome']) ?></td>
                            <td><?= nomeMes($r['mes']) ?> <span class="selo selo-falta">atrasado</span></td>
                            <td class="numero texto-alerta"><?= dinheiro($r['parte_escola']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if ($pendentesDoMes): ?>
                <p class="nota-rodape">⏳ <?= nomeMes($mesAtual) ?>: <?= $pendentesDoMes ?> professor(es) ainda não repassaram (o mês não fechou).</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <!-- Alunos do professor com frequência baixa -->
        <section class="cartao">
            <div class="topo">
                <h2>📊 Atenção à frequência</h2>
                <a class="botao botao-pequeno" href="chamada/frequencia.php">Ver frequência</a>
            </div>
            <?php if (!$freqBaixa): ?>
                <p class="vazio">Todos os seus alunos acima de 75%. ♪</p>
            <?php else: ?>
                <table class="tabela">
                    <thead><tr><th>Aluno</th><th>Instrumento</th><th>Frequência (30 dias)</th></tr></thead>
                    <tbody>
                    <?php foreach ($freqBaixa as $f): ?>
                        <tr>
                            <td><?= e($f['aluno']) ?></td>
                            <td><span class="selo selo-instrumento"><?= e($f['instrumento']) ?></span></td>
                            <td class="numero texto-alerta"><?= $f['percentual'] ?>% <small class="suave">(<?= $f['presencas'] ?>/<?= $f['aulas'] ?>)</small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<!-- Three.js r128 guardado no próprio projeto: funciona mesmo sem internet -->
<script src="assets/js/vendor/three.min.js"></script>
<script src="assets/js/nota3d.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
