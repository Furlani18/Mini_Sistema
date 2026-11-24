-- ==========================================================================
-- Atualização do banco: níveis de acesso, segurança do login, cadastro completo
-- de clientes, desconto/pagamento nos pedidos, histórico de estoque e registro
-- de atividades.
--
-- Execute DEPOIS de usuarios.sql, clientes.sql e relacionamentos.sql.
-- O script só acrescenta colunas/tabelas (não apaga dados) e pode ser
-- executado mais de uma vez sem problema.
-- ==========================================================================

-- --------------------------------------------------------------------------
-- 1) usuarios: nível de acesso
--    ADMIN    → tudo, inclusive usuários, exclusões e registro de atividades
--    OPERADOR → cadastra e edita clientes, produtos e pedidos (não apaga)
-- --------------------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS PERFIL ENUM('ADMIN', 'OPERADOR') NOT NULL DEFAULT 'OPERADOR' AFTER SENHA;

UPDATE usuarios SET PERFIL = 'ADMIN' WHERE EMAIL = 'admin@sistema.com';

-- Garante pelo menos um administrador (promove o usuário mais antigo, se preciso)
UPDATE usuarios SET PERFIL = 'ADMIN'
 WHERE ID = (SELECT ID FROM (SELECT MIN(ID) AS ID FROM usuarios) AS primeiro)
   AND NOT EXISTS (SELECT 1 FROM (SELECT ID FROM usuarios WHERE PERFIL = 'ADMIN') AS admins);

-- --------------------------------------------------------------------------
-- 2) tentativas_login: limita tentativas de senha (contra "chute" de senhas)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentativas_login (
    ID        INT(11)      NOT NULL AUTO_INCREMENT,
    EMAIL     VARCHAR(100) NOT NULL,
    IP        VARCHAR(45)  NOT NULL,
    CRIADO_EM TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    KEY IDX_TENTATIVAS_EMAIL (EMAIL, CRIADO_EM),
    KEY IDX_TENTATIVAS_IP (IP, CRIADO_EM)
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------
-- 3) clientes: documento, endereço e observações
--    CPF_CNPJ guarda só os dígitos (11 = CPF, 14 = CNPJ); NULL = não informado
-- --------------------------------------------------------------------------
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS CPF_CNPJ    VARCHAR(14)  NULL AFTER TELEFONE,
    ADD COLUMN IF NOT EXISTS CEP         VARCHAR(8)   NULL AFTER CPF_CNPJ,
    ADD COLUMN IF NOT EXISTS LOGRADOURO  VARCHAR(150) NULL AFTER CEP,
    ADD COLUMN IF NOT EXISTS NUMERO      VARCHAR(20)  NULL AFTER LOGRADOURO,
    ADD COLUMN IF NOT EXISTS COMPLEMENTO VARCHAR(100) NULL AFTER NUMERO,
    ADD COLUMN IF NOT EXISTS BAIRRO      VARCHAR(100) NULL AFTER COMPLEMENTO,
    ADD COLUMN IF NOT EXISTS CIDADE      VARCHAR(100) NULL AFTER BAIRRO,
    ADD COLUMN IF NOT EXISTS UF          CHAR(2)      NULL AFTER CIDADE,
    ADD COLUMN IF NOT EXISTS OBSERVACOES TEXT         NULL AFTER UF;

-- Dois clientes não podem ter o mesmo CPF/CNPJ (vários NULL são permitidos)
ALTER TABLE clientes ADD UNIQUE KEY IF NOT EXISTS UQ_CLIENTES_CPF_CNPJ (CPF_CNPJ);

-- --------------------------------------------------------------------------
-- 4) pedidos: desconto (em R$) e forma de pagamento
-- --------------------------------------------------------------------------
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS DESCONTO DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER OBSERVACAO,
    ADD COLUMN IF NOT EXISTS FORMA_PAGAMENTO ENUM('PIX', 'DINHEIRO', 'CREDITO', 'DEBITO', 'BOLETO') NULL AFTER DESCONTO;

-- Visão com os totais de cada pedido. Use-a sempre que precisar do valor do pedido:
--   SUBTOTAL = soma dos itens | TOTAL = SUBTOTAL - DESCONTO (nunca negativo)
CREATE OR REPLACE VIEW vw_pedidos AS
SELECT p.ID, p.CLIENTE_ID, p.USUARIO_ID, p.STATUS, p.OBSERVACAO, p.DESCONTO, p.FORMA_PAGAMENTO, p.CRIADO_EM,
       COUNT(i.ID)                                                          AS ITENS,
       COALESCE(SUM(i.QUANTIDADE), 0)                                       AS UNIDADES,
       COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0)                    AS SUBTOTAL,
       GREATEST(COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0) - p.DESCONTO, 0) AS TOTAL
  FROM pedidos p
  LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
 GROUP BY p.ID, p.CLIENTE_ID, p.USUARIO_ID, p.STATUS, p.OBSERVACAO, p.DESCONTO, p.FORMA_PAGAMENTO, p.CRIADO_EM;

-- --------------------------------------------------------------------------
-- 5) movimentos_estoque: histórico de toda entrada e saída de estoque
--    QUANTIDADE positiva = entrada, negativa = saída
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimentos_estoque (
    ID           INT(11)      NOT NULL AUTO_INCREMENT,
    PRODUTO_ID   INT(11)      NOT NULL,
    PEDIDO_ID    INT(11)      NULL,
    USUARIO_ID   INT(11)      NULL,
    TIPO         ENUM('CADASTRO', 'ENTRADA', 'SAIDA', 'VENDA', 'DEVOLUCAO', 'AJUSTE') NOT NULL,
    QUANTIDADE   INT(11)      NOT NULL,
    ESTOQUE_APOS INT(11)      NOT NULL,
    MOTIVO       VARCHAR(255) NOT NULL,
    CRIADO_EM    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    KEY IDX_MOVIMENTOS_PRODUTO (PRODUTO_ID, CRIADO_EM),
    -- Apagar o produto apaga o histórico dele (só produtos nunca vendidos podem ser apagados)
    CONSTRAINT FK_MOVIMENTOS_PRODUTO FOREIGN KEY (PRODUTO_ID)
        REFERENCES produtos (ID) ON DELETE CASCADE ON UPDATE CASCADE,
    -- Apagar o pedido mantém o histórico (o MOTIVO guarda o número do pedido)
    CONSTRAINT FK_MOVIMENTOS_PEDIDO FOREIGN KEY (PEDIDO_ID)
        REFERENCES pedidos (ID) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT FK_MOVIMENTOS_USUARIO FOREIGN KEY (USUARIO_ID)
        REFERENCES usuarios (ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Saldo inicial: produtos que ainda não têm histórico ganham um registro com o estoque atual
INSERT INTO movimentos_estoque (PRODUTO_ID, TIPO, QUANTIDADE, ESTOQUE_APOS, MOTIVO)
SELECT pr.ID, 'CADASTRO', pr.ESTOQUE, pr.ESTOQUE, 'Saldo inicial (início do histórico de estoque)'
  FROM produtos pr
 WHERE NOT EXISTS (SELECT 1 FROM movimentos_estoque m WHERE m.PRODUTO_ID = pr.ID);

-- --------------------------------------------------------------------------
-- 6) atividades: registro de quem fez o quê e quando
--    USUARIO_NOME guarda o nome no momento da ação (continua legível se o usuário for apagado)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS atividades (
    ID           INT(11)      NOT NULL AUTO_INCREMENT,
    USUARIO_ID   INT(11)      NULL,
    USUARIO_NOME VARCHAR(100) NULL,
    ACAO         VARCHAR(20)  NOT NULL,
    ENTIDADE     VARCHAR(30)  NULL,
    ENTIDADE_ID  INT(11)      NULL,
    DESCRICAO    VARCHAR(255) NOT NULL,
    IP           VARCHAR(45)  NULL,
    CRIADO_EM    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    KEY IDX_ATIVIDADES_DATA (CRIADO_EM),
    KEY IDX_ATIVIDADES_ENTIDADE (ENTIDADE, ENTIDADE_ID),
    CONSTRAINT FK_ATIVIDADES_USUARIO FOREIGN KEY (USUARIO_ID)
        REFERENCES usuarios (ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB;
