-- ============================================================
-- Banco de dados do Studio Nay Lopes (MySQL/MariaDB)
--
-- Use este arquivo para uma instalação NOVA (phpMyAdmin > Importar).
-- Em um banco que já está no ar, use o migracao.sql, que preserva
-- os dados existentes.
-- ============================================================

CREATE DATABASE IF NOT EXISTS studio_nay_lopes
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studio_nay_lopes;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tentativas_login;
DROP TABLE IF EXISTS movimentacoes;
DROP TABLE IF EXISTS bloqueios;
DROP TABLE IF EXISTS confirmacoes;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS profissionais;
DROP TABLE IF EXISTS servicos;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Contas de acesso.
-- O tipo separa quem é quem: cliente, profissional ou admin.
-- Apenas o admin (dono do studio) vê o caixa e faz a gestão.
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(120) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    telefone   VARCHAR(20)  NOT NULL DEFAULT '',
    senha_hash VARCHAR(255) NOT NULL,
    tipo       ENUM('cliente','profissional','admin') NOT NULL DEFAULT 'cliente',
    ativo      TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_tipo (tipo)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Profissionais do studio.
-- usuario_id liga a profissional à conta de acesso dela (opcional
-- para profissionais antigas, que não entram no sistema).
-- O nome exibido vem SEMPRE daqui — nunca de um cadastro de cliente.
-- ------------------------------------------------------------
CREATE TABLE profissionais (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT NULL,
    nome          VARCHAR(120) NOT NULL,
    especialidade VARCHAR(255) NOT NULL DEFAULT '',
    telefone      VARCHAR(20)  NOT NULL DEFAULT '',
    comissao      DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- % de repasse
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_profissionais_usuario (usuario_id),
    CONSTRAINT fk_profissionais_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Serviços oferecidos.
-- ativo = 0 esconde do site sem apagar o histórico de atendimentos.
-- ------------------------------------------------------------
CREATE TABLE servicos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120)  NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    duracao_minutos INT           NOT NULL DEFAULT 60,
    descricao       VARCHAR(255)  NULL,
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_servicos_nome (nome)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Agenda.
-- valor guarda o preço no momento da reserva (o serviço pode
-- mudar de preço depois sem alterar o histórico).
-- ------------------------------------------------------------
CREATE TABLE agendamentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NOT NULL,                    -- cliente
    servico_id      INT NOT NULL,
    profissional_id INT NOT NULL,                    -- dona do horário
    data_hora       DATETIME NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    status          ENUM('Pendente','Confirmado','Concluido','Cancelado','Faltou')
                    NOT NULL DEFAULT 'Pendente',
    pago            TINYINT(1) NOT NULL DEFAULT 0,
    forma_pagamento ENUM('Dinheiro','Pix','Cartão de débito','Cartão de crédito','Outro') NULL,
    observacoes     VARCHAR(255) NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_agendamentos_data (data_hora),
    KEY idx_agendamentos_prof (profissional_id, data_hora),
    KEY idx_agendamentos_status (status),
    KEY idx_agendamentos_cliente (usuario_id),
    CONSTRAINT fk_agendamentos_cliente
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_agendamentos_servico
        FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_agendamentos_profissional
        FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Fluxo de caixa.
-- agendamento_id UNIQUE garante uma receita por atendimento.
-- Lançamentos manuais (despesas, produtos) têm agendamento_id NULL.
-- ------------------------------------------------------------
CREATE TABLE movimentacoes (
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
-- Bloqueios de agenda (folga, feriado, compromisso).
-- profissional_id NULL = bloqueio para o studio inteiro.
-- ------------------------------------------------------------
CREATE TABLE bloqueios (
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

-- ============================================================
-- Tentativas de login (limite contra força bruta).
-- Guardado no banco para não ser burlado trocando de cookie.
-- ============================================================
CREATE TABLE tentativas_login (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    email     VARCHAR(150) NOT NULL DEFAULT '',
    ip        VARCHAR(45)  NOT NULL DEFAULT '',
    criado_em DATETIME     NOT NULL,
    KEY idx_tentativas_email (email, criado_em),
    KEY idx_tentativas_ip (ip, criado_em)
) ENGINE=InnoDB;

-- ============================================================
-- Dados iniciais
-- ============================================================
INSERT INTO servicos (nome, valor, duracao_minutos, descricao) VALUES
    ('Luzes',                            350.00, 180, 'Incluso reconstrução, finalização e babyliss.'),
    ('Progressiva',                      130.00, 120, NULL),
    ('Escova',                            60.00,  60, NULL),
    ('Corte',                             45.00,  60, NULL),
    ('Sobrancelha',                       15.00,  30, NULL),
    ('Cílios',                            80.00, 120, NULL),
    ('Manutenção de cílios até 15 dias',  45.00,  60, NULL),
    ('Remoção de cílios',                 45.00,  30, NULL);

INSERT INTO profissionais (nome, especialidade, comissao, ativo) VALUES
    ('Nay Lopes', 'Cabeleireira e designer de sobrancelhas e cílios. Especialista em luzes, progressiva e corte.', 0.00, 1);

-- A conta da administração/profissional é criada pelo terminal, já
-- vinculada ao perfil acima:
--
--     php criar-admin.php "Nay Lopes" seu@email.com
