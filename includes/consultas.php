<?php
/**
 * Consultas usadas em mais de uma página.
 * Ficam aqui para não repetir o mesmo SQL em vários arquivos.
 */
require_once __DIR__ . '/../config/database.php';

/**
 * Aulas (matrículas ativas) de um dia da semana.
 * Se $professorId for informado, traz só as aulas daquele professor.
 */
function aulasDoDia(int $diaSemana, ?int $professorId = null): array
{
    $sql = 'SELECT m.*, a.nome AS aluno, u.nome AS professor
            FROM matriculas m
            JOIN alunos a   ON a.id = m.aluno_id
            JOIN usuarios u ON u.id = m.professor_id
            WHERE m.ativo = 1 AND a.ativo = 1 AND m.dia_semana = :dia';
    $params = [':dia' => $diaSemana];

    if ($professorId) {
        $sql .= ' AND m.professor_id = :prof';
        $params[':prof'] = $professorId;
    }
    $sql .= ' ORDER BY m.hora_inicio';

    $stmt = conectar()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Resumo financeiro de um mês, professor por professor.
 *
 * Regra: o aluno paga a mensalidade ao professor. O professor fica
 * com uma parte e REPASSA à escola o percentual da escola
 * (configurado na tela Financeiro, padrão 30%).
 *
 *   total_mensalidades = soma das mensalidades dos alunos ativos do professor
 *   parte_escola       = total_mensalidades × percentual_escola / 100
 *   parte_professor    = total_mensalidades − parte_escola
 */
function resumoFinanceiro(string $mes): array
{
    $percentual = (float) configuracao('percentual_escola', '30');

    $stmt = conectar()->prepare(
        "SELECT u.id, u.nome,
                COUNT(m.id)                  AS qtd_alunos,
                COALESCE(SUM(m.mensalidade), 0) AS total_mensalidades,
                r.id AS repasse_id, r.valor AS valor_repassado, r.data_pagamento
         FROM usuarios u
         LEFT JOIN matriculas m ON m.professor_id = u.id AND m.ativo = 1
              AND m.aluno_id IN (SELECT id FROM alunos WHERE ativo = 1)
         LEFT JOIN repasses r   ON r.professor_id = u.id AND r.mes = :mes
         WHERE u.perfil = 'professor' AND u.ativo = 1
         GROUP BY u.id
         ORDER BY u.nome"
    );
    $stmt->execute([':mes' => $mes]);
    $linhas = $stmt->fetchAll();

    foreach ($linhas as &$linha) {
        $linha['parte_escola']    = round($linha['total_mensalidades'] * $percentual / 100, 2);
        $linha['parte_professor'] = $linha['total_mensalidades'] - $linha['parte_escola'];
        $linha['repassado']       = $linha['repasse_id'] !== null;
    }
    return $linhas;
}

/**
 * Frequência de cada matrícula: total de chamadas, presenças e %.
 * Pode filtrar por professor e por período (datas AAAA-MM-DD).
 */
function relatorioFrequencia(?int $professorId, string $inicio, string $fim): array
{
    $sql = 'SELECT m.id, a.nome AS aluno, m.instrumento, u.nome AS professor,
                   COUNT(p.id)                 AS aulas,
                   COALESCE(SUM(p.presente), 0) AS presencas
            FROM matriculas m
            JOIN alunos a   ON a.id = m.aluno_id
            JOIN usuarios u ON u.id = m.professor_id
            LEFT JOIN presencas p ON p.matricula_id = m.id AND p.data BETWEEN :inicio AND :fim
            WHERE m.ativo = 1';
    $params = [':inicio' => $inicio, ':fim' => $fim];

    if ($professorId) {
        $sql .= ' AND m.professor_id = :prof';
        $params[':prof'] = $professorId;
    }
    $sql .= ' GROUP BY m.id ORDER BY a.nome';

    $stmt = conectar()->prepare($sql);
    $stmt->execute($params);
    $linhas = $stmt->fetchAll();

    foreach ($linhas as &$linha) {
        $linha['faltas'] = $linha['aulas'] - $linha['presencas'];
        // Evita divisão por zero quando ainda não houve chamada
        $linha['percentual'] = $linha['aulas'] > 0 ? round($linha['presencas'] * 100 / $linha['aulas']) : null;
    }
    return $linhas;
}
