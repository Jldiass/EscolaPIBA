<?php
/**
 * Controle de login e de permissões.
 *
 * Quando a pessoa faz login, guardamos o id dela na SESSÃO
 * ($_SESSION). A sessão é como uma "memória" do servidor ligada
 * ao navegador daquela pessoa, por meio de um cookie.
 *
 * Toda página protegida começa chamando exigirLogin() ou
 * exigirAdmin(). Se a pessoa não tiver permissão, ela é
 * mandada para a tela de login.
 */
require_once __DIR__ . '/../config/database.php';

/*
 * Se algo der errado (um dado inesperado, o banco travado...), mostramos uma
 * mensagem amigável em vez da tela de erro do PHP, que exporia detalhes internos.
 * O erro completo vai para o log do servidor.
 */
set_exception_handler(function (Throwable $erro): void {
    error_log((string) $erro);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<p style="font-family: sans-serif; padding: 24px">Não foi possível concluir esta ação. '
       . 'Confira os dados e tente de novo. <a href="javascript:history.back()">Voltar</a></p>';
});

if (session_status() === PHP_SESSION_NONE) {
    // Cookie da sessão: invisível para JavaScript (httponly) e não enviado por outros sites (samesite)
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
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

function ehAdmin(): bool
{
    return (usuarioAtual()['perfil'] ?? '') === 'admin';
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

/** Bloqueia a página para quem não é admin. */
function exigirAdmin(): array
{
    $usuario = exigirLogin();
    if ($usuario['perfil'] !== 'admin') {
        http_response_code(403);
        exit('Acesso permitido somente ao administrador.');
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
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        http_response_code(400);
        exit('Formulário expirado. Volte e tente de novo.');
    }
}
