<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sistema de Gerenciamento de Clientes</title>
    <link rel="stylesheet" href="css/styly.css" />
  </head>
  <body>
    <div class="container">
      <header>
        <div class="header-top">
          <h1><span class="brand-mark">CRM</span> Gestão de Clientes</h1>
          <div class="user-bar">
            <span class="user-name">👤 <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></span>
            <a href="logout.php" class="btn btn-secondary btn-sm">🚪 Sair</a>
          </div>
        </div>
        <nav>
          <a href="index.php" class="nav-link active">Home</a>
          <a href="cadastro.php" class="nav-link">Cadastrar Cliente</a>
          <a href="clientes.php" class="nav-link">Listar Clientes</a>
        </nav>
      </header>

      <main>
        <section class="welcome">
          <div class="welcome-content">
            <img
              src="imagens/bem-vindo.jpeg"
              alt="Bem-vindo"
              class="welcome-image"
            />
            <h2>Bem-vindo ao Sistema</h2>
            <p>
              Gerencie facilmente seus clientes com nosso sistema intuitivo.
            </p>
            <div class="actions">
              <a href="cadastro.php" class="btn btn-primary">➕ Novo Cliente</a>
              <a href="clientes.php" class="btn btn-secondary"
                >👥 Ver Clientes</a
              >
            </div>
          </div>
        </section>

        <section class="features">
          <h3>Funcionalidades</h3>
          <div class="features-grid">
            <div class="feature-card">
              <span class="feature-icon">➕</span>
              <h4>Cadastrar</h4>
              <p>Adicione novos clientes ao sistema</p>
            </div>
            <div class="feature-card">
              <span class="feature-icon">📋</span>
              <h4>Listar</h4>
              <p>Visualize todos os clientes cadastrados</p>
            </div>
            <div class="feature-card">
              <span class="feature-icon">✏️</span>
              <h4>Atualizar</h4>
              <p>Modifique informações dos clientes</p>
            </div>
            <div class="feature-card">
              <span class="feature-icon">🗑️</span>
              <h4>Apagar</h4>
              <p>Remova clientes do sistema</p>
            </div>
          </div>
        </section>
      </main>

      <footer>
        <p>
          &copy; 2026 Sistema de Gerenciamento de Clientes. Todos os direitos
          reservados.
        </p>
      </footer>
    </div>
  </body>
</html>
