<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Winner!</title>
  <link href="style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
  <style>
      .winner-card {
          background: rgba(255, 215, 0, 0.2);
          border: 2px solid gold;
          padding: 20px;
          border-radius: 15px;
          margin-bottom: 20px;
          animation: pulse 2s infinite;
      }
      @keyframes pulse {
          0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.7); }
          70% { box-shadow: 0 0 0 20px rgba(255, 215, 0, 0); }
          100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); }
      }
  </style>
</head>
<body>

<div class="bg-wrapper">
  <div class="main-content" style="max-width: 600px;">
    <h1 class="game-title">CONGRATS!</h1>
    
    <div class="winner-card">
        <i class='bx bxs-trophy' style="font-size: 4rem; color: gold;"></i>
        <h2 id="winner-name" style="font-family: 'Luckiest Guy'; font-size: 3rem; margin: 10px 0;">Winner Name</h2>
    </div>

    <div class="form-box" style="background: rgba(0,0,0,0.5);">
        <h3 class="mb-3">Leaderboard</h3>
        <div id="leaderboard-list" class="text-start">
            <!-- Populated by JS -->
        </div>
    </div>

    <div class="mt-4">
        <a href="index.html" class="btn-custom" onclick="clearSession()">Main Menu</a>
        <a href="leaderboard.php" class="btn-custom" style="background: linear-gradient(45deg, #f1c40f, #e67e22);">Global Leaderboard</a>
    </div>

    <script>
        function clearSession() {
            localStorage.removeItem('gameId');
            localStorage.removeItem('playerId');
            localStorage.removeItem('isHost');
        }
    </script>
  </div>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const winner = urlParams.get('winner');
    const scores = JSON.parse(decodeURIComponent(urlParams.get('scores')));

    document.getElementById('winner-name').innerText = winner;

    const list = document.getElementById('leaderboard-list');
    scores.sort((a, b) => b.score - a.score); // Sort by score descending

    scores.forEach((p, index) => {
        list.innerHTML += `
            <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(255,255,255,0.1); border-radius: 8px;">
                <div>
                    <span style="font-weight: bold; margin-right: 10px;">#${index + 1}</span>
                    ${p.name}
                </div>
                <div style="font-weight: bold; color: gold;">${p.score} pts</div>
            </div>
        `;
    });
</script>

</body>
</html>
