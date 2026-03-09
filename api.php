<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$action = isset($_POST['action']) ? $_POST['action'] : '';

// DEBUG LOGGING
function debug_log($msg) {
    file_put_contents('api_debug.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// Global Exception Handler
set_exception_handler(function($e) {
    debug_log("Uncaught Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    debug_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    // STRICTOR: Also treat Warnings/Notices as fatal for the API to avoid "Undefined array index" returning success
    echo json_encode(['status' => 'error', 'message' => "Server Logic Error ($errno): $errstr"]);
    exit;
});

include 'db.php';

// Helper to record winner
function recordWinner($conn, $game_id, $winner) {
    if (!$winner) return;

    $stmt = $conn->prepare("UPDATE games SET status = 'finished' WHERE id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();

    // Record Winner for general winners table
    $stmt_w = $conn->prepare("INSERT INTO winners (name, score) VALUES (?, ?)");
    $stmt_w->bind_param("si", $winner['name'], $winner['score']);
    $stmt_w->execute();
        
    // Update points and wins for ALL players who are registered users
    $stmt_p = $conn->prepare("SELECT user_id, name, score FROM players WHERE game_id = ? AND user_id IS NOT NULL");
    $stmt_p->bind_param("i", $game_id);
    $stmt_p->execute();
    $ps = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach($ps as $p) {
        $addWin = ($winner['user_id'] == $p['user_id']) ? 1 : 0;
        $stmt_u = $conn->prepare("UPDATE users SET wins = wins + ?, points = points + ? WHERE id = ?");
        $stmt_u->bind_param("iii", $addWin, $p['score'], $p['user_id']);
        $stmt_u->execute();
    }
}

// Helper function to check timeouts
function checkTimeouts($conn, $game_id) {
    // 1. Mark players as disconnected if last_active > 15 seconds
    $stmt = $conn->prepare("UPDATE players SET is_connected = 0, disconnected_at = NOW() WHERE game_id = ? AND is_connected = 1 AND UNIX_TIMESTAMP(last_active) < (UNIX_TIMESTAMP() - 15)");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();

    // 2. Fetch all players to decide what to do
    $stmt = $conn->prepare("SELECT *, (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(disconnected_at)) as seconds_since_disconnected FROM players WHERE game_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $all_players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (count($all_players) == 0) return;

    $connected_players = array_filter($all_players, function($p) { return $p['is_connected'] == 1; });
    $disconnected_players = array_filter($all_players, function($p) { return $p['is_connected'] == 0; });
    
    // If everyone is gone
    if (count($connected_players) == 0) {
        $stmt = $conn->prepare("UPDATE games SET status = 'finished' WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        return;
    }

    // If only one player is left and others have been disconnected for a total of > 15 seconds
    // Since they were marked as 0 ONLY after 15s of silence, seeing any 0 player is enough if they've been 0 for even 1 more second.
    $waiting_to_remove = array_filter($disconnected_players, function($p) { 
        return $p['seconds_since_disconnected'] !== null && $p['seconds_since_disconnected'] >= 5; 
    });

    if (count($connected_players) == 1 && count($all_players) > 1 && count($waiting_to_remove) > 0) {
        $winner = reset($connected_players);
        recordWinner($conn, $game_id, $winner);
        return;
    }

    // If more than 2 players were playing, and some are waiting to be removed (total 20s skip silence)
    $to_fully_delete = array_filter($disconnected_players, function($p) { 
        return $p['seconds_since_disconnected'] !== null && $p['seconds_since_disconnected'] >= 30; 
    });

    if (count($to_fully_delete) > 0) {
        $stmt_game = $conn->prepare("SELECT turn_index FROM games WHERE id = ?");
        $stmt_game->bind_param("i", $game_id);
        $stmt_game->execute();
        $turn_idx = $stmt_game->get_result()->fetch_assoc()['turn_index'];

        foreach ($to_fully_delete as $rm) {
            // Find index of player to remove
            $idx = -1;
            foreach($all_players as $i => $p) {
                if ($p['id'] == $rm['id']) { $idx = $i; break; }
            }

            $stmt_del = $conn->prepare("DELETE FROM players WHERE id = ?");
            $stmt_del->bind_param("i", $rm['id']);
            $stmt_del->execute();

            if ($idx < $turn_idx) {
                $turn_idx--;
            } elseif ($idx == $turn_idx) {
                if ($turn_idx >= count($all_players) - 1) $turn_idx = 0;
            }
            // Refresh list
            $stmt = $conn->prepare("SELECT id FROM players WHERE game_id = ? ORDER BY id ASC");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $all_players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $new_turn = $turn_idx % count($all_players);
        $stmt_upd = $conn->prepare("UPDATE games SET turn_index = ?, turn_started_at = NOW() WHERE id = ?");
        $stmt_upd->bind_param("ii", $new_turn, $game_id);
        $stmt_upd->execute();
    }

    // Turn Timer Check (10 seconds)
    $stmt_g = $conn->prepare("SELECT turn_index, status, TIMESTAMPDIFF(SECOND, turn_started_at, NOW()) as seconds_elapsed FROM games WHERE id = ?");
    $stmt_g->bind_param("i", $game_id);
    $stmt_g->execute();
    $g = $stmt_g->get_result()->fetch_assoc();

    if ($g && $g['status'] == 'playing' && $g['seconds_elapsed'] > 10) {
        $found_next = false;
        $next_turn = $g['turn_index'];
        $num_players = count($all_players);
        
        // Loop to find next connected player
        for ($i = 0; $i < $num_players; $i++) {
            $next_turn = ($next_turn + 1) % $num_players;
            if ($all_players[$next_turn]['is_connected'] == 1) {
                $found_next = true;
                break;
            }
        }
        
        if ($found_next) {
            $stmt_nxt = $conn->prepare("UPDATE games SET turn_index = ?, turn_started_at = NOW() WHERE id = ?");
            $stmt_nxt->bind_param("ii", $next_turn, $game_id);
            $stmt_nxt->execute();
        } else {
            // No connected players? Wait for recordWinner above to finish game if applicable
            // This case shouldn't happen often as recordWinner handles 0/1 players
        }
    }
}

if ($action == 'create_game') {
    $name = $_POST['name'];
    $color = $_POST['color'];
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $stmt = $conn->prepare("INSERT INTO games (code, turn_started_at) VALUES (?, NOW())");
    $stmt->bind_param("s", $code);
    if ($stmt->execute()) {
        $game_id = $stmt->insert_id;
        $stmt2 = $conn->prepare("INSERT INTO players (game_id, user_id, name, color, is_host) VALUES (?, ?, ?, ?, 1)");
        $stmt2->bind_param("iiss", $game_id, $user_id, $name, $color);
        $stmt2->execute();
        $player_id = $stmt2->insert_id;
        
        echo json_encode(['status' => 'success', 'code' => $code, 'game_id' => $game_id, 'player_id' => $player_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create game']);
    }
}

elseif ($action == 'join_game') {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $color = $_POST['color'];
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    
    $stmt = $conn->prepare("SELECT id, status FROM games WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $game = $result->fetch_assoc();
        if ($game['status'] != 'waiting') {
            echo json_encode(['status' => 'error', 'message' => 'Game already started']);
            exit;
        }
        
        $game_id = $game['id'];
        
        // Ensure unique color
        $stmt_colors = $conn->prepare("SELECT color FROM players WHERE game_id = ?");
        $stmt_colors->bind_param("i", $game_id);
        $stmt_colors->execute();
        $taken_colors = $stmt_colors->get_result()->fetch_all(MYSQLI_ASSOC);
        $taken_list = array_column($taken_colors, 'color');
        
        $available_colors = ["RED", "BLUE", "GREEN", "YELLOW", "PURPLE", "ORANGE"];
        if (in_array($color, $taken_list)) {
            // Pick first available
            foreach($available_colors as $ac) {
                if (!in_array($ac, $taken_list)) {
                    $color = $ac;
                    break;
                }
            }
        }

        $stmt2 = $conn->prepare("INSERT INTO players (game_id, user_id, name, color) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("iiss", $game_id, $user_id, $name, $color);
        if ($stmt2->execute()) {
            $player_id = $stmt2->insert_id;
            echo json_encode(['status' => 'success', 'game_id' => $game_id, 'player_id' => $player_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to join']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid code']);
    }
}

elseif ($action == 'heartbeat') {
    $player_id = $_POST['player_id'];
    $game_id = $_POST['game_id'];
    
    $stmt = $conn->prepare("UPDATE players SET last_active = NOW(), is_connected = 1, disconnected_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    checkTimeouts($conn, $game_id);
    echo json_encode(['status' => 'success']);
}

elseif ($action == 'leave_game') {
    $player_id = $_POST['player_id'];
    $game_id = $_POST['game_id'];
    
    // Mark as disconnected immediately
    $stmt = $conn->prepare("UPDATE players SET is_connected = 0, disconnected_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    // Get all players for this game
    $stmt_p = $conn->prepare("SELECT * FROM players WHERE game_id = ?");
    $stmt_p->bind_param("i", $game_id);
    $stmt_p->execute();
    $all = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);
    $active_players = array_filter($all, function($p) { return $p['is_connected'] == 1; });
    $active_count = count($active_players);
    
    if ($active_count == 0) {
        $stmt_f = $conn->prepare("UPDATE games SET status = 'finished' WHERE id = ?");
        $stmt_f->bind_param("i", $game_id);
        $stmt_f->execute();
    } elseif ($active_count == 1 && count($all) > 1) {
        // Multi-player game, only one left: they win!
        recordWinner($conn, $game_id, reset($active_players));
    }
    
    echo json_encode(['status' => 'success']);
}

elseif ($action == 'get_lobby_status') {
    $game_id = $_POST['game_id'];
    $player_id = isset($_POST['player_id']) ? $_POST['player_id'] : null;
    
    if ($player_id) {
        $stmt = $conn->prepare("UPDATE players SET last_active = NOW(), is_connected = 1, disconnected_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $player_id);
        $stmt->execute();
    }
    
    checkTimeouts($conn, $game_id);
    
    $stmt = $conn->prepare("SELECT * FROM players WHERE game_id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    
    $stmt2 = $conn->prepare("SELECT status FROM games WHERE id = ?");
    $stmt2->bind_param("i", $game_id);
    $stmt2->execute();
    $game_res = $stmt2->get_result();
    if ($game_res->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Game not found']);
        exit;
    }
    $game = $game_res->fetch_assoc();
    
    echo json_encode(['status' => 'success', 'players' => $players, 'game_status' => $game['status'], 'server_time' => date('Y-m-d H:i:s')]);
}

elseif ($action == 'start_game') {
    $game_id = $_POST['game_id'];
    
    // Check if we have at least 2 connected players
    $stmt_c = $conn->prepare("SELECT COUNT(*) as count FROM players WHERE game_id = ? AND is_connected = 1");
    $stmt_c->bind_param("i", $game_id);
    $stmt_c->execute();
    $count = $stmt_c->get_result()->fetch_assoc()['count'];
    
    if ($count < 2) {
        echo json_encode(['status' => 'error', 'message' => 'Waiting for more players. You need at least 2 to start.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE games SET status = 'playing' WHERE id = ?");
    $stmt->bind_param("i", $game_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

elseif ($action == 'update_move') {
    $player_id = $_POST['player_id'];
    $game_id = $_POST['game_id'];
    if (!isset($_POST['roll']) || empty($_POST['roll'])) {
         throw new Exception("Missing roll parameter. Please refresh the page (Cache issue?)");
    }
    $roll = intval($_POST['roll']); 
    
    // Check timeouts first to update game state if someone left
    checkTimeouts($conn, $game_id);
    
    // 1. Verify it's this player's turn
    $stmt_game = $conn->prepare("SELECT turn_index, status FROM games WHERE id = ?");
    $stmt_game->bind_param("i", $game_id);
    $stmt_game->execute();
    $gameData = $stmt_game->get_result()->fetch_assoc();
    
    if (!$gameData || $gameData['status'] != 'playing') {
        echo json_encode(['status' => 'error', 'message' => 'Game not in playing state']);
        exit;
    }

    $stmt_players = $conn->prepare("SELECT id, user_id, name, position, score FROM players WHERE game_id = ? ORDER BY id ASC");
    $stmt_players->bind_param("i", $game_id);
    $stmt_players->execute();
    $all_players = $stmt_players->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $turn_idx = $gameData['turn_index'];
    if ($all_players[$turn_idx]['id'] != $player_id) {
        echo json_encode(['status' => 'error', 'message' => 'Not your turn']);
        exit;
    }

    // 2. Calculate Move (Server Side Validation)
    $current_p = $all_players[$turn_idx];
    $old_pos = intval($current_p['position']);
    $old_score = intval($current_p['score']);
    
    $new_pos = $old_pos + $roll;
    $new_score = $old_score + $roll;
    
    if ($new_pos > 100) {
        $new_pos = $old_pos; // Stay put if over 100
        $new_score = $old_score;
    } else {
        // Snakes and Ladders Configuration (Strictly matches the single "easy" mode)
        $ladders = [1 => 38, 4 => 14, 9 => 31, 21 => 42, 28 => 84, 51 => 67, 72 => 91, 80 => 99];
        $snakes = [17 => 6, 54 => 34, 63 => 19, 64 => 60, 87 => 36, 98 => 79, 95 => 75, 93 => 73];
        
        if (isset($ladders[$new_pos])) {
            $dest = $ladders[$new_pos];
            $new_score += ($dest - $new_pos);
            $new_pos = $dest;
        } elseif (isset($snakes[$new_pos])) {
            $dest = $snakes[$new_pos];
            $new_score -= ($new_pos - $dest);
            $new_pos = $dest;
        }
    }

    // 3. Update Database
    $stmt = $conn->prepare("UPDATE players SET position = ?, score = ?, last_active = NOW(), is_connected = 1, disconnected_at = NULL WHERE id = ?");
    $stmt->bind_param("iii", $new_pos, $new_score, $player_id);
    $stmt->execute();
    
    if ($new_pos == 100) {
        recordWinner($conn, $game_id, $current_p);
    } else {
        // Next turn
        $total_players = count($all_players);
        $next_turn = ($turn_idx + 1) % $total_players;
        $stmt2 = $conn->prepare("UPDATE games SET turn_index = ?, turn_started_at = NOW() WHERE id = ?");
        $stmt2->bind_param("ii", $next_turn, $game_id);
        $stmt2->execute();
    }
    
    echo json_encode(['status' => 'success', 'new_position' => $new_pos, 'new_score' => $new_score]);
}

elseif ($action == 'send_message') {
    $game_id = $_POST['game_id'];
    $player_id = $_POST['player_id'];
    $text = $_POST['text'];
    
    $stmt = $conn->prepare("INSERT INTO messages (game_id, player_id, text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $game_id, $player_id, $text);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
}

elseif ($action == 'get_game_state') {
    $game_id = $_POST['game_id'];
    $player_id = isset($_POST['player_id']) ? $_POST['player_id'] : null;

    if ($player_id) {
        $stmt = $conn->prepare("UPDATE players SET last_active = NOW(), is_connected = 1, disconnected_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $player_id);
        $stmt->execute();
    }
    
    checkTimeouts($conn, $game_id);
    
    // Get players
    $stmt = $conn->prepare("SELECT id, name, color, position, score, is_connected, disconnected_at, TIMESTAMPDIFF(SECOND, disconnected_at, NOW()) as seconds_since_disconnected FROM players WHERE game_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get game info
    $stmt2 = $conn->prepare("SELECT turn_index, status, turn_started_at, TIMESTAMPDIFF(SECOND, turn_started_at, NOW()) as turn_seconds_elapsed FROM games WHERE id = ?");
    $stmt2->bind_param("i", $game_id);
    $stmt2->execute();
    $game_res = $stmt2->get_result();
    if ($game_res->num_rows == 0) {
        // Game might have been deleted if last player timed out
        echo json_encode(['status' => 'error', 'message' => 'Game no longer exists']);
        exit;
    }
    $game = $game_res->fetch_assoc();
    
    // Get last messages
    $stmt_msg = $conn->prepare("SELECT m.text, p.name, p.color, m.created_at FROM messages m JOIN players p ON m.player_id = p.id WHERE m.game_id = ? ORDER BY m.created_at DESC LIMIT 15");
    $stmt_msg->bind_param("i", $game_id);
    $stmt_msg->execute();
    $messages = $stmt_msg->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'status' => 'success', 
        'players' => $players, 
        'turn_index' => $game['turn_index'], 
        'turn_seconds_elapsed' => $game['turn_seconds_elapsed'],
        'game_status' => $game['status'],
        'messages' => array_reverse($messages)
    ]);
}

$conn->close();
?>
