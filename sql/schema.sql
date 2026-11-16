-- =============================================================
-- Música PIBA - Estrutura do banco de dados (SQLite)
-- Etapa 1: tabela de alunos
-- As próximas etapas vão acrescentar turmas, frequência e notas.
-- =============================================================

CREATE TABLE IF NOT EXISTS alunos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    nome            TEXT    NOT NULL,
    data_nascimento TEXT,              -- formato AAAA-MM-DD
    responsavel     TEXT,              -- obrigatório na prática para menores de idade
    telefone        TEXT,
    instrumento     TEXT,              -- instrumento que o aluno estuda
    ativo           INTEGER NOT NULL DEFAULT 1,  -- 1 = ativo, 0 = inativo
    criado_em       TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);
