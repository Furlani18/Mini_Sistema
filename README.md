# 📋 Sistema de Gerenciamento de Clientes

Sistema web para gerenciar clientes, produtos, pedidos e estoque, com uma **loja online** pública na frente, desenvolvido com PHP (sem framework), MySQL/MariaDB, HTML, CSS e JavaScript. A área interna é protegida por login com níveis de acesso.

> 📘 Documentação técnica completa (arquitetura, banco de dados, segurança e guia de consultas SQL): [DOCUMENTACAO.md](DOCUMENTACAO.md)

## 🎯 Funcionalidades

**Loja online (pública, sem login)**
- 🛍️ **Vitrine** - Banner, destaques, categorias, busca, ordenação e fotos dos produtos
- 🛒 **Carrinho** - Quantidades limitadas ao estoque, sempre com o preço atual
- ✅ **Finalização** - Dados de contato, entrega em casa (endereço pelo CEP) ou retirada, e forma de pagamento. O pedido entra no sistema como "Aberto", com o selo **Loja online**
- 💬 **WhatsApp** - Botões de dúvida e de aviso do pedido para o número da loja
- 🔒 **Proteções** - Token CSRF, campo-isca contra robôs e limite de pedidos por hora

**Clientes**
- 👤 **Cadastro completo** - Nome, email (único), telefone, CPF/CNPJ (com validação dos dígitos), data de nascimento, endereço e observações
- 📮 **Endereço pelo CEP** - Logradouro, bairro, cidade e UF preenchidos automaticamente (ViaCEP)
- 🗂️ **Ficha do cliente** - Histórico de pedidos, total gasto, ticket médio, última compra, produtos mais comprados e botão de WhatsApp
- 🎂 **Aniversariantes** - Os do mês aparecem no painel, com atalho para dar parabéns pelo WhatsApp
- 📥 **Importação de planilha** - Clientes em lote a partir de um CSV do Excel, com verificação antes de gravar

**Produtos, estoque e fornecedores**
- 📦 **Produtos** - Foto, categoria, SKU, código de barras, preço, custo e margem; "mostrar na loja" e "destaque"
- 🏷️ **Categorias** - Filtro na lista de produtos e vendas por categoria nos relatórios
- 🚚 **Fornecedores** - Cada entrada de estoque registra de quem foi a compra e quanto custou (custo médio automático)
- 🔁 **Histórico de estoque** - Toda entrada, saída, venda, devolução e ajuste fica registrada, com motivo e responsável

**Vendas e financeiro**
- 📝 **Orçamentos** - Não reservam estoque; viram pedido com um clique
- 🛒 **Pedidos** - Vários produtos por pedido (inclusive por leitor de código de barras), desconto, forma de pagamento e status
- 💳 **Parcelas** - Divisão em até 12x; ao receber a última, o pedido vira "Pago" sozinho
- 💰 **Contas a receber** - A receber, vencido (com cobrança pelo WhatsApp) e recebido no mês
- 🎯 **Meta do mês** - Progresso e projeção de fechamento no painel
- 🖨️ **Comprovante** - Pedido ou orçamento pronto para imprimir, salvar em PDF ou enviar pelo WhatsApp

**Gestão**
- 📊 **Relatórios** - Faturamento, lucro bruto e margem, pedidos por status, produtos mais vendidos, melhores clientes e vendas por categoria, com comparação ao período anterior e exportação em CSV
- 🔎 **Busca global** - `Ctrl+K` em qualquer tela procura clientes, produtos, pedidos e fornecedores
- 📋 **Listas** - Busca, filtros, ordenação (também no celular) e paginação em todas as listas
- 🛡️ **Usuários e perfis** - Administradores e operadores; senha temporária com troca obrigatória no primeiro acesso
- 🗑️ **Lixeira** - Clientes, produtos e pedidos apagados podem ser restaurados
- 🕑 **Registro de atividades** - Quem criou, alterou, apagou, recebeu ou restaurou o quê, e quando
- 💾 **Backup** - Download de um `.sql` com todo o banco, pelo próprio sistema
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
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < melhorias_2.sql
C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < melhorias_3.sql
```

Os scripts podem ser executados mais de uma vez sem duplicar nada. Se o banco já existia de uma
versão anterior, rode só os que faltam (`melhorias.sql`, `melhorias_2.sql` e/ou `melhorias_3.sql`):
eles só acrescentam colunas e tabelas.

### 3. Configurar o acesso ao banco

Copie `config.exemplo.php` para `config.php` e ajuste usuário e senha, se não forem os do XAMPP
(`root` sem senha). O `config.php` fica fora do git.

### 4. Acessar

```
http://localhost/mini_sistema/mini_sistema/
```

Visitantes veem a **loja online**; a equipe entra pelo link **Área da equipe** (ou em `login.php`).
Nome, slogan, WhatsApp e abrir/fechar a loja ficam em **Configurações**; foto e "mostrar na loja",
no cadastro de cada produto.

Ou, dentro da pasta do projeto, `php -S localhost:8000` e acesse `http://localhost:8000`.

**Primeiro acesso:** `admin@sistema.com` / `admin123`. Troque a senha em **Meu perfil** (clique no
seu nome no menu). Enquanto ela não for trocada, o painel mostra um aviso.

### 5. Testes automatizados

```bash
php tests/executar.php
```

Não precisa de Composer: o executor é PHP puro. Os testes de banco rodam numa transação desfeita
no final, então não deixam dados para trás.

## 🔐 Níveis de acesso

| Ação | Administrador | Operador |
|------|:---:|:---:|
| Painel, relatórios, listas e fichas | ✅ | ✅ |
| Cadastrar e editar clientes, produtos e pedidos | ✅ | ✅ |
| Importar clientes e movimentar estoque | ✅ | ✅ |
| Orçamentos, parcelas e contas a receber | ✅ | ✅ |
| Fornecedores e categorias (criar e editar) | ✅ | ✅ |
| Apagar registros (vão para a lixeira) | ✅ | ❌ |
| Gerenciar usuários e ver o registro de atividades | ✅ | ❌ |
| Lixeira, meta de vendas e backup | ✅ | ❌ |

## 🔒 Segurança

- Login obrigatório, senhas com hash bcrypt e bloqueio temporário após 5 tentativas erradas
- Token CSRF em todos os formulários; exclusões só por `POST` com confirmação
- Cookie de sessão `httponly` e `samesite`; ID de sessão regenerado no login e na troca de senha
- *Prepared statements* (anti SQL injection) e `htmlspecialchars()` (anti XSS)
- Validação no navegador e no servidor; chaves estrangeiras e transações garantem a integridade
- Registro de atividades para auditoria
- Senha do banco fora do código (`config.php`) e troca obrigatória de senhas temporárias

Detalhes na seção 9 da [DOCUMENTACAO.md](DOCUMENTACAO.md).

## 💾 Banco de dados

5 tabelas principais relacionadas (`usuarios`, `clientes`, `produtos`, `pedidos` e
`itens_pedido`, que resolve o N:N entre pedidos e produtos), mais `categorias`, `fornecedores`,
`parcelas`, `movimentos_estoque`, `configuracoes`, `lixeira`, `atividades`, `tentativas_login` e a
visão `vw_pedidos` (totais e custo de cada pedido, já com desconto).
Estrutura, regras `ON DELETE` e diagrama na seção 6 da [DOCUMENTACAO.md](DOCUMENTACAO.md).

## 📁 Estrutura

A lista completa de arquivos, com a função de cada um, está na seção 4 da
[DOCUMENTACAO.md](DOCUMENTACAO.md). Em resumo:

- Loja online: `loja.php`, `loja_produto.php`, `loja_carrinho.php`, `loja_finalizar.php`, `loja_pedido.php`
  (+ `loja_inicio.php`, `loja_layout.php`, `loja_funcoes.php`, `css/loja.css`, `js/loja.js`)
- Páginas: `index.php`, `relatorios.php`, `clientes.php`, `cliente_ver.php`, `produtos.php`,
  `produto_estoque.php`, `fornecedores.php`, `categorias.php`, `pedidos.php`, `pedido_ver.php`,
  `contas_receber.php`, `usuarios.php`, `atividades.php`, `lixeira.php`, `configuracoes.php`, `perfil.php`…
- Compartilhados: `auth.php`, `seguranca.php`, `lista.php`, `ui.php`, `validacao.php`, `estoque.php`,
  `financeiro.php`, `lixeira_funcoes.php`, `header.php`, `footer.php`
- `css/style.css` (inclui modo escuro e impressão) e `js/` (validação, máscaras, CEP, pedido, gráfico, busca)
- Scripts do banco: `usuarios.sql`, `clientes.sql`, `relacionamentos.sql`, `melhorias.sql`, `melhorias_2.sql`, `melhorias_3.sql`
- `uploads/produtos/`: fotos dos produtos (fora do git; copie junto com o backup)
- `tests/`: testes automatizados

## 🛠️ Tecnologias

- **Backend**: PHP 8 (puro, sem framework), PDO
- **Banco**: MariaDB/MySQL com InnoDB (chaves estrangeiras, transações, visão)
- **Frontend**: HTML5, CSS3 (variáveis, responsivo, modo escuro), JavaScript puro
- **Serviços externos opcionais**: ViaCEP (busca de endereço) e links `wa.me` do WhatsApp

## 📞 Problemas comuns

1. **Tela branca ou erro de conexão**: confira se o MySQL está ativo no XAMPP.
2. **Erro de coluna ou tabela inexistente**: rode o `melhorias.sql`, o `melhorias_2.sql` e o `melhorias_3.sql`.
   Se o `melhorias_2.sql` parar com "Duplicate entry", há dois clientes com o mesmo email: corrija um e rode de novo.
3. **"Muitas tentativas erradas"**: aguarde 15 minutos ou limpe a tabela `tentativas_login`.
4. **CEP não preenche o endereço**: a busca precisa de internet; sem ela, digite à mão.
5. **Foto do produto não salva**: confira se a pasta `uploads/produtos` existe e se o Apache pode gravar nela.
6. **Usuário esqueceu a senha**: um administrador abre o usuário, gera uma senha temporária e passa para ele; no primeiro acesso ele escolhe uma nova.

## 📄 Licença

Projeto educacional - Uso livre
