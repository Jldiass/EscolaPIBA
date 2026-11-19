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
 * Então criamos as tabelas (sql/schema.sql) e inserimos dados
 * fictícios (sql/dados_ficticios.php) para demonstração.
 */

// Datas e horas no fuso do Brasil (sem isto o PHP usa UTC e, depois das 21h,
// o sistema acharia que já é o dia seguinte)
date_default_timezone_set('America/Sao_Paulo');

function conectar(): PDO
{
    // static: a conexão é criada uma vez só e reaproveitada
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // ESCOLA_DB permite usar outro arquivo (o teste automático usa um banco temporário)
    $arquivo = getenv('ESCOLA_DB') ?: __DIR__ . '/../database/escola.sqlite';
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

    // Banco criado numa versão antiga? Acrescenta a coluna nova sem perder dados.
    $colunas = array_column($pdo->query('PRAGMA table_info(matriculas)')->fetchAll(), 'name');
    if (!in_array('bolsista', $colunas, true)) {
        $pdo->exec('ALTER TABLE matriculas ADD COLUMN bolsista INTEGER NOT NULL DEFAULT 0');
    }

    if ($bancoNovo) {
        require_once __DIR__ . '/../sql/dados_ficticios.php';
        inserirDadosFicticios($pdo);
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

/** Confere se o texto é uma data AAAA-MM-DD que existe (ex.: recusa 31/02). */
function dataValida(mixed $d): bool
{
    if (!is_string($d)) {
        return false;
    }
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

/** Confere se o texto é um horário HH:MM válido (00:00 a 23:59). */
function horaValida(mixed $h): bool
{
    return is_string($h) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h) === 1;
}

/** Confere se o texto é um mês AAAA-MM válido. */
function mesValido(mixed $m): bool
{
    return is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) === 1;
}

/** Formata número como dinheiro: 1234.5 -> "R$ 1.234,50" */
function dinheiro(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/** Nomes dos dias da semana (1 = segunda ... 7 = domingo, padrão ISO) */
const DIAS_SEMANA = [
    1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
    5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo',
];

/** Lê um parâmetro da tabela configuracoes */
function configuracao(string $chave, string $padrao = ''): string
{
    $stmt = conectar()->prepare('SELECT valor FROM configuracoes WHERE chave = ?');
    $stmt->execute([$chave]);
    $valor = $stmt->fetchColumn();
    return $valor === false ? $padrao : $valor;
}

/** Nome do mês em português: '2026-11' -> 'Novembro/2026' */
function nomeMes(string $anoMes): string
{
    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho',
              'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    [$ano, $mes] = explode('-', $anoMes);
    return $meses[(int) $mes - 1] . '/' . $ano;
}

/**
 * Especialidades de um professor: "Teoria musical, Bateria" -> ['Teoria musical', 'Bateria']
 */
function especialidades(?string $texto): array
{
    return array_values(array_filter(array_map('trim', explode(',', $texto ?? ''))));
}

/**
 * Link do WhatsApp (wa.me) com uma mensagem pronta.
 * Telefone com DDD, ex.: "(21) 98888-7777" -> https://wa.me/5521988887777?text=...
 * Devolve null se o telefone não tiver DDD + número.
 */
function linkWhatsApp(?string $telefone, string $mensagem = ''): ?string
{
    $numero = preg_replace('/\D/', '', $telefone ?? '');
    if (strlen($numero) === 10 || strlen($numero) === 11) {
        $numero = '55' . $numero;          // código do Brasil
    }
    if (strlen($numero) < 12 || strlen($numero) > 13) {
        return null;
    }
    return 'https://wa.me/' . $numero . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}
