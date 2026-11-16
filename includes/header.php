<?php
/**
 * Topo de todas as páginas: abre o HTML e desenha o menu lateral.
 *
 * Antes de incluir este arquivo, cada página define:
 *   $titulo  -> texto do título da página
 *   $menu    -> qual item do menu fica destacado ('inicio', 'alunos', ...)
 *   $raiz    -> caminho até a pasta principal ('' na raiz, '../' dentro de alunos/)
 */
$titulo = $titulo ?? 'Música PIBA';
$menu   = $menu ?? '';
$raiz   = $raiz ?? '';

// Itens do menu: [chave, texto, link, ícone]
// Os itens com link null ainda serão feitos nas próximas etapas.
$itensMenu = [
    ['inicio',      'Início',      'index.php',        '♪'],
    ['alunos',      'Alunos',      'alunos/index.php', '♫'],
    ['turmas',      'Turmas',      null,               '𝄞'],
    ['frequencia',  'Chamada',     null,               '✓'],
    ['notas',       'Notas',       null,               '★'],
    ['agenda',      'Agenda',      null,               '🎻'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · Música PIBA</title>
    <link rel="icon" href="<?= $raiz ?>assets/img/logo-piba.png">
    <link rel="stylesheet" href="<?= $raiz ?>assets/css/style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <a class="marca" href="<?= $raiz ?>index.php">
            <img src="<?= $raiz ?>assets/img/logo-piba.png" alt="Logo da PIBA">
            <span>Música<b>PIBA</b></span>
        </a>
        <p class="subtitulo">Escola de Música</p>

        <nav>
            <?php foreach ($itensMenu as [$chave, $texto, $link, $icone]): ?>
                <?php if ($link === null): ?>
                    <span class="aba aba-em-breve" title="Em breve">
                        <i><?= $icone ?></i><?= $texto ?><small>em breve</small>
                    </span>
                <?php else: ?>
                    <a class="aba <?= $chave === $menu ? 'aba-ativa' : '' ?>" href="<?= $raiz . $link ?>">
                        <i><?= $icone ?></i><?= $texto ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="pauta" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
        <p class="rodape-menu">Projeto de Extensão · Anhanguera</p>
    </aside>

    <main class="conteudo">
