-- Database per Stabilimento Balneare
-- MariaDB Schema

CREATE DATABASE IF NOT EXISTS stabilimento_balneare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stabilimento_balneare;

-- Tabella clienti
CREATE TABLE clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    INDEX idx_telefono (telefono),
    INDEX idx_nome (nome)
);

-- Tabella tende balneari
CREATE TABLE tende_balneari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_tenda VARCHAR(10) UNIQUE NOT NULL,
    zona VARCHAR(50) NOT NULL, -- es: "prima fila", "seconda fila", "vip"
    tipo VARCHAR(50) NOT NULL, -- es: "standard", "deluxe", "family"
    prezzo_giornaliero DECIMAL(8,2) NOT NULL,
    posizione_x INT DEFAULT 0, -- per il drag & drop
    posizione_y INT DEFAULT 0, -- per il drag & drop
    attiva BOOLEAN DEFAULT TRUE,
    descrizione TEXT,
    INDEX idx_zona (zona),
    INDEX idx_tipo (tipo)
);

-- Tabella prenotazioni
CREATE TABLE prenotazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    tenda_id INT NOT NULL,
    data_inizio DATE NOT NULL,
    data_fine DATE NOT NULL,
    stato ENUM('confermata', 'provvisoria', 'annullata', 'completata') DEFAULT 'confermata',
    prezzo_totale DECIMAL(10,2) NOT NULL,
    acconto DECIMAL(10,2) DEFAULT 0,
    note TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (tenda_id) REFERENCES tende_balneari(id) ON DELETE CASCADE,
    INDEX idx_date (data_inizio, data_fine),
    INDEX idx_cliente (cliente_id),
    INDEX idx_tenda (tenda_id),
    INDEX idx_stato (stato)
);

-- Tabella conti aperti (spese aggiuntive)
CREATE TABLE conti_aperti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prenotazione_id INT NOT NULL,
    descrizione VARCHAR(200) NOT NULL,
    importo DECIMAL(8,2) NOT NULL,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagato BOOLEAN DEFAULT FALSE,
    note TEXT,
    FOREIGN KEY (prenotazione_id) REFERENCES prenotazioni(id) ON DELETE CASCADE,
    INDEX idx_prenotazione (prenotazione_id),
    INDEX idx_pagato (pagato)
);

-- Tabella servizi extra (catalogo servizi)
CREATE TABLE servizi_extra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT,
    prezzo DECIMAL(8,2) NOT NULL,
    attivo BOOLEAN DEFAULT TRUE
);

-- Tabella per storico modifiche prenotazioni
CREATE TABLE storico_prenotazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prenotazione_id INT NOT NULL,
    azione VARCHAR(50) NOT NULL, -- 'creata', 'modificata', 'spostata', 'annullata'
    dati_precedenti JSON,
    dati_nuovi JSON,
    utente VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prenotazione_id) REFERENCES prenotazioni(id) ON DELETE CASCADE
);

-- Inserimento dati di esempio
INSERT INTO tende_balneari (numero_tenda, zona, tipo, prezzo_giornaliero, posizione_x, posizione_y) VALUES
('T001', 'Prima Fila', 'Standard', 25.00, 50, 50),
('T002', 'Prima Fila', 'Standard', 25.00, 150, 50),
('T003', 'Prima Fila', 'Deluxe', 35.00, 250, 50),
('T004', 'Seconda Fila', 'Standard', 20.00, 50, 150),
('T005', 'Seconda Fila', 'Standard', 20.00, 150, 150),
('T006', 'Seconda Fila', 'Family', 30.00, 250, 150),
('T007', 'Terza Fila', 'Standard', 18.00, 50, 250),
('T008', 'Terza Fila', 'Standard', 18.00, 150, 250),
('T009', 'VIP', 'Deluxe', 45.00, 350, 50),
('T010', 'VIP', 'Deluxe', 45.00, 350, 150);

INSERT INTO servizi_extra (nome, descrizione, prezzo) VALUES
('Colazione', 'Colazione continentale', 8.00),
('Pranzo', 'Pranzo al ristorante', 15.00),
('Aperitivo', 'Aperitivo serale', 12.00),
('Lettino Extra', 'Lettino aggiuntivo', 5.00),
('Ombrellone Extra', 'Ombrellone aggiuntivo', 8.00);

-- Esempio clienti
INSERT INTO clienti (nome, telefono, email) VALUES
('Mario Rossi', '3331234567', 'mario.rossi@email.com'),
('Giulia Bianchi', '3337654321', 'giulia.bianchi@email.com'),
('Luca Verdi', '3339876543', 'luca.verdi@email.com');