<?php
/**
 * Conexão com o banco de dados SQLite.
 *
 * Usamos PDO (PHP Data Objects), a forma padrão e segura do PHP
 * falar com bancos de dados. O SQLite guarda tudo em um único
 * arquivo (database/escola.sqlite), então não precisa instalar
 * servidor de banco: ideal para uma escola pequena.
 *
 * Na primeira vez que o sistema roda, o arquivo ainda não existe.
 * Então criamos as tabelas (sql/schema.sql) e inserimos alguns
 * alunos fictícios (sql/dados_ficticios.sql) para demonstração.
 */

function conectar(): PDO
{
    // static: a conexão é criada uma vez só e reaproveitada
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $arquivo = __DIR__ . '/../database/escola.sqlite';
    $bancoNovo = !file_exists($arquivo);

    $pdo = new PDO('sqlite:' . $arquivo);
    // Lança exceção quando der erro, em vez de falhar em silêncio
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Cada linha volta como array associativo: $linha['nome']
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // SQLite só respeita chaves estrangeiras com isto ligado
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Sempre roda o schema (CREATE TABLE IF NOT EXISTS não duplica nada)
    $pdo->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));

    if ($bancoNovo) {
        $pdo->exec(file_get_contents(__DIR__ . '/../sql/dados_ficticios.sql'));
    }

    return $pdo;
}

/**
 * Escapa texto antes de mostrar no HTML.
 * Evita que alguém cadastre um nome com <script> e ataque a página (XSS).
 */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}
