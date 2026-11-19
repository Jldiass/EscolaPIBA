<?php
/**
 * Abas da área Financeiro (seção Gestão do menu).
 *   Resumo do mês            -> visão geral do dinheiro da escola no mês
 *   Mensalidades dos alunos  -> o aluno paga o professor (o professor informa quem pagou)
 *   Repasses dos professores -> o professor repassa a parte da escola
 * O professor só usa Mensalidades, então para ele as abas não aparecem.
 * Espera $aba ('resumo', 'mensalidades' ou 'repasses') e $mes ('AAAA-MM').
 */
if (ehAdmin()):
    $abas = [
        'resumo'       => ['📊 Resumo do mês', 'index.php'],
        'mensalidades' => ['💳 Mensalidades dos alunos', 'mensalidades.php'],
        'repasses'     => ['🏦 Repasses dos professores', 'repasses.php'],
    ];
    ?>
    <nav class="filtros abas-financeiro" aria-label="Financeiro">
        <?php foreach ($abas as $chave => [$nome, $arquivo]): ?>
            <a class="filtro <?= $aba === $chave ? 'filtro-ativo' : '' ?>" href="<?= $arquivo ?>?mes=<?= e($mes) ?>"><?= $nome ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif;
