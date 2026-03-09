# Online Snake and Ladder

This is a web-based version of the classic Snake and Ladder board game. It allows players to play together online or against a computer. The project is focused on providing a smooth multiplayer experience with real-time updates and a competitive leaderboard system.

**Live Demo:** [http://snakeandladder.infinityfreeapp.com/](http://snakeandladder.infinityfreeapp.com/)


### Core Features

* Online Multiplayer: Create or join custom rooms using unique game codes to play with friends remotely.
* Local Multiplayer: Play with friends on the same device by taking turns on a single board.
* Player vs Computer: Practice your skills against an intelligent bot in single-player mode.
* Real-time Updates: The game state synchronizes instantly across all connected devices in online mode.
* Turn Timer: An automated timer ensures the game keeps moving by skipping turns if a player is inactive.
* Global Leaderboard: Track your progress and compete for the top spot based on wins and points.
* Chat System: Communicate with other players during online matches using the built-in chat.
* Reconnection Support: Automatically handles brief disconnections to maintain game stability.


### Technologies Used

* Frontend: HTML5, CSS3, and JavaScript.
* Backend: PHP for handling game logic and API requests.
* Database: MySQL to store user data, game states, and leaderboard rankings.
* Environment: Recommended to run on a local server like XAMPP or any PHP-supported hosting.

### Installation and Setup

* Move the project files to your local server directory, such as the htdocs folder in XAMPP.
* Open the db.php file and update the database credentials to match your local MySQL settings.
* Import the database structure by running the setup_db.php file in your browser. This will automatically create the necessary tables.
* Access the game by navigating to the index.html file through your localhost URL.

### How to Play

* Register a new account or log in with your existing credentials.
* Select a game mode from the lobby.
* To play with friends online, one player creates a game and shares the generated code with others.
* Players join using the code and wait for the host to start the match.
* Roll the dice on your turn and move toward the 100th square while avoiding snakes and using ladders to climb.
* The first player to reach exactly 100 wins the game.
