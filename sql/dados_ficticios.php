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

    // [nome, nascimento, responsável, telefone, instrumento, professor, dia, início, fim, mensalidade, bolsista]
    // Teoria musical é bancada pela igreja (mensalidade 0). O Pedro tem bolsa da igreja na viola.
    $alunos = [
        ['Ana Beatriz Souza',    '2012-03-14', 'Carla Souza',    '(00) 90000-0001', 'Violino',        $thiago,  1, '14:00', '15:00', 80],
        ['Lucas Oliveira',       '2010-07-22', 'Marcos Oliveira','(00) 90000-0002', 'Violino',        $thiago,  3, '15:00', '16:00', 80],
        ['Mariana Lima',         '2008-11-05', null,             '(00) 90000-0003', 'Viola',          $geovana, 2, '18:00', '19:00', 80],
        ['Pedro Henrique Alves', '2013-01-30', 'Juliana Alves',  '(00) 90000-0004', 'Viola',          $geovana, 4, '14:00', '15:00', 80, 1],
        ['Sofia Martins',        '2011-09-18', 'Renata Martins', '(00) 90000-0005', 'Teoria musical', $elias,   6, '09:00', '10:00', 0],
        ['Gabriel Rocha',        '2009-05-02', 'Paulo Rocha',    '(00) 90000-0006', 'Bateria',        $elias,   6, '10:00', '11:00', 100],
        ['Isabela Ferreira',     '2006-12-11', null,             '(00) 90000-0007', 'Teoria musical', $elias,   6, '11:00', '12:00', 0],
        ['João Vitor Ramos',     '2014-08-25', 'Elaine Ramos',   '(00) 90000-0008', 'Bateria',        $elias,   5, '19:00', '20:00', 100],
    ];

    $insAluno = $pdo->prepare(
        'INSERT INTO alunos (nome, data_nascimento, responsavel, telefone, instrumento) VALUES (?, ?, ?, ?, ?)'
    );
    $insMatricula = $pdo->prepare(
        'INSERT INTO matriculas (aluno_id, professor_id, instrumento, dia_semana, hora_inicio, hora_fim, mensalidade, bolsista)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $matriculas = [];
    $insPresenca = $pdo->prepare('INSERT INTO presencas (matricula_id, data, presente) VALUES (?, ?, ?)');

    foreach ($alunos as $i => $a) {
        [$nome, $nasc, $resp, $tel, $inst, $prof, $dia, $ini, $fim, $valor] = $a;
        $bolsista = $a[10] ?? 0;
        $insAluno->execute([$nome, $nasc, $resp, $tel, $inst]);
        $insMatricula->execute([$pdo->lastInsertId(), $prof, $inst, $dia, $ini, $fim, $valor, $bolsista]);
        $matriculaId = $pdo->lastInsertId();
        $matriculas[$i] = $matriculaId;

        // Chamadas das 4 últimas semanas (algumas faltas para o relatório ficar interessante)
        for ($semana = 4; $semana >= 1; $semana--) {
            $data = new DateTime("-$semana week");
            $data->setISODate((int) $data->format('o'), (int) $data->format('W'), $dia);
            // João Vitor (índice 7) faltou nas 2 últimas semanas: aparece em alerta na frequência
            $faltou = ($i + $semana) % 5 === 0 || ($i === 7 && $semana <= 2);
            $insPresenca->execute([$matriculaId, $data->format('Y-m-d'), $faltou ? 0 : 1]);
        }
    }

    // Avisos de exemplo: um evento social, um interno e um lembrete
    $aviso = $pdo->prepare(
        'INSERT INTO avisos (tipo, titulo, descricao, data, hora, local, valido_ate, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $aviso->execute(['social', 'Recital de fim de ano', 'Apresentação dos alunos para as famílias e a comunidade.',
                     date('Y-m-d', strtotime('+18 days')), '19:00', 'Templo principal', null]);
    $aviso->execute(['interno', 'Reunião de professores', 'Planejamento do próximo semestre.',
                     date('Y-m-d', strtotime('+5 days')), '20:00', 'Sala 102', null]);
    $aviso->execute(['lembrete', 'Não usar a sala 101', 'Sala em reforma. As aulas foram para a sala 103.',
                     date('Y-m-d'), null, 'Sala 101', date('Y-m-d', strtotime('+30 days'))]);

    // A escola fica com 30% da mensalidade; o professor fica com 70%
    $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('percentual_escola', '30')");

    // Mês passado: Thiago e Geovana já repassaram, Pastor Elias ainda não (30% de 2 alunos de bateria)
    $mesPassado = (new DateTime('first day of last month'))->format('Y-m');
    $repasse = $pdo->prepare(
        'INSERT INTO repasses (professor_id, mes, valor, data_pagamento) VALUES (?, ?, ?, ?)'
    );
    $repasse->execute([$thiago,  $mesPassado, 48.0, $mesPassado . '-10']);
    $repasse->execute([$geovana, $mesPassado, 24.0, $mesPassado . '-12']);  // só a Mariana paga (o Pedro é bolsista)

    // Mensalidades informadas pelos professores (só aulas cobradas: 0, 1, 2, 5 e 7)
    //   mês passado: todos pagaram, menos o João Vitor (índice 7)
    //   este mês: Lucas (1) e João Vitor (7) não pagaram; Mariana (2) ainda sem informação
    $mesAtual = date('Y-m');
    $pagamento = $pdo->prepare('INSERT INTO pagamentos (matricula_id, mes, pago, observacao) VALUES (?, ?, ?, ?)');
    foreach ($matriculas as $i => $matriculaId) {
        if (in_array($i, [3, 4, 6], true)) {
            continue;   // bolsista e teoria gratuita: não pagam
        }
        $pagamento->execute([$matriculaId, $mesPassado, $i === 7 ? 0 : 1, $i === 7 ? 'Família pediu para pagar depois' : null]);
        if (in_array($i, [1, 7], true)) {
            $pagamento->execute([$matriculaId, $mesAtual, 0, null]);
        } elseif ($i !== 2) {
            $pagamento->execute([$matriculaId, $mesAtual, 1, null]);
        }
    }
}
