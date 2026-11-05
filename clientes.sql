-- Tabela de clientes gerenciados pelo CRUD (cadastro, listagem, atualização e exclusão)
-- Execute este script no banco `mini_sistema` (via phpMyAdmin ou linha de comando)

CREATE TABLE IF NOT EXISTS clientes (
    ID INT(11) NOT NULL AUTO_INCREMENT,
    NOME VARCHAR(100) NOT NULL,
    EMAIL VARCHAR(100) NOT NULL,
    TELEFONE VARCHAR(20) NULL,
    CRIADO_EM TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID)
);

-- Garante a coluna CRIADO_EM em tabelas `clientes` criadas anteriormente sem ela
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS CRIADO_EM TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
