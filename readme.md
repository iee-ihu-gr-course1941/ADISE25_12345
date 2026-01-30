# Ξερή - Web API Card Game

## Περιγραφή

Το παιχνίδι **Ξερή** υλοποιημένο ως RESTful Web API. Παίζεται από 2 παίκτες (human-human) μέσω CLI. Δεν διαθέτει GUI - όλη η αλληλεπίδραση γίνεται μέσω HTTP requests.

**Τεχνολογίες:** PHP / MySQL / JSON


---



## API Documentation

### Base URL

```
https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/
```

---

### Authentication

Το API χρησιμοποιεί **Bearer Token** authentication. Μετά το login, κάθε request πρέπει να περιέχει:

```
Authorization: Bearer <token>
```

---

### Endpoints

#### 1. Login / Register

**POST** `/login.php`

Δημιουργεί νέο χρήστη ή κάνει login υπάρχοντα.

**Request:**
```bash
curl -X POST https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"player1"}'
```

**Response (New Player):**
```json
{
  "message": "Player created",
  "player_id": 1,
  "username": "player1",
  "token": "88dba143a436d5c3988ec8e89c4478461bcde0678008d9e175053dbe7b8ede5e"
}
```

**Response (Existing Player):**
```json
{
  "message": "Login successful",
  "player_id": 1,
  "username": "player1",
  "token": "new_token_here..."
}
```

---

#### 2. Create Game

**POST** `/games.php`

Δημιουργεί νέο παιχνίδι. Ο παίκτης περιμένει αντίπαλο.

**Request:**
```bash
curl -X POST https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/games.php \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "message": "Game created - waiting for opponent",
  "game_id": 1,
  "status": "waiting",
  "your_hand": ["2H", "7C", "8S", "7S", "JS", "5C"],
  "table_cards": ["4H", "JD", "5S", "6S"]
}
```

---

#### 3. List Available Games

**GET** `/games.php`

Επιστρέφει λίστα παιχνιδιών που περιμένουν παίκτη.

**Request:**
```bash
curl https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/games.php \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
[
  {
    "id": 1,
    "status": "waiting",
    "created_at": "2025-01-30 12:00:00",
    "player1": "player1"
  }
]
```

---

#### 4. Get Specific Game

**GET** `/games.php?id={game_id}`

Επιστρέφει πληροφορίες συγκεκριμένου παιχνιδιού.

**Request:**
```bash
curl "https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/games.php?id=1" \
  -H "Authorization: Bearer <token>"
```

---

#### 5. Join Game

**POST** `/games.php?id={game_id}`

Ο δεύτερος παίκτης μπαίνει σε υπάρχον παιχνίδι.

**Request:**
```bash
curl -X POST "https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/games.php?id=1" \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "message": "Joined game successfully",
  "game_id": 1,
  "status": "active",
  "your_hand": ["3D", "KH", "9S", "AC", "6D", "QC"]
}
```

**Errors:**
- `{"error": "Game not available"}` - Το παιχνίδι δεν υπάρχει ή έχει ήδη ξεκινήσει
- `{"error": "Cannot join your own game"}` - Δεν μπορείς να μπεις στο δικό σου παιχνίδι

---

#### 6. Game Status

**GET** `/status.php?game_id={id}`

Επιστρέφει την τρέχουσα κατάσταση του παιχνιδιού.

**Request:**
```bash
curl "https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/status.php?game_id=1" \
  -H "Authorization: Bearer <token>"
```

**Response (Active Game):**
```json
{
  "game_id": 1,
  "status": "active",
  "your_hand": ["2H", "7C", "8S", "7S", "JS", "5C"],
  "table_cards": ["4H", "JD", "5S", "6S"],
  "cards_in_deck": 36,
  "opponent_cards": 6,
  "your_score": 0,
  "your_xeres": 0,
  "your_jack_xeres": 0,
  "your_captured": 0,
  "is_your_turn": true,
  "players": {
    "player1": "player1",
    "player2": "player2"
  }
}
```

**Response (Finished Game):**
```json
{
  "game_id": 1,
  "status": "finished",
  "your_hand": [],
  "table_cards": [],
  "cards_in_deck": 0,
  "opponent_cards": 0,
  "your_score": 15,
  "your_xeres": 1,
  "your_jack_xeres": 0,
  "your_captured": 28,
  "is_your_turn": false,
  "players": {
    "player1": "player1",
    "player2": "player2"
  },
  "final_scores": {
    "your_score": 15,
    "opponent_score": 12,
    "your_cards": 28,
    "opponent_cards": 24,
    "your_xeres": 1,
    "your_jack_xeres": 0,
    "opponent_xeres": 0,
    "opponent_jack_xeres": 0,
    "result": "You win!"
  }
}
```

---

#### 7. Play Card

**POST** `/game.php`

Παίζει ένα φύλλο από το χέρι.

**Request:**
```bash
curl -X POST https://users.iee.ihu.gr/~iee2021042/ADISE25_12345/api/game.php \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"game_id": 1, "card": "7C"}'
```

**Response (No Capture):**
```json
{
  "message": "Card played",
  "card_played": "7C",
  "captured": false,
  "captured_cards": [],
  "xeri": false,
  "jack_xeri": false,
  "your_hand": ["2H", "8S", "7S", "JS", "5C"],
  "table_cards": ["4H", "JD", "5S", "6S", "7C"],
  "cards_dealt": false,
  "game_over": false
}
```

**Response (Capture):**
```json
{
  "message": "Capture!",
  "card_played": "JD",
  "captured": true,
  "captured_cards": ["4H", "JD"],
  "xeri": false,
  "jack_xeri": false,
  "your_hand": ["2H", "8S", "7S", "5C"],
  "table_cards": [],
  "cards_dealt": false,
  "game_over": false
}
```

**Response (Xeri):**
```json
{
  "message": "Capture!",
  "card_played": "7C",
  "captured": true,
  "captured_cards": ["7H", "7C"],
  "xeri": true,
  "jack_xeri": false,
  "your_hand": ["2H", "8S", "JS", "5C"],
  "table_cards": [],
  "cards_dealt": false,
  "game_over": false
}
```

**Response (Game Over):**
```json
{
  "message": "Game Over!",
  "card_played": "5C",
  "captured": false,
  "captured_cards": [],
  "xeri": false,
  "jack_xeri": false,
  "your_hand": [],
  "table_cards": [],
  "cards_dealt": false,
  "game_over": true,
  "final_scores": {
    "your_score": 15,
    "opponent_score": 12,
    "your_cards": 28,
    "opponent_cards": 24,
    "your_xeres": 1,
    "your_jack_xeres": 0,
    "opponent_xeres": 0,
    "opponent_jack_xeres": 0,
    "winner": "you"
  }
}
```

**Errors:**
- `{"error": "Game not found"}` - Το παιχνίδι δεν υπάρχει
- `{"error": "Game is not active"}` - Το παιχνίδι δεν είναι ενεργό
- `{"error": "It's not your turn"}` - Δεν είναι η σειρά σου
- `{"error": "Card not in your hand"}` - Το φύλλο δεν είναι στο χέρι σου

---

## Database Schema

### Players Table

```sql
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    token VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary Key |
| username | VARCHAR(50) | Unique username |
| token | VARCHAR(64) | Auth token |
| created_at | TIMESTAMP | Creation time |

### Games Table

```sql
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1_id INT NOT NULL,
    player2_id INT,
    deck JSON,
    table_cards JSON,
    current_turn INT,
    status ENUM('waiting','active','finished') DEFAULT 'waiting',
    last_capture_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player1_id) REFERENCES players(id),
    FOREIGN KEY (player2_id) REFERENCES players(id),
    FOREIGN KEY (current_turn) REFERENCES players(id),
    FOREIGN KEY (last_capture_by) REFERENCES players(id)
);
```

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary Key |
| player1_id | INT | FK to players |
| player2_id | INT | FK to players |
| deck | JSON | Remaining cards in deck |
| table_cards | JSON | Cards on table |
| current_turn | INT | Player ID whose turn |
| status | ENUM | waiting/active/finished |
| last_capture_by | INT | Last player who captured |
| created_at | TIMESTAMP | Creation time |

### Game_Players Table

```sql
CREATE TABLE game_players (
    game_id INT,
    player_id INT,
    hand JSON,
    captured JSON,
    score INT DEFAULT 0,
    xeres INT DEFAULT 0,
    jack_xeres INT DEFAULT 0,
    PRIMARY KEY (game_id, player_id),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
);
```

| Column | Type | Description |
|--------|------|-------------|
| game_id | INT | FK to games |
| player_id | INT | FK to players |
| hand | JSON | Cards in hand |
| captured | JSON | Captured cards |
| score | INT | Final score |
| xeres | INT | Regular xeres count |
| jack_xeres | INT | Jack xeres count (20 points each) |

---

## Card Format

Τα φύλλα αναπαρίστανται ως string με format: `{value}{suit}`

**Values:** A, 2, 3, 4, 5, 6, 7, 8, 9, 10, J, Q, K

**Suits:**
- H = Hearts (Κούπες) ♥
- D = Diamonds (Καρό) ♦
- C = Clubs (Σπαθιά) ♣
- S = Spades (Μπαστούνια) ♠

**Παραδείγματα:**
- `AH` = Άσσος Κούπα
- `10D` = 10 Καρό
- `JS` = Βαλές Μπαστούνι
- `QC` = Ντάμα Σπαθί

---
## Error Responses

Όλα τα errors επιστρέφονται σε JSON format:

```json
{
  "error": "Error message description"
}
```

**Παραδείγματα:**
- `{"error": "Username is required"}` - Λείπει το username
- `{"error": "Missing token"}` - Λείπει το Authorization header
- `{"error": "Invalid token"}` - Λάθος token
- `{"error": "Game not found"}` - Το παιχνίδι δεν υπάρχει
- `{"error": "Game not available"}` - Το παιχνίδι δεν είναι διαθέσιμο
- `{"error": "You are not in this game"}` - Δεν είσαι παίκτης σε αυτό το παιχνίδι
- `{"error": "It's not your turn"}` - Δεν είναι η σειρά σου
- `{"error": "Card not in your hand"}` - Το φύλλο δεν είναι στο χέρι σου
- `{"error": "game_id and card are required"}` - Λείπουν απαραίτητα πεδία

---

## Παράδειγμα Πλήρους Παιχνιδιού

```bash
# 1. Login Player 1
curl -X POST http://localhost/xeri/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"player1"}'
# Response: {"token":"TOKEN1",...}

# 2. Create Game (Player 1)
curl -X POST http://localhost/xeri/api/games.php \
  -H "Authorization: Bearer TOKEN1"
# Response: {"game_id":1,"your_hand":[...],...}

# 3. Login Player 2
curl -X POST http://localhost/xeri/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"player2"}'
# Response: {"token":"TOKEN2",...}

# 4. Join Game (Player 2)
curl -X POST "http://localhost/xeri/api/games.php?id=1" \
  -H "Authorization: Bearer TOKEN2"
# Response: {"status":"active","your_hand":[...],...}

# 5. Check Status (Player 1)
curl "http://localhost/xeri/api/status.php?game_id=1" \
  -H "Authorization: Bearer TOKEN1"
# Response: {"is_your_turn":true,...}

# 6. Play Card (Player 1)
curl -X POST http://localhost/xeri/api/game.php \
  -H "Authorization: Bearer TOKEN1" \
  -H "Content-Type: application/json" \
  -d '{"game_id":1,"card":"7H"}'
# Response: {"captured":false,...}

# 7. Check Status (Player 2)
curl "http://localhost/xeri/api/status.php?game_id=1" \
  -H "Authorization: Bearer TOKEN2"
# Response: {"is_your_turn":true,...}

# 8. Play Card (Player 2)
curl -X POST http://localhost/xeri/api/game.php \
  -H "Authorization: Bearer TOKEN2" \
  -H "Content-Type: application/json" \
  -d '{"game_id":1,"card":"7D"}'
# Response: {"captured":true,"xeri":true,...}

# Repeat steps 5-8 until game ends
```

---
