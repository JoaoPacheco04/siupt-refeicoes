-- =========================================================================
-- SIUPT REFEIÇÕES — SCRIPTS SQL DE REFERÊNCIA
-- Documento de consulta com todos os scripts usados ao longo do projeto.
-- Organizado por secção. Confirma sempre que a base de dados selecionada
-- no SSMS é "siupt_refeicoes" antes de correr qualquer bloco.
-- =========================================================================


-- =========================================================================
-- 1. CRIAR A BASE DE DADOS
-- =========================================================================
CREATE DATABASE siupt_refeicoes;
GO

USE siupt_refeicoes;
GO


-- =========================================================================
-- 2. CRIAR AS TABELAS — ESQUEMA BASE (do analista)
-- =========================================================================

CREATE TABLE users (
    U_ID INT IDENTITY(1,1) PRIMARY KEY,
    U_BICC VARCHAR(20) NOT NULL UNIQUE,
    U_PASS VARCHAR(255) NOT NULL,
    U_NOME VARCHAR(150) NOT NULL,
    U_EMAIL VARCHAR(150) NOT NULL,
    U_PERFIL INT NOT NULL  -- 1 = aluno, 2 = funcionario
);
GO

CREATE TABLE restaurante_tipo_refeicao (
    RTP_ID INT IDENTITY(1,1) PRIMARY KEY,
    RTP_NOME VARCHAR(50) NOT NULL,
    RM_PRATO_DIA BIT NOT NULL DEFAULT 0
);
GO

CREATE TABLE restaurante_menu (
    RM_ID INT IDENTITY(1,1) PRIMARY KEY,
    RM_NOME VARCHAR(150) NOT NULL,
    RM_DATA DATE NULL,  -- NULL = prato extra, sem data
    RM_TP_ID INT NOT NULL REFERENCES restaurante_tipo_refeicao(RTP_ID),
    RM_ATIVO BIT NOT NULL DEFAULT 1
);
GO

CREATE TABLE restaurante_preco_tipo_refeicao (
    RPTR_ID INT IDENTITY(1,1) PRIMARY KEY,
    RPTR_TP_ID INT NOT NULL REFERENCES restaurante_tipo_refeicao(RTP_ID),
    RPTR_PRECO DECIMAL(10,2) NOT NULL,
    RPTR_DATAINICIO DATETIME NOT NULL
);
GO

CREATE TABLE restaurante_data_limite (
    RDL_ID INT IDENTITY(1,1) PRIMARY KEY,
    RDL_RTP_ID INT NOT NULL REFERENCES restaurante_tipo_refeicao(RTP_ID),
    RDL_HORA TIME NOT NULL,
    RDL_DIA_ANTECEDENCIA INT NOT NULL DEFAULT 1
);
GO

CREATE TABLE restaurante_pedido (
    RP_ID INT IDENTITY(1,1) PRIMARY KEY,
    RP_U_ID INT NOT NULL REFERENCES users(U_ID),
    RP_DATA_REFEICAO DATE NOT NULL,
    RP_PRECO_TOTAL DECIMAL(10,2) NOT NULL,
    RP_QRCODE VARCHAR(64) NOT NULL UNIQUE,
    RP_UTILIZADO BIT NOT NULL DEFAULT 0
);
GO

CREATE TABLE restaurante_compra (
    RC_ID INT IDENTITY(1,1) PRIMARY KEY,
    RC_RP_ID INT NOT NULL REFERENCES restaurante_pedido(RP_ID),
    RC_MENU_COMPLETO BIT NOT NULL DEFAULT 0,
    RC_RM_ID INT NOT NULL REFERENCES restaurante_menu(RM_ID),
    RC_PRECO DECIMAL(10,2) NOT NULL,
    RC_DATA_COMPRA DATETIME NOT NULL DEFAULT GETDATE()
);
GO


-- =========================================================================
-- 3. TABELAS EXTRA — pagamento e validação
-- (não vinham no diagrama do analista; acrescentadas para suportar
--  histórico de pagamentos e auditoria de validações)
-- =========================================================================

CREATE TABLE restaurante_pagamento (
    RPG_ID INT IDENTITY(1,1) PRIMARY KEY,
    RPG_RP_ID INT NOT NULL REFERENCES restaurante_pedido(RP_ID),
    RPG_METODO VARCHAR(20) NOT NULL DEFAULT 'simulado',
    RPG_REF_GATEWAY VARCHAR(64) NULL,
    RPG_ESTADO VARCHAR(20) NOT NULL, -- 'sucesso' / 'falhado'
    RPG_DATA_TENTATIVA DATETIME NOT NULL DEFAULT GETDATE()
);
GO

CREATE TABLE restaurante_validacao (
    RV_ID INT IDENTITY(1,1) PRIMARY KEY,
    RV_RP_ID INT NOT NULL REFERENCES restaurante_pedido(RP_ID),
    RV_FUNCIONARIO_ID INT NOT NULL REFERENCES users(U_ID),
    RV_DATA_VALIDACAO DATETIME NOT NULL DEFAULT GETDATE()
);
GO


-- =========================================================================
-- 4. MIGRAÇÕES POSTERIORES (colunas acrescentadas depois do esquema inicial)
-- =========================================================================

-- Distingue pedido pago de não pago (resolve bug: pedido recusado
-- continuava a poder ser levantado, porque só existia RP_UTILIZADO)
ALTER TABLE restaurante_pedido ADD RP_PAGO BIT NOT NULL DEFAULT 0;
GO

-- Código curto alfanumérico (6 caracteres) para o funcionário poder validar
-- manualmente sem precisar do QR code completo de 48 caracteres
ALTER TABLE restaurante_pedido ADD RP_CODIGO_CURTO VARCHAR(10) NULL;
GO

-- Versão segura para correr múltiplas vezes sem dar erro se a coluna já existir:
-- IF NOT EXISTS (
--     SELECT 1 FROM sys.columns
--     WHERE object_id = OBJECT_ID('restaurante_pedido') AND name = 'RP_PAGO'
-- )
-- ALTER TABLE restaurante_pedido ADD RP_PAGO BIT NOT NULL DEFAULT 0;


-- =========================================================================
-- 5. DADOS DE TESTE — tipos de refeição
-- =========================================================================

INSERT INTO restaurante_tipo_refeicao (RTP_NOME, RM_PRATO_DIA) VALUES
('Carne', 1),
('Peixe', 1),
('Vegetariano', 1),
('Sopa', 1),
('Sobremesa', 1),
('Bebida', 1),
('Prato extra', 0),
('Menu Completo', 0); -- assunção: preço fixo próprio; a confirmar com o analista
GO


-- =========================================================================
-- 6. DADOS DE TESTE — utilizadores
-- Password de ambos: "teste123"
-- Hash bcrypt real, gerado e confirmado a funcionar com password_verify()
-- =========================================================================

INSERT INTO users (U_BICC, U_PASS, U_NOME, U_EMAIL, U_PERFIL) VALUES
('12345678', '$2a$10$DsUfOVmv5JmzGcka2EEibe.p9yzmSrKxXQkAp52mt7N8c7o58AQ.G', 'Aluno Teste', 'aluno.teste@upt.pt', 1),
('87654321', '$2a$10$DsUfOVmv5JmzGcka2EEibe.p9yzmSrKxXQkAp52mt7N8c7o58AQ.G', 'Funcionário Teste', 'funcionario.teste@upt.pt', 2);
GO

-- Se precisares de gerar um novo hash para outra password, corre localmente:
-- php -r "echo password_hash('a_tua_password', PASSWORD_BCRYPT);"


-- =========================================================================
-- 7. DADOS DE TESTE — pratos da ementa e extras (semana completa 27-31/07)
-- Ajusta as datas para a semana que estiveres a testar
-- =========================================================================

DECLARE @carne INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Carne');
DECLARE @peixe INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Peixe');
DECLARE @vegetariano INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Vegetariano');
DECLARE @sopa INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Sopa');
DECLARE @sobremesa INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Sobremesa');
DECLARE @bebida INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Bebida');
DECLARE @extra INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Prato extra');

INSERT INTO restaurante_menu (RM_NOME, RM_DATA, RM_TP_ID) VALUES
-- Segunda, 27/07
('Bife à Portuguesa', '2026-07-27', @carne),
('Bacalhau à Brás', '2026-07-27', @peixe),
('Feijoada de legumes', '2026-07-27', @vegetariano),
('Canja', '2026-07-27', @sopa),
('Fruta da época', '2026-07-27', @sobremesa),
('Água / Sumo diluído', '2026-07-27', @bebida),

-- Terça, 28/07
('Costeletas grelhadas', '2026-07-28', @carne),
('Salmão grelhado', '2026-07-28', @peixe),
('Lasanha de lentilhas', '2026-07-28', @vegetariano),
('Caldo verde', '2026-07-28', @sopa),
('Gelatina', '2026-07-28', @sobremesa),
('Água / Sumo diluído', '2026-07-28', @bebida),

-- Quarta, 29/07
('Arroz de pato', '2026-07-29', @carne),
('Dourada grelhada', '2026-07-29', @peixe),
('Empadão de grão', '2026-07-29', @vegetariano),
('Sopa de agrião', '2026-07-29', @sopa),
('Pudim', '2026-07-29', @sobremesa),
('Água / Sumo diluído', '2026-07-29', @bebida),

-- Quinta, 30/07
('Rojões à moda do Minho', '2026-07-30', @carne),
('Filetes de pescada', '2026-07-30', @peixe),
('Hambúrguer vegetariano', '2026-07-30', @vegetariano),
('Creme de cenoura', '2026-07-30', @sopa),
('Fruta da época', '2026-07-30', @sobremesa),
('Água / Sumo diluído', '2026-07-30', @bebida),

-- Sexta, 31/07
('Frango assado', '2026-07-31', @carne),
('Bacalhau com natas', '2026-07-31', @peixe),
('Seitan grelhado', '2026-07-31', @vegetariano),
('Caldo verde', '2026-07-31', @sopa),
('Mousse de chocolate', '2026-07-31', @sobremesa),
('Água / Sumo diluído', '2026-07-31', @bebida),

-- Pratos extras (sem data, sempre disponíveis)
('Frango', NULL, @extra),
('Omelete', NULL, @extra),
('Sopa do dia', NULL, @extra);
GO


-- =========================================================================
-- 8. DADOS DE TESTE — preços vigentes
-- =========================================================================

DECLARE @carne2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Carne');
DECLARE @peixe2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Peixe');
DECLARE @vegetariano2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Vegetariano');
DECLARE @sopa2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Sopa');
DECLARE @sobremesa2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Sobremesa');
DECLARE @bebida2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Bebida');
DECLARE @extra2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Prato extra');
DECLARE @menuCompleto2 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Menu Completo');

INSERT INTO restaurante_preco_tipo_refeicao (RPTR_TP_ID, RPTR_PRECO, RPTR_DATAINICIO) VALUES
(@carne2, 3.50, '2026-01-01'),
(@peixe2, 3.50, '2026-01-01'),
(@vegetariano2, 3.00, '2026-01-01'),
(@sopa2, 0.80, '2026-01-01'),
(@sobremesa2, 1.00, '2026-01-01'),
(@bebida2, 0.60, '2026-01-01'),
(@extra2, 4.00, '2026-01-01'),
(@menuCompleto2, 5.00, '2026-01-01'); -- assunção: a confirmar com o analista
GO


-- =========================================================================
-- 9. DADOS DE TESTE — prazos de corte (14h30 do dia anterior)
-- Sem linha para "Prato extra" nem "Menu Completo" de propósito
-- (extras não têm data, logo não faz sentido ter prazo)
-- =========================================================================

DECLARE @carne3 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Carne');
DECLARE @peixe3 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Peixe');
DECLARE @vegetariano3 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Vegetariano');
DECLARE @sopa3 INT = (SELECT RTP_ID FROM restaurante_tipo_refeicao WHERE RTP_NOME = 'Sopa');

INSERT INTO restaurante_data_limite (RDL_RTP_ID, RDL_HORA, RDL_DIA_ANTECEDENCIA) VALUES
(@carne3, '14:30:00', 1),
(@peixe3, '14:30:00', 1),
(@vegetariano3, '14:30:00', 1),
(@sopa3, '14:30:00', 1);
GO


-- =========================================================================
-- 10. LIMPEZA DE DADOS DE TESTE
-- Apaga TODOS os pedidos de um utilizador (por BI/CC), respeitando a ordem
-- das chaves estrangeiras. Útil para reiniciares os testes do zero.
-- =========================================================================

USE siupt_refeicoes;
GO

DECLARE @meuId INT = (SELECT U_ID FROM users WHERE U_BICC = '12345678'); -- troca o BI/CC se necessário

DELETE FROM restaurante_validacao WHERE RV_RP_ID IN (
    SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = @meuId
);

DELETE FROM restaurante_pagamento WHERE RPG_RP_ID IN (
    SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = @meuId
);

DELETE FROM restaurante_compra WHERE RC_RP_ID IN (
    SELECT RP_ID FROM restaurante_pedido WHERE RP_U_ID = @meuId
);

DELETE FROM restaurante_pedido WHERE RP_U_ID = @meuId;


-- Confirmar que ficou limpo:
SELECT COUNT(*) AS pedidos_restantes FROM restaurante_pedido
WHERE RP_U_ID = (SELECT U_ID FROM users WHERE U_BICC = '12345678');


-- =========================================================================
-- 11. QUERIES DE VERIFICAÇÃO / DIAGNÓSTICO
-- =========================================================================

-- Ver todos os pratos da ementa, por dia e tipo
SELECT rm.RM_DATA, rtp.RTP_NOME, rm.RM_NOME
FROM restaurante_menu rm
JOIN restaurante_tipo_refeicao rtp ON rm.RM_TP_ID = rtp.RTP_ID
WHERE rm.RM_DATA IS NOT NULL
ORDER BY rm.RM_DATA, rtp.RTP_NOME;

-- Ver pedidos de um utilizador, com código curto
SELECT RP_ID, RP_DATA_REFEICAO, RP_CODIGO_CURTO, RP_PAGO, RP_UTILIZADO
FROM restaurante_pedido
WHERE RP_U_ID = (SELECT U_ID FROM users WHERE U_BICC = '12345678')
ORDER BY RP_ID DESC;

-- Ver colunas e tipos de todas as tabelas
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Ver colunas e tipos de uma tabela específica
EXEC sp_help 'restaurante_pedido';

-- Ver as chaves estrangeiras (relações entre tabelas)
SELECT 
    fk.name AS constraint_name,
    tp.name AS tabela_origem,
    cp.name AS coluna_origem,
    tr.name AS tabela_destino,
    cr.name AS coluna_destino
FROM sys.foreign_keys fk
JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
JOIN sys.tables tp ON fkc.parent_object_id = tp.object_id
JOIN sys.columns cp ON fkc.parent_object_id = cp.object_id AND fkc.parent_column_id = cp.column_id
JOIN sys.tables tr ON fkc.referenced_object_id = tr.object_id
JOIN sys.columns cr ON fkc.referenced_object_id = cr.object_id AND fkc.referenced_column_id = cr.column_id;


-- =========================================================================
-- 12. MIGRAÇÃO — tabela restaurante_dia_especial
-- Dias em que a cantina encerra por razões não feriado (férias, greve, evento).
-- RDE_PERMITE_EXTRAS = 1 → dia encerrado mas extras ainda podem ser comprados.
-- RDE_PERMITE_EXTRAS = 0 → encerrado total, sem qualquer compra.
-- =========================================================================

CREATE TABLE restaurante_dia_especial (
    RDE_ID             INT IDENTITY(1,1) PRIMARY KEY,
    RDE_DATA           DATE NOT NULL UNIQUE,
    RDE_MOTIVO         VARCHAR(150) NULL,
    RDE_PERMITE_EXTRAS BIT NOT NULL DEFAULT 0
);
GO


-- =========================================================================
-- 13. MIGRAÇÃO — tabela restaurante_papel_utilizador
-- Controlo de papéis adicionais da cantina (Atendente e Administrador)
-- Atribui papéis de gestão da cantina a utilizadores específicos.
-- Um utilizador pode ter 'atendente', 'admin_cantina' ou ambos.
-- Alunos e colaboradores que apenas compram NÃO têm entradas aqui.
-- =========================================================================

CREATE TABLE restaurante_papel_utilizador (
    RPU_ID     INT IDENTITY(1,1) PRIMARY KEY,
    RPU_U_ID   INT NOT NULL REFERENCES users(U_ID),
    RPU_PAPEL  VARCHAR(50) NOT NULL,
    RPU_DATA   DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT UQ_Papel_Utilizador UNIQUE (RPU_U_ID, RPU_PAPEL)
);
GO

-- Dado de teste: promover o utilizador funcionário (BI/CC 87654321) a atendente e admin.
-- Ajusta o BI/CC se o teu utilizador de teste for diferente.
INSERT INTO restaurante_papel_utilizador (RPU_U_ID, RPU_PAPEL)
SELECT U_ID, 'atendente'    FROM users WHERE U_BICC = '87654321';
INSERT INTO restaurante_papel_utilizador (RPU_U_ID, RPU_PAPEL)
SELECT U_ID, 'admin_cantina' FROM users WHERE U_BICC = '87654321';
GO


-- =========================================================================
-- 14. MIGRAÇÕES — tabelas em falta no esquema inicial
-- (Adicionadas ao longo do desenvolvimento; registadas aqui para que
--  uma instalação de raiz consiga criar tudo num único script.)
-- =========================================================================

-- Feriados nacionais e municipais
CREATE TABLE restaurante_feriado (
    RF_ID   INT IDENTITY(1,1) PRIMARY KEY,
    RF_DATA DATE NOT NULL UNIQUE,
    RF_NOME VARCHAR(100) NOT NULL
);
GO

-- Avaliações das refeições (1-5 estrelas, com motivo opcional para <= 2)
CREATE TABLE restaurante_avaliacao (
    RAV_ID      INT IDENTITY(1,1) PRIMARY KEY,
    RAV_RP_ID   INT NOT NULL REFERENCES restaurante_pedido(RP_ID),
    RAV_ESTRELAS TINYINT NOT NULL CHECK (RAV_ESTRELAS BETWEEN 1 AND 5),
    RAV_MOTIVO  VARCHAR(50) NULL,
    UNIQUE (RAV_RP_ID)  -- um pedido só pode ter uma avaliação
);
GO

-- Motivos de reclamação editáveis no backoffice
CREATE TABLE restaurante_motivo_reclamacao (
    RMR_ID     INT IDENTITY(1,1) PRIMARY KEY,
    RMR_CODIGO VARCHAR(50) NOT NULL UNIQUE,
    RMR_LABEL  VARCHAR(150) NOT NULL,
    RMR_ATIVO  BIT NOT NULL DEFAULT 1
);
GO

-- Transferências bem-sucedidas entre utilizadores
CREATE TABLE restaurante_transferencia (
    RT_ID        INT IDENTITY(1,1) PRIMARY KEY,
    RT_RP_ID     INT NOT NULL REFERENCES restaurante_pedido(RP_ID),
    RT_DE_U_ID   INT NOT NULL REFERENCES users(U_ID),
    RT_PARA_U_ID INT NOT NULL REFERENCES users(U_ID),
    RT_DATA      DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- Tentativas de transferência falhadas (auditoria)
CREATE TABLE restaurante_transferencia_tentativa (
    RTT_ID           INT IDENTITY(1,1) PRIMARY KEY,
    RTT_RP_ID        INT NOT NULL,
    RTT_DE_U_ID      INT NOT NULL,
    RTT_BICC_DESTINO VARCHAR(20) NOT NULL,
    RTT_MOTIVO_FALHA VARCHAR(50) NOT NULL,
    RTT_DATA         DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =========================================================================
-- 15. MIGRAÇÃO — tabela restaurante_feriado_geracao (MELHORIA 2)
-- Controlo de geração automática de feriados por ano.
-- Substitui o limiar de contagem (>= 14) que causava regeneração
-- inesperada quando o admin apagava feriados manualmente.
-- =========================================================================

CREATE TABLE restaurante_feriado_geracao (
    RFG_ID   INT IDENTITY(1,1) PRIMARY KEY,
    RFG_ANO  INT NOT NULL UNIQUE,
    RFG_DATA DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- Popular com os anos já gerados (se a BD já existir e tiver feriados):
-- INSERT INTO restaurante_feriado_geracao (RFG_ANO)
-- SELECT DISTINCT YEAR(RF_DATA) FROM restaurante_feriado
-- WHERE YEAR(RF_DATA) NOT IN (SELECT RFG_ANO FROM restaurante_feriado_geracao);


