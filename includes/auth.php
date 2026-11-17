<?php
/**
 * Controle de login e de permissões.
 *
 * Quando a pessoa faz login, guardamos o id dela na SESSÃO
 * ($_SESSION). A sessão é como uma "memória" do servidor ligada
 * ao navegador daquela pessoa, por meio de um cookie.
 *
 * Toda página protegida começa chamando exigirLogin() ou
 * exigirSecretaria(). Se a pessoa não tiver permissão, ela é
 * mandada para a tela de login.
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Devolve os dados de quem está logado, ou null. */
function usuarioAtual(): ?array
{
    static $usuario = false;
    if ($usuario !== false) {
        return $usuario;
    }
    $usuario = null;
    if (!empty($_SESSION['usuario_id'])) {
        $stmt = conectar()->prepare('SELECT * FROM usuarios WHERE id = ? AND ativo = 1');
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch() ?: null;
    }
    return $usuario;
}

function ehSecretaria(): bool
{
    return (usuarioAtual()['perfil'] ?? '') === 'secretaria';
}

/** Bloqueia a página para quem não fez login. */
function exigirLogin(): array
{
    $usuario = usuarioAtual();
    if (!$usuario) {
        header('Location: ' . raizUrl() . 'login.php');
        exit;
    }
    return $usuario;
}

/** Bloqueia a página para quem não é da secretaria. */
function exigirSecretaria(): array
{
    $usuario = exigirLogin();
    if ($usuario['perfil'] !== 'secretaria') {
        http_response_code(403);
        exit('Acesso permitido somente à secretaria.');
    }
    return $usuario;
}

/** Caminho até a pasta principal, a partir da página atual ('' ou '../'). */
function raizUrl(): string
{
    // Se a página está na pasta principal, a raiz é ''. Se está numa subpasta (alunos/), é '../'
    return realpath(dirname($_SERVER['SCRIPT_FILENAME'])) === realpath(__DIR__ . '/..') ? '' : '../';
}

/**
 * Proteção CSRF: cada formulário leva um código secreto da sessão.
 * Assim, outro site não consegue enviar um formulário "em nome" do usuário.
 */
function campoCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function verificarCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Formulário expirado. Volte e tente de novo.');
    }
}
