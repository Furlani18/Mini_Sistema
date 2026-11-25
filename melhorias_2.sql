-- ==========================================================================
-- Atualização 2: orçamentos, parcelas / contas a receber, metas, categorias,
-- SKU e código de barras, fornecedores e custo (margem), lixeira, data de
-- nascimento, email único de cliente e troca de senha obrigatória.
--
-- Execute DEPOIS de melhorias.sql. Só acrescenta colunas/tabelas (não apaga
-- dados) e pode ser executado mais de uma vez sem problema.
-- ==========================================================================

SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- 1) clientes: data de nascimento e email único
--    Se este passo falhar com "Duplicate entry", existem dois clientes com o
--    mesmo email: corrija um deles e rode o script de novo.
-- --------------------------------------------------------------------------
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS NASCIMENTO DATE NULL AFTER CPF_CNPJ;

ALTER TABLE clientes ADD UNIQUE KEY IF NOT EXISTS UQ_CLIENTES_EMAIL (EMAIL);

-- --------------------------------------------------------------------------
-- 2) usuarios: obrigar a troca de senha no próximo acesso
--    (marcado quando um administrador cria o usuário ou redefine a senha dele)
-- --------------------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS TROCAR_SENHA TINYINT(1) NOT NULL DEFAULT 0 AFTER PERFIL;

-- --------------------------------------------------------------------------
-- 3) pedidos: novo status ORCAMENTO (não movimenta estoque nem conta como venda)
-- --------------------------------------------------------------------------
ALTER TABLE pedidos
    MODIFY STATUS ENUM('ORCAMENTO', 'ABERTO', 'PAGO', 'ENVIADO', 'CANCELADO') NOT NULL DEFAULT 'ABERTO';

-- --------------------------------------------------------------------------
-- 4) categorias de produto
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    ID        INT(11)     NOT NULL AUTO_INCREMENT,
    NOME      VARCHAR(60) NOT NULL,
    CRIADO_EM TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY UQ_CATEGORIAS_NOME (NOME)
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 5) produtos: categoria, SKU, código de barras e custo médio
--    CUSTO = custo médio ponderado, recalculado a cada entrada com custo informado
-- --------------------------------------------------------------------------
ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS CATEGORIA_ID  INT(11)       NULL AFTER DESCRICAO,
    ADD COLUMN IF NOT EXISTS SKU           VARCHAR(40)   NULL AFTER CATEGORIA_ID,
    ADD COLUMN IF NOT EXISTS CODIGO_BARRAS VARCHAR(14)   NULL AFTER SKU,
    ADD COLUMN IF NOT EXISTS CUSTO         DECIMAL(10,2) NULL AFTER PRECO;

ALTER TABLE produtos ADD UNIQUE KEY IF NOT EXISTS UQ_PRODUTOS_SKU (SKU);
ALTER TABLE produtos ADD UNIQUE KEY IF NOT EXISTS UQ_PRODUTOS_CODIGO_BARRAS (CODIGO_BARRAS);
ALTER TABLE produtos ADD KEY IF NOT EXISTS IDX_PRODUTOS_CATEGORIA (CATEGORIA_ID);

-- Apagar uma categoria deixa os produtos dela "sem categoria"
SET @fk := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'FK_PRODUTOS_CATEGORIA');
SET @sql := IF(@fk = 0,
    'ALTER TABLE produtos ADD CONSTRAINT FK_PRODUTOS_CATEGORIA FOREIGN KEY (CATEGORIA_ID)
        REFERENCES categorias (ID) ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0');
PREPARE passo FROM @sql; EXECUTE passo; DEALLOCATE PREPARE passo;

-- --------------------------------------------------------------------------
-- 6) fornecedores
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fornecedores (
    ID          INT(11)      NOT NULL AUTO_INCREMENT,
    NOME        VARCHAR(100) NOT NULL,
    CPF_CNPJ    VARCHAR(14)  NULL,
    CONTATO     VARCHAR(100) NULL,
    EMAIL       VARCHAR(100) NULL,
    TELEFONE    VARCHAR(20)  NULL,
    OBSERVACOES TEXT         NULL,
    CRIADO_EM   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY UQ_FORNECEDORES_NOME (NOME),
    UNIQUE KEY UQ_FORNECEDORES_CPF_CNPJ (CPF_CNPJ)
) ENGINE = InnoDB;

-- Entradas de estoque passam a guardar o fornecedor e o custo unitário pago
ALTER TABLE movimentos_estoque
    ADD COLUMN IF NOT EXISTS FORNECEDOR_ID  INT(11)       NULL AFTER USUARIO_ID,
    ADD COLUMN IF NOT EXISTS CUSTO_UNITARIO DECIMAL(10,2) NULL AFTER QUANTIDADE;

ALTER TABLE movimentos_estoque ADD KEY IF NOT EXISTS IDX_MOVIMENTOS_FORNECEDOR (FORNECEDOR_ID);

-- Apagar o fornecedor mantém o histórico de entradas (sem o fornecedor)
SET @fk := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'FK_MOVIMENTOS_FORNECEDOR');
SET @sql := IF(@fk = 0,
    'ALTER TABLE movimentos_estoque ADD CONSTRAINT FK_MOVIMENTOS_FORNECEDOR FOREIGN KEY (FORNECEDOR_ID)
        REFERENCES fornecedores (ID) ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0');
PREPARE passo FROM @sql; EXECUTE passo; DEALLOCATE PREPARE passo;

-- --------------------------------------------------------------------------
-- 7) itens_pedido: custo do produto no momento da venda (para a margem de lucro)
-- --------------------------------------------------------------------------
ALTER TABLE itens_pedido
    ADD COLUMN IF NOT EXISTS CUSTO_UNITARIO DECIMAL(10,2) NULL AFTER PRECO_UNITARIO;

-- --------------------------------------------------------------------------
-- 8) parcelas: como o pedido vai ser pago (contas a receber)
--    PAGO_EM NULL = ainda não recebida
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parcelas (
    ID              INT(11)       NOT NULL AUTO_INCREMENT,
    PEDIDO_ID       INT(11)       NOT NULL,
    NUMERO          TINYINT       NOT NULL,
    VALOR           DECIMAL(10,2) NOT NULL,
    VENCIMENTO      DATE          NOT NULL,
    PAGO_EM         DATE          NULL,
    FORMA_PAGAMENTO ENUM('PIX', 'DINHEIRO', 'CREDITO', 'DEBITO', 'BOLETO') NULL,
    CRIADO_EM       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY UQ_PARCELAS_PEDIDO_NUMERO (PEDIDO_ID, NUMERO),
    KEY IDX_PARCELAS_VENCIMENTO (PAGO_EM, VENCIMENTO),
    CONSTRAINT CK_PARCELAS_VALOR CHECK (VALOR > 0),
    -- Apagar o pedido apaga as parcelas dele
    CONSTRAINT FK_PARCELAS_PEDIDO FOREIGN KEY (PEDIDO_ID)
        REFERENCES pedidos (ID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 9) configuracoes: ajustes do sistema (ex.: meta de vendas do mês)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes (
    CHAVE VARCHAR(50)  NOT NULL,
    VALOR VARCHAR(255) NULL,
    PRIMARY KEY (CHAVE)
) ENGINE = InnoDB;

INSERT IGNORE INTO configuracoes (CHAVE, VALOR) VALUES ('meta_mensal', NULL);

-- --------------------------------------------------------------------------
-- 10) lixeira: cópia (JSON) de clientes, produtos e pedidos apagados,
--     para que um administrador possa restaurar
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lixeira (
    ID           INT(11)      NOT NULL AUTO_INCREMENT,
    ENTIDADE     VARCHAR(30)  NOT NULL,
    ENTIDADE_ID  INT(11)      NOT NULL,
    DESCRICAO    VARCHAR(255) NOT NULL,
    DADOS        LONGTEXT     NOT NULL,
    USUARIO_ID   INT(11)      NULL,
    USUARIO_NOME VARCHAR(100) NULL,
    APAGADO_EM   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    KEY IDX_LIXEIRA_ENTIDADE (ENTIDADE, APAGADO_EM),
    CONSTRAINT CK_LIXEIRA_JSON CHECK (JSON_VALID(DADOS)),
    CONSTRAINT FK_LIXEIRA_USUARIO FOREIGN KEY (USUARIO_ID)
        REFERENCES usuarios (ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 11) vw_pedidos: agora também com o custo das mercadorias de cada pedido
--     CUSTO           = soma de quantidade × custo unitário (itens com custo conhecido)
--     ITENS_SEM_CUSTO = itens cujo custo não foi informado (a margem fica incompleta)
-- --------------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_pedidos AS
SELECT p.ID, p.CLIENTE_ID, p.USUARIO_ID, p.STATUS, p.OBSERVACAO, p.DESCONTO, p.FORMA_PAGAMENTO, p.CRIADO_EM,
       COUNT(i.ID)                                                          AS ITENS,
       COALESCE(SUM(i.QUANTIDADE), 0)                                       AS UNIDADES,
       COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0)                    AS SUBTOTAL,
       GREATEST(COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0) - p.DESCONTO, 0) AS TOTAL,
       COALESCE(SUM(i.QUANTIDADE * i.CUSTO_UNITARIO), 0)                    AS CUSTO,
       COUNT(CASE WHEN i.ID IS NOT NULL AND i.CUSTO_UNITARIO IS NULL THEN 1 END) AS ITENS_SEM_CUSTO
  FROM pedidos p
  LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
 GROUP BY p.ID, p.CLIENTE_ID, p.USUARIO_ID, p.STATUS, p.OBSERVACAO, p.DESCONTO, p.FORMA_PAGAMENTO, p.CRIADO_EM;
