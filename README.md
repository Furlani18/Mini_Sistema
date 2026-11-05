# 📋 Sistema de Gerenciamento de Clientes

Um sistema CRUD (Create, Read, Update, Delete) simples e intuitivo para gerenciar clientes, desenvolvido com PHP, HTML, CSS e JavaScript, protegido por tela de login.

> 📘 Documentação técnica completa (arquitetura, banco de dados e guia de consultas SQL): [DOCUMENTACAO.md](DOCUMENTACAO.md)

## 🎯 Funcionalidades

- 🔐 **Login** - Acesso ao sistema protegido por autenticação com sessão
- ✅ **Cadastrar** - Adicione novos clientes ao sistema
- ✅ **Listar** - Visualize todos os clientes em uma tabela elegante
- ✅ **Atualizar** - Edite informações dos clientes existentes
- ✅ **Apagar** - Remova clientes com confirmação de segurança

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
├── apagar.php       # Página de exclusão (requer login)
├── css/
│   └── styly.css    # Estilos CSS (inclui tela de login)
├── js/
│   ├── cadastro.js  # Validações de cadastro
│   └── atualizar.js # Validações de atualização
├── imagens/         # Pasta para armazenar imagens
├── conexao.php      # Conexão com o banco mini_sistema
├── usuarios.sql     # Script de criação da tabela `usuarios` + usuário padrão
└── clientes.sql     # Script de criação da tabela `clientes`
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

Os dados dos clientes são armazenados no MySQL do XAMPP, no banco `mini_sistema`, tabela `clientes`. A tabela deve conter as colunas `ID` (chave primaria com auto incremento), `NOME`, `EMAIL` e `TELEFONE`.

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
- **Listar Clientes** - Tabela com todos os clientes cadastrados
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
4. Se o banco `mini_sistema` e as tabelas `clientes` e `usuarios` foram criadas corretamente

## 📄 Licença

Projeto educacional - Uso livre

---

**Desenvolvido com ❤️ para gerenciamento simples de clientes**
