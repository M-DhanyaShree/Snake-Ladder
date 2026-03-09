<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'register') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
    
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['username'] = $username;
        echo json_encode(['status' => 'success', 'message' => 'Registered successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
    }
}

elseif ($action == 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        echo json_encode(['status' => 'success', 'message' => 'Logged in successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
    }
}

elseif ($action == 'logout') {
    session_destroy();
    echo json_encode(['status' => 'success']);
}

elseif ($action == 'get_session') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'success', 'username' => $_SESSION['username'], 'user_id' => $_SESSION['user_id']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    }
}

$conn->close();
?>
