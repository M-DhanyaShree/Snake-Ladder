<?php
$servername = "localhost";
$username = "root";
$password = "Dhan@122006";
$dbname = "snake_ladder_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    throw new Exception("Connection failed: " . $conn->connect_error);
}
