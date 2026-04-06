<?php

return [
  /* Scratch game configuration */

  /* ============================
    Board size
    - Defines the size of the scratch game board (e.g. 5 for a 5x5 board).
    - The service will attempt to use the full board size but may reduce it if prize constraints require it to ensure a valid board can be generated.
  ============================ */
  'boardsize' => env('SCRATCH_GAME_BOARD_SIZE', 5),

  /* ============================
    Matches to win
    Defines the number of matching symbols required for a player to win.
  ============================ */
  'matchestowin' => env('SCRATCH_GAME_MATCHES_TO_WIN', 3),

  /* ============================
    Maximum tries
    Defines the maximum number of attempts a player can make in a single game session.
  ============================ */
  'maxtries' => env('SCRATCH_GAME_MAX_TRIES', 9),
];