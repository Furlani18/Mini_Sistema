-- Tabela de usuários para autenticação do sistema
-- Execute este script no banco `mini_sistema` (via phpMyAdmin ou linha de comando)

CREATE TABLE IF NOT EXISTS usuarios (
    ID INT(11) NOT NULL AUTO_INCREMENT,
    NOME VARCHAR(100) NOT NULL,
    EMAIL VARCHAR(100) NOT NULL,
    TELEFONE VARCHAR(20) NULL,
    SENHA VARCHAR(255) NOT NULL,
    CRIADO_EM TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY UQ_USUARIOS_EMAIL (EMAIL)
);

-- Garante a coluna TELEFONE em bancos criados com uma versão anterior deste script
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS TELEFONE VARCHAR(20) NULL AFTER EMAIL;

-- Usuário padrão criado automaticamente (login: admin@sistema.com / senha: admin123)
-- A senha já está com hash bcrypt (password_hash do PHP). Altere-a após o primeiro acesso.
INSERT INTO usuarios (NOME, EMAIL, TELEFONE, SENHA)
SELECT 'Administrador', 'admin@sistema.com', '(11) 99999-9999', '$2y$10$WwIGamvh.3DWV.SUUaHQh.P6tHWdjhNBdOh1pLUB0DZe8DA1GE5d6'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE EMAIL = 'admin@sistema.com');
