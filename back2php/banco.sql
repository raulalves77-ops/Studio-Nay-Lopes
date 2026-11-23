-- ============================================================
-- Banco de dados do Studio Nay Lopes (MySQL)
-- Importe este arquivo no phpMyAdmin (aba "Importar").
-- ============================================================

CREATE DATABASE IF NOT EXISTS studio_nay_lopes
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studio_nay_lopes;

-- Apaga as tabelas antigas (filhas primeiro) para poder importar de novo
DROP TABLE IF EXISTS confirmacoes;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS profissionais;
DROP TABLE IF EXISTS servicos;
DROP TABLE IF EXISTS usuarios;

-- Clientes E administradores ficam na mesma tabela; a coluna "tipo" diferencia.
CREATE TABLE usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(120) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,      -- é o login
    telefone   VARCHAR(20)  NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,             -- nunca a senha em texto puro
    tipo       ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE profissionais (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120) NOT NULL,
    especialidade VARCHAR(255) NOT NULL
);

CREATE TABLE servicos (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(120)  NOT NULL UNIQUE,
    valor     DECIMAL(10,2) NOT NULL,
    descricao VARCHAR(255)  NULL
);

-- ON DELETE CASCADE: apagou o usuário/serviço/profissional,
-- os agendamentos ligados a ele são apagados junto.
CREATE TABLE agendamentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NOT NULL,
    servico_id      INT NOT NULL,
    profissional_id INT NULL,                      -- opcional
    data_hora       DATETIME NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,        -- preço na hora da reserva
    status          ENUM('Pendente','Cancelado') NOT NULL DEFAULT 'Pendente',
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)      REFERENCES usuarios(id)      ON DELETE CASCADE,
    FOREIGN KEY (servico_id)      REFERENCES servicos(id)      ON DELETE CASCADE,
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
);

-- Registro das confirmações "enviadas" após cada agendamento
CREATE TABLE confirmacoes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    agendamento_id INT NOT NULL,
    enviada_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE
);

-- Dados iniciais (os mesmos da página de serviços)
INSERT INTO servicos (nome, valor, descricao) VALUES
    ('Luzes',                            350.00, 'Incluso reconstrução, finalização e babyliss.'),
    ('Progressiva',                      130.00, NULL),
    ('Escova',                            60.00, NULL),
    ('Corte',                             45.00, NULL),
    ('Sobrancelha',                       15.00, NULL),
    ('Cílios',                            80.00, NULL),
    ('Manutenção de cílios até 15 dias',  45.00, NULL),
    ('Remoção de cílios',                 45.00, NULL);

INSERT INTO profissionais (nome, especialidade) VALUES
    ('Nay Lopes', 'Cabeleireira e designer de sobrancelhas e cílios. Especialista em luzes, progressiva e corte.');

-- O administrador é criado pelo terminal:  php criar-admin.php seu@email.com
