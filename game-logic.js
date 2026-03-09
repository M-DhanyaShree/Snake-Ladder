
const config = {
    snakes: { 17: 7, 54: 34, 63: 19, 64: 60, 87: 36, 98: 79, 95: 75, 93: 73 },
    ladders: { 1: 38, 4: 14, 9: 31, 21: 42, 28: 84, 51: 67, 72: 91, 80: 99 }
};

let gameMode = 'local';
let players = [];
let currentPlayerIndex = 0;
let gameOver = false;
let isRolling = false;
let turnTimer = null;
let timeLeft = 10;
const sounds = {
    roll: new Audio('https://www.soundjay.com/misc/sounds/dice-roll-1.mp3'),
    move: new Audio('https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3'),
    snake: new Audio('https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3'),
    ladder: new Audio('https://assets.mixkit.co/active_storage/sfx/2019/2019-preview.mp3'),
    win: new Audio('https://assets.mixkit.co/active_storage/sfx/2013/2013-preview.mp3'),
    bg: new Audio('https://assets.mixkit.co/active_storage/sfx/123/123-preview.mp3') // Placeholder, let's find a better loop
};

// Better peaceful background music
const bgMusic = new Audio('https://www.soundhelix.com/examples/mp3/SoundHelix-Song-17.mp3');
bgMusic.loop = true;
bgMusic.volume = 0.3;

function playSound(type) {
    if (sounds[type]) {
        sounds[type].currentTime = 0;
        sounds[type].play().catch(e => { });
    }
}

function startBgMusic() {
    bgMusic.play().catch(e => {
        // Auto-play might be blocked, wait for first click
        document.addEventListener('click', () => {
            bgMusic.play();
        }, { once: true });
    });
}

// Initialize game
function initGame(mode, difficulty, playerData) {
    gameMode = mode;
    players = playerData;

    const board = document.getElementById("game-board");
    board.innerHTML = '';
    for (let row = 9; row >= 0; row--) {
        for (let col = 0; col < 10; col++) {
            let num = (row % 2 === 0) ? row * 10 + (10 - col) : row * 10 + col + 1;
            const cell = document.createElement("div");
            cell.className = "box";
            cell.id = "box-" + num;
            // Optional: Add numbers to board for clarity
            // cell.innerText = num; 
            board.appendChild(cell);
        }
    }

    // Initialize tokens
    players.forEach((p, i) => {
        p.position = 0;
        p.score = 0;
        const token = document.createElement("div");
        token.className = "token";
        token.id = `token-${i}`;
        token.style.backgroundColor = p.color;
        // Initial position off-board or at 1? Let's say off-board (0)
        // But visually maybe put them at start
        board.appendChild(token);
        updateTokenPosition(i, 0);
    });

    updateCurrentPlayerDisplay();

    // Set board background
    board.style.backgroundImage = "url('board2.jpg')";

    startTimer();
    startBgMusic();
}

function startTimer() {
    if (turnTimer) clearInterval(turnTimer);
    timeLeft = 10;
    updateTimerUI();
    turnTimer = setInterval(() => {
        if (gameOver || isRolling) return;
        timeLeft--;
        updateTimerUI();
        if (timeLeft <= 0) {
            clearInterval(turnTimer);
            nextTurn();
        }
    }, 1000);
}

function updateTimerUI() {
    const timerEl = document.getElementById(`timer-${currentPlayerIndex}`);
    if (timerEl) {
        timerEl.innerText = `${timeLeft}s`;
        if (timeLeft < 4) {
            timerEl.style.color = "#ff3b30";
            timerEl.style.background = "rgba(255, 59, 48, 0.3)";
        } else {
            timerEl.style.color = "#ff3b30";
            timerEl.style.background = "rgba(255, 59, 48, 0.1)";
        }
    }
}

function getPosition(cell) {
    if (cell === 0) return { x: -20, y: 580 }; // Start position
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

function updateTokenPosition(playerIndex, cell) {
    const token = document.getElementById(`token-${playerIndex}`);
    const coords = getPosition(cell);
    // Add some offset for multiple players on same tile
    const offset = playerIndex * 5;
    token.style.left = `${coords.x + 5 + offset}px`;
    token.style.top = `${coords.y + 5 + offset}px`;
}

function rollDice() {
    if (gameOver) return;

    // AI Check
    if (gameMode === 'computer' && players[currentPlayerIndex].name.startsWith("AI")) {
        // AI logic handles its own roll
        return;
    }

    performRoll();
}

function performRoll() {
    if (isRolling) return;
    isRolling = true;
    playSound('roll');
    const dice = document.getElementById('dice');
    const result = Math.floor(Math.random() * 6) + 1;

    dice.classList.remove("reRoll");
    void dice.offsetWidth;
    dice.classList.add("reRoll");
    dice.dataset.side = result;

    setTimeout(() => {
        isRolling = false;
        handleMove(currentPlayerIndex, result);
    }, 600);
}

function handleMove(pIndex, steps) {
    const player = players[pIndex];
    let newPos = player.position + steps;

    // Rule: Need exact number to finish.
    if (newPos > 100) {
        newPos = player.position; // Stay put
        showPopup(pIndex, "Need exact roll!", player.position);
    } else {
        // Move
        player.position = newPos;
        player.score += steps; // Simple score logic
        playSound('move');
        updateTokenPosition(pIndex, newPos);

        // Check Snakes/Ladders
        setTimeout(() => checkEntity(pIndex), 600);
        return;
    }

    nextTurn();
}

function checkEntity(pIndex) {
    const player = players[pIndex];
    const pos = player.position;

    if (config.ladders[pos]) {
        const dest = config.ladders[pos];
        showPopup(pIndex, "LADDER! 🚀", pos);
        playSound('ladder');
        player.position = dest;
        player.score += (dest - pos);
        setTimeout(() => updateTokenPosition(pIndex, dest), 500);
    } else if (config.snakes[pos]) {
        const dest = config.snakes[pos];
        showPopup(pIndex, "SNAKE! 🐍", pos);
        playSound('snake');
        player.position = dest;
        player.score -= (pos - dest); // Lose points
        setTimeout(() => updateTokenPosition(pIndex, dest), 500);
    }

    if (player.position === 100) {
        handleWin(pIndex);
    } else {
        nextTurn();
    }
}

function nextTurn() {
    if (gameOver) return;
    currentPlayerIndex = (currentPlayerIndex + 1) % players.length;
    updateCurrentPlayerDisplay();
    startTimer();

    if (gameMode === 'computer' && players[currentPlayerIndex].name.includes("AI")) {
        setTimeout(aiTurn, 1000);
    }
}

function aiTurn() {
    if (gameOver) return;
    performRoll();
}

function updateCurrentPlayerDisplay() {
    // Highlight active player card
    document.querySelectorAll('.player-card').forEach((card, i) => {
        if (i === currentPlayerIndex) card.classList.add('active');
        else card.classList.remove('active');

        // Update score in UI
        const scoreEl = document.getElementById(`score-${i}`);
        if (scoreEl && players[i]) scoreEl.innerText = players[i].score;
    });
}

function showPopup(pIndex, text, pos) {
    const board = document.getElementById("game-board");
    const popup = document.createElement("div");
    popup.className = "score-popup show";
    popup.textContent = text;
    const coords = getPosition(pos);
    popup.style.left = (coords.x + 20) + "px";
    popup.style.top = (coords.y - 20) + "px";
    board.appendChild(popup);
    setTimeout(() => popup.remove(), 1500);
}

function handleWin(pIndex) {
    gameOver = true;
    playSound('win');
    const winner = players[pIndex];

    // Prepare data for winner page
    const scoresData = players.map(p => ({ name: p.name, score: p.score }));
    const scoresJson = encodeURIComponent(JSON.stringify(scoresData));

    setTimeout(() => {
        window.location.href = `winner.php?winner=${encodeURIComponent(winner.name)}&scores=${scoresJson}`;
    }, 1000);
}
