<?php
/**
 * Funções do Banco de partituras.
 *
 * O acervo musical da escola fica organizado em pastas:
 *   - Orquestra           -> partituras da prática de orquestra
 *   - Métodos de ensino   -> os livros/métodos que os professores usam nas aulas
 *   - pastas criadas pelos usuários (ex.: Apresentação de fim de ano, Recital)
 * Cada partitura é um PDF e tem um nível: básico, intermediário ou avançado.
 *
 * Os PDFs ficam em database/partituras/ com nome aleatório e só são
 * abertos por partituras/arquivo.php, que exige login.
 */
require_once __DIR__ . '/../config/database.php';

const NIVEIS_PARTITURA = [
    'basico'        => ['Básico',        'selo-ativo'],
    'intermediario' => ['Intermediário', 'selo-pendente'],
    'avancado'      => ['Avançado',      'selo-falta'],
];

const PASTAS_PADRAO = [
    ['Orquestra',         'Partituras da prática de orquestra, separadas por naipe ou grade completa.', '🎼'],
    ['Métodos de ensino', 'Livros e métodos que os professores usam nas aulas de instrumento.',          '📘'],
];

const TAMANHO_MAX_PDF = 20 * 1024 * 1024;   // 20 MB

/** Pasta onde os PDFs ficam guardados (criada se não existir). */
function pastaArquivosPartituras(): string
{
    // Fica ao lado do arquivo do banco (database/partituras/)
    $dir = dirname(getenv('ESCOLA_DB') ?: __DIR__ . '/../database/escola.sqlite') . '/partituras';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Garante as pastas padrão (útil para bancos criados antes do acervo existir). */
function garantirPastasPadrao(PDO $pdo): void
{
    $existentes = $pdo->query('SELECT nome FROM partituras_pastas')->fetchAll(PDO::FETCH_COLUMN);
    $inserir = $pdo->prepare('INSERT INTO partituras_pastas (nome, descricao, icone, fixa) VALUES (?, ?, ?, 1)');
    foreach (PASTAS_PADRAO as [$nome, $descricao, $icone]) {
        if (!in_array($nome, $existentes, true)) {
            $inserir->execute([$nome, $descricao, $icone]);
        }
    }
}

/** Pastas com a quantidade de partituras de cada uma. */
function pastasPartituras(): array
{
    $pdo = conectar();
    garantirPastasPadrao($pdo);
    return $pdo->query(
        'SELECT p.*, COUNT(t.id) AS total FROM partituras_pastas p LEFT JOIN partituras t ON t.pasta_id = p.id
         GROUP BY p.id ORDER BY p.fixa DESC, p.nome'
    )->fetchAll();
}

/** Quem pode excluir: o admin ou quem enviou. */
function podeExcluirPartitura(array $partitura): bool
{
    $usuario = usuarioAtual();
    return $usuario && (ehAdmin() || (int) $partitura['enviado_por'] === (int) $usuario['id']);
}

/** Tamanho legível: 1,2 MB / 340 KB */
function tamanhoArquivo(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
                             : max(1, (int) round($bytes / 1024)) . ' KB';
}

/**
 * Salva um PDF enviado pelo formulário.
 * Devolve [nomeDoArquivo, tamanho] ou lança RuntimeException com a mensagem para o usuário.
 */
function salvarPdfEnviado(?array $upload): array
{
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Escolha o arquivo PDF da partitura.');
    }
    if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE || $upload['size'] > TAMANHO_MAX_PDF) {
        throw new RuntimeException('O PDF passa do limite de ' . tamanhoArquivo(TAMANHO_MAX_PDF) . '.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('Não foi possível receber o arquivo. Tente de novo.');
    }
    // Confere o conteúdo, não só a extensão: todo PDF começa com "%PDF-"
    $inicio = (string) file_get_contents($upload['tmp_name'], false, null, 0, 5);
    if ($inicio !== '%PDF-' || strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        throw new RuntimeException('O arquivo precisa ser um PDF.');
    }
    $nome = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($upload['tmp_name'], pastaArquivosPartituras() . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar o arquivo no servidor.');
    }
    return [$nome, (int) $upload['size']];
}

/**
 * Gera um PDF simples de uma página, com título e linhas de texto.
 * Usado só para os dados fictícios (o acervo de exemplo já abre com PDFs de verdade).
 */
function pdfDeExemplo(string $titulo, array $linhas): string
{
    // Fonte padrão do PDF usa Latin-1; troca acentos que ela não tem
    $txt = fn(string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'));
    $conteudo = "BT /F1 22 Tf 60 760 Td (" . $txt($titulo) . ") Tj ET\n";
    $y = 720;
    foreach ($linhas as $linha) {
        $conteudo .= "BT /F1 12 Tf 60 $y Td (" . $txt($linha) . ") Tj ET\n";
        $y -= 20;
    }
    // Cinco linhas de pauta, para parecer partitura
    for ($pauta = 0; $pauta < 6; $pauta++) {
        $base = 520 - $pauta * 70;
        for ($l = 0; $l < 5; $l++) {
            $conteudo .= '60 ' . ($base - $l * 8) . ' m 535 ' . ($base - $l * 8) . " l S\n";
        }
    }
    $objetos = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        "<< /Length " . strlen($conteudo) . " >>\nstream\n" . $conteudo . "endstream",
    ];
    $pdf = "%PDF-1.4\n";
    $posicoes = [];
    foreach ($objetos as $i => $obj) {
        $posicoes[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$obj\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    foreach ($posicoes as $p) {
        $pdf .= sprintf("%010d 00000 n \n", $p);
    }
    return $pdf . "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}
