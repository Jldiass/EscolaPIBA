<?php
/**
 * Topo de todas as páginas: abre o HTML e desenha o menu lateral.
 *
 * Antes de incluir este arquivo, cada página define:
 *   $titulo  -> texto do título da página
 *   $menu    -> qual item do menu fica destacado ('inicio', 'alunos', ...)
 *
 * O menu muda conforme o perfil: o admin vê tudo, o professor
 * vê só o que é dele.
 */
require_once __DIR__ . '/auth.php';

$titulo  = $titulo ?? 'Música PIBA';
$menu    = $menu ?? '';
$raiz    = raizUrl();
$usuario = usuarioAtual();

// Itens do menu: [chave, texto, link, ícone, só admin?]
// Um item com link null é um título de grupo.
$itensMenu = [
    ['inicio',      'Início',       'index.php',              '♪',  false],
    ['grupo',       'Escola',       null,                     '',   false],
    ['alunos',      ehAdmin() ? 'Alunos' : 'Meus alunos', 'alunos/index.php', '♫', false],
    ['professores', 'Professores',  'professores/index.php',  '𝄞',  true],
    ['avisos',      'Avisos',       'avisos/index.php',       '📣', false],
    ['grupo',       'Aulas',        null,                     '',   false],
    ['agenda',      'Agenda',       'agenda.php',             '🗓', false],
    ['chamada',     'Chamada',      'chamada/index.php',      '✓',  false],
    ['frequencia',  'Frequência',   'chamada/frequencia.php', '📊', false],
    ['orquestra',   'Orquestra',    'orquestra/index.php',    '🎼', false],
    ['grupo',       'Gestão',       null,                     '',   false],
    ['financeiro',  ehAdmin() ? 'Financeiro' : 'Mensalidades', ehAdmin() ? 'financeiro/index.php' : 'financeiro/mensalidades.php', ehAdmin() ? '💰' : '💳', false],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · Música PIBA</title>
    <link rel="icon" href="<?= $raiz ?>assets/img/logo-piba.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap">
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
            <?php foreach ($itensMenu as [$chave, $texto, $link, $icone, $soSecretaria]): ?>
                <?php if ($soSecretaria && !ehAdmin()) continue; ?>
                <?php if ($link === null): ?>
                    <div class="grupo-menu"><?= $texto ?></div>
                    <?php continue; ?>
                <?php endif; ?>
                <a class="aba <?= $chave === $menu ? 'aba-ativa' : '' ?>" href="<?= $raiz . $link ?>">
                    <i><?= $icone ?></i><?= $texto ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="pauta" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>

        <?php if ($usuario): ?>
            <div class="usuario">
                <div>
                    <b><?= e($usuario['nome']) ?></b>
                    <small><?= $usuario['perfil'] === 'admin' ? 'Administrador' : 'Professor(a)' ?></small>
                </div>
                <div class="acoes">
                    <a class="botao botao-pequeno" href="<?= $raiz ?>logout.php">Sair</a>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <main class="conteudo">
