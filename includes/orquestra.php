<?php
/**
 * Funções da Orquestra.
 *
 * Assiduidade de cada membro = presenças nos últimos ENSAIOS_STATUS ensaios
 * (contando só os ensaios desde que a pessoa entrou na orquestra).
 *   75% ou mais  -> Frequente
 *   40% a 74%    -> De vez em quando
 *   menos de 40% -> Ausente
 *   sem ensaio   -> Novo
 */
require_once __DIR__ . '/../config/database.php';

const ENSAIOS_STATUS = 8;

const NAIPES = ['Cordas', 'Madeiras', 'Metais', 'Percussão', 'Teclado', 'Coro'];

/** [rótulo, classe do selo] de cada status */
const STATUS_ORQUESTRA = [
    'frequente' => ['Frequente',        'selo-ativo'],
    'as_vezes'  => ['De vez em quando', 'selo-pendente'],
    'ausente'   => ['Ausente',          'selo-falta'],
    'novo'      => ['Novo',             'selo-inativo'],
];

/** Id do professor que rege a orquestra (é quem faz a chamada dos ensaios). */
function regenteOrquestra(): ?int
{
    $id = (int) configuracao('regente_orquestra', '0');
    return $id ?: null;
}

/** Só o regente marca a chamada do ensaio; o admin cadastra membros e consulta. */
function podeFazerChamadaOrquestra(): bool
{
    $usuario = usuarioAtual();
    return $usuario && !ehAdmin() && (int) $usuario['id'] === regenteOrquestra();
}

/** Classifica um percentual de presença. */
function statusAssiduidade(?int $percentual): string
{
    return match (true) {
        $percentual === null => 'novo',
        $percentual >= 75    => 'frequente',
        $percentual >= 40    => 'as_vezes',
        default              => 'ausente',
    };
}

/**
 * Membros com a assiduidade calculada.
 * Cada membro ganha: 'historico' (lista dos últimos ensaios: 1, 0 ou null),
 * 'ensaios', 'presencas', 'percentual' e 'status'.
 */
function membrosOrquestra(bool $soAtivos = true): array
{
    $pdo = conectar();
    $ensaios = $pdo->query('SELECT id, data FROM orquestra_ensaios ORDER BY data DESC LIMIT ' . ENSAIOS_STATUS)->fetchAll();
    $ensaios = array_reverse($ensaios);   // do mais antigo para o mais novo

    $membros = $pdo->query(
        'SELECT o.*, a.nome AS aluno FROM orquestra_membros o LEFT JOIN alunos a ON a.id = o.aluno_id'
        . ($soAtivos ? ' WHERE o.ativo = 1' : '') . ' ORDER BY o.naipe, o.nome'
    )->fetchAll();

    // presença[membro][ensaio] = 1 ou 0
    $presenca = [];
    if ($ensaios) {
        $ids = implode(',', array_map('intval', array_column($ensaios, 'id')));
        foreach ($pdo->query("SELECT ensaio_id, membro_id, presente FROM orquestra_presencas WHERE ensaio_id IN ($ids)") as $p) {
            $presenca[$p['membro_id']][$p['ensaio_id']] = (int) $p['presente'];
        }
    }

    foreach ($membros as &$m) {
        $m['historico'] = [];
        $total = $veio = 0;
        foreach ($ensaios as $en) {
            // Ensaio antes de a pessoa entrar não conta
            if ($en['data'] < substr($m['criado_em'], 0, 10)) {
                $m['historico'][] = ['data' => $en['data'], 'presente' => null];
                continue;
            }
            $valor = $presenca[$m['id']][$en['id']] ?? null;
            $m['historico'][] = ['data' => $en['data'], 'presente' => $valor];
            if ($valor !== null) {
                $total++;
                $veio += $valor;
            }
        }
        $m['ensaios']    = $total;
        $m['presencas']  = $veio;
        $m['percentual'] = $total ? (int) round($veio * 100 / $total) : null;
        $m['status']     = statusAssiduidade($m['percentual']);
    }
    return $membros;
}
