<?php
/**
 * Ações de matrícula (só admin, só POST):
 *   acao=criar     -> nova matrícula do aluno com um professor
 *   acao=encerrar  -> encerra (não apaga: o histórico de chamadas continua)
 *   acao=reativar  -> volta a matrícula para ativa
 *   acao=bolsa     -> dá ou tira a bolsa da igreja (aluno bolsista não paga)
 */
require __DIR__ . '/../includes/auth.php';

exigirAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verificarCsrf();

$pdo = conectar();
$alunoId = (int) ($_POST['aluno_id'] ?? 0);
$acao = $_POST['acao'] ?? '';

// O aluno precisa existir
$stmt = $pdo->prepare('SELECT COUNT(*) FROM alunos WHERE id = ?');
$stmt->execute([$alunoId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit('Aluno não encontrado.');
}
$matriculaId = (int) ($_POST['matricula_id'] ?? 0);

/** Volta para a ficha do aluno com uma mensagem. */
function voltar(int $alunoId, string $parametro): void
{
    header('Location: ver.php?id=' . $alunoId . '&' . $parametro);
    exit;
}

if ($acao === 'reativar') {
    // Antes de reativar, confere se o horário não bate com outra aula do professor
    $stmt = $pdo->prepare('SELECT * FROM matriculas WHERE id = ? AND aluno_id = ?');
    $stmt->execute([$matriculaId, $alunoId]);
    $m = $stmt->fetch();
    if ($m) {
        $stmt = $pdo->prepare(
            'SELECT a.nome FROM matriculas x JOIN alunos a ON a.id = x.aluno_id
             WHERE x.professor_id = ? AND x.dia_semana = ? AND x.ativo = 1 AND x.id <> ?
               AND x.hora_inicio < ? AND x.hora_fim > ?'
        );
        $stmt->execute([$m['professor_id'], $m['dia_semana'], $m['id'], $m['hora_fim'], $m['hora_inicio']]);
        if ($conflito = $stmt->fetchColumn()) {
            voltar($alunoId, 'erro=' . urlencode("Não dá para reativar: o professor já tem aula com $conflito nesse horário."));
        }
    }
}

if ($acao === 'encerrar' || $acao === 'reativar') {
    $pdo->prepare('UPDATE matriculas SET ativo = ? WHERE id = ? AND aluno_id = ?')
        ->execute([$acao === 'reativar' ? 1 : 0, $matriculaId, $alunoId]);
    voltar($alunoId, 'msg=' . ($acao === 'reativar' ? 'reativada' : 'encerrada'));
}

if ($acao === 'bolsa') {
    $pdo->prepare('UPDATE matriculas SET bolsista = 1 - bolsista WHERE id = ? AND aluno_id = ?')
        ->execute([$matriculaId, $alunoId]);
    voltar($alunoId, 'msg=bolsa');
}

if ($acao === 'criar') {
    // O select manda "id|especialidade": explode() separa as duas partes
    [$professorId, $instrumento] = array_pad(explode('|', $_POST['professor_especialidade'] ?? '', 2), 2, '');
    $professorId = (int) $professorId;
    $dia         = (int) ($_POST['dia_semana'] ?? 0);
    $inicio      = $_POST['hora_inicio'] ?? '';
    $fim         = $_POST['hora_fim'] ?? '';
    $mensalidade = (float) ($_POST['mensalidade'] ?? 0);
    $bolsista    = !empty($_POST['bolsista']) ? 1 : 0;

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
    if (!horaValida($inicio) || !horaValida($fim)) {
        voltar($alunoId, 'erro=' . urlencode('Informe o horário no formato HH:MM.'));
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
        'INSERT INTO matriculas (aluno_id, professor_id, instrumento, dia_semana, hora_inicio, hora_fim, mensalidade, bolsista)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$alunoId, $professorId, $instrumento, $dia, $inicio, $fim, $mensalidade, $bolsista]);
    voltar($alunoId, 'msg=matriculado');
}

http_response_code(400);
exit('Ação inválida.');
