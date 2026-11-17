# Documentação do Sistema – Música PIBA

## 1. Visão geral

O **Música PIBA** organiza o dia a dia da escola de música comunitária da PIBA. Antes, cadastros, horários, chamadas e o controle do dinheiro ficavam em cadernos e mensagens. Com o sistema, tudo isso fica em um só lugar:

- o **administrador** (secretaria) cadastra alunos, professores e outros administradores, faz as matrículas e acompanha o financeiro;
- cada **professor** entra com o próprio login, vê seus alunos e sua agenda da semana, faz a chamada e informa quem pagou a mensalidade.

## 2. Perfis de acesso

| Recurso | Administrador | Professor |
|---|:---:|:---:|
| Painel inicial | Próximas aulas de todos, avisos, alunos que não pagaram, professores sem repasse | Próximas aulas dele, avisos, mensalidades em aberto, frequência baixa |
| Ver alunos | Todos | Só os matriculados com ele |
| Cadastrar, editar e excluir alunos | ✅ | ❌ |
| Matricular aluno com professor | ✅ | ❌ |
| Cadastrar professores e administradores, definir logins | ✅ | ❌ |
| Definir e trocar senhas | ✅ (de todos, em Professores → Editar) | ❌ (pede ao admin) |
| Informar se o aluno pagou a mensalidade | ❌ (só consulta) | ✅ (alunos dele) |
| Agenda semanal | De todos, com filtro | A dele |
| Fazer chamada | ❌ (só consulta) | Das aulas dele |
| Consultar chamadas (presentes, faltas, chamadas não feitas) | De todos, com filtro por professor | Das aulas dele |
| Relatório de frequência | Todos | Alunos dele |
| Gestão → Financeiro (Resumo do mês, Mensalidades dos alunos, Repasses dos professores) | ✅ | Só a aba Mensalidades, com os alunos dele |
| Marcar aluno como bolsista | ✅ | ❌ |
| Ver avisos (eventos e lembretes) | ✅ | ✅ |
| Cadastrar, editar e excluir avisos | ✅ | ❌ |

## 3. Professores e especialidades

Cada professor tem uma ou mais **especialidades**. A matrícula do aluno é sempre feita em **professor + especialidade**, então o professor vê os alunos separados por área (por exemplo, "alunos de bateria" e "alunos de teoria").

| Professor | Especialidades | Login |
|---|---|---|
| Thiago | Violino | `thiago` |
| Geovana | Viola | `geovana` |
| Pastor Elias (Ministro de Música) | Teoria musical, Bateria | `elias` |

Novos professores e especialidades são cadastrados pelo administrador, em **Professores → + Novo professor**. Outros administradores (por exemplo, alguém da secretaria) entram em **Professores → + Novo administrador**.

Login inicial do administrador: **`admin`** / senha **`piba123`** (troque no primeiro acesso, em Professores → Administradores → Editar).

## 4. Requisitos

### Requisitos funcionais

| Código | Requisito |
|---|---|
| RF01 | Login com usuário e senha; dois perfis: administrador e professor |
| RF02 | Cadastrar, editar, buscar e excluir alunos (nome, nascimento, responsável, telefone, instrumento) |
| RF03 | Exigir responsável para aluno menor de 18 anos |
| RF04 | Cadastrar professores com login, senha e especialidades; desativar sem apagar o histórico |
| RF05 | Matricular o aluno com um professor + especialidade, com dia da semana, horário e mensalidade; marcar a aula como gratuita (mensalidade 0) ou o aluno como bolsista da igreja |
| RF06 | Impedir duas aulas do mesmo professor no mesmo horário |
| RF07 | Mostrar a agenda semanal: por professor e geral |
| RF08 | O professor registra a chamada (presente/faltou + observação) de cada aula dele; o administrador apenas consulta, por dia e por professor, com o resumo de presentes, faltas e chamadas não feitas |
| RF09 | Calcular a frequência de cada aluno em um período e destacar quem está abaixo de 75% |
| RF10 | Calcular, por mês, quanto cada professor recebe e quanto deve repassar à escola (só aulas cobradas) |
| RF11 | Registrar e desfazer o repasse de cada professor no mês; mostrar quem está pendente |
| RF12 | Permitir que o administrador ajuste o percentual da escola |
| RF13 | O administrador define e troca as senhas de todos os usuários; ele não pode remover o próprio acesso |
| RF14 | O administrador publica avisos: eventos sociais, eventos internos (com data, hora e local) e lembretes com validade (ex.: "não usar a sala 101"); todos os usuários veem os avisos e o painel mostra os próximos |
| RF15 | O professor informa, mês a mês, se cada aluno dele pagou ou não a mensalidade (com observação); o administrador só consulta |
| RF16 | Um painel inicial para cada perfil. Admin: próximas aulas de todos os professores, avisos e lembretes, alunos que não pagaram e professores com repasse atrasado. Professor: as próximas aulas dele, avisos, mensalidades em aberto e alunos com frequência abaixo de 75% |

### Requisitos não funcionais

| Código | Requisito |
|---|---|
| RNF01 | Funcionar no navegador do computador e do celular |
| RNF02 | Rodar em computador simples, sem servidor de banco (SQLite) |
| RNF03 | Senhas guardadas só como hash (`password_hash`) |
| RNF04 | Proteção contra SQL Injection, XSS e CSRF |
| RNF05 | Dados pessoais fora do repositório público (LGPD) |
| RNF06 | Interface em português, com identidade visual da escola |

## 5. Regras de negócio

**O que é pago e o que é gratuito.** A escola é um projeto cultural da igreja, aberto à comunidade.
- **Teoria musical** e **prática de orquestra** são **bancadas pela igreja**: a matrícula fica com mensalidade 0 ("Gratuita").
- Só as **aulas de instrumento** têm mensalidade. O aluno que consegue **bolsa da igreja** fica marcado como **Bolsista** e também não paga.
- Aulas gratuitas e de bolsistas ficam fora da cobrança, do repasse e da inadimplência. O resumo do mês mostra quantas são.

**Mensalidade e repasse.** O aluno de instrumento paga a mensalidade ao professor. O professor fica com uma parte e repassa à escola o **percentual da escola** (padrão **30%**, ajustável em Gestão → Financeiro → Repasses dos professores).

```
total do professor no mês = soma das mensalidades dos alunos ativos dele que pagam
                            (sem bolsistas e sem aulas gratuitas)
parte da escola           = total × percentual da escola
professor recebe          = total − parte da escola
```

Exemplo com o Pastor Elias: 2 alunos de bateria (R$ 100 cada) = **R$ 200**. Os 2 alunos de teoria não entram, porque a teoria é bancada pela igreja. A escola fica com 30% = **R$ 60**, e ele recebe **R$ 140**.

**Situação do repasse.** No mês escolhido, cada professor aparece como **Pendente** até o administrador clicar em **Registrar repasse**. Depois fica **Repassado**, com a data e o valor. Um registro feito por engano pode ser desfeito.

**Chamada.** Quem faz a chamada no dia a dia é o próprio professor. O administrador vê a mesma tela só para consulta: a situação de cada aluno e quantas chamadas o professor ainda não fez. Se tentar salvar, o sistema recusa (erro 403).

**Inadimplência.** São dois casos, os dois aparecem no painel do admin:
- **Aluno que não pagou:** o aluno paga a mensalidade ao professor, e o professor marca em *Mensalidades* quem pagou e quem não pagou. Aparece no painel com os meses em aberto e o valor total. Quem ainda não foi informado no mês aparece como "sem informação".
- **Professor sem repasse:** mês que já fechou e o professor não repassou a parte da escola. O mês atual ainda está no prazo e aparece só como lembrete.

**Frequência.** `frequência = presenças ÷ aulas com chamada × 100`. Abaixo de **75%** o aluno aparece com o selo "atenção", para a escola procurar a família antes que ele desista.

**Conflito de horário.** Duas aulas do mesmo professor se sobrepõem quando uma começa antes de a outra terminar. Nesse caso a matrícula é recusada.

**Avisos.** Um evento (social ou interno) aparece em "Próximos eventos" até a data dele passar; depois vai para o histórico. Um lembrete começa na data informada e fica visível até a data "válido até" (em branco = até o administrador apagar).

**Exclusões.** Matrículas e professores são **encerrados/desativados**, não apagados, para não perder o histórico de chamadas e repasses. Só o aluno pode ser excluído de vez, pelo administrador.

## 6. Banco de dados

```mermaid
erDiagram
    USUARIOS ||--o{ MATRICULAS : "ensina"
    ALUNOS   ||--o{ MATRICULAS : "estuda"
    MATRICULAS ||--o{ PRESENCAS : "tem chamadas"
    USUARIOS ||--o{ REPASSES : "repassa"
    USUARIOS ||--o{ AVISOS : "publica"
    MATRICULAS ||--o{ PAGAMENTOS : "mensalidades"

    USUARIOS {
        int id PK
        text nome
        text login UK
        text senha_hash
        text perfil "admin ou professor"
        text instrumentos "especialidades"
        int ativo
    }
    ALUNOS {
        int id PK
        text nome
        text data_nascimento
        text responsavel
        text telefone
        text instrumento
        int ativo
    }
    MATRICULAS {
        int id PK
        int aluno_id FK
        int professor_id FK
        text instrumento "especialidade"
        int dia_semana "1=seg ... 7=dom"
        text hora_inicio
        text hora_fim
        real mensalidade "0 = gratuita"
        int bolsista "1 = bolsa da igreja"
        int ativo
    }
    PRESENCAS {
        int id PK
        int matricula_id FK
        text data
        int presente "1 ou 0"
        text observacao
    }
    REPASSES {
        int id PK
        int professor_id FK
        text mes "AAAA-MM"
        real valor
        text data_pagamento
    }
    PAGAMENTOS {
        int id PK
        int matricula_id FK
        text mes "AAAA-MM"
        int pago "1 ou 0"
        text observacao
    }
    AVISOS {
        int id PK
        text tipo "social, interno ou lembrete"
        text titulo
        text descricao
        text data
        text hora
        text local
        text valido_ate "só lembrete"
        int criado_por FK
    }
```

Há também a tabela `configuracoes` (chave/valor), que guarda o `percentual_escola`. O SQL completo está em [`sql/schema.sql`](../sql/schema.sql).

## 7. Telas

### Login
![Login](img/01-login.png)

### Painel do administrador
Nota musical 3D girando e a próxima aula. Números da escola (alunos, aulas por semana, alunos que não pagaram, repasses atrasados). Depois, quatro quadros: próximas aulas de todos os professores, avisos e lembretes, alunos que não pagaram (informado pelo professor) e professores sem repasse.
![Painel do administrador](img/02-painel-secretaria.png)

### Alunos
Busca, filtro por especialidade e professores de cada aluno.
![Alunos](img/03-alunos.png)

### Ficha do aluno e matrícula
Dados, últimas chamadas, matrículas com frequência e o formulário de nova matrícula.
![Ficha do aluno](img/04-ficha-aluno.png)

### Professores
![Professores](img/05-professores.png)

### Cadastro de professor (administrador)
Nome, especialidades, telefone, login e senha. O mesmo formulário cadastra outro administrador.
![Novo professor](img/18-novo-professor.png)

### Agenda semanal (administrador vê todos, uma cor por professor)
![Agenda geral](img/06-agenda-geral.png)

### Chamada do professor
O professor marca presente ou faltou e pode deixar uma observação.
![Chamada do professor](img/07-chamada.png)

### Chamada vista pelo administrador (só consulta)
Resumo do dia (presentes, faltas, chamadas não feitas), filtro por professor e a situação de cada aluno, sem botões de marcar.
![Chamada do administrador](img/15-chamada-admin.png)

### Frequência
![Frequência](img/08-frequencia.png)

### Gestão → Financeiro
Um só item no menu, com três abas.

**Resumo do mês:** quanto entra de mensalidade (paga, não paga, sem informação), quantas aulas são bancadas pela igreja (gratuitas e bolsistas), a parte da escola e quanto já foi repassado. Também mostra a inadimplência: alunos que não pagaram e professores com repasse atrasado.
![Financeiro: resumo do mês](img/09-financeiro.png)

**Mensalidades dos alunos (só consulta):** quem pagou, quem não pagou e quem ainda não foi informado, com filtro por professor e o valor em aberto.
![Mensalidades do administrador](img/19-mensalidades-admin.png)

**Repasses dos professores:** quanto cada professor deve repassar, quem já repassou e o percentual da escola.
![Repasses dos professores](img/21-repasses.png)

### Aluno bolsista
Na ficha do aluno, o admin dá ou tira a bolsa da igreja. Na nova matrícula, a mensalidade 0 indica aula gratuita (teoria, orquestra).
![Ficha de aluno bolsista](img/22-ficha-bolsista.png)

### Avisos
Lembretes ativos em amarelo e próximos eventos com a data em destaque, o tipo (social ou interno) e quanto tempo falta. Professores veem tudo, mas só o administrador edita.
![Avisos](img/13-avisos.png)

### Novo aviso
O administrador escolhe o tipo; o campo "válido até" só aparece para lembrete.
![Novo aviso](img/14-novo-aviso.png)

### Área do professor (exemplo: Pastor Elias)
Cada professor entra com o próprio login e só vê o que é dele. O menu não mostra Professores nem Financeiro.

**Painel do professor:** números dele (alunos, aulas por semana, frequência dos últimos 30 dias, mensalidades em aberto) e quatro quadros: minhas próximas aulas, avisos e lembretes, mensalidades em aberto e alunos com frequência baixa.
![Painel do professor](img/10-painel-professor.png)

**Meus alunos:** separados por especialidade (aqui, os alunos de bateria).
![Alunos de bateria](img/11-alunos-de-bateria.png)

**Agenda da semana:** só as aulas dele.
![Agenda do professor](img/12-agenda-professor.png)

**Chamada:** é o professor quem marca presente ou faltou (veja a tela "Chamada do professor" acima).

**Frequência dos alunos dele:** quem está abaixo de 75% aparece com o selo "atenção".
![Frequência do professor](img/16-frequencia-professor.png)

**Gestão → Mensalidades:** o professor marca quem pagou e quem não pagou no mês, com uma observação (ex.: "paga dia 20"). Só aparecem os alunos que pagam.
![Mensalidades do professor](img/20-mensalidades-professor.png)

## 8. Identidade visual

Segue o guia de estilo do Mundo 3D, adaptado para música:

- **Fundo claro e minimalista**, com cor só nos detalhes; botões e pílulas com raio de 99px; cards de 12 a 18px com sombra suave.
- **Cores de destaque:** laranja `#ff6b2c`, rosa `#ff4fa3`, violeta `#7b5cff`, azul `#2f8cff`, verde-água `#00b8a9`, amarelo `#ffc93c`, verde `#1fa463`, com fundos pastel nos indicadores.
- **Degradê arco-íris** na barra do topo do menu, no nome "PIBA", nas barras de frequência e na borda do dia de hoje na agenda.
- **Fontes:** Inter Tight (títulos), Inter (texto) e JetBrains Mono (horários, valores e etiquetas).
- **Animações:** nota musical 3D em Three.js no painel; cartões que aparecem subindo ao rolar (IntersectionObserver); ícones flutuando; entradas com "quique"; avatares com anel girando. Quem ativa "reduzir movimento" no sistema operacional vê tudo parado.

## 9. Segurança e privacidade

| Medida | Protege contra |
|---|---|
| Senhas com `password_hash` / `password_verify` | Vazamento de senhas mesmo se o banco for copiado |
| `session_regenerate_id` no login | Roubo de sessão |
| Consultas preparadas (PDO) em tudo que vem do usuário | SQL Injection |
| `htmlspecialchars` em todo texto exibido | XSS (scripts maliciosos) |
| Código CSRF em todos os formulários | Formulários enviados por outros sites |
| Checagem de perfil em cada página (`exigirLogin` / `exigirAdmin`) | Professor acessar dados da administração ou de outro professor |
| Banco fora do Git + alunos fictícios no repositório | Exposição de dados pessoais (LGPD) |

## 10. Limitações e próximos passos

- A mensalidade é a **atual** de cada matrícula. Se um valor mudar no meio do ano, os meses anteriores passam a mostrar o valor novo. Uma melhoria futura é guardar o histórico de valores.
- O pagamento do aluno é **informado pelo professor**. O sistema não recebe pagamentos nem emite recibos.
- Ideias: notas e boletim, aviso por WhatsApp de falta e exportar relatórios em PDF.
