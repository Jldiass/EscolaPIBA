<?php
/**
 * Roteador do servidor embutido do PHP (php -S localhost:8000 router.php).
 *
 * Sem ele, o "php -S" entrega QUALQUER arquivo da pasta para quem pedir,
 * inclusive o banco database/escola.sqlite (com alunos e senhas) e os PDFs
 * guardados. Aqui bloqueamos as pastas internas: só as páginas .php e os
 * arquivos de assets/ ficam acessíveis pelo navegador.
 *
 * No Apache (XAMPP) quem faz esse bloqueio são os arquivos .htaccess.
 */
$caminho = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Windows ignora pontos e espaços no fim do nome ("database." = "database"); tiramos aqui também
$normalizado = preg_replace('#[. ]+(/|$)#', '$1', $caminho);

// Pastas internas, arquivos ocultos (.git...) e caminhos com ".." nunca podem ser abertos
$bloqueado = '#(^|/)\.|^/(database|sql|includes|config|tests|docs)(/|$)|^/router\.php$#i';
if (str_contains($caminho, '..') || preg_match($bloqueado, $caminho) || preg_match($bloqueado, $normalizado)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Fora isso, só páginas .php e os arquivos da pasta assets/ (CSS, JS, imagens)
if (!str_ends_with($caminho, '/') && !preg_match('#\.php$#i', $caminho) && !preg_match('#^/assets/#i', $normalizado)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Arquivos comuns (CSS, JS, imagens) e páginas .php: o servidor cuida normalmente
return false;
