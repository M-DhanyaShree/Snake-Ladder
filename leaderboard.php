<?php
session_start();
include 'db.php';

// 1. Recent Winners (Last 10 games)
$recent_stmt = $conn->query("SELECT name, score, won_at FROM winners ORDER BY won_at DESC LIMIT 10");
$recent_winners = [];
if ($recent_stmt) {
    while ($row = $recent_stmt->fetch_assoc()) {
        $recent_winners[] = $row;
    }
}

// 2. Global Top Players (By Wins and Points)
$top_stmt = $conn->query("SELECT username, wins, points FROM users WHERE wins > 0 OR points > 0 ORDER BY wins DESC, points DESC LIMIT 10");
$top_players = [];
if ($top_stmt) {
    while ($row = $top_stmt->fetch_assoc()) {
        $top_players[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Snake & Ladder - Leaderboard</title>
  <link href="style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
  <style>
      .table { color: white; background: rgba(0,0,0,0.5); border-radius: 10px; overflow: hidden; }
      .table th { border-bottom-color: rgba(255,255,255,0.2); }
      .table td { border-bottom-color: rgba(255,255,255,0.1); }
      .leaderboard-container { gap: 30px; display: flex; flex-wrap: wrap; justify-content: center; }
  </style>
</head>
<body>

<div class="bg-wrapper">
  
  <div class="main-content" style="max-width: 1000px; width: 95%;">
    <h2 class="mb-4 game-title" style="font-size: 3.5rem;">Leaderboard</h2>
    
    <div class="leaderboard-container">
        
        <!-- Global Rankings -->
        <div style="flex: 1; min-width: 350px;">
            <h3 class="mb-3" style="color: gold;"><i class='bx bxs-medal'></i> Global Top Players</h3>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Wins</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($top_players)): ?>
                    <tr><td colspan="4">No rankings available yet.</td></tr>
                    <?php else: foreach($top_players as $i => $u): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td style="color: gold; font-weight: bold;"><?= $u['wins'] ?></td>
                        <td style="color: #00d2ff; font-weight: bold;"><?= $u['points'] ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="mt-4">
        <a href="index.html" class="btn-custom">Back to Home</a>
    </div>
  </div>

</div>

</body>
</html>
