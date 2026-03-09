<?php
$servername = "localhost";
$username = "root";
$password = "password";
$dbname = "snake_ladder_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    throw new Exception("Connection failed: " . $conn->connect_error);
}
