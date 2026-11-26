-- ============================================================================
-- Melhorias 3: loja online (vitrine pública com carrinho)
--
--   produtos     → FOTO (arquivo em uploads/produtos/), NA_LOJA (aparece na loja?),
--                  DESTAQUE (aparece em "Destaques" na página inicial da loja),
--                  DESCRICAO ampliada para 1000 caracteres
--   pedidos      → ORIGEM (SISTEMA = registrado pela equipe, LOJA = feito pelo cliente no site)
--                  e ENTREGA (endereço de entrega ou "Retirar na loja", com o telefone de contato)
--   vw_pedidos   → passa a trazer ORIGEM
--   configuracoes→ nome, slogan, WhatsApp e situação (aberta/fechada) da loja
--
-- Pode ser executado mais de uma vez: só acrescenta o que ainda não existe.
--   C:\xampp\mysql\bin\mysql.exe -u root mini_sistema < melhorias_3.sql
-- ============================================================================

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS FOTO VARCHAR(60) NULL AFTER CUSTO,
    ADD COLUMN IF NOT EXISTS NA_LOJA TINYINT(1) NOT NULL DEFAULT 1 AFTER FOTO,
    ADD COLUMN IF NOT EXISTS DESTAQUE TINYINT(1) NOT NULL DEFAULT 0 AFTER NA_LOJA,
    MODIFY COLUMN DESCRICAO VARCHAR(1000) NULL;

ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS ORIGEM ENUM('SISTEMA', 'LOJA') NOT NULL DEFAULT 'SISTEMA' AFTER STATUS,
    ADD COLUMN IF NOT EXISTS ENTREGA VARCHAR(500) NULL AFTER FORMA_PAGAMENTO;

CREATE INDEX IF NOT EXISTS IDX_PEDIDOS_ORIGEM ON pedidos (ORIGEM, STATUS);
CREATE INDEX IF NOT EXISTS IDX_PRODUTOS_LOJA ON produtos (NA_LOJA, DESTAQUE);

CREATE OR REPLACE VIEW vw_pedidos AS
SELECT p.ID, p.CLIENTE_ID, p.USUARIO_ID, p.STATUS, p.ORIGEM, p.OBSERVACAO, p.DESCONTO, p.FORMA_PAGAMENTO, p.CRIADO_EM,
       COUNT(i.ID)                                                          AS ITENS,
       COALESCE(SUM(i.QUANTIDADE), 0)                                       AS UNIDADES,
       COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0)                    AS SUBTOTAL,
       GREATEST(COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0) - p.DESCONTO, 0) AS TOTAL,
       COALESCE(SUM(i.QUANTIDADE * i.CUSTO_UNITARIO), 0)                    AS CUSTO,
       COUNT(CASE WHEN i.ID IS NOT NULL AND i.CUSTO_UNITARIO IS NULL THEN 1 END) AS ITENS_SEM_CUSTO
  FROM pedidos p
  LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
 GROUP BY p.ID, p.CLIENTE_ID, p.USUARIO_ID, p.STATUS, p.ORIGEM, p.OBSERVACAO, p.DESCONTO, p.FORMA_PAGAMENTO, p.CRIADO_EM;

-- Configurações da loja (INSERT IGNORE mantém o que já foi configurado)
INSERT IGNORE INTO configuracoes (CHAVE, VALOR) VALUES
    ('loja_ativa', '1'),
    ('loja_nome', 'Minha Loja'),
    ('loja_slogan', 'Produtos selecionados com entrega rápida e atendimento de verdade.'),
    ('loja_whatsapp', NULL);
