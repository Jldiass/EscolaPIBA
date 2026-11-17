<?php
/**
 * Funções dos avisos, usadas na tela Avisos e no painel inicial.
 *
 * Três tipos:
 *   social   -> evento aberto (recital, culto especial, confraternização)
 *   interno  -> evento da equipe (reunião, ensaio, formação)
 *   lembrete -> recado que fica visível até a data "válido até" (ex.: não usar a sala 101)
 */
require_once __DIR__ . '/../config/database.php';

const TIPOS_AVISO = [
    'social'   => ['Evento social',  '🎉', 'selo-social'],
    'interno'  => ['Evento interno', '🗂', 'selo-interno'],
    'lembrete' => ['Lembrete',       '📌', 'selo-lembrete'],
];

/** Próximos eventos (de hoje em diante), em ordem de data. */
function proximosEventos(int $limite = 50): array
{
    $stmt = conectar()->prepare(
        "SELECT * FROM avisos WHERE tipo IN ('social', 'interno') AND data >= date('now', 'localtime')
         ORDER BY data, hora LIMIT ?"
    );
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

/** Lembretes que ainda valem hoje (sem "válido até" ou com data futura). */
function lembretesAtivos(): array
{
    return conectar()->query(
        "SELECT * FROM avisos WHERE tipo = 'lembrete'
           AND (valido_ate IS NULL OR valido_ate >= date('now', 'localtime'))
         ORDER BY criado_em DESC"
    )->fetchAll();
}

/** Eventos que já passaram e lembretes vencidos (histórico). */
function avisosPassados(): array
{
    return conectar()->query(
        "SELECT * FROM avisos
         WHERE (tipo IN ('social', 'interno') AND data < date('now', 'localtime'))
            OR (tipo = 'lembrete' AND valido_ate < date('now', 'localtime'))
         ORDER BY COALESCE(valido_ate, data) DESC LIMIT 30"
    )->fetchAll();
}

/** "em 5 dias", "amanhã", "hoje" */
function quandoFalta(string $data): string
{
    $dias = (int) (new DateTime(date('Y-m-d')))->diff(new DateTime($data))->format('%r%a');
    return match (true) {
        $dias === 0 => 'hoje',
        $dias === 1 => 'amanhã',
        $dias > 1   => "em $dias dias",
        default     => 'já passou',
    };
}
