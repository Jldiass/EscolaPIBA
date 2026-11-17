# 🎵 Música PIBA – EscolaPIBA

Sistema web de gestão da **Escola de Música comunitária da PIBA**, para a **secretaria** e os **professores**:

- 👩‍🎓 **Alunos:** cadastro completo, ficha individual e matrícula com professor, dia, horário e mensalidade
- 🎻 **Professores:** cada um com o próprio **login** e as suas **especialidades** (violino, viola, teoria musical, bateria…)
- 🗓 **Agenda semanal:** cada professor vê as aulas da semana dele; a secretaria vê a de todos
- ✓ **Chamada:** o professor marca presença ou falta em cada aula
- 📊 **Frequência:** percentual de presença por aluno, com alerta abaixo de 75%
- 💰 **Financeiro:** quanto cada professor recebe pelos seus alunos e **quem já repassou** a parte da escola no mês

Desenvolvido por **João** como parte do **Projeto de Extensão II – Ação e Difusão Cultural**, curso de Ciência da Computação (Anhanguera).

![Painel da secretaria](docs/img/02-painel-secretaria.png)

## Como rodar

1. Instale o **PHP 8** (por exemplo pelo [XAMPP](https://www.apachefriends.org/pt_br/)) e confira se a extensão `pdo_sqlite` está ativa no `php.ini`.
2. Baixe o repositório:
   ```
   git clone https://github.com/Jldiass/EscolaPIBA.git
   cd EscolaPIBA
   ```
3. Inicie o servidor:
   ```
   php -S localhost:8000
   ```
4. Abra http://localhost:8000

Na primeira execução o banco `database/escola.sqlite` é criado sozinho, com dados de demonstração.

### Logins de teste (senha de todos: `piba123`)

| Login | Perfil | O que vê |
|---|---|---|
| `secretaria` | Secretaria | Tudo: alunos, professores, agenda geral, chamada, frequência e financeiro |
| `thiago` | Professor de Violino | Só os alunos e a agenda dele |
| `geovana` | Professora de Viola | Só os alunos e a agenda dela |
| `elias` | Pastor Elias, Ministro de Música: Teoria musical e Bateria | Só os alunos dele, separados por especialidade |

> ⚠️ Antes de usar de verdade na escola, troque as senhas de todos (Professores → Editar) e apague o arquivo `database/escola.sqlite` de demonstração.

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Telas | HTML5 + CSS3 (sem framework) |
| Lógica no servidor | PHP 8 |
| Banco de dados | SQLite, acessado com PDO |
| Login | Sessão PHP + senhas com `password_hash` |

## Documentação

- 📘 [Documentação do sistema](docs/DOCUMENTACAO.md): requisitos, perfis, regras de negócio, banco de dados e telas
- 🧑‍💻 [Como o código funciona](docs/COMO-O-CODIGO-FUNCIONA.md): explicação de cada arquivo, segurança e exercícios

## Privacidade (LGPD)

Os **alunos** do repositório são **fictícios**. Os dados reais ficam só no arquivo do banco, no computador da escola, que nunca vai para o GitHub (`.gitignore`). Cada professor só enxerga os próprios alunos.
