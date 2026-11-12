# 📋 Sistema de Gerenciamento de Clientes

Um sistema CRUD (Create, Read, Update, Delete) simples e intuitivo para gerenciar clientes, desenvolvido com PHP, HTML, CSS e JavaScript, protegido por tela de login.

> 📘 Documentação técnica completa (arquitetura, banco de dados e guia de consultas SQL): [DOCUMENTACAO.md](DOCUMENTACAO.md)

## 🎯 Funcionalidades

- 🔐 **Login** - Acesso ao sistema protegido por autenticação com sessão
- ✅ **Cadastrar** - Adicione novos clientes ao sistema
- ✅ **Listar** - Visualize todos os clientes em uma tabela elegante
- ✅ **Atualizar** - Edite informações dos clientes existentes
- ✅ **Apagar** - Remova clientes com confirmação de segurança
- 📦 **Produtos** - Cadastre, edite e apague produtos (preço e estoque)
- 🛒 **Pedidos** - Registre pedidos de um cliente com vários produtos, altere o status e veja o total
- 🗂️ **Relacionamentos** - Página com o diagrama das 5 tabelas, seus relacionamentos e uma consulta ao vivo

## 📁 Estrutura do Projeto

```
mini_sistema/
├── login.php        # Tela de login (porta de entrada do sistema)
├── logout.php       # Encerra a sessão do usuário
├── auth.php         # Protege as páginas: exige sessão ativa
├── sessao.php       # Inicializa a sessão PHP (session_start)
├── index.php        # Página inicial (requer login)
├── cadastro.php     # Formulário de cadastro (requer login)
├── clientes.php     # Lista de clientes (requer login)
├── atualizar.php    # Formulário de atualização (requer login)
├── apagar.php       # Exclui o cliente e volta para a lista (requer login)
├── produtos.php     # Cadastro/edição/lista de produtos
├── produto_apagar.php # Exclui produto (se nunca foi vendido)
├── pedidos.php      # Lista de pedidos
├── pedido_novo.php  # Registro de pedido com itens
├── pedido_ver.php   # Detalhes do pedido e mudança de status
├── pedido_apagar.php # Exclui pedido (itens em cascata)
├── relacionamentos.php # Diagrama e explicação das 5 tabelas
├── estoque.php      # Baixa/devolução de estoque dos pedidos
├── header.php       # Cabeçalho, menu e breadcrumb compartilhados
├── footer.php       # Rodapé, toasts e modal de exclusão compartilhados
├── form_cliente.php # Campos do formulário de cliente (cadastro e atualização)
├── validacao.php    # Regras de validação do cliente (servidor)
├── ui.php           # Ícones SVG, iniciais do avatar e mensagens flash
├── css/
│   └── style.css    # Estilos CSS (inclui tela de login)
├── js/
│   ├── app.js        # Toasts e modal de confirmação de exclusão
│   ├── formulario.js # Validação e máscara dos formulários de cliente
│   ├── clientes.js   # Busca instantânea na lista
│   └── pedido.js     # Itens e total do formulário de pedido
├── imagens/         # Pasta para armazenar imagens
├── conexao.php      # Conexão com o banco mini_sistema
├── usuarios.sql     # Script de criação da tabela `usuarios` + usuário padrão
├── clientes.sql     # Script de criação da tabela `clientes`
└── relacionamentos.sql # Script de `produtos`, `pedidos` e `itens_pedido` (+ dados de exemplo)
```

## 🔐 Login

O sistema inteiro (Home, Cadastro, Listagem, Atualização e Exclusão) fica atrás de uma
tela de login. Todas as páginas protegidas incluem `auth.php`, que verifica se existe uma
sessão ativa (`$_SESSION['usuario_id']`); se não houver, o usuário é redirecionado para
`login.php`, e após autenticar é enviado de volta para a página que tentou acessar.

A autenticação consulta a tabela `usuarios` do banco `mini_sistema` via PDO, comparando a
senha informada com o hash armazenado (`password_hash`/`password_verify`). A tabela guarda
`NOME`, `EMAIL`, `TELEFONE` (opcional) e `SENHA` (hash) de cada usuário do sistema.

**Credenciais padrão** (criadas automaticamente ao rodar `usuarios.sql`):
- Email: `admin@sistema.com`
- Senha: `admin123`
- Telefone: `(11) 99999-9999`

> Troque a senha padrão após o primeiro acesso (não há tela de troca de senha ainda —
> gere um novo hash com `password_hash()` em PHP e atualize a coluna `SENHA` no banco).

## 🚀 Como Usar

### 1. Pré-requisitos
- PHP 7.0 ou superior instalado
- Apache e MySQL ativos no XAMPP
- Navegador web moderno

### 2. Iniciar o servidor

Navegue até a pasta do projeto e execute:

```bash
cd /Users/karol/Downloads/mini_sistema
php -S localhost:8000
```

### 3. Acessar o sistema

Abra seu navegador e acesse:
```
http://localhost:8000
```

## 💾 Armazenamento de Dados

Os dados ficam no MySQL do XAMPP, no banco `mini_sistema`, em **5 tabelas relacionadas**:

| Tabela         | Conteúdo                         | Relacionamentos |
|----------------|----------------------------------|-----------------|
| `usuarios`     | Usuários que fazem login         | 1:N com `pedidos` (quem registrou) |
| `clientes`     | Clientes                         | 1:N com `pedidos` (quem comprou) |
| `produtos`     | Catálogo (preço e estoque)       | 1:N com `itens_pedido` |
| `pedidos`      | Pedidos (cliente, status, data)  | FK para `clientes` e `usuarios`; 1:N com `itens_pedido` |
| `itens_pedido` | Produtos de cada pedido          | FK para `pedidos` e `produtos` — resolve o **N:N** entre pedidos e produtos |

Execute os scripts nesta ordem: `usuarios.sql`, `clientes.sql` e `relacionamentos.sql`.
Detalhes, regras `ON DELETE` e diagrama na seção 6 da [DOCUMENTACAO.md](DOCUMENTACAO.md).

## 🎨 Características

- **Design Responsivo** - Funciona em desktop, tablet e mobile
- **Interface Intuitiva** - Fácil de navegar e usar
- **Validações** - Validação de email e nome em tempo real
- **Formatação de Telefone** - Formatação automática de números de telefone
- **Mensagens de Feedback** - Alertas visuais de sucesso/erro
- **Tabela Organizada** - Visualização clara de todos os clientes

## 📝 Campos do Formulário

- **Nome** - Campo obrigatório (mínimo 3 caracteres)
- **Email** - Campo obrigatório (deve ser um email válido)
- **Telefone** - Campo opcional (formatado automaticamente)

## 🔒 Segurança

- Todas as páginas do sistema exigem login (sessão PHP validada em `auth.php`)
- Senhas armazenadas com hash bcrypt (`password_hash`/`password_verify`), nunca em texto puro
- ID de sessão regenerado a cada login (`session_regenerate_id`) para evitar fixação de sessão
- Proteção contra XSS com `htmlspecialchars()`
- Validação de dados no cliente e servidor
- Confirmação de exclusão para evitar remoções acidentais

## 📱 Navegação

- **Login** - Tela de acesso ao sistema (obrigatória)
- **Home** - Página inicial com informações gerais
- **Cadastrar Cliente** - Formulário para adicionar novo cliente
- **Listar Clientes** - Tabela com todos os clientes cadastrados (com a quantidade de pedidos)
- **Produtos** - Cadastro e lista de produtos
- **Pedidos** - Lista de pedidos, novo pedido e detalhes
- **Relacionamentos** - Diagrama das tabelas e seus relacionamentos
- **Sair** - Encerra a sessão e retorna à tela de login

## 🛠️ Tecnologias Utilizadas

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Armazenamento**: MySQL (PDO)
- **Design**: Responsivo e moderno com gradientes

## 📞 Suporte

Para dúvidas ou problemas, verifique:
1. Se o PHP está instalado corretamente
2. Se a pasta `imagens` existe
3. Se o MySQL está ativo no painel do XAMPP
4. Se o banco `mini_sistema` e as 5 tabelas (`usuarios`, `clientes`, `produtos`, `pedidos` e `itens_pedido`) foram criadas corretamente

## 📄 Licença

Projeto educacional - Uso livre

---

**Desenvolvido com ❤️ para gerenciamento simples de clientes**
