
DROP TABLE IF EXISTS game_players;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS players;

-- =============================================
-- PLAYERS TABLE
-- Αποθηκεύει τους χρήστες και τα tokens τους
-- =============================================
CREATE TABLE players (
                         id INT AUTO_INCREMENT PRIMARY KEY,
                         username VARCHAR(50) UNIQUE NOT NULL,
                         token VARCHAR(64),
                         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- GAMES TABLE
-- Αποθηκεύει την κατάσταση κάθε παιχνιδιού
-- =============================================
CREATE TABLE games (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       player1_id INT NOT NULL,
                       player2_id INT,
                       deck JSON,                                              -- Υπόλοιπα φύλλα στην τράπουλα
                       table_cards JSON,                                       -- Φύλλα στο τραπέζι
                       current_turn INT,                                       -- ID παίκτη που παίζει
                       status ENUM('waiting','active','finished') DEFAULT 'waiting',
                       last_capture_by INT,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       FOREIGN KEY (player1_id) REFERENCES players(id),
                       FOREIGN KEY (player2_id) REFERENCES players(id),
                       FOREIGN KEY (current_turn) REFERENCES players(id),
                       FOREIGN KEY (last_capture_by) REFERENCES players(id)
);

-- =============================================
-- GAME_PLAYERS TABLE
-- Αποθηκεύει τα χέρια, τα μαζεμένα φύλλα και το σκορ
-- =============================================
CREATE TABLE game_players (
                              game_id INT,
                              player_id INT,
                              hand JSON,                                              -- Φύλλα στο χέρι
                              captured JSON,                                          -- Μαζεμένα φύλλα
                              score INT DEFAULT 0,                                    -- Τελικό σκορ
                              xeres INT DEFAULT 0,                                    -- Κανονικές ξερές
                              jack_xeres INT DEFAULT 0,                               -- Ξερές με Βαλέ (20 πόντοι)
                              PRIMARY KEY (game_id, player_id),
                              FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                              FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
);

