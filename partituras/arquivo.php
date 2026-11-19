<?php
/**
 * Abre (ou baixa, com &baixar=1) o PDF de uma partitura. Só para quem está logado.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/partituras.php';

exigirLogin();

$stmt = conectar()->prepare('SELECT titulo, arquivo FROM partituras WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$p = $stmt->fetch();
$caminho = $p ? pastaArquivosPartituras() . '/' . basename($p['arquivo']) : null;

if (!$p || !is_file($caminho)) {
    http_response_code(404);
    exit('Partitura não encontrada.');
}

$nomeDownload = preg_replace('/[^A-Za-z0-9 _.-]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $p['titulo'])) ?: 'partitura';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: ' . (isset($_GET['baixar']) ? 'attachment' : 'inline') . '; filename="' . trim($nomeDownload) . '.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($caminho);
