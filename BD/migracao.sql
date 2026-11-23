-- ============================================================
-- MIGRAÇÃO do banco que JÁ ESTÁ NO AR
--
-- Faça backup antes (phpMyAdmin > Exportar).
-- Rode este arquivo UMA vez, ANTES de subir os arquivos novos.
-- Se o banco tiver outro nome, ajuste o USE abaixo.
-- ============================================================

USE if0_43486335_studio_nay_lopes;

-- ------------------------------------------------------------
-- 1) Contas: novo tipo "profissional" + coluna ativo
-- ------------------------------------------------------------
ALTER TABLE usuarios
    MODIFY tipo ENUM('cliente','profissional','admin') NOT NULL DEFAULT 'cliente',
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER tipo;

-- ------------------------------------------------------------
-- 2) Serviços: duração, ativo/inativo e data de criação
-- ------------------------------------------------------------
ALTER TABLE servicos
    ADD COLUMN duracao_minutos INT NOT NULL DEFAULT 60 AFTER valor,
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER descricao,
    ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- 3) Profissionais: vínculo com a conta, contato, comissão e status
--    O nome da profissional continua só nesta tabela — separado
--    de qualquer cadastro de cliente.
-- ------------------------------------------------------------
ALTER TABLE profissionais
    ADD COLUMN usuario_id INT NULL AFTER id,
    ADD COLUMN telefone VARCHAR(20) NOT NULL DEFAULT '' AFTER especialidade,
    ADD COLUMN comissao DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER telefone,
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER comissao,
    ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uk_profissionais_usuario (usuario_id),
    ADD CONSTRAINT fk_profissionais_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 4) Agenda: novos status, pagamento, observações e índices
-- ------------------------------------------------------------
ALTER TABLE agendamentos
    MODIFY status ENUM('Pendente','Confirmado','Concluido','Cancelado','Faltou')
           NOT NULL DEFAULT 'Pendente',
    ADD COLUMN pago TINYINT(1) NOT NULL DEFAULT 0 AFTER valor,
    ADD COLUMN forma_pagamento
        ENUM('Dinheiro','Pix','Cartão de débito','Cartão de crédito','Outro')
        NULL AFTER pago,
    ADD COLUMN observacoes VARCHAR(255) NULL AFTER forma_pagamento,
    ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP;

-- Todo agendamento passa a ter uma profissional responsável.
-- (Só execute o ALTER abaixo se existir pelo menos uma profissional
--  cadastrada; sem isso a atualização não encontra substituta.)
UPDATE agendamentos
   SET profissional_id = (SELECT id FROM profissionais ORDER BY id LIMIT 1)
 WHERE profissional_id IS NULL;

ALTER TABLE agendamentos MODIFY profissional_id INT NOT NULL;

ALTER TABLE agendamentos
    ADD INDEX idx_agendamentos_data (data_hora),
    ADD INDEX idx_agendamentos_prof (profissional_id, data_hora),
    ADD INDEX idx_agendamentos_status (status),
    ADD INDEX idx_agendamentos_cliente (usuario_id);

-- ------------------------------------------------------------
-- 5) Fluxo de caixa
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimentacoes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tipo           ENUM('entrada','saida') NOT NULL,
    categoria      VARCHAR(60)  NOT NULL DEFAULT 'Outros',
    descricao      VARCHAR(255) NOT NULL,
    valor          DECIMAL(10,2) NOT NULL,
    data           DATE NOT NULL,
    agendamento_id INT NULL,
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_movimentacoes_agendamento (agendamento_id),
    KEY idx_movimentacoes_data (data),
    -- SET NULL: se a conta da cliente for excluída, o dinheiro recebido
    -- continua no histórico do caixa (sem vínculo com o agendamento).
    CONSTRAINT fk_movimentacoes_agendamento
        FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6) Bloqueios de agenda
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bloqueios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NULL,
    inicio          DATETIME NOT NULL,
    fim             DATETIME NOT NULL,
    motivo          VARCHAR(255) NOT NULL DEFAULT '',
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bloqueios_periodo (inicio, fim),
    CONSTRAINT fk_bloqueios_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A tabela "confirmacoes" não é mais usada pelo sistema.
-- Os registros podem ser mantidos como histórico; para removê-la:
-- DROP TABLE IF EXISTS confirmacoes;

-- ------------------------------------------------------------
-- 7) Tentativas de login (limite contra força bruta)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentativas_login (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    email     VARCHAR(150) NOT NULL DEFAULT '',
    ip        VARCHAR(45)  NOT NULL DEFAULT '',
    criado_em DATETIME     NOT NULL,
    KEY idx_tentativas_email (email, criado_em),
    KEY idx_tentativas_ip (ip, criado_em)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8) Depois de importar, rode no terminal para criar a conta do
--    studio (ela fica vinculada ao perfil de profissional):
--
--       php criar-admin.php "Nay Lopes" seu@email.com
--
--    Depois de criar a conta, APAGUE o criar-admin.php do servidor.
-- ------------------------------------------------------------
