<?php
/**
 * Consultas usadas em mais de uma página.
 * Ficam aqui para não repetir o mesmo SQL em vários arquivos.
 */
require_once __DIR__ . '/../config/database.php';

/**
 * Quais aulas são cobradas. Teoria musical e prática de orquestra são
 * bancadas pela igreja (mensalidade 0), e o aluno de instrumento pode
 * ter bolsa da igreja. Essas aulas ficam fora da cobrança, do repasse
 * e da inadimplência.
 */
const AULA_COBRADA = 'm.mensalidade > 0 AND m.bolsista = 0';

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
 *                        (só aulas cobradas: sem bolsistas e sem aulas gratuitas)
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
         LEFT JOIN matriculas m ON m.professor_id = u.id AND m.ativo = 1 AND " . AULA_COBRADA . "
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

/**
 * Próximas aulas a partir de agora, olhando os próximos 7 dias.
 * Cada linha ganha a chave 'data' (AAAA-MM-DD) do dia em que a aula vai acontecer.
 */
function proximasAulas(?int $professorId = null, int $limite = 8): array
{
    $agora = date('H:i');
    $lista = [];
    for ($d = 0; $d < 7 && count($lista) < $limite; $d++) {
        $dia = new DateTime("+$d day");
        foreach (aulasDoDia((int) $dia->format('N'), $professorId) as $aula) {
            if ($d === 0 && $aula['hora_fim'] <= $agora) {
                continue;               // aula de hoje que já terminou
            }
            $aula['data'] = $dia->format('Y-m-d');
            $lista[] = $aula;
            if (count($lista) === $limite) {
                break;
            }
        }
    }
    return $lista;
}

/**
 * Alunos que o professor informou que NÃO pagaram a mensalidade.
 * Junta os meses em aberto de cada matrícula e soma o valor devido.
 */
function alunosInadimplentes(?int $professorId = null): array
{
    $sql = "SELECT m.id, a.id AS aluno_id, a.nome AS aluno, m.instrumento, m.mensalidade, u.nome AS professor,
                   GROUP_CONCAT(g.mes) AS meses, COUNT(g.id) AS qtd_meses
            FROM pagamentos g
            JOIN matriculas m ON m.id = g.matricula_id
            JOIN alunos a     ON a.id = m.aluno_id
            JOIN usuarios u   ON u.id = m.professor_id
            WHERE g.pago = 0 AND m.ativo = 1 AND a.ativo = 1 AND " . AULA_COBRADA;
    $params = [];
    if ($professorId) {
        $sql .= ' AND m.professor_id = ?';
        $params[] = $professorId;
    }
    $sql .= ' GROUP BY m.id ORDER BY qtd_meses DESC, a.nome';

    $stmt = conectar()->prepare($sql);
    $stmt->execute($params);
    $linhas = $stmt->fetchAll();
    foreach ($linhas as &$linha) {
        $meses = explode(',', $linha['meses']);
        sort($meses);
        $linha['meses'] = $meses;
        $linha['total'] = $linha['qtd_meses'] * $linha['mensalidade'];
    }
    return $linhas;
}

/**
 * Professores que ainda não repassaram a parte da escola.
 * Olha o mês passado (já fechado) e o mês atual.
 */
function repassesPendentes(): array
{
    $pendentes = [];
    foreach ([date('Y-m', strtotime('first day of last month')), date('Y-m')] as $mes) {
        foreach (resumoFinanceiro($mes) as $linha) {
            if (!$linha['repassado'] && $linha['parte_escola'] > 0) {
                $linha['mes'] = $mes;
                $pendentes[] = $linha;
            }
        }
    }
    return $pendentes;
}

/** Quantos alunos ativos ainda estão sem informação de pagamento no mês. */
function mensalidadesSemInformacao(string $mes, ?int $professorId = null): int
{
    $sql = 'SELECT COUNT(*) FROM matriculas m JOIN alunos a ON a.id = m.aluno_id
            LEFT JOIN pagamentos g ON g.matricula_id = m.id AND g.mes = ?
            WHERE m.ativo = 1 AND a.ativo = 1 AND g.id IS NULL AND ' . AULA_COBRADA;
    $params = [$mes];
    if ($professorId) {
        $sql .= ' AND m.professor_id = ?';
        $params[] = $professorId;
    }
    $stmt = conectar()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}
