<?php
/**
 * Exclui um aluno. Só aceita POST (vindo do botão Excluir da lista).
 */
require __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use o botão Excluir da lista de alunos.');
}

$id = (int) ($_POST['id'] ?? 0);

$stmt = conectar()->prepare('DELETE FROM alunos WHERE id = ?');
$stmt->execute([$id]);

header('Location: index.php?msg=excluido');
exit;
