<?php
/**
 * Ações de matrícula (só secretaria, só POST):
 *   acao=criar     -> nova matrícula do aluno com um professor
 *   acao=encerrar  -> encerra (não apaga: o histórico de chamadas continua)
 *   acao=reativar  -> volta a matrícula para ativa
 */
require __DIR__ . '/../includes/auth.php';

exigirSecretaria();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verificarCsrf();

$pdo = conectar();
$alunoId = (int) ($_POST['aluno_id'] ?? 0);
$acao = $_POST['acao'] ?? '';

/** Volta para a ficha do aluno com uma mensagem. */
function voltar(int $alunoId, string $parametro): void
{
    header('Location: ver.php?id=' . $alunoId . '&' . $parametro);
    exit;
}

if ($acao === 'encerrar' || $acao === 'reativar') {
    $pdo->prepare('UPDATE matriculas SET ativo = ? WHERE id = ? AND aluno_id = ?')
        ->execute([$acao === 'reativar' ? 1 : 0, (int) $_POST['matricula_id'], $alunoId]);
    voltar($alunoId, 'msg=' . ($acao === 'reativar' ? 'reativada' : 'encerrada'));
}

if ($acao === 'criar') {
    // O select manda "id|especialidade": explode() separa as duas partes
    [$professorId, $instrumento] = array_pad(explode('|', $_POST['professor_especialidade'] ?? '', 2), 2, '');
    $professorId = (int) $professorId;
    $dia         = (int) ($_POST['dia_semana'] ?? 0);
    $inicio      = $_POST['hora_inicio'] ?? '';
    $fim         = $_POST['hora_fim'] ?? '';
    $mensalidade = (float) ($_POST['mensalidade'] ?? 0);

    // Validações
    if (!$professorId || $instrumento === '' || $dia < 1 || $dia > 7) {
        voltar($alunoId, 'erro=' . urlencode('Preencha todos os campos da matrícula.'));
    }
    // A especialidade precisa ser mesmo uma das do professor
    $stmt = $pdo->prepare("SELECT instrumentos FROM usuarios WHERE id = ? AND perfil = 'professor' AND ativo = 1");
    $stmt->execute([$professorId]);
    if (!in_array($instrumento, especialidades($stmt->fetchColumn() ?: ''), true)) {
        voltar($alunoId, 'erro=' . urlencode('Esse professor não ensina ' . $instrumento . '.'));
    }
    if ($fim <= $inicio) {
        voltar($alunoId, 'erro=' . urlencode('O horário de fim precisa ser depois do início.'));
    }
    if ($mensalidade < 0) {
        voltar($alunoId, 'erro=' . urlencode('Mensalidade inválida.'));
    }

    // Conflito de horário: o professor já tem aula que se sobrepõe a esta?
    // Dois horários se sobrepõem quando um começa antes do outro terminar.
    $stmt = $pdo->prepare(
        'SELECT a.nome FROM matriculas m JOIN alunos a ON a.id = m.aluno_id
         WHERE m.professor_id = ? AND m.dia_semana = ? AND m.ativo = 1
           AND m.hora_inicio < ? AND m.hora_fim > ?'
    );
    $stmt->execute([$professorId, $dia, $fim, $inicio]);
    if ($conflito = $stmt->fetchColumn()) {
        voltar($alunoId, 'erro=' . urlencode("Conflito: o professor já tem aula com $conflito nesse horário."));
    }

    $pdo->prepare(
        'INSERT INTO matriculas (aluno_id, professor_id, instrumento, dia_semana, hora_inicio, hora_fim, mensalidade)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$alunoId, $professorId, $instrumento, $dia, $inicio, $fim, $mensalidade]);
    voltar($alunoId, 'msg=matriculado');
}

http_response_code(400);
exit('Ação inválida.');
