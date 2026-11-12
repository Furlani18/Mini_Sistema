-- ==========================================================================
-- Módulo de vendas: 3 novas tabelas relacionadas às tabelas já existentes
-- ==========================================================================
-- Pré-requisito: usuarios.sql e clientes.sql já executados no banco `mini_sistema`.
-- Pode ser executado mais de uma vez sem duplicar nada.
--
-- Relacionamentos criados:
--   clientes  1 ── N  pedidos       (pedidos.CLIENTE_ID      → clientes.ID)
--   usuarios  1 ── N  pedidos       (pedidos.USUARIO_ID      → usuarios.ID)
--   pedidos   1 ── N  itens_pedido  (itens_pedido.PEDIDO_ID  → pedidos.ID)
--   produtos  1 ── N  itens_pedido  (itens_pedido.PRODUTO_ID → produtos.ID)
--   pedidos   N ── N  produtos      (através da tabela associativa itens_pedido)

-- Garante que os acentos deste arquivo sejam lidos como UTF-8 (o cliente mysql do Windows
-- usa outro charset por padrão e gravaria "mecÃ¢nico" no lugar de "mecânico").
SET NAMES utf8mb4;

-- Chaves estrangeiras só funcionam no motor InnoDB: garante isso nas tabelas antigas.
ALTER TABLE usuarios ENGINE = InnoDB;
ALTER TABLE clientes ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 3) produtos: catálogo de produtos que podem ser vendidos
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produtos (
    ID        INT(11)       NOT NULL AUTO_INCREMENT,
    NOME      VARCHAR(100)  NOT NULL,
    DESCRICAO VARCHAR(255)  NULL,
    PRECO     DECIMAL(10,2) NOT NULL,
    ESTOQUE   INT(11)       NOT NULL DEFAULT 0,
    CRIADO_EM TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY UQ_PRODUTOS_NOME (NOME),
    CONSTRAINT CK_PRODUTOS_PRECO   CHECK (PRECO >= 0),
    CONSTRAINT CK_PRODUTOS_ESTOQUE CHECK (ESTOQUE >= 0)
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 4) pedidos: cada pedido pertence a UM cliente e foi registrado por UM usuário
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
    ID         INT(11)      NOT NULL AUTO_INCREMENT,
    CLIENTE_ID INT(11)      NOT NULL,
    USUARIO_ID INT(11)      NULL,
    STATUS     ENUM('ABERTO', 'PAGO', 'ENVIADO', 'CANCELADO') NOT NULL DEFAULT 'ABERTO',
    OBSERVACAO VARCHAR(255) NULL,
    CRIADO_EM  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    KEY IDX_PEDIDOS_CLIENTE (CLIENTE_ID),
    KEY IDX_PEDIDOS_USUARIO (USUARIO_ID),
    -- Não deixa apagar um cliente que tem pedidos
    CONSTRAINT FK_PEDIDOS_CLIENTE FOREIGN KEY (CLIENTE_ID)
        REFERENCES clientes (ID) ON DELETE RESTRICT ON UPDATE CASCADE,
    -- Se o usuário for apagado, o pedido continua existindo, sem "registrado por"
    CONSTRAINT FK_PEDIDOS_USUARIO FOREIGN KEY (USUARIO_ID)
        REFERENCES usuarios (ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 5) itens_pedido: tabela associativa (N:N) entre pedidos e produtos
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS itens_pedido (
    ID             INT(11)       NOT NULL AUTO_INCREMENT,
    PEDIDO_ID      INT(11)       NOT NULL,
    PRODUTO_ID     INT(11)       NOT NULL,
    QUANTIDADE     INT(11)       NOT NULL,
    PRECO_UNITARIO DECIMAL(10,2) NOT NULL, -- preço do produto no momento da venda
    PRIMARY KEY (ID),
    UNIQUE KEY UQ_ITEM_PEDIDO_PRODUTO (PEDIDO_ID, PRODUTO_ID),
    KEY IDX_ITENS_PRODUTO (PRODUTO_ID),
    CONSTRAINT CK_ITENS_QUANTIDADE CHECK (QUANTIDADE > 0),
    -- Apagar um pedido apaga junto os seus itens
    CONSTRAINT FK_ITENS_PEDIDO FOREIGN KEY (PEDIDO_ID)
        REFERENCES pedidos (ID) ON DELETE CASCADE ON UPDATE CASCADE,
    -- Não deixa apagar um produto que já foi vendido
    CONSTRAINT FK_ITENS_PRODUTO FOREIGN KEY (PRODUTO_ID)
        REFERENCES produtos (ID) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ==========================================================================
-- Dados de exemplo (só são inseridos se ainda não existirem)
-- ==========================================================================

INSERT IGNORE INTO produtos (NOME, DESCRICAO, PRECO, ESTOQUE) VALUES
    ('Notebook 15"',         'Notebook 8 GB RAM, SSD 256 GB', 3499.90, 10),
    ('Mouse sem fio',        'Mouse óptico USB sem fio',        89.90, 50),
    ('Teclado mecânico',     'Teclado ABNT2 com iluminação',   299.00, 25),
    ('Monitor 24"',          'Monitor Full HD IPS',            899.00, 15),
    ('Headset USB',          'Headset com microfone',          199.50, 30);

INSERT INTO clientes (NOME, EMAIL, TELEFONE)
SELECT 'Ana Souza', 'ana.souza@exemplo.com', '(11) 98888-1111'
WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE EMAIL = 'ana.souza@exemplo.com');

INSERT INTO clientes (NOME, EMAIL, TELEFONE)
SELECT 'Bruno Lima', 'bruno.lima@exemplo.com', '(21) 97777-2222'
WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE EMAIL = 'bruno.lima@exemplo.com');

-- Pedido de exemplo #1: Ana compra 1 notebook e 2 mouses
INSERT INTO pedidos (CLIENTE_ID, USUARIO_ID, STATUS, OBSERVACAO)
SELECT c.ID, (SELECT ID FROM usuarios WHERE EMAIL = 'admin@sistema.com'), 'PAGO', 'Pedido de exemplo #1'
FROM clientes c
WHERE c.EMAIL = 'ana.souza@exemplo.com'
  AND NOT EXISTS (SELECT 1 FROM pedidos WHERE OBSERVACAO = 'Pedido de exemplo #1');

-- Pedido de exemplo #2: Bruno compra 1 monitor, 1 teclado e 1 headset
INSERT INTO pedidos (CLIENTE_ID, USUARIO_ID, STATUS, OBSERVACAO)
SELECT c.ID, (SELECT ID FROM usuarios WHERE EMAIL = 'admin@sistema.com'), 'ABERTO', 'Pedido de exemplo #2'
FROM clientes c
WHERE c.EMAIL = 'bruno.lima@exemplo.com'
  AND NOT EXISTS (SELECT 1 FROM pedidos WHERE OBSERVACAO = 'Pedido de exemplo #2');

-- Itens (INSERT IGNORE + chave única PEDIDO_ID/PRODUTO_ID evitam duplicar)
INSERT IGNORE INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO)
SELECT p.ID, pr.ID, 1, pr.PRECO FROM pedidos p JOIN produtos pr ON pr.NOME = 'Notebook 15"'
WHERE p.OBSERVACAO = 'Pedido de exemplo #1';

INSERT IGNORE INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO)
SELECT p.ID, pr.ID, 2, pr.PRECO FROM pedidos p JOIN produtos pr ON pr.NOME = 'Mouse sem fio'
WHERE p.OBSERVACAO = 'Pedido de exemplo #1';

INSERT IGNORE INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO)
SELECT p.ID, pr.ID, 1, pr.PRECO FROM pedidos p JOIN produtos pr ON pr.NOME = 'Monitor 24"'
WHERE p.OBSERVACAO = 'Pedido de exemplo #2';

INSERT IGNORE INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO)
SELECT p.ID, pr.ID, 1, pr.PRECO FROM pedidos p JOIN produtos pr ON pr.NOME = 'Teclado mecânico'
WHERE p.OBSERVACAO = 'Pedido de exemplo #2';

INSERT IGNORE INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO)
SELECT p.ID, pr.ID, 1, pr.PRECO FROM pedidos p JOIN produtos pr ON pr.NOME = 'Headset USB'
WHERE p.OBSERVACAO = 'Pedido de exemplo #2';
