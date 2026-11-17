-- =============================================================
-- Música PIBA - Estrutura do banco de dados (SQLite)
--
--   usuarios    -> quem entra no sistema (administradores e professores)
--   alunos      -> dados pessoais dos alunos
--   matriculas  -> liga ALUNO + PROFESSOR + instrumento, com o
--                  horário semanal da aula e o valor da mensalidade
--   presencas   -> chamada: se o aluno veio ou faltou em cada aula
--   repasses    -> valor que cada professor repassou à escola no mês
--   configuracoes -> parâmetros da escola (ex.: % da escola)
--   avisos      -> eventos sociais, eventos internos e lembretes
--   pagamentos  -> mensalidade do aluno: o professor informa se pagou no mês
--   orquestra_* -> membros da orquestra, ensaios e presença (assiduidade)
-- =============================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nome        TEXT    NOT NULL,
    login       TEXT    NOT NULL UNIQUE,
    senha_hash  TEXT    NOT NULL,                 -- nunca guardamos a senha pura
    perfil      TEXT    NOT NULL CHECK (perfil IN ('admin', 'professor')), -- admin = secretaria/administração
    telefone    TEXT,
    instrumentos TEXT,                            -- o que o professor ensina
    ativo       INTEGER NOT NULL DEFAULT 1,
    criado_em   TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS alunos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    nome            TEXT    NOT NULL,
    data_nascimento TEXT,              -- formato AAAA-MM-DD
    responsavel     TEXT,              -- obrigatório para menores de idade
    telefone        TEXT,
    instrumento     TEXT,              -- instrumento principal
    ativo           INTEGER NOT NULL DEFAULT 1,
    criado_em       TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS matriculas (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    aluno_id      INTEGER NOT NULL REFERENCES alunos(id) ON DELETE CASCADE,
    professor_id  INTEGER NOT NULL REFERENCES usuarios(id),
    instrumento   TEXT    NOT NULL,
    dia_semana    INTEGER NOT NULL CHECK (dia_semana BETWEEN 1 AND 7), -- 1 = segunda ... 7 = domingo
    hora_inicio   TEXT    NOT NULL,   -- 'HH:MM'
    hora_fim      TEXT    NOT NULL,
    mensalidade   REAL    NOT NULL DEFAULT 0,  -- valor mensal (0 = gratuita: teoria e orquestra, bancadas pela igreja)
    bolsista      INTEGER NOT NULL DEFAULT 0,  -- 1 = aula de instrumento com bolsa da igreja (não paga)
    ativo         INTEGER NOT NULL DEFAULT 1,
    criado_em     TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS presencas (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    matricula_id  INTEGER NOT NULL REFERENCES matriculas(id) ON DELETE CASCADE,
    data          TEXT    NOT NULL,   -- dia da aula, AAAA-MM-DD
    presente      INTEGER NOT NULL,   -- 1 = presente, 0 = faltou
    observacao    TEXT,
    UNIQUE (matricula_id, data)       -- uma chamada por aula
);

CREATE TABLE IF NOT EXISTS repasses (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    professor_id  INTEGER NOT NULL REFERENCES usuarios(id),
    mes           TEXT    NOT NULL,   -- 'AAAA-MM'
    valor         REAL    NOT NULL,   -- quanto o professor repassou à escola
    data_pagamento TEXT   NOT NULL,
    observacao    TEXT,
    registrado_por INTEGER REFERENCES usuarios(id),
    UNIQUE (professor_id, mes)
);

CREATE TABLE IF NOT EXISTS configuracoes (
    chave  TEXT PRIMARY KEY,
    valor  TEXT NOT NULL
);

-- Avisos: eventos (sociais e internos) e lembretes, cadastrados pelo admin
CREATE TABLE IF NOT EXISTS avisos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo        TEXT    NOT NULL CHECK (tipo IN ('social', 'interno', 'lembrete')),
    titulo      TEXT    NOT NULL,
    descricao   TEXT,
    data        TEXT,              -- dia do evento, ou início do lembrete (AAAA-MM-DD)
    hora        TEXT,              -- opcional, 'HH:MM'
    local       TEXT,              -- opcional
    valido_ate  TEXT,              -- lembrete: até quando aparece (vazio = até ser apagado)
    criado_por  INTEGER REFERENCES usuarios(id),
    criado_em   TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- Mensalidades: o professor informa, mês a mês, se cada aluno pagou ou não
CREATE TABLE IF NOT EXISTS pagamentos (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    matricula_id  INTEGER NOT NULL REFERENCES matriculas(id) ON DELETE CASCADE,
    mes           TEXT    NOT NULL,   -- 'AAAA-MM'
    pago          INTEGER NOT NULL,   -- 1 = pagou, 0 = não pagou
    observacao    TEXT,
    informado_em  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
    UNIQUE (matricula_id, mes)        -- uma informação por aluno por mês
);

-- Orquestra: membros (alunos da escola ou não), ensaios e presença em cada ensaio
CREATE TABLE IF NOT EXISTS orquestra_membros (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nome        TEXT    NOT NULL,
    naipe       TEXT    NOT NULL,     -- Cordas, Madeiras, Metais, Percussão...
    instrumento TEXT    NOT NULL,
    telefone    TEXT,
    aluno_id    INTEGER REFERENCES alunos(id) ON DELETE SET NULL,  -- se também é aluno da escola
    ativo       INTEGER NOT NULL DEFAULT 1,
    criado_em   TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS orquestra_ensaios (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    data        TEXT    NOT NULL UNIQUE,  -- um ensaio por dia
    observacao  TEXT
);

CREATE TABLE IF NOT EXISTS orquestra_presencas (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ensaio_id   INTEGER NOT NULL REFERENCES orquestra_ensaios(id) ON DELETE CASCADE,
    membro_id   INTEGER NOT NULL REFERENCES orquestra_membros(id) ON DELETE CASCADE,
    presente    INTEGER NOT NULL,         -- 1 = veio, 0 = faltou
    UNIQUE (ensaio_id, membro_id)
);
