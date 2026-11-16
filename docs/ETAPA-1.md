# Etapa 1 – Estrutura, visual e cadastro de alunos

Este documento explica o que foi feito nesta etapa e como cada parte funciona, para servir de estudo e de base para a apresentação.

## 1. Como uma página PHP funciona

Quando você abre `alunos/index.php` no navegador:

1. O navegador pede a página ao servidor PHP.
2. O PHP executa o código: conecta no banco, faz a consulta e monta o HTML.
3. O navegador recebe **só o HTML pronto** e mostra a tabela.

Por isso o código mistura PHP (`<?php ... ?>`) e HTML: o PHP decide *o que* aparece, o HTML diz *como* aparece.

## 2. O banco de dados (`sql/schema.sql` e `config/database.php`)

A tabela `alunos` guarda: nome, data de nascimento, responsável, telefone, instrumento e se está ativo.

A função `conectar()` em `config/database.php`:
- abre o arquivo `database/escola.sqlite` usando **PDO**;
- cria a tabela se ela ainda não existir;
- na primeira vez, insere 5 alunos **fictícios** para demonstração.

## 3. O layout (`includes/header.php` e `includes/footer.php`)

Todas as páginas usam o mesmo topo com o menu lateral. Em vez de copiar o menu em cada arquivo, cada página faz:

```php
$titulo = 'Alunos';   // título da aba
$menu   = 'alunos';   // item do menu que fica dourado
$raiz   = '../';      // caminho até a pasta principal
require __DIR__ . '/../includes/header.php';
```

Os itens "Turmas", "Chamada", "Notas" e "Agenda" aparecem como **em breve**: serão feitos nas próximas etapas.

## 4. CRUD de alunos

CRUD = **C**reate (criar), **R**ead (ler), **U**pdate (atualizar), **D**elete (excluir).

| Arquivo | Faz o quê |
|---|---|
| `alunos/index.php` | **Lê** e lista os alunos, com busca por nome ou instrumento |
| `alunos/form.php` | **Cria** (sem `?id`) ou **atualiza** (com `?id=3`) um aluno |
| `alunos/excluir.php` | **Exclui** um aluno |

### Regras de validação (em `form.php`)
- Nome com pelo menos 3 letras.
- Instrumento obrigatório e precisa ser um da lista.
- Aluno **menor de 18 anos** precisa ter responsável.

## 5. Segurança (bom citar no relatório)

| Cuidado | Onde | Protege contra |
|---|---|---|
| Consultas preparadas (`prepare` + `execute`) | todas as consultas com dados do usuário | **SQL Injection** |
| Função `e()` com `htmlspecialchars` | todo texto mostrado na tela | **XSS** (script malicioso) |
| Excluir só por `POST` + confirmação | `excluir.php` | Exclusão acidental por link |
| Banco fora do Git (`.gitignore`) | `database/` | Vazamento de dados pessoais (LGPD) |

## 6. O visual

O CSS (`assets/css/style.css`) usa **variáveis de cor** no `:root`. O tema é escuro, com menu lateral, e a cor principal é o **dourado** dos instrumentos de metal. Os detalhes de música (pauta, clave de sol e notas) são feitos só com CSS e caracteres Unicode (♪ ♫ 𝄞), sem imagens extras.

## Exercícios para você fazer (e commitar!)

Pequenas mudanças para praticar e deixar sua marca no código:

1. **Fácil:** acrescente um instrumento novo na lista `$instrumentos` de `alunos/form.php` (por exemplo "Contrabaixo").
2. **Fácil:** troque o texto do banner em `index.php` por uma frase sua sobre a escola.
3. **Médio:** no painel (`index.php`), crie um quarto cartão mostrando quantos alunos são **menores de idade**.
   Dica: `SELECT COUNT(*) FROM alunos WHERE data_nascimento > date('now', '-18 years')`
4. **Médio:** na lista de alunos, adicione um filtro para mostrar só os **ativos**.

Depois de cada mudança:
```
git add .
git commit -m "Descreva aqui o que você mudou"
git push
```
