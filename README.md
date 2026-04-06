# Xtremepush Backend Test

Straight-to-the-point documentation for running and understanding the project.

## 1) Project Setup

1. Clone the repository.
2. Install PHP dependencies.

```bash
composer install
```

3. Install frontend dependencies and build assets.

```bash
npm install
npm run build
```

4. Create environment file and configure database credentials.

```bash
copy .env.example .env
```

5. Generate application key.

```bash
php artisan key:generate
```

6. Run database migrations and seeders.

```bash
php artisan migrate
php artisan db:seed
```

7. Create storage symlink for uploaded images.

```bash
php artisan storage:link
```

8. Start the app.

```bash
php artisan serve
```

9. Open Backstage.

```text
http://127.0.0.1:8000/backstage
```

Default login:
- Email: test@xtremepush.com
- Password: test123

## 2) How To Run The Game

Use a campaign slug URL with account and segment:

```text
http://127.0.0.1:8000/test-campaign-1?a=account&segment=low
```

Segment must be one of: low, med, high.

## 3) Game Flow Logic

Game flow is handled mainly by FrontendController, GameSessionService, and ApiController.

1. Campaign page load
- Validates campaign state (upcoming/ended/active).
- Builds a game context from campaign + account + segment.
- Reuses unfinished game if one exists; otherwise creates a new game.

2. Game creation
- Board planning decides winning or losing board.
- For winning boards, a winnable prize is selected and reserved.
- Game tiles are persisted with tile_image values from Prize image.

3. Tile flip API (/api/flip)
- Locks game row transactionally.
- Resolves requested display tile, reveals it, returns tile image.
- Checks revealed-match count against matches_to_win.
- Finalizes game as won/lost and returns final message when needed.

4. Persistence
- Revealed tiles are stored in DB, so state survives refreshes.

## 4) Prize Selection Logic

Prize selection is segment-aware and availability-aware.

1. Eligible prizes
- Filtered by campaign, segment, and start/end time windows.

2. Winnable prizes
- Filtered by segment and daily_limit capacity.
- Uses daily_prize_counters to ensure reserved_count < daily_limit.

3. Weighted winner pick
- Winner selection uses weighted SQL strategy:

```php
->orderByRaw('-LOG(RAND()) / weight')
```

4. Board planning rules
- max appearance per prize = matches_to_win - 1 for filler distribution.
- Board must support max_tries and prize constraints.
- If a losing board is impossible, planner attempts a winning board.

## 5) Campaign Modification Checks Added

Campaign create/update requests include cross-field validation for board feasibility.

1. Hard board-size check
- max_tries cannot exceed configured board capacity.
- Capacity is board_size x board_size from config/scratchgame.php.

2. Segment bottleneck check (Update)
- Counts prizes per segment (low/med/high).
- Uses the segment with the lowest count as worst-case validation.
- Blocks update when minimum required prizes are not met for that segment.
- Error message tells exactly which segment is blocking the change and why.

3. Minimum required prizes formula

For a configuration to be feasible:

minRequiredPrizes = ceil((ceil(sqrt(max_tries))^2) / (matches_to_win - 1))

## 6) Major Changes In This Project

1. Service-oriented game architecture
- Game session, board planning, prize selection, and prize availability are separated into dedicated services.

2. Robust game finalization
- Winner/loser finalization updates game status and prize counters consistently in transactions.

3. Segment-aware campaign validation
- Campaign updates now validate against the least-populated segment to prevent impossible game settings.

4. Prize image upload implementation
- Backstage prize forms support file upload.
- Uploaded files are stored on the public disk and saved as image URL/path.
- Requires php artisan storage:link.

5. Frontend config improvements
- Frontend receives revealed tiles and game messages from server-built config.

## 7) Quick Troubleshooting

1. Images not visible
- Run php artisan storage:link.
- Confirm files exist under storage/app/public/prizes.

2. Game not loading
- Check campaign date range and URL query params (a and segment).

3. Asset issues
- Rebuild frontend assets with npm run build.
