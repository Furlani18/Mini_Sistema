# 📋 Sistema de Gerenciamento de Clientes

Sistema web para gerenciar clientes, produtos, pedidos e estoque, desenvolvido com PHP (sem framework), MySQL/MariaDB, HTML, CSS e JavaScript, protegido por login com níveis de acesso.

> 📘 Documentação técnica completa (arquitetura, banco de dados, segurança e guia de consultas SQL): [DOCUMENTACAO.md](DOCUMENTACAO.md)

## 🎯 Funcionalidades

**Clientes**
- 👤 **Cadastro completo** - Nome, email, telefone, CPF/CNPJ (com validação dos dígitos), endereço e observações
- 📮 **Endereço pelo CEP** - Logradouro, bairro, cidade e UF preenchidos automaticamente (ViaCEP)
- 🗂️ **Ficha do cliente** - Histórico de pedidos, total gasto, ticket médio, última compra e produtos mais comprados
- 📥 **Importação de planilha** - Clientes em lote a partir de um CSV do Excel, com verificação antes de gravar

**Produtos e estoque**
- 📦 **Produtos** - Preço, estoque inicial e situação do estoque (baixo / esgotado)
- 🔁 **Histórico de estoque** - Toda entrada, saída, venda, devolução e ajuste fica registrada, com motivo e responsável

**Pedidos**
- 🛒 **Pedidos** - Vários produtos por pedido, desconto, forma de pagamento e status
- ✏️ **Edição** - Pedidos em aberto podem ser alterados; o estoque é ajustado só pela diferença
- 🖨️ **Comprovante** - Página pronta para imprimir ou salvar em PDF

**Gestão**
- 📊 **Relatórios** - Faturamento por mês/dia, pedidos por status, produtos mais vendidos e melhores clientes, com comparação ao período anterior e exportação em CSV
- 🔎 **Listas** - Busca, filtros, ordenação por coluna e paginação em todas as listas
- 🛡️ **Usuários e perfis** - Administradores e operadores; "Meu perfil" para trocar dados e senha
- 🕑 **Registro de atividades** - Quem criou, alterou, apagou ou importou o quê, e quando
- 🌙 **Modo escuro** - Alternável no menu (ou automático, conforme o sistema operacional)
- 🗃️ **Banco de dados** - Página com o diagrama das tabelas, seus relacionamentos e uma consulta ao vivo

## 🚀 Como Usar

### 1. Pré-requisitos
- XAMPP com **PHP 8.0+** e **MariaDB 10.2+** (o XAMPP atual já serve)
- Apache e MySQL ativos no painel do XAMPP

### 2. Criar o banco

Crie o banco `mini_sistema` (charset `utf8mb4`) e execute os scripts **nesta ordem**:

```bash
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < usuarios.sql
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < clientes.sql
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < relacionamentos.sql
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < melhorias.sql
```

Os scripts podem ser executados mais de uma vez sem duplicar nada. Se o banco já existia de uma
versão anterior, rode apenas o `melhorias.sql` (ele só acrescenta colunas e tabelas).

### 3. Acessar

```
http://localhost/mini_sistema/mini_sistema/login.php
```

Ou, dentro da pasta do projeto, `php -S localhost:8000` e acesse `http://localhost:8000`.

**Primeiro acesso:** `admin@sistema.com` / `admin123`. Troque a senha em **Meu perfil** (clique no
seu nome no menu). Enquanto ela não for trocada, o painel mostra um aviso.

## 🔐 Níveis de acesso

| Ação | Administrador | Operador |
|------|:---:|:---:|
| Painel, relatórios, listas e fichas | ✅ | ✅ |
| Cadastrar e editar clientes, produtos e pedidos | ✅ | ✅ |
| Importar clientes e movimentar estoque | ✅ | ✅ |
| Apagar clientes, produtos e pedidos | ✅ | ❌ |
| Gerenciar usuários e ver o registro de atividades | ✅ | ❌ |

## 🔒 Segurança

- Login obrigatório, senhas com hash bcrypt e bloqueio temporário após 5 tentativas erradas
- Token CSRF em todos os formulários; exclusões só por `POST` com confirmação
- Cookie de sessão `httponly` e `samesite`; ID de sessão regenerado no login e na troca de senha
- *Prepared statements* (anti SQL injection) e `htmlspecialchars()` (anti XSS)
- Validação no navegador e no servidor; chaves estrangeiras e transações garantem a integridade
- Registro de atividades para auditoria

Detalhes na seção 9 da [DOCUMENTACAO.md](DOCUMENTACAO.md).

## 💾 Banco de dados

5 tabelas principais relacionadas (`usuarios`, `clientes`, `produtos`, `pedidos` e
`itens_pedido`, que resolve o N:N entre pedidos e produtos), mais `movimentos_estoque`,
`atividades`, `tentativas_login` e a visão `vw_pedidos` (totais de cada pedido já com desconto).
Estrutura, regras `ON DELETE` e diagrama na seção 6 da [DOCUMENTACAO.md](DOCUMENTACAO.md).

## 📁 Estrutura

A lista completa de arquivos, com a função de cada um, está na seção 4 da
[DOCUMENTACAO.md](DOCUMENTACAO.md). Em resumo:

- Páginas: `index.php`, `relatorios.php`, `clientes.php`, `cliente_ver.php`, `produtos.php`,
  `produto_estoque.php`, `pedidos.php`, `pedido_ver.php`, `usuarios.php`, `atividades.php`, `perfil.php`…
- Compartilhados: `auth.php`, `seguranca.php`, `lista.php`, `ui.php`, `validacao.php`, `estoque.php`,
  `header.php`, `footer.php`
- `css/style.css` (inclui modo escuro e impressão) e `js/` (validação, máscaras, CEP, pedido, gráfico)
- Scripts do banco: `usuarios.sql`, `clientes.sql`, `relacionamentos.sql`, `melhorias.sql`

## 🛠️ Tecnologias

- **Backend**: PHP 8 (puro, sem framework), PDO
- **Banco**: MariaDB/MySQL com InnoDB (chaves estrangeiras, transações, visão)
- **Frontend**: HTML5, CSS3 (variáveis, responsivo, modo escuro), JavaScript puro
- **Serviço externo opcional**: ViaCEP (busca de endereço)

## 📞 Problemas comuns

1. **Tela branca ou erro de conexão**: confira se o MySQL está ativo no XAMPP.
2. **Erro de coluna ou tabela inexistente**: rode o `melhorias.sql`.
3. **"Muitas tentativas erradas"**: aguarde 15 minutos ou limpe a tabela `tentativas_login`.
4. **CEP não preenche o endereço**: a busca precisa de internet; sem ela, digite à mão.

## 📄 Licença

Projeto educacional - Uso livre
