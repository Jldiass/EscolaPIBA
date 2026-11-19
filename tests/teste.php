<?php
/**
 * Teste automático do sistema inteiro.
 *
 * Sobe o servidor embutido do PHP com um banco TEMPORÁRIO (o banco de
 * verdade, database/escola.sqlite, não é tocado), entra com cada perfil,
 * abre todas as telas e testa as regras principais e a segurança.
 *
 * Como rodar, na pasta do projeto:
 *     php tests/teste.php
 *
 * No fim mostra quantas verificações passaram. Se alguma falhar, o
 * comando termina com erro (código 1), o que faz o GitHub marcar ✗.
 */

$raiz  = dirname(__DIR__);
$porta = 8765;
$base  = "http://127.0.0.1:$porta";
$tmp   = sys_get_temp_dir() . '/escolapiba-teste-' . getmypid();
@mkdir($tmp, 0777, true);
$log   = "$tmp/servidor.log";

// ---------------------------------------------------------------- servidor
$env = array_merge(getenv(), ['ESCOLA_DB' => "$tmp/escola.sqlite"]);
$servidor = proc_open(
    [PHP_BINARY, '-d', 'display_errors=1', '-S', "127.0.0.1:$porta", 'router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
    $pipes, $raiz, $env
);
register_shutdown_function(function () use ($servidor, $tmp) {
    proc_terminate($servidor);
    foreach (glob("$tmp/partituras/*") ?: [] as $f) { @unlink($f); }
    @rmdir("$tmp/partituras");
    foreach (glob("$tmp/*") ?: [] as $f) { @unlink($f); }
    @rmdir($tmp);
});
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $porta); $i++) {
    usleep(100000);
}

// ---------------------------------------------------------------- ajudantes
$ok = 0;
$falhas = [];

function confere(bool $condicao, string $descricao): void
{
    global $ok, $falhas;
    if ($condicao) {
        $ok++;
    } else {
        $falhas[] = $descricao;
        echo "  ✗ $descricao\n";
    }
}

/** Faz uma requisição HTTP. Devolve [código, corpo, cabeçalhos]. Guarda o cookie da sessão em $sessao. */
function pedir(string $metodo, string $caminho, ?array $dados = null, ?string &$sessao = null): array
{
    global $base;
    $cabecalhos = $sessao ? "Cookie: $sessao\r\n" : '';
    $corpo = '';
    if ($dados !== null) {
        $corpo = http_build_query($dados);
        $cabecalhos .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $metodo, 'header' => $cabecalhos, 'content' => $corpo,
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20,
    ]]);
    $resposta = @file_get_contents($base . '/' . ltrim($caminho, '/'), false, $ctx);
    $headers = $http_response_header ?? [];
    preg_match('#HTTP/\S+ (\d{3})#', $headers[0] ?? '', $m);
    foreach ($headers as $h) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $h, $c)) {
            $sessao = $c[1];
        }
    }
    return [(int) ($m[1] ?? 0), (string) $resposta, $headers];
}

function entrar(string $login): string
{
    $sessao = null;
    pedir('GET', 'login.php', null, $sessao);
    [$codigo, , $headers] = pedir('POST', 'login.php', ['login' => $login, 'senha' => 'piba123'], $sessao);
    confere($codigo === 302 && in_array('Location: index.php', $headers, true), "login de $login funciona");
    return $sessao;
}

function token(string $sessao, string $pagina): string
{
    [, $html] = pedir('GET', $pagina, null, $sessao);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m);
    return $m[1] ?? '';
}

function semErroPhp(string $html): bool
{
    return !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/', $html);
}

// ---------------------------------------------------------------- 1. arquivos internos bloqueados
echo "1. Arquivos internos não abrem pelo navegador\n";
pedir('GET', 'login.php');
$admin = entrar('admin');   // cria o banco temporário e os PDFs de exemplo
$pdf = basename((string) (glob("$tmp/partituras/*.pdf")[0] ?? 'x.pdf'));
foreach (['database/escola.sqlite', 'DATABASE/escola.sqlite', 'database./escola.sqlite', "database/partituras/$pdf",
          'sql/schema.sql', 'sql/dados_ficticios.php', 'includes/auth.php', 'config/database.php',
          '.git/config', '.gitignore', 'router.php', 'tests/teste.php', 'README.md'] as $arquivo) {
    [$codigo] = pedir('GET', $arquivo);
    confere($codigo === 404, "bloqueia /$arquivo (veio $codigo)");
}
foreach (['assets/css/style.css', 'assets/js/app.js', 'assets/js/vendor/three.min.js', 'assets/img/logo-piba.png'] as $arquivo) {
    [$codigo] = pedir('GET', $arquivo);
    confere($codigo === 200, "entrega /$arquivo");
}

// ---------------------------------------------------------------- 2. login
echo "2. Login\n";
[$codigo, $html] = pedir('POST', 'login.php', ['login' => 'admin', 'senha' => 'errada']);
confere($codigo === 200 && str_contains($html, 'Login ou senha incorretos'), 'senha errada é recusada');
[$codigo, , $headers] = pedir('GET', 'index.php');
confere($codigo === 302, 'sem login, o painel manda para o login');
$sessaoCookie = implode("\n", array_filter(pedir('GET', 'login.php')[2], fn($h) => stripos($h, 'Set-Cookie') === 0));
confere($sessaoCookie === '' || stripos($sessaoCookie, 'httponly') !== false, 'cookie da sessão é HttpOnly');

// ---------------------------------------------------------------- 3. todas as telas, nos dois perfis
echo "3. Todas as telas abrem sem erro\n";
$telas = ['index.php', 'alunos/index.php', 'alunos/ver.php?id=1', 'avisos/index.php', 'agenda.php', 'agenda.php?semana=-1',
          'agenda.php?semana=1', 'chamada/index.php', 'chamada/frequencia.php', 'orquestra/index.php', 'orquestra/maestros.php',
          'orquestra/chamada.php', 'partituras/index.php', 'financeiro/mensalidades.php'];
$soAdmin = ['alunos/form.php', 'alunos/form.php?id=1', 'professores/index.php', 'professores/form.php', 'professores/form.php?id=2',
            'avisos/form.php', 'avisos/form.php?tipo=lembrete', 'orquestra/membro.php', 'financeiro/index.php',
            'financeiro/repasses.php', 'financeiro/index.php?mes=2026-01'];
foreach (array_merge($telas, $soAdmin) as $tela) {
    [$codigo, $html] = pedir('GET', $tela, null, $admin);
    confere($codigo === 200 && semErroPhp($html), "admin abre $tela ($codigo)");
}
$thiago = entrar('thiago');
foreach (array_diff($telas, ['alunos/ver.php?id=1']) as $tela) {
    [$codigo, $html] = pedir('GET', $tela, null, $thiago);
    confere($codigo === 200 && semErroPhp($html), "professor abre $tela ($codigo)");
}
foreach (array_diff($soAdmin, ['financeiro/index.php', 'financeiro/index.php?mes=2026-01']) as $tela) {
    [$codigo] = pedir('GET', $tela, null, $thiago);
    confere($codigo === 403, "professor é barrado em $tela ($codigo)");
}

// ---------------------------------------------------------------- 4. alunos e matrícula
echo "4. Alunos e matrícula\n";
$t = token($admin, 'alunos/form.php');
[$codigo, $html] = pedir('POST', 'alunos/form.php', ['csrf' => $t, 'nome' => 'Aluno Teste', 'instrumento' => 'Violino', 'data_nascimento' => '2026-02-31'], $admin);
confere($codigo === 200 && str_contains($html, 'Data de nascimento inválida'), 'recusa nascimento 31/02');
[$codigo, $html] = pedir('POST', 'alunos/form.php', ['csrf' => $t, 'nome' => 'Aluno Teste', 'instrumento' => 'Violino', 'data_nascimento' => '2015-05-10'], $admin);
confere($codigo === 200 && str_contains($html, 'informe o responsável'), 'menor de idade exige responsável');
[$codigo, , $headers] = pedir('POST', 'alunos/form.php', ['csrf' => $t, 'nome' => 'Aluno Teste', 'instrumento' => 'Violino', 'data_nascimento' => '2000-05-10', 'ativo' => '1'], $admin);
$novoId = 0;
foreach ($headers as $h) {
    if (preg_match('/Location: ver\.php\?id=(\d+)/', $h, $m)) { $novoId = (int) $m[1]; }
}
confere($codigo === 302 && $novoId > 0, 'cadastra aluno');

$pdo = new PDO('sqlite:' . "$tmp/escola.sqlite");
$thiagoId = (int) $pdo->query("SELECT id FROM usuarios WHERE login = 'thiago'")->fetchColumn();
$aulaThiago = $pdo->query("SELECT * FROM matriculas WHERE professor_id = $thiagoId AND ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$matricula = fn(array $extra) => pedir('POST', 'alunos/matricula.php', $extra + ['csrf' => $t, 'acao' => 'criar', 'aluno_id' => $novoId,
    'professor_especialidade' => "$thiagoId|Violino", 'dia_semana' => 7, 'hora_inicio' => '08:00', 'hora_fim' => '09:00', 'mensalidade' => '80'], $admin);
$destino = fn(array $r) => implode(' ', array_filter($r[2], fn($h) => str_starts_with($h, 'Location')));

confere(str_contains($destino($matricula(['hora_inicio' => 'zz'])), 'erro='), 'recusa horário inválido');
confere(str_contains($destino($matricula(['hora_inicio' => '10:00'])), 'erro='), 'recusa fim antes do início');
confere(str_contains($destino($matricula(['professor_especialidade' => "$thiagoId|Bateria"])), 'erro='), 'recusa especialidade que o professor não ensina');
confere(str_contains($destino($matricula(['dia_semana' => $aulaThiago['dia_semana'], 'hora_inicio' => $aulaThiago['hora_inicio'], 'hora_fim' => $aulaThiago['hora_fim']])), 'Conflito'), 'bloqueia conflito de horário');
confere(str_contains($destino($matricula(['aluno_id' => 99999])), '') && $matricula(['aluno_id' => 99999])[0] === 404, 'aluno inexistente dá 404 (não erro 500)');
confere(str_contains($destino($matricula([])), 'msg=matriculado'), 'matricula no domingo 08:00');
$novaMatricula = (int) $pdo->query("SELECT MAX(id) FROM matriculas")->fetchColumn();

// encerra, cria outra no mesmo horário e tenta reativar a primeira: tem que barrar
pedir('POST', 'alunos/matricula.php', ['csrf' => $t, 'acao' => 'encerrar', 'aluno_id' => $novoId, 'matricula_id' => $novaMatricula], $admin);
confere(str_contains($destino($matricula([])), 'msg=matriculado'), 'horário liberado depois de encerrar');
$r = pedir('POST', 'alunos/matricula.php', ['csrf' => $t, 'acao' => 'reativar', 'aluno_id' => $novoId, 'matricula_id' => $novaMatricula], $admin);
confere(str_contains($destino($r), 'reativar'), 'não reativa matrícula que bate com outra aula');

[$codigo, $html] = pedir('GET', "alunos/ver.php?id=$novoId", null, $thiago);
confere($codigo === 200, 'professor vê o aluno novo dele');
$elias = entrar('elias');
[$codigo] = pedir('GET', "alunos/ver.php?id=$novoId", null, $elias);
confere($codigo === 403, 'outro professor não vê o aluno');

// ---------------------------------------------------------------- 5. chamada
echo "5. Chamada\n";
$domingo = date('Y-m-d', strtotime('last sunday', strtotime('tomorrow')));
$mId = (int) $pdo->query("SELECT id FROM matriculas WHERE aluno_id = $novoId AND ativo = 1")->fetchColumn();
$tp = token($thiago, "chamada/index.php?data=$domingo");
[$codigo] = pedir('POST', "chamada/index.php?data=$domingo", ['csrf' => $tp, 'presenca' => [$mId => '1']], $thiago);
confere($codigo === 302 && (int) $pdo->query("SELECT presente FROM presencas WHERE matricula_id = $mId")->fetchColumn() === 1, 'professor marca presença');
$te = token($elias, "chamada/index.php?data=$domingo");
pedir('POST', "chamada/index.php?data=$domingo", ['csrf' => $te, 'presenca' => [$mId => '0']], $elias);
confere((int) $pdo->query("SELECT presente FROM presencas WHERE matricula_id = $mId")->fetchColumn() === 1, 'outro professor não altera a chamada');
[$codigo] = pedir('POST', "chamada/index.php?data=$domingo", ['csrf' => token($admin, 'chamada/index.php'), 'presenca' => [$mId => '0']], $admin);
confere($codigo === 403, 'admin só consulta a chamada');
[$codigo] = pedir('POST', "chamada/index.php?data=$domingo", ['presenca' => [$mId => '0']], $thiago);
confere($codigo === 400, 'sem código CSRF o formulário é recusado');
[$codigo, $html] = pedir('GET', 'chamada/index.php?data=2026-02-31', null, $thiago);
confere($codigo === 200 && semErroPhp($html), 'data inexistente volta para hoje');

// ---------------------------------------------------------------- 6. financeiro
echo "6. Financeiro\n";
$mes = date('Y-m');
$ta = token($admin, 'financeiro/repasses.php');
[$codigo, , $h] = pedir('POST', "financeiro/repasses.php?mes=$mes", ['csrf' => $ta, 'acao' => 'repassar', 'professor_id' => $thiagoId, 'valor' => '50'], $admin);
confere((float) $pdo->query("SELECT valor FROM repasses WHERE professor_id = $thiagoId AND mes = '$mes'")->fetchColumn() === 50.0, 'registra repasse');
pedir('POST', "financeiro/repasses.php?mes=$mes", ['csrf' => $ta, 'acao' => 'desfazer', 'professor_id' => $thiagoId], $admin);
confere(!$pdo->query("SELECT COUNT(*) FROM repasses WHERE professor_id = $thiagoId AND mes = '$mes'")->fetchColumn(), 'desfaz repasse');
[$codigo] = pedir('POST', "financeiro/repasses.php?mes=$mes", ['csrf' => $ta, 'acao' => 'repassar', 'professor_id' => 99999, 'valor' => '50'], $admin);
confere($codigo === 302, 'professor inexistente não derruba a página');
[$codigo, $html] = pedir('GET', 'financeiro/repasses.php?msg=' . urlencode('Recado falso'), null, $admin);
confere(!str_contains($html, 'Recado falso'), 'mensagem da URL não aparece na tela');
[$codigo, $html] = pedir('GET', 'financeiro/repasses.php?mes=2026-13', null, $admin);
confere($codigo === 200 && semErroPhp($html), 'mês 13 volta para o mês atual');
pedir('POST', "financeiro/repasses.php?mes=$mes", ['csrf' => $ta, 'acao' => 'percentual', 'percentual' => '25'], $admin);
confere(str_contains(pedir('GET', 'financeiro/repasses.php', null, $admin)[1], '25%'), 'altera o percentual da escola');

$tm = token($thiago, 'financeiro/mensalidades.php');
pedir('POST', 'financeiro/mensalidades.php', ['csrf' => $tm, 'pago' => [$mId => '0']], $thiago);
confere((int) $pdo->query("SELECT pago FROM pagamentos WHERE matricula_id = $mId AND mes = '$mes'")->fetchColumn() === 0, 'professor informa mensalidade não paga');
confere(str_contains(pedir('GET', 'index.php', null, $admin)[1], 'Aluno Teste'), 'painel do admin mostra o aluno que não pagou');

// ---------------------------------------------------------------- 7. usuários
echo "7. Usuários\n";
$tu = token($admin, 'professores/form.php');
[$codigo, $html] = pedir('POST', 'professores/form.php', ['csrf' => $tu, 'nome' => 'Outro', 'login' => 'thiago', 'perfil' => 'professor', 'instrumentos' => 'Piano', 'senha' => '123456'], $admin);
confere(str_contains($html, 'já está em uso'), 'login repetido é recusado');
[$codigo] = pedir('POST', 'professores/form.php', ['csrf' => $tu, 'nome' => 'Prof Novo', 'login' => 'novo', 'perfil' => 'professor', 'instrumentos' => 'Piano', 'senha' => '123456', 'ativo' => '1'], $admin);
confere($codigo === 302, 'cadastra professor novo');
$adminId = (int) $pdo->query("SELECT id FROM usuarios WHERE login = 'admin'")->fetchColumn();
[, $html] = pedir('POST', "professores/form.php?id=$adminId", ['csrf' => $tu, 'nome' => 'Administração', 'login' => 'admin', 'perfil' => 'professor', 'instrumentos' => 'x', 'ativo' => '1'], $admin);
confere(str_contains($html, 'próprio acesso'), 'admin não tira o próprio acesso');

// ---------------------------------------------------------------- 8. dados estranhos não derrubam o sistema
echo "8. Dados estranhos\n";
foreach (['alunos/index.php?busca[]=1', 'chamada/index.php?data[]=1', 'chamada/frequencia.php?inicio[]=1',
          'financeiro/index.php?mes[]=1', 'orquestra/chamada.php?data[]=1', 'partituras/index.php?nivel[]=1',
          'agenda.php?semana=abc', 'chamada/frequencia.php?inicio=2026-12-01&fim=2026-01-01'] as $tela) {
    [$codigo, $html] = pedir('GET', $tela, null, $admin);
    confere(semErroPhp($html) && !str_contains($html, '/home/') && !str_contains($html, 'Stack trace'), "não mostra erro interno em $tela");
}
[$codigo, $html] = pedir('POST', 'login.php', ['login' => ['a'], 'senha' => 'b']);
confere(semErroPhp($html), 'login com dado estranho não mostra erro interno');
[$codigo, $html] = pedir('POST', 'avisos/form.php', ['csrf' => ['x']], $admin);
confere($codigo === 400, 'CSRF estranho é recusado');

// ---------------------------------------------------------------- 9. log do servidor
$logTexto = (string) file_get_contents($log);
preg_match_all('/PHP (Warning|Notice|Deprecated):.*$/m', $logTexto, $avisos);
confere(!$avisos[0], 'nenhum aviso do PHP no log' . ($avisos[0] ? ":\n    " . implode("\n    ", array_unique($avisos[0])) : ''));

// ---------------------------------------------------------------- resultado
$total = $ok + count($falhas);
echo "\n" . ($falhas ? '✗' : '✓') . " $ok de $total verificações passaram.\n";
exit($falhas ? 1 : 0);
