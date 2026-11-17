<?php
/**
 * Dados FICTÍCIOS para demonstração (nunca use dados reais aqui).
 *
 * É um arquivo PHP (e não .sql) porque as senhas precisam passar
 * por password_hash() antes de ir para o banco.
 *
 * Logins de teste (senha de todos: piba123):
 *   admin       -> ADMINISTRADOR: cadastra e vê tudo (alunos, professores, financeiro...)
 *   thiago      -> professor de Violino
 *   geovana     -> professora de Viola
 *   elias       -> Pastor Elias, Ministro de Música: Teoria musical e Bateria
 *
 * Os PROFESSORES são os reais da escola (só o primeiro nome).
 * Os ALUNOS são todos inventados.
 */

function inserirDadosFicticios(PDO $pdo): void
{
    $senha = password_hash('piba123', PASSWORD_DEFAULT);

    $usuario = $pdo->prepare(
        'INSERT INTO usuarios (nome, login, senha_hash, perfil, telefone, instrumentos)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $usuario->execute(['Administração', 'admin', $senha, 'admin', null, null]);
    // Professores da escola, cada um com sua(s) especialidade(s)
    $usuario->execute(['Thiago',        'thiago', $senha, 'professor', null, 'Violino']);
    $thiago = $pdo->lastInsertId();
    $usuario->execute(['Geovana',       'geovana', $senha, 'professor', null, 'Viola']);
    $geovana = $pdo->lastInsertId();
    $usuario->execute(['Pastor Elias',  'elias', $senha, 'professor', null, 'Teoria musical, Bateria']);
    $elias = $pdo->lastInsertId();

    // [nome, nascimento, responsável, telefone, instrumento, professor, dia, início, fim, mensalidade]
    $alunos = [
        ['Ana Beatriz Souza',    '2012-03-14', 'Carla Souza',    '(00) 90000-0001', 'Violino',        $thiago,  1, '14:00', '15:00', 80],
        ['Lucas Oliveira',       '2010-07-22', 'Marcos Oliveira','(00) 90000-0002', 'Violino',        $thiago,  3, '15:00', '16:00', 80],
        ['Mariana Lima',         '2008-11-05', null,             '(00) 90000-0003', 'Viola',          $geovana, 2, '18:00', '19:00', 80],
        ['Pedro Henrique Alves', '2013-01-30', 'Juliana Alves',  '(00) 90000-0004', 'Viola',          $geovana, 4, '14:00', '15:00', 80],
        ['Sofia Martins',        '2011-09-18', 'Renata Martins', '(00) 90000-0005', 'Teoria musical', $elias,   6, '09:00', '10:00', 50],
        ['Gabriel Rocha',        '2009-05-02', 'Paulo Rocha',    '(00) 90000-0006', 'Bateria',        $elias,   6, '10:00', '11:00', 100],
        ['Isabela Ferreira',     '2006-12-11', null,             '(00) 90000-0007', 'Teoria musical', $elias,   6, '11:00', '12:00', 50],
        ['João Vitor Ramos',     '2014-08-25', 'Elaine Ramos',   '(00) 90000-0008', 'Bateria',        $elias,   5, '19:00', '20:00', 100],
    ];

    $insAluno = $pdo->prepare(
        'INSERT INTO alunos (nome, data_nascimento, responsavel, telefone, instrumento) VALUES (?, ?, ?, ?, ?)'
    );
    $insMatricula = $pdo->prepare(
        'INSERT INTO matriculas (aluno_id, professor_id, instrumento, dia_semana, hora_inicio, hora_fim, mensalidade)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insPresenca = $pdo->prepare('INSERT INTO presencas (matricula_id, data, presente) VALUES (?, ?, ?)');

    foreach ($alunos as $i => [$nome, $nasc, $resp, $tel, $inst, $prof, $dia, $ini, $fim, $valor]) {
        $insAluno->execute([$nome, $nasc, $resp, $tel, $inst]);
        $insMatricula->execute([$pdo->lastInsertId(), $prof, $inst, $dia, $ini, $fim, $valor]);
        $matriculaId = $pdo->lastInsertId();

        // Chamadas das 4 últimas semanas (algumas faltas para o relatório ficar interessante)
        for ($semana = 4; $semana >= 1; $semana--) {
            $data = new DateTime("-$semana week");
            $data->setISODate((int) $data->format('o'), (int) $data->format('W'), $dia);
            // João Vitor (índice 7) faltou nas 2 últimas semanas: aparece em alerta na frequência
            $faltou = ($i + $semana) % 5 === 0 || ($i === 7 && $semana <= 2);
            $insPresenca->execute([$matriculaId, $data->format('Y-m-d'), $faltou ? 0 : 1]);
        }
    }

    // A escola fica com 30% da mensalidade; o professor fica com 70%
    $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('percentual_escola', '30')");

    // Mês passado: Thiago e Geovana já repassaram, Pastor Elias ainda não
    $mesPassado = (new DateTime('first day of last month'))->format('Y-m');
    $repasse = $pdo->prepare(
        'INSERT INTO repasses (professor_id, mes, valor, data_pagamento) VALUES (?, ?, ?, ?)'
    );
    $repasse->execute([$thiago,  $mesPassado, 48.0, $mesPassado . '-10']);
    $repasse->execute([$geovana, $mesPassado, 48.0, $mesPassado . '-12']);
}
