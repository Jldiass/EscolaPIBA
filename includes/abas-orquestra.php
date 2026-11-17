<?php
/**
 * Abas da Orquestra: Membros, Maestros e Chamada dos ensaios.
 * Espera $aba ('membros', 'maestros' ou 'chamada').
 */
$abasOrquestra = [
    'membros'  => ['🎻 Membros', 'index.php'],
    'maestros' => ['🎼 Maestros', 'maestros.php'],
    'chamada'  => ['✓ Chamada dos ensaios', 'chamada.php'],
];
?>
<nav class="filtros abas-orquestra" aria-label="Orquestra">
    <?php foreach ($abasOrquestra as $chave => [$nome, $arquivo]): ?>
        <a class="filtro <?= $aba === $chave ? 'filtro-ativo' : '' ?>" href="<?= $arquivo ?>"><?= $nome ?></a>
    <?php endforeach; ?>
</nav>
