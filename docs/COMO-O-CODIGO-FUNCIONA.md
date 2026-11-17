# Como o código funciona

Guia para entender, explicar na apresentação e modificar o sistema.

## 1. O caminho de uma página

Quando alguém abre `chamada/index.php` no navegador:

1. O navegador pede a página ao servidor PHP.
2. O PHP executa o arquivo: confere o login, consulta o banco e monta o HTML.
3. O navegador recebe **só o HTML pronto** e mostra a tela.

Ao clicar em **Salvar**, o formulário é enviado por **POST** para a mesma página. O PHP grava no banco e **redireciona** de volta (padrão *Post/Redirect/Get*). Assim, se a pessoa apertar F5, nada é salvo duas vezes.

## 2. Estrutura de pastas

```
EscolaPIBA/
├── index.php                 Painel inicial (muda conforme o perfil)
├── login.php / logout.php    Entrar e sair
├── conta.php                 Trocar a própria senha
├── agenda.php                Calendário semanal
├── alunos/
│   ├── index.php             Lista + busca + filtro por especialidade
│   ├── form.php              Cadastrar / editar aluno (secretaria)
│   ├── ver.php               Ficha do aluno + matrículas + nova matrícula
│   ├── matricula.php         Cria / encerra / reativa matrícula (só recebe POST)
│   └── excluir.php           Exclui aluno (só recebe POST)
├── professores/
│   ├── index.php             Cartões dos professores + lista de administradores
│   └── form.php              Cadastrar / editar professor ou administrador, login e senha
├── chamada/
│   ├── index.php             Chamada do dia
│   └── frequencia.php        Relatório de frequência
├── financeiro/index.php      Repasses do mês e percentual da escola
├── includes/
│   ├── auth.php              Login, sessão, permissões e proteção CSRF
│   ├── consultas.php         Consultas reutilizadas (aulas do dia, financeiro, frequência)
│   ├── header.php            Topo + menu lateral (muda conforme o perfil)
│   └── footer.php            Rodapé
├── config/database.php       Conexão SQLite + funções de apoio (e, dinheiro, nomeMes...)
├── sql/
│   ├── schema.sql            Criação das tabelas
│   └── dados_ficticios.php   Dados de demonstração
├── assets/css/style.css      Todo o visual (cores, fontes, animações)
├── assets/js/app.js          Cartões aparecendo ao rolar (IntersectionObserver)
├── assets/js/nota3d.js       Nota musical 3D do painel (Three.js)
├── assets/js/vendor/         Biblioteca Three.js (cópia local)
└── assets/img/logo-piba.png  Logo da igreja
```

## 3. Banco de dados (`config/database.php`)

`conectar()` abre o arquivo `database/escola.sqlite` com **PDO**. Se o arquivo ainda não existe, ele roda `sql/schema.sql` para criar as tabelas e `sql/dados_ficticios.php` para inserir a demonstração.

A tabela mais importante é **`matriculas`**: ela liga **aluno + professor + especialidade** e guarda o **horário semanal** e a **mensalidade**. Agenda, chamada, frequência e financeiro saem todos dela.

## 4. Login e permissões (`includes/auth.php`)

- No login, `password_verify()` compara a senha digitada com o hash salvo. Se bater, o id do usuário vai para `$_SESSION['usuario_id']`.
- Toda página começa com uma destas linhas:
  ```php
  $usuario = exigirLogin();      // qualquer pessoa logada
  exigirAdmin();                 // só o administrador
  ```
- Para o professor ver **só o que é dele**, as consultas recebem o id dele:
  ```php
  $professorId = ehAdmin() ? null : (int) $usuario['id'];
  $aulas = aulasDoDia($diaSemana, $professorId);
  ```

## 5. As consultas principais (`includes/consultas.php`)

| Função | O que devolve | Usada em |
|---|---|---|
| `aulasDoDia($dia, $professorId)` | Matrículas ativas de um dia da semana | Painel, agenda, chamada |
| `resumoFinanceiro($mes)` | Por professor: alunos, total, parte da escola, parte do professor e se já repassou | Painel, financeiro |
| `relatorioFrequencia($prof, $inicio, $fim)` | Por matrícula: aulas, presenças, faltas e % | Painel do professor, frequência |

O cálculo do financeiro, passo a passo:

```php
$linha['parte_escola']    = round($linha['total_mensalidades'] * $percentual / 100, 2);
$linha['parte_professor'] = $linha['total_mensalidades'] - $linha['parte_escola'];
$linha['repassado']       = $linha['repasse_id'] !== null;   // existe registro de repasse no mês?
```

## 6. Trechos que valem explicar na apresentação

**Conflito de horário** (`alunos/matricula.php`): duas aulas se sobrepõem quando uma começa antes de a outra terminar.
```sql
WHERE professor_id = ? AND dia_semana = ? AND ativo = 1
  AND hora_inicio < :fim_nova AND hora_fim > :inicio_nova
```

**Salvar chamada sem duplicar** (`chamada/index.php`): o "UPSERT" insere e, se já existe chamada daquela aula naquele dia, atualiza.
```sql
INSERT INTO presencas (matricula_id, data, presente, observacao) VALUES (?, ?, ?, ?)
ON CONFLICT (matricula_id, data) DO UPDATE SET presente = excluded.presente
```

**Professor + especialidade em um só campo** (`alunos/ver.php`): o select envia `"4|Bateria"` e o PHP separa com `explode('|', ...)`.

## 7. A nota musical 3D (`assets/js/nota3d.js`)

Feita com **Three.js**: uma *cena*, uma *câmera* e um *renderizador* desenham objetos 3D num `<canvas>`.

- **Cabeça:** `SphereGeometry` achatada com `scale.set(1.3, 0.95, 0.9)`.
- **Haste:** `CylinderGeometry` fino.
- **Bandeira:** `TubeGeometry` seguindo uma curva de Bézier.
- **Animação:** `requestAnimationFrame` chama `animar()` cerca de 60 vezes por segundo. A cada vez, a nota gira um pouco (`rotation.y`) e sobe/desce com `Math.sin` (efeito de flutuar).
- Se o Three.js não carregar, aparece um ♪ simples no lugar. A página nunca quebra.

## 8. Segurança

| Cuidado | Onde |
|---|---|
| Consultas preparadas (`prepare` + `execute`) | Todas as consultas com dados do usuário |
| `e()` = `htmlspecialchars` | Todo texto mostrado na tela |
| `campoCsrf()` + `verificarCsrf()` | Todos os formulários POST |
| `password_hash` / `password_verify` | Cadastro de professor e login |
| `exigirLogin()` / `exigirAdmin()` | Topo de cada página |

## 9. Exercícios para você fazer (e commitar!)

1. **Fácil:** mude o percentual padrão da escola em `sql/dados_ficticios.php` e veja o financeiro mudar (apague `database/escola.sqlite` antes de testar).
2. **Fácil:** acrescente "Canto" à lista `$instrumentos` em `alunos/form.php`.
3. **Médio:** em `chamada/frequencia.php`, troque a constante `FREQUENCIA_MINIMA` para 80 e explique o efeito.
4. **Médio:** no painel do professor (`index.php`), mostre um quarto cartão com quanto ele vai receber no mês. Dica: use `resumoFinanceiro(date('Y-m'))` e procure a linha com o `id` dele.
5. **Desafio:** em `assets/js/nota3d.js`, troque as cores da nota ou acrescente uma segunda nota girando ao lado.

Depois de cada mudança:
```
git add .
git commit -m "Descreva aqui o que você mudou"
git push
```
