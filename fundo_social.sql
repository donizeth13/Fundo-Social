CREATE DATABASE IF NOT EXISTS fundo_social
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE fundo_social;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('admin', 'gerente', 'usuario') NOT NULL DEFAULT 'usuario',
    imagem VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) NOT NULL DEFAULT '' UNIQUE,
    nome VARCHAR(150) NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    descricao TEXT NULL,
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quantidade (quantidade),
    INDEX idx_nome (nome)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beneficiarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) NOT NULL DEFAULT '' UNIQUE,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    telefone VARCHAR(15) NOT NULL,
    email VARCHAR(150) NULL,
    senha VARCHAR(255) NULL,
    renda_per_capita DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    situacao_trabalho VARCHAR(50) NOT NULL DEFAULT 'desempregado',
    dependentes INT NOT NULL DEFAULT 0,
    endereco TEXT NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NOT NULL DEFAULT 'Caçapava',
    estado VARCHAR(2) NOT NULL DEFAULT 'SP',
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    score_prioridade INT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_nome (nome),
    INDEX idx_renda (renda_per_capita),
    INDEX idx_score (score_prioridade)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS guias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(32) NOT NULL DEFAULT '' UNIQUE,
    item_id INT NOT NULL,
    beneficiario_id INT NOT NULL,
    usuario_id INT NOT NULL,
    quantidade INT NOT NULL,
    justificativa TEXT NOT NULL,
    status ENUM('ativo', 'encerrada', 'cancelado', 'pendente') NOT NULL DEFAULT 'ativo',
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_saidafiscal DATETIME NULL,
    observations TEXT NULL,
    INDEX idx_item (item_id),
    INDEX idx_beneficiario (beneficiario_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status),
    INDEX idx_data (data_criacao),
    FOREIGN KEY (item_id) REFERENCES itens(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (beneficiario_id) REFERENCES beneficiarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS itens_guias_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guia_id INT NULL,
    item_id INT NOT NULL,
    quantidade_inicial INT NOT NULL,
    quantidade_final INT NOT NULL,
    quantidade_movimentada INT NOT NULL,
    tipo_movimentacao ENUM('entrada', 'saida') NOT NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_guia (guia_id),
    INDEX idx_item (item_id),
    FOREIGN KEY (guia_id) REFERENCES guias(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (item_id) REFERENCES itens(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE itens_guias_estoque MODIFY guia_id INT NULL;

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL,
    tabela VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    dados_antigos JSON NULL,
    dados_novos JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_data (data_hora),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

DROP TRIGGER IF EXISTS trg_item_codigo;
DROP TRIGGER IF EXISTS trg_beneficiario_codigo;
DROP TRIGGER IF EXISTS trg_guia_numero;

DELIMITER $$
CREATE TRIGGER trg_item_codigo BEFORE INSERT ON itens
FOR EACH ROW
BEGIN
    IF NEW.codigo = '' THEN
        SET NEW.codigo = CONCAT('ITM', RIGHT(UUID_SHORT(), 17));
    END IF;
END$$

CREATE TRIGGER trg_beneficiario_codigo BEFORE INSERT ON beneficiarios
FOR EACH ROW
BEGIN
    IF NEW.codigo = '' THEN
        SET NEW.codigo = CONCAT('BEN', UUID_SHORT());
    END IF;
END$$

CREATE TRIGGER trg_guia_numero BEFORE INSERT ON guias
FOR EACH ROW
BEGIN
    IF NEW.numero = '' THEN
        SET NEW.numero = CONCAT('GUA', UUID_SHORT());
    END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO usuarios (id, nome, email, senha, perfil, ativo) VALUES
(1, 'Administrador', 'admin@fundo.com', '$2y$10$3ONRvAuW71idsKcz5P7coOvJrUlnxLUJKSDJRFt5WUfWHFg8HzMGa', 'admin', 1),
(2, 'Maria Silva', 'maria@fundo.com', '$2y$10$3ONRvAuW71idsKcz5P7coOvJrUlnxLUJKSDJRFt5WUfWHFg8HzMGa', 'gerente', 1),
(3, 'João Santos', 'joao@fundo.com', '$2y$10$3ONRvAuW71idsKcz5P7coOvJrUlnxLUJKSDJRFt5WUfWHFg8HzMGa', 'usuario', 1);

INSERT IGNORE INTO itens (codigo, nome, quantidade, descricao) VALUES
('ITM-SEED-001', 'Leite em pó', 150, 'Leite em pó desidratado'),
('ITM-SEED-002', 'Arroz', 200, 'Arroz para consumo humano'),
('ITM-SEED-003', 'Feijão', 250, 'Feijão carioca'),
('ITM-SEED-004', 'Açúcar', 300, 'Açúcar refinado'),
('ITM-SEED-005', 'Óleo de soja', 180, 'Óleo de soja');

INSERT IGNORE INTO beneficiarios (nome, cpf, telefone, email, renda_per_capita, situacao_trabalho, dependentes, endereco, bairro) VALUES
('Ana Silva Santos', '12345678901', '(11) 99999-0001', 'ana@email.com', 150.00, 'desempregado', 4, 'Rua das Flores, 123', 'Centro'),
('Carlos Santos', '12345678902', '(11) 99999-0002', 'carlos@email.com', 280.00, 'desempregado', 3, 'Avenida Paulista, 456', 'Jardim América'),
('Maria Oliveira', '12345678903', '(11) 99999-0003', 'maria@email.com', 450.00, 'empregado', 2, 'Rua dos Pinheiros, 789', 'Jardim Primavera');
