
let gameId, playerId, myTurn = false, isRolling = false;
let players = [];
let lastTurnIndex = -1;
let prevPositions = {};

const sounds = {
    roll: new Audio('https://www.soundjay.com/misc/sounds/dice-roll-1.mp3'),
    move: new Audio('https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3'),
    snake: new Audio('https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3'),
    ladder: new Audio('https://assets.mixkit.co/active_storage/sfx/2019/2019-preview.mp3'),
    win: new Audio('https://assets.mixkit.co/active_storage/sfx/2013/2013-preview.mp3')
};

const bgMusic = new Audio('https://www.soundhelix.com/examples/mp3/SoundHelix-Song-17.mp3');
bgMusic.loop = true;
bgMusic.volume = 0.2;

function playSound(type) {
    if (sounds[type]) {
        sounds[type].currentTime = 0;
        sounds[type].play().catch(e => { });
    }
}

function startBgMusic() {
    bgMusic.play().catch(e => {
        document.addEventListener('click', () => { bgMusic.play(); }, { once: true });
    });
}

// Config strictly matches the single "easy" mode
const config = {
    snakes: { 17: 6, 54: 34, 63: 19, 64: 60, 87: 36, 98: 79, 95: 75, 93: 73 },
    ladders: { 1: 38, 4: 14, 9: 31, 21: 42, 28: 84, 51: 67, 72: 91, 80: 99 }
};

// Chat Event
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && document.activeElement.id === 'chat-input') {
        sendMessage();
    }
});

function initOnlineGame(gId, pId) {
    gameId = gId;
    playerId = pId;

    // Render board (same as local)
    const board = document.getElementById("game-board");
    board.innerHTML = '';
    for (let row = 9; row >= 0; row--) {
        for (let col = 0; col < 10; col++) {
            let num = (row % 2 === 0) ? row * 10 + (10 - col) : row * 10 + col + 1;
            const cell = document.createElement("div");
            cell.className = "box";
            cell.id = "box-" + num;
            board.appendChild(cell);
        }
    }

    // Start polling
    setInterval(pollGameState, 2000);
    pollGameState();

    // Start peaceful bg music
    startBgMusic();
}

async function pollGameState() {
    const formData = new FormData();
    formData.append('action', 'get_game_state');
    formData.append('game_id', gameId);
    formData.append('player_id', playerId);

    try {
        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            updateGameState(data);
        } else if (data.status === 'error' && data.message === 'Game no longer exists') {
            alert("Game ended or timed out.");
            window.location.href = 'index.html';
        }
    } catch (e) {
        console.error(e);
    }
}

function updateGameState(data) {
    players = data.players;
    const turnIndex = parseInt(data.turn_index);
    const now = new Date();

    // Update Players List & Tokens
    const playerList = document.getElementById('player-list');
    playerList.innerHTML = '';

    // Handle tokens
    const currentTokenIds = players.map(p => `token-${p.id}`);
    document.querySelectorAll('.token').forEach(t => {
        if (!currentTokenIds.includes(t.id)) t.remove();
    });

    players.forEach((p, i) => {
        const isDisconnected = parseInt(p.is_connected) === 0;
        let disconnectTimer = "";
        let turnBadge = "";

        if (isDisconnected && p.seconds_since_disconnected !== null) {
            const diff = parseInt(p.seconds_since_disconnected);
            const remaining = Math.max(0, 15 - diff);
            disconnectTimer = `<div style="color: #ff3b30; font-size: 0.75rem; font-weight: bold; margin-top: 4px;">Retrying: ${remaining}s</div>`;
        }

        if (i === turnIndex && data.game_status === 'playing') {
            const diff = parseInt(data.turn_seconds_elapsed || 0);
            const timeLeft = Math.max(0, 10 - diff);
            turnBadge = `<div class="timer-badge" style="display:block;">${timeLeft}s</div>`;
        }

        // Update Sidebar with Premium Card
        playerList.innerHTML += `
          <div class="player-card ${i === turnIndex ? 'active' : ''} ${isDisconnected ? 'disconnected' : ''}">
            <div class="color-dot" style="background-color: ${p.color}; color: rgba(255,255,255,0.8);">
                ${p.name.charAt(0).toUpperCase()}
            </div>
            <div class="player-info" style="flex: 1;">
                <h4>${p.name} ${p.id == playerId ? '(You)' : ''}</h4>
                <p>Score: ${p.score}</p>
                ${disconnectTimer}
                ${turnBadge}
            </div>
          </div>
        `;

        if (!isDisconnected) {
            // Update Token
            let token = document.getElementById(`token-${p.id}`);
            if (!token) {
                token = document.createElement("div");
                token.className = "token";
                token.id = `token-${p.id}`;
                token.style.backgroundColor = p.color;
                document.getElementById("game-board").appendChild(token);
            }

            // Move token using transform for better performance/animations
            const coords = getPosition(parseInt(p.position));
            const offset = i * 5;
            token.style.transform = `translate(${coords.x + 5 + offset}px, ${coords.y + 5 + offset}px)`;
            token.style.left = "0px";
            token.style.top = "0px";
            token.style.opacity = "1";
        } else {
            const token = document.getElementById(`token-${p.id}`);
            if (token) token.style.opacity = "0.3";
        }

        // Sound Logic: Detection of move/snake/ladder via state diff
        const oldPos = prevPositions[p.id];
        const newPos = parseInt(p.position);
        if (oldPos !== undefined && newPos !== oldPos) {
            if (newPos > oldPos) {
                if (newPos - oldPos > 6) playSound('ladder');
                else playSound('move');
            } else {
                playSound('snake');
            }
        }
        prevPositions[p.id] = newPos;
    });

    // Handle Dice Enabling
    const myPlayerIndex = players.findIndex(p => p.id == playerId);
    if (myPlayerIndex === turnIndex && myPlayerIndex !== -1 && data.game_status === 'playing') {
        myTurn = true;
        if (!isRolling) {
            document.getElementById('dice').style.opacity = "1";
            document.getElementById('dice').style.pointerEvents = "auto";
        }
    } else {
        myTurn = false;
        document.getElementById('dice').style.opacity = "0.5";
        document.getElementById('dice').style.pointerEvents = "none";
    }

    // Update Chat
    const chatBox = document.getElementById('chat-messages');
    if (data.messages && chatBox) {
        const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;

        chatBox.innerHTML = data.messages.map(m => `
            <div class="mb-1">
                <span style="color: ${m.color}; font-weight: bold;">${m.name}:</span> 
                <span style="color: #eee;">${m.text}</span>
            </div>
        `).join('');

        if (isAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Check Win

    if (data.game_status === 'finished') {
        const winner = players.find(p => parseInt(p.position) === 100) || players.find(p => parseInt(p.is_connected) === 1);
        if (winner) {
            if (lastTurnIndex !== -2) { // Use -2 as a flag that win sound played
                playSound('win');
                lastTurnIndex = -2;
            }
            const scoresData = players.map(p => ({ name: p.name, score: p.score }));
            const scoresJson = encodeURIComponent(JSON.stringify(scoresData));
            setTimeout(() => {
                window.location.href = `winner.php?winner=${encodeURIComponent(winner.name)}&scores=${scoresJson}`;
            }, 1500);
        } else if (players.length === 0) {
            alert("Everyone left the game.");
            window.location.href = 'index.html';
        }
    }
}

function getPosition(cell) {
    if (cell === 0) return { x: -20, y: 580 };
    const board = document.getElementById('game-board');
    const size = board.offsetWidth / 10;
    const row = Math.floor((cell - 1) / 10);
    let col = (cell - 1) % 10;
    if (row % 2 === 1) col = 9 - col;
    return {
        x: col * size,
        y: board.offsetHeight - (row + 1) * size
    };
}

function rollDice() {
    if (!myTurn) return;

    const dice = document.getElementById('dice');
    const result = Math.floor(Math.random() * 6) + 1;

    // Animation
    isRolling = true;
    dice.classList.remove("reRoll");
    void dice.offsetWidth;
    dice.classList.add("reRoll");
    dice.dataset.side = result;
    playSound('roll');

    setTimeout(async () => {
        isRolling = false;
        const myPlayer = players.find(p => p.id == playerId);
        if (!myPlayer) return;

        // Send update with just the roll, let server decide position
        const formData = new FormData();
        formData.append('action', 'update_move');
        formData.append('player_id', playerId);
        formData.append('game_id', gameId);
        formData.append('roll', result);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success') {
                myTurn = false;
                // document.getElementById('dice').style.pointerEvents = "none";
            } else {
                alert(data.message);
            }
        } catch (e) {
            console.error("Move update failed", e);
        }
    }, 600); // Quicker 600ms response
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text) return;

    input.value = '';
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('game_id', gameId);
    formData.append('player_id', playerId);
    formData.append('text', text);

    await fetch('api.php', { method: 'POST', body: formData });
}

async function exitGame() {
    if (!confirm("Are you sure you want to exit the game?")) return;

    const formData = new FormData();
    formData.append('action', 'leave_game');
    formData.append('game_id', gameId);
    formData.append('player_id', playerId);

    try {
        await fetch('api.php', { method: 'POST', body: formData });
    } catch (e) { }

    // Clear session
    localStorage.removeItem('gameId');
    localStorage.removeItem('playerId');
    localStorage.removeItem('isHost');

    window.location.href = 'index.html';
}

window.addEventListener('beforeunload', () => {
    // Send immediate leave signal using beacon
    const data = new FormData();
    data.append('action', 'leave_game');
    data.append('game_id', gameId);
    data.append('player_id', playerId);
    navigator.sendBeacon('api.php', data);
});

// Handle resizing (rest of the file...)
window.addEventListener('resize', () => {
    if (players.length > 0) {
        // Redraw tokens on next poll or manually here
        players.forEach((p, i) => {
            let token = document.getElementById(`token-${p.id}`);
            if (token && parseInt(p.is_connected) === 1) {
                const coords = getPosition(parseInt(p.position));
                const offset = i * 5;
                token.style.transform = `translate(${coords.x + 5 + offset}px, ${coords.y + 5 + offset}px)`;
            }
        });
    }
});
