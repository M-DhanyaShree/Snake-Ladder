<?php
$servername = "localhost";
$username = "root";
$password = "Dhan@122006";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS snake_ladder_db";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

$conn->select_db("snake_ladder_db");

// Create games table
$sql = "CREATE TABLE IF NOT EXISTS games (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'waiting',
    turn_index INT(6) DEFAULT 0,
    turn_started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'games' created successfully<br>";
} else {
    echo "Error creating table 'games': " . $conn->error . "<br>";
}

// Create players table
$sql = "CREATE TABLE IF NOT EXISTS players (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT(6) UNSIGNED,
    user_id INT(6) UNSIGNED NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(20) NOT NULL,
    position INT(6) DEFAULT 0,
    score INT(6) DEFAULT 0,
    is_host BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_connected TINYINT(1) DEFAULT 1,
    disconnected_at TIMESTAMP NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "Table 'players' created or already exists<br>";
} else {
    echo "Error creating table 'players': " . $conn->error . "<br>";
}

// Create users table for authentication
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    wins INT(6) DEFAULT 0,
    points INT(10) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully<br>";
} else {
    echo "Error creating table 'users': " . $conn->error . "<br>";
}

// MIGRATION: Add points column to users if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'points'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN points INT(10) DEFAULT 0");
}

// MIGRATION: Add missing columns if tables already existed
$result = $conn->query("SHOW COLUMNS FROM games LIKE 'turn_started_at'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE games ADD COLUMN turn_started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

$result = $conn->query("SHOW COLUMNS FROM players LIKE 'user_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE players ADD COLUMN user_id INT(6) UNSIGNED NULL");
}

$result = $conn->query("SHOW COLUMNS FROM players LIKE 'is_connected'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE players ADD COLUMN is_connected TINYINT(1) DEFAULT 1");
    $conn->query("ALTER TABLE players ADD COLUMN disconnected_at TIMESTAMP NULL");
    echo "Upgraded 'players' table with new columns<br>";
}

// Create winners table for leaderboard
$sql = "CREATE TABLE IF NOT EXISTS winners (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    score INT(6) NOT NULL,
    won_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create messages table for chat
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT(6) UNSIGNED,
    player_id INT(6) UNSIGNED,
    text VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
)";
$conn->query($sql);

$conn->close();


?>
<br>
<a href="index.html">Back to Home</a>
