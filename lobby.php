<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Snake & Ladder - Lobby</title>
  <link href="style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="bg-wrapper">
  
  <!-- Initial Selection -->
  <div class="form-box text-center" id="lobby-menu">
    <h2 class="mb-4" style="font-family: 'Luckiest Guy'; color: var(--accent-color);">Online Multiplayer</h2>
    <button class="btn-custom" onclick="showCreate()">Create Group</button>
    <br>
    <button class="btn-custom" onclick="showJoin()">Join Group</button>
    <br>
    <a href="index.html" class="text-white mt-3 d-inline-block">Back to Menu</a>
  </div>

  <!-- Create Group Form -->
  <div class="form-box text-center" id="create-form" style="display:none;">
    <h3 class="mb-3">Create Group</h3>
    <input type="text" id="host-name" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
    <select id="host-color" class="form-select mb-3">
        <option value="RED">Red</option>
        <option value="BLUE">Blue</option>
        <option value="GREEN">Green</option>
        <option value="YELLOW">Yellow</option>
    </select>
    <button class="btn-custom" onclick="createGame()">Create</button>
    <button class="btn btn-secondary mt-2" onclick="showMenu()">Back</button>
  </div>

  <!-- Join Group Form -->
  <div class="form-box text-center" id="join-form" style="display:none;">
    <h3 class="mb-3">Join Group</h3>
    <input type="text" id="join-code" class="form-control mb-2" placeholder="Enter Game Code">
    <input type="text" id="player-name" class="form-control mb-2" value="<?= htmlspecialchars($username) ?>" readonly>
    <select id="player-color" class="form-select mb-3">
        <option value="RED">Red</option>
        <option value="BLUE">Blue</option>
        <option value="GREEN">Green</option>
        <option value="YELLOW">Yellow</option>
    </select>
    <button class="btn-custom" onclick="joinGame()">Join</button>
    <button class="btn btn-secondary mt-2" onclick="showMenu()">Back</button>
  </div>

  <!-- Waiting Room -->
  <div class="form-box text-center" id="waiting-room" style="display:none;">
    <h3 class="mb-2">Waiting Room</h3>
    <h1 id="display-code" style="font-family: 'Luckiest Guy'; color: gold; letter-spacing: 5px;">CODE</h1>
    <p>Share this code with your friends!</p>
    
    <div id="player-list-display" class="text-start mb-4" style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 10px;">
        <!-- Players will appear here -->
    </div>

    <div id="host-controls" style="display:none;">
        <button class="btn-custom" onclick="startGame()">Start Game</button>
    </div>
    <div id="waiting-msg">Waiting for host to start...</div>
  </div>

</div>

<script>
let gameId = null;
let playerId = null;
let isHost = false;
let pollInterval = null;

function showMenu() {
    document.getElementById('lobby-menu').style.display = 'block';
    document.getElementById('create-form').style.display = 'none';
    document.getElementById('join-form').style.display = 'none';
}

function showCreate() {
    document.getElementById('lobby-menu').style.display = 'none';
    document.getElementById('create-form').style.display = 'block';
}

function showJoin() {
    document.getElementById('lobby-menu').style.display = 'none';
    document.getElementById('join-form').style.display = 'block';
}

async function createGame() {
    const name = document.getElementById('host-name').value;
    const color = document.getElementById('host-color').value;
    if(!name) return alert("Enter name");

    const formData = new FormData();
    formData.append('action', 'create_game');
    formData.append('name', name);
    formData.append('color', color);
    formData.append('user_id', '<?= $user_id ?>');

    const res = await fetch('api.php', { method: 'POST', body: formData });
    const data = await res.json();

    if(data.status === 'success') {
        gameId = data.game_id;
        playerId = data.player_id;
        isHost = true;
        
        localStorage.setItem('gameId', gameId);
        localStorage.setItem('playerId', playerId);
        localStorage.setItem('isHost', 'true');
        
        document.getElementById('display-code').innerText = data.code;
        enterWaitingRoom();
    } else {
        alert(data.message);
    }
}

async function joinGame() {
    const code = document.getElementById('join-code').value;
    const name = document.getElementById('player-name').value;
    const color = document.getElementById('player-color').value;
    if(!code || !name) return alert("Fill all fields");

    const formData = new FormData();
    formData.append('action', 'join_game');
    formData.append('code', code);
    formData.append('name', name);
    formData.append('color', color);
    formData.append('user_id', '<?= $user_id ?>');

    const res = await fetch('api.php', { method: 'POST', body: formData });
    const data = await res.json();

    if(data.status === 'success') {
        gameId = data.game_id;
        playerId = data.player_id;
        isHost = false;
        
        localStorage.setItem('gameId', gameId);
        localStorage.setItem('playerId', playerId);
        localStorage.setItem('isHost', 'false');
        
        document.getElementById('display-code').innerText = code;
        enterWaitingRoom();
    } else {
        alert(data.message);
    }
}

async function checkSavedSession() {
    const savedGameId = localStorage.getItem('gameId');
    const savedPlayerId = localStorage.getItem('playerId');
    const savedIsHost = localStorage.getItem('isHost');

    if (savedGameId && savedPlayerId) {
        // Test if session is still valid
        const formData = new FormData();
        formData.append('action', 'get_lobby_status');
        formData.append('game_id', savedGameId);
        formData.append('player_id', savedPlayerId);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                gameId = savedGameId;
                playerId = savedPlayerId;
                isHost = (savedIsHost === 'true');
                
                // If game is already playing, redirect
                if (data.game_status === 'playing') {
                    window.location.href = `online_board.php?game_id=${gameId}&player_id=${playerId}`;
                    return;
                }
                
                // Otherwise re-enter waiting room
                document.getElementById('display-code').innerText = data.players.length > 0 ? "RECOVERED" : "CODE";
                enterWaitingRoom();
                return;
            }
        } catch(e) { console.warn("Failed to recover session", e); }
    }
    showMenu();
}

window.onload = checkSavedSession;

function enterWaitingRoom() {
    document.getElementById('create-form').style.display = 'none';
    document.getElementById('join-form').style.display = 'none';
    document.getElementById('waiting-room').style.display = 'block';
    
    if(isHost) {
        document.getElementById('host-controls').style.display = 'block';
        document.getElementById('waiting-msg').style.display = 'none';
    }

    pollInterval = setInterval(updateLobby, 2000);
    updateLobby();
}

async function updateLobby() {
    const formData = new FormData();
    formData.append('action', 'get_lobby_status');
    formData.append('game_id', gameId);
    formData.append('player_id', playerId);

    const res = await fetch('api.php', { method: 'POST', body: formData });
    const data = await res.json();

    if(data.status === 'success') {
        const list = document.getElementById('player-list-display');
        list.innerHTML = '';
        data.players.forEach(p => {
            const isDisconnected = parseInt(p.is_connected) === 0;
            list.innerHTML += `<div class="d-flex align-items-center mb-2" style="opacity: ${isDisconnected ? 0.5 : 1}">
                <div style="width: 15px; height: 15px; background: ${p.color}; border-radius: 50%; margin-right: 10px; border: 1px solid white;"></div>
                <strong>${p.name}</strong> ${p.is_host == 1 ? '(Host)' : ''} ${isDisconnected ? '<span class="badge bg-danger ms-2">Disconnected</span>' : ''}
            </div>`;
        });

        if(data.game_status === 'playing') {
            window.location.href = `online_board.php?game_id=${gameId}&player_id=${playerId}`;
        } else if (data.game_status === 'finished') {
            alert("Game session has ended.");
            clearSession();
            window.location.href = 'lobby.php';
        }

        // Self-check: If I'm not in the list, I might have timed out
        const me = data.players.find(p => p.id == playerId);
        if (!me) {
            console.warn("My record is gone from player list.");
            // Optional: Re-join? Or alert and logout.
            // For now, let's keep it simple.
        }

    } else if (data.status === 'error') {
        alert("Lobby Error: " + data.message);
        clearSession();
        window.location.href = 'lobby.php';
    }
}

async function startGame() {
    if (!gameId) return alert("Game ID missing!");
    
    // Disable button to prevent double clicks and show feedback
    const btn = document.querySelector('#host-controls button');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = "Starting...";

    try {
        const formData = new FormData();
        formData.append('action', 'start_game');
        formData.append('game_id', gameId);
        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success') {
            console.log("Game status updated to playing. Waiting for poll to redirect.");
        } else {
            btn.disabled = false;
            btn.innerText = originalText;
            alert(data.message || 'Could not start game');
        }
    } catch (e) {
        console.error("Fetch error:", e);
        btn.disabled = false;
        btn.innerText = originalText;
        alert("Failed to connect to server.");
    }
}
function clearSession() {
    localStorage.removeItem('gameId');
    localStorage.removeItem('playerId');
    localStorage.removeItem('isHost');
}
</script>

</body>
</html>
