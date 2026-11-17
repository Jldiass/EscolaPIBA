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

const FUNCOES_MAESTRO = ['Regente titular', 'Regente assistente', 'Spalla', 'Chefe de naipe', 'Preparador(a) de coro'];

/** Maestros (ativos por padrão), com o login de professor ligado, se houver. */
function maestrosOrquestra(bool $soAtivos = true): array
{
    return conectar()->query(
        'SELECT m.*, u.nome AS login_nome FROM orquestra_maestros m LEFT JOIN usuarios u ON u.id = m.usuario_id'
        . ($soAtivos ? ' WHERE m.ativo = 1' : '') . ' ORDER BY m.ativo DESC, m.id'
    )->fetchAll();
}

/**
 * Quem faz a chamada do ensaio: o professor ligado a um maestro ativo.
 * O admin cadastra membros e maestros e só consulta a chamada.
 */
function podeFazerChamadaOrquestra(): bool
{
    $usuario = usuarioAtual();
    if (!$usuario || ehAdmin()) {
        return false;
    }
    $stmt = conectar()->prepare('SELECT COUNT(*) FROM orquestra_maestros WHERE usuario_id = ? AND ativo = 1');
    $stmt->execute([$usuario['id']]);
    return (bool) $stmt->fetchColumn();
}

/** Mensagem pronta para chamar o membro, conforme o status de assiduidade. */
function mensagemContato(string $nome, string $status): string
{
    $primeiro = explode(' ', trim($nome))[0];
    return match ($status) {
        'ausente'  => "Olá, $primeiro! Aqui é da Escola de Música PIBA. Sentimos sua falta nos ensaios da orquestra. Está tudo bem? Se precisar de alguma ajuda para voltar, conte com a gente!",
        'as_vezes' => "Olá, $primeiro! Aqui é da Escola de Música PIBA. Notamos que você não tem conseguido vir a todos os ensaios da orquestra. Contamos com você no próximo ensaio!",
        default    => "Olá, $primeiro! Aqui é da Escola de Música PIBA, sobre a orquestra.",
    };
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
