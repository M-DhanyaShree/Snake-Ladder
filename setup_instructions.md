# Snake & Ladder Ultimate - Setup Instructions

## Finalized Project Overview
This project is a premium Snake & Ladder game featuring:
- **Local Multiplayer** (up to 4 players)
- **Play vs AI** (with smart turn management)
- **Online Multiplayer** (real-time synchronized gameplay)
- **User Authentication** (Login/Signup with lifetime stats)
- **Global Leaderboard** (Top winners and point earners)
- **Dynamic Sound Effects** (Roll, Move, Snake, Ladder, Win)
- **Anti-Cheat Validation** (Server-side move calculation)

---

## Prerequisites
1.  **XAMPP** (Apache & MySQL).
2.  **Web Browser** (Chrome/Edge/Firefox).

---

## Database & Server Setup
1.  **Start Services**: Open XAMPP Control Panel and start **Apache** and **MySQL**.
2.  **Initialize Database**:
    - Open your browser and go to: `http://localhost/fwt project sem 2/setup_db.php`.
    - This will automatically create the `snake_ladder_db` and all required tables: `users`, `games`, `players`, `winners`, and `messages`.
3.  **Authentication**: Users must sign up to have their wins and points recorded on the global leaderboard.

---

## Gameplay Guide

### 1. Online Multiplayer
- **Create Group**: One player creates a room and gets a 6-digit room code.
- **Join Group**: Other players enter the code to join the lobby.
- **Start Game**: The host can start the game once at least 2 players are present.
- **Turn Timer**: Players have **10 seconds** to roll the dice. If they miss their turn, it automatically passes to the next player.
- **Disconnection**: If a player leaves, they have a **15-second grace period** to return before being marked as inactive. If only one player remains, they are automatically declared the winner.

### 2. Local & AI Modes
- **Local**: Play with friends on the same computer.
- **Computer**: Challenge our AI. We've ensured unique colors for every participant!
- **Scoreboard**: View live scores in the sidebar as you play.

---

## Sound Effects Features
The game now includes immersive audio feedback:
- 🎲 **Dice Roll**: Playful rattling sound when you click the dice.
- ♟️ **Token Move**: Satisfying "pop" sound for every successful move.
- 🐍 **Snake Bite**: A "slide down" whistle effect when you hit a snake.
- 🚀 **Ladder Climb**: A chime sound for successful climbs.
- 🏆 **Victory**: A celebratory success sound once someone reaches square 100.

*Note: Most modern browsers require you to interact with the page (click anywhere) before audio can be played.*

---

## Technical Highlights (Resume Ready)
- **Move Validation**: All online movements are calculated and validated on the server (`api.php`) to prevent client-side manipulation.
- **State Synchronization**: Polling system with state-diffing for smooth UI updates and synchronized audio.
- **Clean Architecture**: Decoupled frontend (`game-logic.js`, `online-game.js`) and backend logic.
- **Premium UI**: Modern glassmorphism design with responsive elements and smooth CSS transitions.

Enjoy the game!
