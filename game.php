<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Snake & Ladder - Setup</title>
  <link href="style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="bg-wrapper">
  <div class="form-box text-center" id="main-form">
    
    <?php
      $mode = isset($_GET['mode']) ? $_GET['mode'] : 'local';
    ?>

    <h2 class="mb-4" style="font-family: 'Luckiest Guy', cursive; color: var(--accent-color);">
      <?php 
        if($mode == 'computer') echo "Play vs Computer";
        else echo "Local Multiplayer";
      ?>
    </h2>

    <form id="player-count-form">
      <input type="hidden" id="game-mode" value="<?= $mode ?>">
      
      <?php if($mode == 'computer'): ?>
        <!-- Difficulty is fixed to easy -->
        <input type="hidden" name="difficulty" id="difficulty" value="easy">
        <div class="mb-4 text-start">
           <label for="cnt" class="form-label">Number of Computer Players:</label>
           <select name="cnt" id="cnt" class="form-select" required>
             <option value="1">1 Computer</option>
             <option value="2">2 Computers</option>
             <option value="3">3 Computers</option>
           </select>
        </div>
      <?php else: ?>
        <div class="mb-4 text-start">
          <label for="cnt" class="form-label">Choose the number of players:</label>
          <select name="cnt" id="cnt" class="form-select" required>
            <option value="">-- Select --</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6</option>
          </select>
        </div>
      <?php endif; ?>

      <button type="button" onclick="generatePlayerFields()" class="btn-custom">Next</button>
      <br>
      <a href="index.html" class="text-white mt-3 d-inline-block">Back to Menu</a>
    </form>

    <form id="player-names-form" action="board.php" method="post" style="display:none;">
      <input type="hidden" name="mode" value="<?= $mode ?>">
      <input type="hidden" name="difficulty" id="form-difficulty">
    </form>
  </div>
</div>

<script>
const colors = ["RED", "BLUE", "GREEN", "YELLOW", "PURPLE", "ORANGE"];
const colorHex = {
  RED: "#ff4d4d",
  BLUE: "#4d79ff",
  GREEN: "#33cc33",
  YELLOW: "#ffff66",
  PURPLE: "#cc66ff",
  ORANGE: "#ff9933"
};

function generatePlayerFields() {
  const cnt = document.getElementById("cnt").value;
  const mode = document.getElementById("game-mode").value;
  
  if (!cnt) return;

  const namesForm = document.getElementById("player-names-form");
  document.getElementById("player-count-form").style.display = "none";
  namesForm.style.display = "block";
  namesForm.innerHTML = '';

  // Pass difficulty if computer mode
  if(mode === 'computer') {
      const diff = document.getElementById("difficulty").value;
      namesForm.innerHTML += `<input type="hidden" name="difficulty" value="${diff}">`;
      namesForm.innerHTML += `<input type="hidden" name="mode" value="computer">`;
      
      // Player 1 (User)
      namesForm.innerHTML += `
      <div class="mb-3 text-start">
        <label class="form-label">Your Name:</label>
        <input type="text" value="You" class="form-control" name="player1" required>
        <input type="hidden" name="color1" value="BLUE">
      </div>`;

      // Computer Players
      const otherColors = colors.filter(c => c !== "BLUE");
      for(let i=0; i<cnt; i++) {
          namesForm.innerHTML += `
          <input type="hidden" name="player${i+2}" value="AI ${i+1}">
          <input type="hidden" name="color${i+2}" value="${otherColors[i]}">
          `;
      }
      
      namesForm.innerHTML += `<button type="submit" name="sub" class="btn-custom">Start Game</button>`;
      
  } else {
      namesForm.innerHTML += `<input type="hidden" name="mode" value="local">`;
      for (let i = 0; i < cnt; i++) {
        let colorOptionsHTML = colors.map(color => `
          <div class="color-option d-inline-block mb-2 me-2">
            <input type="radio" id="color-${i}-${color}" name="color${i+1}" value="${color}" class="color-radio" data-player="${i}" required>
            <label for="color-${i}-${color}" style="background-color: ${colorHex[color]}; width: 40px; height: 40px; border-radius: 50%; display:block; cursor:pointer; border: 2px solid white;"></label>
          </div>
        `).join('');

        namesForm.innerHTML += `
          <div class="mb-3 text-start">
            <label for="player${i+1}" class="form-label">Player ${i + 1} Name:</label>
            <input type="text" value="Player ${i+1}" class="form-control" id="player${i+1}" name="player${i+1}" required>
          </div>
          <div class="mb-4 text-start">
            <label class="form-label">Choose Color : </label><br>
            <div id="color-options-${i}">${colorOptionsHTML}</div>
          </div>
          <hr style="background: white;">
        `;
      }
      namesForm.innerHTML += `<button type="submit" name="sub" class="btn-custom">Start Game</button>`;
  }

  // Add event listeners for color selection uniqueness (only for local)
  if(mode === 'local') {
      document.querySelectorAll(".color-radio").forEach(radio => {
        radio.addEventListener("change", updateColorRadios);
      });
  }
}

function updateColorRadios() {
  const selectedColors = {};

  document.querySelectorAll(".color-radio:checked").forEach(radio => {
    selectedColors[radio.value] = parseInt(radio.dataset.player);
  });

  document.querySelectorAll(".color-radio").forEach(radio => {
    const color = radio.value;
    const player = parseInt(radio.dataset.player);
    // If color is taken by another player, disable it
    if (selectedColors[color] !== undefined && selectedColors[color] !== player) {
      radio.disabled = true;
      radio.nextElementSibling.style.opacity = "0.3";
      radio.nextElementSibling.style.cursor = "not-allowed";
    } else {
      radio.disabled = false;
      radio.nextElementSibling.style.opacity = "1";
      radio.nextElementSibling.style.cursor = "pointer";
    }
  });
}
</script>
</body>
</html>
