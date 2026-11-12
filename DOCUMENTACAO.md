# 📘 Documentação Técnica — Mini Sistema de Gerenciamento de Clientes

> Documentação completa do projeto: arquitetura, banco de dados, fluxo de cada página,
> front-end, segurança, problemas conhecidos e um guia de consultas SQL (do básico ao avançado).

---

## Sumário

1. [Visão geral](#1-visão-geral)
2. [Tecnologias](#2-tecnologias)
3. [Instalação e execução](#3-instalação-e-execução)
4. [Estrutura de arquivos](#4-estrutura-de-arquivos)
5. [Arquitetura e fluxo de requisições](#5-arquitetura-e-fluxo-de-requisições)
6. [Banco de dados](#6-banco-de-dados)
7. [Descrição de cada arquivo](#7-descrição-de-cada-arquivo)
8. [Front-end (CSS e JavaScript)](#8-front-end-css-e-javascript)
9. [Segurança](#9-segurança)
10. [Problemas conhecidos e melhorias sugeridas](#10-problemas-conhecidos-e-melhorias-sugeridas)
11. [Guia de consultas SQL](#11-guia-de-consultas-sql)

---

## 1. Visão geral

O **Mini Sistema** é uma aplicação web CRUD (Create, Read, Update, Delete) para cadastro de
clientes, escrita em PHP puro (sem framework), com banco MySQL/MariaDB acessado via **PDO**.
Todo o sistema fica protegido por uma tela de **login** baseada em sessão PHP.

| Funcionalidade | Página          | Operação SQL principal |
|----------------|-----------------|------------------------|
| Login          | `login.php`     | `SELECT`               |
| Logout         | `logout.php`    | —                      |
| Home           | `index.php`     | —                      |
| Cadastrar      | `cadastro.php`  | `INSERT`               |
| Listar/Buscar  | `clientes.php`  | `SELECT`               |
| Atualizar      | `atualizar.php` | `SELECT` + `UPDATE`    |
| Apagar         | `apagar.php`    | `SELECT` + `DELETE`    |
| Cadastrar/editar produto | `produto_cadastro.php` | `INSERT` / `SELECT` + `UPDATE` |
| Listar/Buscar produtos | `produtos.php` | `SELECT` + `LEFT JOIN` |
| Apagar produto | `produto_apagar.php` | `SELECT` + `DELETE` |
| Listar pedidos | `pedidos.php`   | `SELECT` com `JOIN` em 4 tabelas + `GROUP BY` |
| Novo pedido    | `pedido_novo.php` | `INSERT` em `pedidos` e `itens_pedido` (transação) |
| Ver pedido / mudar status | `pedido_ver.php` | `SELECT` com `JOIN` + `UPDATE` |
| Apagar pedido  | `pedido_apagar.php` | `DELETE` (itens apagados por `CASCADE`) |
| Relacionamentos | `relacionamentos.php` | Diagrama + `SELECT` com `JOIN` nas 5 tabelas |

O banco tem **5 tabelas relacionadas**: `usuarios`, `clientes`, `produtos`, `pedidos` e
`itens_pedido` (detalhes e diagrama na [seção 6](#6-banco-de-dados)).

---

## 2. Tecnologias

| Camada         | Tecnologia                                         |
|----------------|----------------------------------------------------|
| Servidor       | Apache (XAMPP) ou servidor embutido do PHP         |
| Back-end       | PHP 7.4+ (`declare(strict_types=1)` em parte dos arquivos) |
| Banco de dados | MariaDB/MySQL (o XAMPP traz MariaDB)               |
| Acesso a dados | PDO com *prepared statements*                      |
| Front-end      | HTML5, CSS3 (variáveis CSS, layout responsivo), JavaScript puro |

---

## 3. Instalação e execução

### 3.1 Pré-requisitos
- XAMPP instalado, com **Apache** e **MySQL** iniciados no painel de controle.
- Projeto dentro de `C:\xampp\htdocs\mini_sistema\mini_sistema`.

### 3.2 Criar o banco e as tabelas

No phpMyAdmin (`http://localhost/phpmyadmin`) ou no terminal do MySQL:

```sql
CREATE DATABASE IF NOT EXISTS mini_sistema
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mini_sistema;
```

Depois, execute os três scripts do projeto, **nesta ordem** (as tabelas novas dependem das antigas):
- [usuarios.sql](usuarios.sql) — cria a tabela `usuarios` (login) e o usuário administrador;
- [clientes.sql](clientes.sql) — cria a tabela `clientes` (dados do CRUD);
- [relacionamentos.sql](relacionamentos.sql) — cria `produtos`, `pedidos` e `itens_pedido` com as
  chaves estrangeiras, e insere dados de exemplo (5 produtos, 2 clientes e 2 pedidos).

Pelo terminal:

```bash
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < usuarios.sql
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < clientes.sql
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < relacionamentos.sql
```

Os três scripts podem ser executados mais de uma vez sem duplicar tabelas nem dados.

### 3.3 Acessar

- Via Apache do XAMPP: `http://localhost/mini_sistema/mini_sistema/login.php`
- Ou via servidor embutido do PHP (na pasta do projeto), acessando depois `http://localhost:8000`:

```bash
php -S localhost:8000
```

**Login padrão:** `admin@sistema.com` / `admin123`

### 3.4 Configuração da conexão

Os dados de conexão ficam em [conexao.php](conexao.php):

| Variável    | Valor padrão   |
|-------------|----------------|
| `$host`     | `127.0.0.1`    |
| `$dbname`   | `mini_sistema` |
| `$user`     | `root`         |
| `$password` | *(vazio)*      |

---

## 4. Estrutura de arquivos

```
mini_sistema/
├── conexao.php      # Cria o objeto $pdo (conexão com o banco)
├── sessao.php       # Inicia a sessão PHP, se ainda não iniciada
├── auth.php         # "Guarda" das páginas: exige usuário logado
├── login.php        # Formulário e processamento do login
├── logout.php       # Destrói a sessão e volta ao login
├── index.php        # Página inicial (Home)
├── cadastro.php     # Formulário de cadastro de cliente (INSERT)
├── clientes.php     # Listagem + busca de clientes (SELECT)
├── atualizar.php    # Edição de cliente (SELECT + UPDATE)
├── apagar.php       # Exclusão de cliente (bloqueada se ele tiver pedidos)
├── produto_cadastro.php # Cadastro e edição de produto (INSERT / UPDATE)
├── produtos.php     # Listagem + busca de produtos
├── produto_apagar.php # Exclusão de produto (bloqueada se ele já foi vendido)
├── pedidos.php      # Listagem de pedidos (JOIN clientes/usuarios/itens_pedido)
├── pedido_novo.php  # Registro de pedido com itens (transação)
├── pedido_ver.php   # Detalhes do pedido e mudança de status
├── pedido_apagar.php # Exclusão de pedido (itens apagados em cascata)
├── relacionamentos.php # Diagrama e explicação das 5 tabelas + consulta ao vivo
├── estoque.php      # devolver_estoque() e baixar_estoque()
├── header.php       # Cabeçalho, menu e breadcrumb compartilhados
├── footer.php       # Rodapé, toasts e modal de exclusão compartilhados
├── form_cliente.php # Campos do formulário de cliente (cadastro e atualização)
├── validacao.php    # validar_cliente(): regras de validação no servidor
├── ui.php           # icone(), iniciais(), flash() e flash_pegar()
├── usuarios.sql     # Script da tabela `usuarios` + usuário admin
├── clientes.sql     # Script da tabela `clientes`
├── relacionamentos.sql # Script de `produtos`, `pedidos` e `itens_pedido` + dados de exemplo
├── css/
│   └── style.css    # Estilo de todas as páginas
├── js/
│   ├── app.js        # Toasts e modal de confirmação de exclusão
│   ├── formulario.js # Validação/máscara dos formulários de cliente
│   ├── busca.js     # Busca instantânea nas listas (clientes, produtos e pedidos)
│   └── pedido.js    # Linhas de itens e total do formulário de pedido
├── imagens/
│   └── bem-vindo.jpeg
├── README.md        # Resumo do projeto
└── DOCUMENTACAO.md  # Este documento
```

---

## 5. Arquitetura e fluxo de requisições

O projeto segue o modelo **"uma página PHP = uma tela"**: cada arquivo processa o formulário
(quando a requisição é `POST`) no topo e, em seguida, renderiza o HTML.

### 5.1 Cadeia de includes

```
página protegida (ex.: clientes.php)
   ├── require auth.php
   │      └── require sessao.php  → session_start()
   │      └── sem $_SESSION['usuario_id']? → redireciona p/ login.php?redirect=clientes.php
   └── require conexao.php        → $pdo pronto para uso
```

### 5.2 Fluxo de login

```
Usuário acessa clientes.php sem estar logado
        │
        ▼
auth.php → Location: login.php?redirect=clientes.php
        │
        ▼
login.php (POST email + senha)
   ├── SELECT ID, NOME, EMAIL, SENHA FROM usuarios WHERE EMAIL = ?
   ├── password_verify(senha, hash)
   │     ├── OK   → session_regenerate_id(true)
   │     │          grava usuario_id / usuario_nome / usuario_email na sessão
   │     │          Location: clientes.php   (valor de redirect)
   │     └── Falha → "Email ou senha inválidos!"
```

O parâmetro `redirect` é validado com a regex `^[a-zA-Z0-9_\-]+\.php$`, impedindo
redirecionamento para sites externos (*open redirect*).

### 5.3 Fluxo do CRUD

```
cadastro.php  ──POST──► validar_cliente() ──► INSERT ──► toast de sucesso
clientes.php  ──GET───► SELECT ... ORDER BY ID DESC ──► tabela + busca JS
   ├── ✏️ Editar  → atualizar.php?id=N ──GET──► SELECT por ID
   │                                   ──POST─► UPDATE por ID
   └── Apagar → modal de confirmação → apagar.php?id=N ──► SELECT + DELETE
                                                    └► flash() + redireciona → clientes.php (toast)
```

### 5.4 Variáveis de sessão

| Chave                       | Conteúdo                         |
|-----------------------------|----------------------------------|
| `$_SESSION['usuario_id']`   | ID do usuário logado             |
| `$_SESSION['usuario_nome']` | Nome exibido no cabeçalho        |
| `$_SESSION['usuario_email']`| Email do usuário logado          |

---

## 6. Banco de dados

Banco: **`mini_sistema`** (charset `utf8mb4`).

### 6.1 Tabela `usuarios`

Usada para **autenticação**. Criada por [usuarios.sql](usuarios.sql).

| Coluna      | Tipo           | Restrições                          | Descrição               |
|-------------|----------------|-------------------------------------|-------------------------|
| `ID`        | `INT(11)`      | PK, `AUTO_INCREMENT`                | Identificador           |
| `NOME`      | `VARCHAR(100)` | `NOT NULL`                          | Nome do usuário         |
| `EMAIL`     | `VARCHAR(100)` | `NOT NULL`, `UNIQUE`                | Login                   |
| `TELEFONE`  | `VARCHAR(20)`  | `NULL`                              | Telefone (opcional)     |
| `SENHA`     | `VARCHAR(255)` | `NOT NULL`                          | Hash bcrypt da senha    |
| `CRIADO_EM` | `TIMESTAMP`    | `DEFAULT CURRENT_TIMESTAMP`         | Data de criação         |

### 6.2 Tabela `clientes`

Tabela dos **clientes** gerenciados pelo CRUD (cadastro, listagem, atualização e exclusão).
Criada por [clientes.sql](clientes.sql). A coluna `CRIADO_EM` é preenchida automaticamente e é
usada em várias consultas da seção 11:

```sql
CREATE TABLE IF NOT EXISTS clientes (
    ID        INT(11)      NOT NULL AUTO_INCREMENT,
    NOME      VARCHAR(100) NOT NULL,
    EMAIL     VARCHAR(100) NOT NULL,
    TELEFONE  VARCHAR(20)  NULL,
    CRIADO_EM TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID)
);
```

### 6.3 Tabela `produtos`

Catálogo de produtos que podem ser vendidos. Criada por [relacionamentos.sql](relacionamentos.sql).

| Coluna      | Tipo            | Restrições                         | Descrição                    |
|-------------|-----------------|------------------------------------|------------------------------|
| `ID`        | `INT(11)`       | PK, `AUTO_INCREMENT`               | Identificador                |
| `NOME`      | `VARCHAR(100)`  | `NOT NULL`, `UNIQUE`               | Nome do produto              |
| `DESCRICAO` | `VARCHAR(255)`  | `NULL`                             | Descrição (opcional)         |
| `PRECO`     | `DECIMAL(10,2)` | `NOT NULL`, `CHECK (PRECO >= 0)`   | Preço atual                  |
| `ESTOQUE`   | `INT(11)`       | `NOT NULL`, `DEFAULT 0`, `CHECK (ESTOQUE >= 0)` | Quantidade disponível |
| `CRIADO_EM` | `TIMESTAMP`     | `DEFAULT CURRENT_TIMESTAMP`        | Data de criação              |

### 6.4 Tabela `pedidos`

Cada pedido pertence a **um cliente** e foi registrado por **um usuário** do sistema.

| Coluna       | Tipo           | Restrições                                   | Descrição                         |
|--------------|----------------|----------------------------------------------|-----------------------------------|
| `ID`         | `INT(11)`      | PK, `AUTO_INCREMENT`                         | Número do pedido                  |
| `CLIENTE_ID` | `INT(11)`      | `NOT NULL`, **FK → `clientes.ID`**           | Quem comprou                      |
| `USUARIO_ID` | `INT(11)`      | `NULL`, **FK → `usuarios.ID`**               | Quem registrou (usuário logado)   |
| `STATUS`     | `ENUM`         | `'ABERTO'`, `'PAGO'`, `'ENVIADO'`, `'CANCELADO'` (padrão `ABERTO`) | Situação |
| `OBSERVACAO` | `VARCHAR(255)` | `NULL`                                       | Observação livre                  |
| `CRIADO_EM`  | `TIMESTAMP`    | `DEFAULT CURRENT_TIMESTAMP`                  | Data do pedido                    |

### 6.5 Tabela `itens_pedido`

Tabela **associativa** que resolve o relacionamento **N:N** entre `pedidos` e `produtos`: cada
linha diz "neste pedido, este produto, nesta quantidade, por este preço".

| Coluna           | Tipo            | Restrições                              | Descrição                                |
|------------------|-----------------|-----------------------------------------|------------------------------------------|
| `ID`             | `INT(11)`       | PK, `AUTO_INCREMENT`                    | Identificador                            |
| `PEDIDO_ID`      | `INT(11)`       | `NOT NULL`, **FK → `pedidos.ID`**       | Pedido ao qual o item pertence           |
| `PRODUTO_ID`     | `INT(11)`       | `NOT NULL`, **FK → `produtos.ID`**      | Produto vendido                          |
| `QUANTIDADE`     | `INT(11)`       | `NOT NULL`, `CHECK (QUANTIDADE > 0)`    | Quantidade                               |
| `PRECO_UNITARIO` | `DECIMAL(10,2)` | `NOT NULL`                              | Preço **no momento da venda** (cópia de `produtos.PRECO`) |

Chave única `(PEDIDO_ID, PRODUTO_ID)`: o mesmo produto não aparece duas vezes no mesmo pedido
(se o usuário escolher o mesmo produto em duas linhas, o sistema soma as quantidades).

> **Por que copiar o preço?** Se o preço do produto mudar amanhã, os pedidos antigos continuam
> com o valor que o cliente realmente pagou.

### 6.6 Relacionamentos

| # | Relacionamento | Tipo | Chave estrangeira | `ON DELETE` | Significado |
|---|----------------|------|-------------------|-------------|-------------|
| 1 | `clientes` → `pedidos` | **1 : N** | `pedidos.CLIENTE_ID` → `clientes.ID` (`FK_PEDIDOS_CLIENTE`) | `RESTRICT` | Um cliente faz vários pedidos; cada pedido é de um único cliente. Cliente com pedidos **não pode ser apagado**. |
| 2 | `usuarios` → `pedidos` | **1 : N** | `pedidos.USUARIO_ID` → `usuarios.ID` (`FK_PEDIDOS_USUARIO`) | `SET NULL` | Um usuário registra vários pedidos. Se o usuário for apagado, o pedido continua, com `USUARIO_ID = NULL`. |
| 3 | `pedidos` → `itens_pedido` | **1 : N** | `itens_pedido.PEDIDO_ID` → `pedidos.ID` (`FK_ITENS_PEDIDO`) | `CASCADE` | Um pedido tem vários itens. **Apagar o pedido apaga os itens** automaticamente. |
| 4 | `produtos` → `itens_pedido` | **1 : N** | `itens_pedido.PRODUTO_ID` → `produtos.ID` (`FK_ITENS_PRODUTO`) | `RESTRICT` | Um produto aparece em vários itens. Produto já vendido **não pode ser apagado**. |
| 5 | `pedidos` ↔ `produtos` | **N : N** | através de `itens_pedido` | — | Um pedido tem vários produtos e um produto está em vários pedidos. |

Todas as chaves estrangeiras usam `ON UPDATE CASCADE` e exigem o motor **InnoDB** (o script
converte `usuarios` e `clientes` para InnoDB, caso ainda não estejam).

**Como cada regra aparece no sistema:**
- Apagar um cliente com pedidos → mensagem "não pode ser apagado: ele possui N pedido(s)".
- Apagar um produto já vendido → mensagem "não pode ser apagado: ele aparece em N pedido(s)".
- Apagar um pedido → os itens somem junto (`CASCADE`) e o estoque é devolvido.
- A lista de clientes mostra a coluna **Pedidos** (link para os pedidos daquele cliente).

### 6.7 Diagrama (DER)

O mesmo diagrama aparece desenhado, com a quantidade de registros de cada tabela, na página
**Relacionamentos** do sistema ([relacionamentos.php](relacionamentos.php)).

```
┌──────────────────┐                                  ┌──────────────────┐
│     usuarios     │                                  │     clientes     │
├──────────────────┤                                  ├──────────────────┤
│ PK ID            │                                  │ PK ID            │
│    NOME          │                                  │    NOME          │
│ UQ EMAIL         │                                  │    EMAIL         │
│    TELEFONE      │                                  │    TELEFONE      │
│    SENHA (hash)  │                                  │    CRIADO_EM     │
│    CRIADO_EM     │                                  └────────┬─────────┘
└────────┬─────────┘                                           │ 1
         │ 1                                                   │
         │              ┌──────────────────────┐               │
         │            N │       pedidos        │ N             │
         └─────────────►├──────────────────────┤◄──────────────┘
                        │ PK ID                │
                        │ FK CLIENTE_ID        │
                        │ FK USUARIO_ID        │
                        │    STATUS            │
                        │    OBSERVACAO        │
                        │    CRIADO_EM         │
                        └──────────┬───────────┘
                                   │ 1
                                   │
                                   ▼ N
                        ┌──────────────────────┐         ┌──────────────────┐
                        │     itens_pedido     │ N     1 │     produtos     │
                        ├──────────────────────┤◄────────┤──────────────────┤
                        │ PK ID                │         │ PK ID            │
                        │ FK PEDIDO_ID         │         │ UQ NOME          │
                        │ FK PRODUTO_ID        │         │    DESCRICAO     │
                        │    QUANTIDADE        │         │    PRECO         │
                        │    PRECO_UNITARIO    │         │    ESTOQUE       │
                        └──────────────────────┘         │    CRIADO_EM     │
                                                         └──────────────────┘
```

Em notação Mermaid (renderiza no GitHub/VS Code):

```mermaid
erDiagram
    usuarios ||--o{ pedidos : "registra"
    clientes ||--o{ pedidos : "faz"
    pedidos  ||--|{ itens_pedido : "contém"
    produtos ||--o{ itens_pedido : "aparece em"

    usuarios {
        int ID PK
        varchar NOME
        varchar EMAIL UK
        varchar TELEFONE
        varchar SENHA
        timestamp CRIADO_EM
    }
    clientes {
        int ID PK
        varchar NOME
        varchar EMAIL
        varchar TELEFONE
        timestamp CRIADO_EM
    }
    pedidos {
        int ID PK
        int CLIENTE_ID FK
        int USUARIO_ID FK
        enum STATUS
        varchar OBSERVACAO
        timestamp CRIADO_EM
    }
    itens_pedido {
        int ID PK
        int PEDIDO_ID FK
        int PRODUTO_ID FK
        int QUANTIDADE
        decimal PRECO_UNITARIO
    }
    produtos {
        int ID PK
        varchar NOME UK
        varchar DESCRICAO
        decimal PRECO
        int ESTOQUE
        timestamp CRIADO_EM
    }
```

### 6.8 Regras de estoque

Implementadas em [estoque.php](estoque.php), sempre dentro de uma transação:

| Ação                                   | Efeito no estoque               |
|----------------------------------------|---------------------------------|
| Registrar pedido                       | Baixa (falha se não houver estoque suficiente) |
| Mudar status para **Cancelado**        | Devolve                         |
| Tirar um pedido de **Cancelado**       | Baixa de novo (se houver estoque) |
| Apagar pedido (não cancelado)          | Devolve                         |

---

## 7. Descrição de cada arquivo

### [conexao.php](conexao.php)
Cria a instância `$pdo` com três opções importantes:
- `ERRMODE_EXCEPTION` — erros SQL viram exceções (nada falha em silêncio);
- `FETCH_ASSOC` — resultados vêm como arrays associativos (`$linha['NOME']`);
- `EMULATE_PREPARES => false` — *prepared statements* reais no servidor.

Se a conexão falhar, responde HTTP 500 com uma mensagem amigável.

### [sessao.php](sessao.php)
Chama `session_start()` somente se a sessão ainda não foi iniciada, evitando *warnings* quando
incluído mais de uma vez.

### [auth.php](auth.php)
Incluído no topo de toda página protegida. Se não existir `$_SESSION['usuario_id']`, redireciona
para `login.php?redirect=<página atual>` e encerra o script com `exit`.

### [login.php](login.php)
- Se o usuário já estiver logado, vai direto para `index.php`.
- No `POST`, busca o usuário pelo email e confere a senha com `password_verify()`.
- Em caso de sucesso, regenera o ID da sessão e grava os dados do usuário.
- Mantém o email digitado no campo após um erro.

### [logout.php](logout.php)
Esvazia `$_SESSION`, expira o cookie de sessão, chama `session_destroy()` e volta ao login.

### [index.php](index.php)
Página inicial: mensagem de boas-vindas, imagem, atalhos (Novo Cliente, Novo Pedido, Ver
Relacionamentos) e os cards de funcionalidades em três grupos — **Clientes**, **Produtos** e
**Pedidos** —, cada um com Cadastrar, Listar, Atualizar e Apagar. Os cards são gerados a partir do
array `$grupos`.

### [cadastro.php](cadastro.php)
Recebe `nome`, `email` e `telefone` por `POST`, valida campos obrigatórios (nome e email) e
executa um `INSERT` com *prepared statement*.

### [clientes.php](clientes.php)
Executa um `SELECT` ordenado por `ID DESC` (mais recentes primeiro) e monta a tabela. Cada linha
recebe o atributo `data-search` (nome + email + telefone em minúsculas) usado pela busca em
[js/busca.js](js/busca.js). Mostra o total de registros ao lado da busca. Cada cliente tem
um avatar com as iniciais (`iniciais()` em `ui.php`) e botões só com ícone para editar/apagar.
Em telas pequenas a tabela vira uma lista de cartões.

### [atualizar.php](atualizar.php)
- Lê `id` da URL (convertido com `(int)`), busca o registro e redireciona para a lista se não existir.
- No `POST`, valida com `validar_cliente()` e executa o `UPDATE`; erros aparecem embaixo de cada campo.

### [apagar.php](apagar.php)
Busca o registro pelo `id` junto com a quantidade de pedidos (`LEFT JOIN pedidos`). Se o cliente
tiver pedidos, não apaga e mostra o aviso (a FK `RESTRICT` também impediria no banco). Caso
contrário, executa o `DELETE`, guarda a mensagem com `flash()` e redireciona para a lista, onde a
mensagem aparece como toast. A confirmação acontece antes, no modal aberto por `js/app.js`.

### [produto_cadastro.php](produto_cadastro.php)
Formulário de produto, no mesmo modelo do cadastro de cliente. Sem `id` faz `INSERT` (e limpa o
formulário para o próximo); com `?id=ID` carrega o produto e o `POST` faz `UPDATE`. Aceita preço
como `1.234,50` ou `1234.50`. Nome duplicado (erro `23000` da chave `UNIQUE`) vira mensagem no
campo. Produto inexistente volta para a lista com um aviso.

### [produtos.php](produtos.php)
Lista de produtos no mesmo formato da lista de clientes: busca instantânea, contador, botões de
editar/apagar e cartões no celular. Usa `LEFT JOIN itens_pedido` + `SUM` para mostrar quantas
unidades de cada produto já foram vendidas.

### [produto_apagar.php](produto_apagar.php)
Conta em quantos itens de pedido o produto aparece; se for mais de zero, recusa a exclusão.

### [pedidos.php](pedidos.php)
Lista os pedidos com `INNER JOIN clientes`, `LEFT JOIN usuarios` e `LEFT JOIN itens_pedido`,
agrupando (`GROUP BY`) para calcular a quantidade de itens e o total de cada pedido. Aceita o
filtro `?cliente=ID` (usado pelo link da coluna "Pedidos" na lista de clientes). Tem a mesma busca
instantânea da lista de clientes (por número, cliente, usuário ou status).

### [pedido_novo.php](pedido_novo.php)
Formulário com cliente, status, observação e linhas de itens (produto + quantidade). No `POST`,
dentro de **uma transação**: insere em `pedidos` (com `USUARIO_ID` = usuário logado), insere cada
item em `itens_pedido` copiando o preço atual de `produtos` (`INSERT ... SELECT`) e baixa o
estoque. Se faltar estoque, faz `rollBack()` e nada é gravado.

### [pedido_ver.php](pedido_ver.php)
Mostra os dados do pedido, do cliente e do usuário (JOINs) e a tabela de itens
(`itens_pedido INNER JOIN produtos`) com subtotal e total. Permite alterar o status, aplicando as
regras de estoque da seção 6.8.

### [pedido_apagar.php](pedido_apagar.php)
Devolve o estoque (se o pedido não estava cancelado) e apaga o pedido; os itens são removidos pelo
próprio banco (`ON DELETE CASCADE`).

### [relacionamentos.php](relacionamentos.php)
Página de apresentação do modelo: diagrama SVG das 5 tabelas (com PK/FK, cardinalidade 1–N e
quantidade de registros), tabela explicando cada relacionamento e uma consulta ao vivo com `JOIN`
nas 5 tabelas, exibindo o SQL e o resultado.

### [estoque.php](estoque.php)
Funções `devolver_estoque()` e `baixar_estoque()` (usa `SELECT ... FOR UPDATE` para travar as
linhas dos produtos durante a transação).

### [usuarios.sql](usuarios.sql)
Cria a tabela `usuarios`, garante a coluna `TELEFONE` (sintaxe `ADD COLUMN IF NOT EXISTS`,
própria do MariaDB) e insere o administrador padrão apenas se ele ainda não existir
(`INSERT ... SELECT ... WHERE NOT EXISTS`).

### [clientes.sql](clientes.sql)
Cria a tabela `clientes` (se ainda não existir) e garante a coluna `CRIADO_EM` em tabelas
criadas anteriormente sem ela.

### [relacionamentos.sql](relacionamentos.sql)
Converte `usuarios` e `clientes` para InnoDB, cria `produtos`, `pedidos` e `itens_pedido` com as
chaves estrangeiras e `CHECK`s, e insere dados de exemplo apenas se ainda não existirem
(`INSERT IGNORE` e `INSERT ... SELECT ... WHERE NOT EXISTS`).

---

## 8. Front-end (CSS e JavaScript)

### 8.1 [css/style.css](css/style.css)
Folha de estilo única para todas as telas, com fonte Arial. As cores, espaçamentos (`--space-1`
a `--space-6`), raios (`--radius-*`) e sombras (`--shadow-*`) ficam em variáveis no `:root`.
Inclui cabeçalho/breadcrumb, cards, formulários com erro por campo (`.has-error`), tabela que
vira cartões no celular, alertas, toasts (`.toast`), modal (`.modal`), botões (`.btn-primary`,
`.btn-secondary`, `.btn-outline`, `.btn-danger`, `.btn-danger-outline`, `.btn-icon`, `.btn-sm`)
e a tela de login.

### 8.2 [js/formulario.js](js/formulario.js)
Usado por qualquer `<form data-validar>` (cadastro e atualização). As regras são as mesmas de
`validacao.php`:

| Função                | O que faz                                                                 |
|-----------------------|---------------------------------------------------------------------------|
| validação no `blur`   | Valida o campo e mostra o erro embaixo dele (`.has-error`)                |
| validação no `input`  | Depois de um erro, revalida enquanto o usuário digita                     |
| `formatarTelefone()`  | Máscara automática: `(11) 9999-9999` ou `(11) 99999-9999` (máx. 11 dígitos) |
| validação no `submit` | Bloqueia o envio e foca o primeiro campo inválido                         |

### 8.2.1 [js/app.js](js/app.js)
Carregado em todas as páginas internas (via `footer.php`). Fecha os toasts sozinho após 4,5 s
(pausa com o mouse em cima) e abre o modal de confirmação para links com
`data-confirmar-exclusao`.

### 8.3 [js/busca.js](js/busca.js)
Busca instantânea (sem recarregar a página), usada nas listas de **clientes**, **produtos** e
**pedidos**: a cada tecla, esconde as linhas `<tr data-search>` cujo texto não contém o termo e
atualiza o contador ("2 de 5 produtos"). A página indica o campo com `data-busca` e as palavras do
contador com `data-singular`/`data-plural` em `#resultadoBusca`.

### 8.4 [js/pedido.js](js/pedido.js)
No formulário de novo pedido: o botão "Adicionar produto" clona uma linha de item, a lixeira
remove a linha (exceto a última) e, a cada mudança, recalcula o subtotal de cada linha e o total
do pedido (usando o `data-preco` de cada opção). O cálculo é só visual — o servidor usa o preço
gravado em `produtos`.

---

## 9. Segurança

| Medida                                   | Onde                                   |
|------------------------------------------|----------------------------------------|
| *Prepared statements* (anti SQL Injection) | Todas as consultas com parâmetros     |
| Senhas com hash bcrypt                   | `password_hash` / `password_verify`    |
| Regeneração do ID de sessão no login     | `login.php` (`session_regenerate_id`)  |
| Escape de saída (anti XSS)               | `htmlspecialchars()` nos dados exibidos |
| Validação do `redirect` (anti open redirect) | `login.php` (regex)                 |
| Conversão do `id` para inteiro           | `atualizar.php`, `apagar.php`          |
| Confirmação antes de excluir             | Modal em `js/app.js` (`data-confirmar-exclusao`) |

---

## 10. Problemas conhecidos e melhorias sugeridas

### 10.1 ✅ Corrigido: mistura entre as tabelas `usuarios` e `clientes`

Antes, as páginas do CRUD usavam tabelas diferentes para a mesma operação (cadastro e
listagem em `usuarios`, edição e exclusão em `clientes`). Isso fazia o cadastro falhar
(`usuarios.SENHA` é `NOT NULL`), a lista mostrar os usuários do sistema em vez dos clientes, e
editar/apagar não terem efeito visível.

**Situação atual:** cada tabela tem um único papel.

| Arquivo         | Leitura (`SELECT`) | Escrita                  |
|-----------------|--------------------|--------------------------|
| `login.php`     | `FROM usuarios`    | —                        |
| `cadastro.php`  | —                  | `INSERT INTO clientes`   |
| `clientes.php`  | `FROM clientes`    | —                        |
| `atualizar.php` | `FROM clientes`    | `UPDATE clientes`        |
| `apagar.php`    | `FROM clientes`    | `DELETE FROM clientes`   |

> Se você já cadastrou "clientes" na tabela `usuarios` antes da correção, eles continuam lá.
> Para copiá-los para `clientes` (ignorando o admin), use:

```sql
INSERT INTO clientes (NOME, EMAIL, TELEFONE)
SELECT NOME, EMAIL, TELEFONE FROM usuarios
WHERE EMAIL <> 'admin@sistema.com';
```

### 10.2 Outras melhorias
- Validar o formato do email também no servidor (`filter_var($email, FILTER_VALIDATE_EMAIL)`).
- Exclusão via `GET` pode ser disparada por um link externo (CSRF). O ideal é excluir via
  `POST` com um token CSRF.
- Tratar email duplicado (capturar `PDOException` código `23000`).
- Remover a dica de credenciais padrão da tela de login em produção.

---

## 11. Guia de consultas SQL

> Todas as consultas consideram o banco `mini_sistema` e as tabelas descritas na [seção 6](#6-banco-de-dados).
> Execute-as no phpMyAdmin (aba **SQL**) ou no cliente `mysql`. Antes de rodar, selecione o banco:

```sql
USE mini_sistema;
```

### 11.0 Dados de exemplo

Para testar as consultas, insira alguns clientes:

```sql
INSERT INTO clientes (NOME, EMAIL, TELEFONE, CRIADO_EM) VALUES
('Ana Souza',       'ana.souza@gmail.com',      '(11) 98765-4321', '2026-01-15 10:00:00'),
('Bruno Lima',      'bruno.lima@hotmail.com',   '(21) 99876-5432', '2026-02-03 14:30:00'),
('Carla Mendes',    'carla@empresa.com.br',     NULL,              '2026-02-20 09:15:00'),
('Diego Alves',     'diego.alves@gmail.com',    '(11) 3456-7890',  '2026-03-11 16:45:00'),
('Elisa Ferreira',  'elisa.f@yahoo.com',        '(31) 98888-7777', '2026-03-28 11:20:00'),
('Fábio Rocha',     'fabio.rocha@gmail.com',    '(11) 97777-6666', '2026-04-05 08:00:00'),
('Gabriela Nunes',  'gabi.nunes@empresa.com.br','(41) 3333-2222',  '2026-05-19 17:10:00'),
('Ana Souza',       'ana.souza@gmail.com',      '(11) 98765-4321', '2026-06-01 12:00:00');
```

*(O último registro é propositalmente duplicado, para os exemplos de busca de duplicatas.)*

---

### 🟢 Nível 1 — Básico

**1. Listar todos os clientes**
```sql
SELECT * FROM clientes;
```

**2. Escolher colunas específicas**
```sql
SELECT NOME, EMAIL FROM clientes;
```

**3. Ordenar (mais recentes primeiro — igual à tela `clientes.php`)**
```sql
SELECT ID, NOME, EMAIL, TELEFONE
FROM clientes
ORDER BY ID DESC;
```

**4. Buscar um cliente pelo ID (usado em `atualizar.php`)**
```sql
SELECT * FROM clientes WHERE ID = 3;
```

**5. Limitar a quantidade de resultados**
```sql
SELECT NOME, EMAIL FROM clientes ORDER BY NOME LIMIT 5;
```

**6. Inserir um cliente (usado em `cadastro.php`)**
```sql
INSERT INTO clientes (NOME, EMAIL, TELEFONE)
VALUES ('Helena Castro', 'helena@gmail.com', '(11) 91234-5678');
```

**7. Atualizar um cliente (usado em `atualizar.php`)**
```sql
UPDATE clientes
SET NOME = 'Helena Castro Silva', TELEFONE = '(11) 90000-1111'
WHERE ID = 9;
```
> ⚠️ Nunca esqueça o `WHERE` em `UPDATE`/`DELETE` — sem ele, **todas** as linhas são alteradas.

**8. Excluir um cliente (usado em `apagar.php`)**
```sql
DELETE FROM clientes WHERE ID = 9;
```

---

### 🟡 Nível 2 — Filtros e funções

**9. Busca por parte do nome (`LIKE`)**
```sql
SELECT * FROM clientes WHERE NOME LIKE '%ana%';
```

**10. Busca em várias colunas (equivalente à busca da tela de listagem)**
```sql
SELECT ID, NOME, EMAIL, TELEFONE
FROM clientes
WHERE NOME     LIKE '%gmail%'
   OR EMAIL    LIKE '%gmail%'
   OR TELEFONE LIKE '%gmail%';
```

**11. Clientes sem telefone (`IS NULL`)**
```sql
SELECT NOME, EMAIL FROM clientes
WHERE TELEFONE IS NULL OR TELEFONE = '';
```

**12. Clientes de uma lista de DDDs (`IN`)**
```sql
SELECT NOME, TELEFONE
FROM clientes
WHERE SUBSTRING(TELEFONE, 2, 2) IN ('11', '21');
```

**13. Cadastrados em um intervalo de datas (`BETWEEN`)**
```sql
SELECT NOME, CRIADO_EM
FROM clientes
WHERE CRIADO_EM BETWEEN '2026-02-01' AND '2026-03-31 23:59:59';
```

**14. Contar quantos clientes existem (`COUNT`)**
```sql
SELECT COUNT(*) AS total_clientes FROM clientes;
```

**15. Formatando a saída (funções de texto e data)**
```sql
SELECT
    UPPER(NOME)                              AS nome_maiusculo,
    LOWER(EMAIL)                             AS email,
    COALESCE(TELEFONE, 'Não informado')      AS telefone,
    DATE_FORMAT(CRIADO_EM, '%d/%m/%Y %H:%i') AS cadastrado_em
FROM clientes;
```

**16. Paginação (página 2, 3 registros por página)**
```sql
SELECT ID, NOME, EMAIL
FROM clientes
ORDER BY ID
LIMIT 3 OFFSET 3;   -- OFFSET = (página - 1) * itens_por_página
```

---

### 🟠 Nível 3 — Agrupamentos e subconsultas

**17. Quantidade de clientes por provedor de email (`GROUP BY`)**
```sql
SELECT
    SUBSTRING_INDEX(EMAIL, '@', -1) AS dominio,
    COUNT(*)                        AS quantidade
FROM clientes
GROUP BY dominio
ORDER BY quantidade DESC;
```

**18. Apenas domínios com mais de 1 cliente (`HAVING`)**
```sql
SELECT SUBSTRING_INDEX(EMAIL, '@', -1) AS dominio, COUNT(*) AS quantidade
FROM clientes
GROUP BY dominio
HAVING COUNT(*) > 1;
```

**19. Cadastros por mês**
```sql
SELECT
    DATE_FORMAT(CRIADO_EM, '%Y-%m') AS mes,
    COUNT(*)                        AS novos_clientes
FROM clientes
GROUP BY mes
ORDER BY mes;
```

**20. Classificar o tipo de telefone (`CASE`)**
```sql
SELECT
    NOME,
    TELEFONE,
    CASE
        WHEN TELEFONE IS NULL OR TELEFONE = ''                    THEN 'Sem telefone'
        WHEN LENGTH(REGEXP_REPLACE(TELEFONE, '[^0-9]', '')) = 11 THEN 'Celular'
        WHEN LENGTH(REGEXP_REPLACE(TELEFONE, '[^0-9]', '')) = 10 THEN 'Fixo'
        ELSE 'Formato desconhecido'
    END AS tipo_telefone
FROM clientes;
```

**21. Encontrar emails duplicados**
```sql
SELECT EMAIL, COUNT(*) AS vezes, GROUP_CONCAT(ID ORDER BY ID) AS ids
FROM clientes
GROUP BY EMAIL
HAVING COUNT(*) > 1;
```

**22. Subconsulta: o cliente cadastrado mais recentemente**
```sql
SELECT NOME, EMAIL, CRIADO_EM
FROM clientes
WHERE CRIADO_EM = (SELECT MAX(CRIADO_EM) FROM clientes);
```

**23. Subconsulta com `IN`: clientes dos domínios mais populares**
```sql
SELECT NOME, EMAIL
FROM clientes
WHERE SUBSTRING_INDEX(EMAIL, '@', -1) IN (
    SELECT dominio FROM (
        SELECT SUBSTRING_INDEX(EMAIL, '@', -1) AS dominio
        FROM clientes
        GROUP BY dominio
        HAVING COUNT(*) >= 2
    ) AS populares
);
```

**24. `JOIN` entre tabelas: clientes que também são usuários do sistema**
```sql
SELECT c.ID AS id_cliente, c.NOME, c.EMAIL, u.ID AS id_usuario
FROM clientes c
INNER JOIN usuarios u ON u.EMAIL = c.EMAIL;
```

**25. `LEFT JOIN`: clientes que **não** são usuários**
```sql
SELECT c.NOME, c.EMAIL
FROM clientes c
LEFT JOIN usuarios u ON u.EMAIL = c.EMAIL
WHERE u.ID IS NULL;
```

**26. `UNION`: lista única de todos os contatos (clientes + usuários)**
```sql
SELECT NOME, EMAIL, 'Cliente' AS origem FROM clientes
UNION
SELECT NOME, EMAIL, 'Usuário' AS origem FROM usuarios
ORDER BY NOME;
```

---

### 🔴 Nível 4 — Avançado

> As consultas com `WITH` (CTE) e funções de janela (`OVER`) exigem **MySQL 8+** ou
> **MariaDB 10.2+** (as versões atuais do XAMPP atendem).

**27. CTE (`WITH`): percentual de clientes por domínio**
```sql
WITH por_dominio AS (
    SELECT SUBSTRING_INDEX(EMAIL, '@', -1) AS dominio, COUNT(*) AS qtd
    FROM clientes
    GROUP BY dominio
)
SELECT
    dominio,
    qtd,
    ROUND(qtd * 100.0 / (SELECT SUM(qtd) FROM por_dominio), 1) AS percentual
FROM por_dominio
ORDER BY qtd DESC;
```

**28. Numerar os clientes dentro de cada domínio (`ROW_NUMBER`)**
```sql
SELECT
    NOME,
    EMAIL,
    ROW_NUMBER() OVER (
        PARTITION BY SUBSTRING_INDEX(EMAIL, '@', -1)
        ORDER BY CRIADO_EM
    ) AS ordem_no_dominio
FROM clientes;
```

**29. Total acumulado de cadastros ao longo do tempo (*running total*)**
```sql
SELECT
    DATE_FORMAT(CRIADO_EM, '%Y-%m') AS mes,
    COUNT(*)                        AS novos,
    SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(CRIADO_EM, '%Y-%m')) AS acumulado
FROM clientes
GROUP BY mes
ORDER BY mes;
```

**30. Intervalo entre um cadastro e o anterior (`LAG`)**
```sql
SELECT
    NOME,
    CRIADO_EM,
    LAG(CRIADO_EM) OVER (ORDER BY CRIADO_EM) AS cadastro_anterior,
    DATEDIFF(CRIADO_EM, LAG(CRIADO_EM) OVER (ORDER BY CRIADO_EM)) AS dias_desde_anterior
FROM clientes;
```

**31. Remover duplicatas mantendo o registro mais antigo**
```sql
-- 1) Conferir o que será apagado
SELECT c1.*
FROM clientes c1
JOIN clientes c2 ON c1.EMAIL = c2.EMAIL AND c1.ID > c2.ID;

-- 2) Apagar
DELETE c1
FROM clientes c1
JOIN clientes c2 ON c1.EMAIL = c2.EMAIL AND c1.ID > c2.ID;
```

**32. Impedir emails duplicados daqui para frente (índice único)**
```sql
ALTER TABLE clientes ADD UNIQUE KEY UQ_CLIENTES_EMAIL (EMAIL);
```

**33. Índice para acelerar buscas por nome + análise com `EXPLAIN`**
```sql
CREATE INDEX IDX_CLIENTES_NOME ON clientes (NOME);

EXPLAIN SELECT * FROM clientes WHERE NOME LIKE 'Ana%';
-- Observação: LIKE '%ana%' (curinga no início) NÃO consegue usar o índice.
```

**34. Normalizar telefones: deixar só os dígitos**
```sql
SELECT NOME, TELEFONE, REGEXP_REPLACE(TELEFONE, '[^0-9]', '') AS somente_digitos
FROM clientes
WHERE TELEFONE IS NOT NULL;
```

**35. *Upsert*: inserir ou atualizar se o email já existir** *(requer o índice único do item 32)*
```sql
INSERT INTO clientes (NOME, EMAIL, TELEFONE)
VALUES ('Ana Souza', 'ana.souza@gmail.com', '(11) 95555-4444')
ON DUPLICATE KEY UPDATE
    NOME     = VALUES(NOME),
    TELEFONE = VALUES(TELEFONE);
```

**36. View: relatório pronto para consulta**
```sql
CREATE OR REPLACE VIEW vw_clientes_resumo AS
SELECT
    ID,
    NOME,
    EMAIL,
    SUBSTRING_INDEX(EMAIL, '@', -1)          AS dominio,
    COALESCE(TELEFONE, 'Não informado')      AS telefone,
    DATE_FORMAT(CRIADO_EM, '%d/%m/%Y')       AS data_cadastro
FROM clientes;

-- Uso:
SELECT * FROM vw_clientes_resumo WHERE dominio = 'gmail.com';
```

**37. Transação: várias operações "tudo ou nada"**
```sql
START TRANSACTION;

UPDATE clientes SET TELEFONE = NULL WHERE TELEFONE = '';
DELETE FROM clientes WHERE EMAIL NOT LIKE '%@%.%';

-- Conferiu e está tudo certo?
COMMIT;
-- Algo deu errado? Desfaça com:
-- ROLLBACK;
```

**38. Stored procedure: busca reutilizável**
```sql
DELIMITER //

CREATE PROCEDURE sp_buscar_clientes(IN termo VARCHAR(100))
BEGIN
    SELECT ID, NOME, EMAIL, TELEFONE
    FROM clientes
    WHERE NOME     LIKE CONCAT('%', termo, '%')
       OR EMAIL    LIKE CONCAT('%', termo, '%')
       OR TELEFONE LIKE CONCAT('%', termo, '%')
    ORDER BY NOME;
END //

DELIMITER ;

-- Uso:
CALL sp_buscar_clientes('gmail');
```

**39. Trigger + tabela de auditoria: registrar toda exclusão**
```sql
CREATE TABLE IF NOT EXISTS clientes_log (
    ID          INT AUTO_INCREMENT PRIMARY KEY,
    CLIENTE_ID  INT,
    NOME        VARCHAR(100),
    EMAIL       VARCHAR(100),
    ACAO        VARCHAR(10),
    DATA_ACAO   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DELIMITER //

CREATE TRIGGER trg_clientes_delete
AFTER DELETE ON clientes
FOR EACH ROW
BEGIN
    INSERT INTO clientes_log (CLIENTE_ID, NOME, EMAIL, ACAO)
    VALUES (OLD.ID, OLD.NOME, OLD.EMAIL, 'DELETE');
END //

DELIMITER ;

-- Depois de apagar um cliente pelo sistema, confira:
SELECT * FROM clientes_log ORDER BY DATA_ACAO DESC;
```

**40. Painel de indicadores em uma única consulta**
```sql
SELECT
    (SELECT COUNT(*) FROM clientes)                                        AS total_clientes,
    (SELECT COUNT(*) FROM clientes WHERE TELEFONE IS NULL OR TELEFONE = '') AS sem_telefone,
    (SELECT COUNT(DISTINCT SUBSTRING_INDEX(EMAIL, '@', -1)) FROM clientes) AS dominios_distintos,
    (SELECT COUNT(*) FROM clientes
       WHERE CRIADO_EM >= DATE_SUB(NOW(), INTERVAL 30 DAY))                AS novos_ultimos_30_dias,
    (SELECT COUNT(*) FROM usuarios)                                        AS usuarios_do_sistema;
```

---

### 🔐 Consultas úteis para a tabela `usuarios`

**Listar usuários sem expor o hash da senha**
```sql
SELECT ID, NOME, EMAIL, TELEFONE, CRIADO_EM FROM usuarios;
```

**Consulta feita pelo login (`login.php`)**
```sql
SELECT ID, NOME, EMAIL, SENHA FROM usuarios WHERE EMAIL = 'admin@sistema.com';
```

**Trocar a senha de um usuário**

Gere o hash em PHP (nunca grave a senha em texto puro):
```bash
php -r "echo password_hash('NovaSenha@123', PASSWORD_DEFAULT);"
```
e atualize no banco:
```sql
UPDATE usuarios
SET SENHA = '$2y$10$...hash_gerado_acima...'
WHERE EMAIL = 'admin@sistema.com';
```

---

### 🔗 Consultas com as tabelas relacionadas (JOIN)

**Pedidos com o nome do cliente (INNER JOIN — relacionamento 1:N)**
```sql
SELECT p.ID, c.NOME AS cliente, p.STATUS, p.CRIADO_EM
FROM pedidos p
INNER JOIN clientes c ON c.ID = p.CLIENTE_ID;
```

**Todos os clientes e quantos pedidos cada um tem (LEFT JOIN — inclui quem tem 0)**
```sql
SELECT c.NOME, COUNT(p.ID) AS qtd_pedidos
FROM clientes c
LEFT JOIN pedidos p ON p.CLIENTE_ID = c.ID
GROUP BY c.ID, c.NOME
ORDER BY qtd_pedidos DESC;
```

**Itens de um pedido com o nome do produto (relacionamento N:N via `itens_pedido`)**
```sql
SELECT pr.NOME, i.QUANTIDADE, i.PRECO_UNITARIO,
       i.QUANTIDADE * i.PRECO_UNITARIO AS subtotal
FROM itens_pedido i
INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
WHERE i.PEDIDO_ID = 1;
```

**Total de cada pedido**
```sql
SELECT p.ID, c.NOME AS cliente, SUM(i.QUANTIDADE * i.PRECO_UNITARIO) AS total
FROM pedidos p
JOIN clientes c     ON c.ID = p.CLIENTE_ID
JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
GROUP BY p.ID, c.NOME;
```

**Consulta juntando as 5 tabelas (a mesma da página Relacionamentos)**
```sql
SELECT p.ID AS pedido, c.NOME AS cliente, u.NOME AS registrado_por,
       pr.NOME AS produto, i.QUANTIDADE, i.QUANTIDADE * i.PRECO_UNITARIO AS subtotal
FROM pedidos p
INNER JOIN clientes     c  ON c.ID  = p.CLIENTE_ID
LEFT  JOIN usuarios     u  ON u.ID  = p.USUARIO_ID
INNER JOIN itens_pedido i  ON i.PEDIDO_ID = p.ID
INNER JOIN produtos     pr ON pr.ID = i.PRODUTO_ID
ORDER BY p.ID;
```

**Produtos mais vendidos**
```sql
SELECT pr.NOME, SUM(i.QUANTIDADE) AS unidades, SUM(i.QUANTIDADE * i.PRECO_UNITARIO) AS faturamento
FROM produtos pr
JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
JOIN pedidos p      ON p.ID = i.PEDIDO_ID
WHERE p.STATUS <> 'CANCELADO'
GROUP BY pr.ID, pr.NOME
ORDER BY unidades DESC;
```

**Produtos que nunca foram vendidos**
```sql
SELECT pr.NOME
FROM produtos pr
LEFT JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
WHERE i.ID IS NULL;
```

**Testando a integridade referencial**
```sql
-- Falha com erro 1451: o cliente tem pedidos (ON DELETE RESTRICT)
DELETE FROM clientes WHERE ID = (SELECT CLIENTE_ID FROM pedidos LIMIT 1);

-- Falha com erro 1452: o cliente 9999 não existe (FK exige um cliente válido)
INSERT INTO pedidos (CLIENTE_ID) VALUES (9999);

-- Ver todas as chaves estrangeiras do banco
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'mini_sistema' AND REFERENCED_TABLE_NAME IS NOT NULL;
```

---

**Projeto educacional — uso livre.**
