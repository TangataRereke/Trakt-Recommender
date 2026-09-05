<?php

class StateStore
{
    private string $dataDir;
    private array $lists = ['30', '40', '60', 'sleepy', 'sitcom'];

    private array $defaultShows = [
        'sitcom' => [
            'id' => 4538,
            'title' => 'Hazel',
            'overview' => 'Based on the Saturday Evening Post cartoons, the series centered around Hazel Burke, a maid, who for the first four seasons worked for the Baxter family.',
            'status' => 'Ended',
            'language' => 'English',
            'runtime' => 30,
            'posterUrl' => 'https://static.tvmaze.com/uploads/images/original_untouched/20/52120.jpg',
            'genres' => ['Comedy', 'Family'],
            'firstAired' => '1961-09-28',
            'seasonCount' => 5,
            'totalEpisodes' => 154
        ],
        'sleepy' => [
            'id' => 6620,
            'title' => 'Tales of Tomorrow',
            'overview' => 'In this anthology series, tales of horror and science fiction are filmed live and presented to the viewing audience.',
            'status' => 'Ended',
            'language' => 'English',
            'runtime' => 30,
            'posterUrl' => 'https://static.tvmaze.com/uploads/images/original_untouched/25/62665.jpg',
            'genres' => ['Horror', 'Science-Fiction', 'Mystery'],
            'firstAired' => '1951-08-03',
            'seasonCount' => 2,
            'totalEpisodes' => 85
        ],
        '40' => [
            'id' => 42,
            'title' => 'Sleepy Hollow',
            'overview' => 'Sleepy Hollow is a thrilling mystery-adventure drama series spanning two and a half centuries, in which a resurrected Ichabod Crane faces off against resurrected threats.',
            'status' => 'Ended',
            'language' => 'English',
            'runtime' => 60,
            'posterUrl' => 'https://static.tvmaze.com/uploads/images/original_untouched/81/204166.jpg',
            'genres' => ['Drama', 'Mystery', 'Supernatural'],
            'firstAired' => '2013-09-16',
            'seasonCount' => 4,
            'totalEpisodes' => 62
        ],
        '60' => [
            'id' => 8557,
            'title' => 'Frontier',
            'overview' => 'Set against the stunning, raw backdrop of 1700s Canada, Frontier is revolving around warring factions vying for control of the fur trade.',
            'status' => 'Ended',
            'language' => 'English',
            'runtime' => 47,
            'posterUrl' => 'https://static.tvmaze.com/uploads/images/original_untouched/173/434300.jpg',
            'genres' => ['Drama', 'Action', 'Adventure'],
            'firstAired' => '2016-11-06',
            'seasonCount' => 3,
            'totalEpisodes' => 18
        ],
        '30' => [
            'id' => 2146,
            'title' => 'The Lone Ranger',
            'overview' => 'Who was that masked man? The Lone Ranger, of course - sole survivor of a group of ambushed Texas Rangers, who was nursed back to health by Tonto.',
            'status' => 'Ended',
            'language' => 'English',
            'runtime' => 30,
            'posterUrl' => 'https://static.tvmaze.com/uploads/images/original_untouched/12/30000.jpg',
            'genres' => ['Action', 'Adventure', 'Western'],
            'firstAired' => '1949-09-15',
            'seasonCount' => 5,
            'totalEpisodes' => 221
        ]
    ];

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? __DIR__ . '/../data/';
        $this->dataDir = rtrim($dir, '/\\') . '/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
        $this->ensureFilesExist();
    }

    private function ensureFilesExist(): void
    {
        foreach ($this->lists as $key) {
            $file = $this->dataDir . "list_{$key}.txt";
            if (!file_exists($file) || filesize($file) === 0) {
                if (isset($this->defaultShows[$key])) {
                    $jsonLine = json_encode($this->defaultShows[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    file_put_contents($file, $jsonLine);
                } else {
                    file_put_contents($file, "");
                }
            }
        }
        $skippedFile = $this->dataDir . "skipped.txt";
        if (!file_exists($skippedFile)) {
            file_put_contents($skippedFile, "");
        }
        $watchNextFile = $this->dataDir . "watch_next.txt";
        if (!file_exists($watchNextFile)) {
            file_put_contents($watchNextFile, "");
        }
    }

    public function getWatchNextFile(): string
    {
        return $this->dataDir . "watch_next.txt";
    }

    public function getWatchNextShows(): array
    {
        $file = $this->getWatchNextFile();
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $shows = [];
        if ($lines !== false) {
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) continue;
                $data = json_decode($trimmed, true);
                if (is_array($data) && isset($data['id'])) {
                    $shows[] = $data;
                }
            }
        }
        return $shows;
    }

    public function isWatchNext(int $showId): bool
    {
        $shows = $this->getWatchNextShows();
        foreach ($shows as $s) {
            if ((int)($s['id'] ?? 0) === $showId) {
                return true;
            }
        }
        return false;
    }

    public function toggleWatchNext(array $show): bool
    {
        $showId = (int)($show['id'] ?? 0);
        if ($showId <= 0) {
            return false;
        }

        $shows = $this->getWatchNextShows();
        $exists = false;
        $newShows = [];

        foreach ($shows as $s) {
            if ((int)($s['id'] ?? 0) === $showId) {
                $exists = true;
                continue; // Remove it
            }
            $newShows[] = json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($exists) {
            // It was present, now removed
            $content = !empty($newShows) ? implode(PHP_EOL, $newShows) . PHP_EOL : "";
            file_put_contents($this->getWatchNextFile(), $content, LOCK_EX);
            return false; // Now false (unselected)
        } else {
            // Not present, add it
            $jsonLine = json_encode($show, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($this->getWatchNextFile(), $jsonLine, FILE_APPEND | LOCK_EX);
            return true; // Now true (selected)
        }
    }

    public function getListFile(string $key): string
    {
        return $this->dataDir . "list_{$key}.txt";
    }

    public function getSkippedFile(): string
    {
        return $this->dataDir . "skipped.txt";
    }

    public function getShowsInList(string $listKey): array
    {
        $file = $this->getListFile($listKey);
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $shows = [];
        if ($lines !== false) {
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) continue;
                $data = json_decode($trimmed, true);
                if (is_array($data) && isset($data['id'])) {
                    $shows[] = $data;
                }
            }
        }
        return $shows;
    }

    public function getAllListShowIds(): array
    {
        $ids = [];
        foreach ($this->lists as $key) {
            $shows = $this->getShowsInList($key);
            foreach ($shows as $s) {
                if (isset($s['id'])) {
                    $ids[(int)$s['id']] = true;
                }
            }
        }
        return array_keys($ids);
    }

    public function isInLists(int $showId): bool
    {
        $allIds = $this->getAllListShowIds();
        return in_array($showId, $allIds, true);
    }

    public function addShowToList(string $listKey, array $show): bool
    {
        if (!in_array($listKey, $this->lists, true)) {
            return false;
        }

        $showId = (int)($show['id'] ?? 0);
        if ($showId <= 0) {
            return false;
        }

        // Check if already in this list
        $existing = $this->getShowsInList($listKey);
        foreach ($existing as $s) {
            if ((int)$s['id'] === $showId) {
                return true; // already added
            }
        }

        $jsonLine = json_encode($show, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return file_put_contents($this->getListFile($listKey), $jsonLine, FILE_APPEND | LOCK_EX) !== false;
    }

    public function removeShowFromList(string $listKey, int $showId): bool
    {
        if (!in_array($listKey, $this->lists, true)) {
            return false;
        }

        $shows = $this->getShowsInList($listKey);
        $newShows = [];
        $modified = false;

        foreach ($shows as $s) {
            if ((int)$s['id'] === $showId) {
                $modified = true;
                continue;
            }
            $newShows[] = json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($modified) {
            $content = !empty($newShows) ? implode(PHP_EOL, $newShows) . PHP_EOL : "";
            file_put_contents($this->getListFile($listKey), $content, LOCK_EX);
        }

        return true;
    }

    public function getSkippedShowIds(): array
    {
        $file = $this->getSkippedFile();
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ids = [];
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (is_numeric($line)) {
                    $ids[(int)$line] = true;
                } else {
                    $data = json_decode($line, true);
                    if (is_array($data) && isset($data['id'])) {
                        $ids[(int)$data['id']] = true;
                    }
                }
            }
        }
        return array_keys($ids);
    }

    public function isSkipped(int $showId): bool
    {
        $skippedIds = $this->getSkippedShowIds();
        return in_array($showId, $skippedIds, true);
    }

    public function skipShow(int $showId): bool
    {
        if ($showId <= 0) {
            return false;
        }

        if ($this->isSkipped($showId)) {
            return true;
        }

        $line = (string)$showId . PHP_EOL;
        return file_put_contents($this->getSkippedFile(), $line, FILE_APPEND | LOCK_EX) !== false;
    }

    public function getCycleIndex(): int
    {
        $file = $this->dataDir . "cycle_index.txt";
        if (!file_exists($file)) {
            return 0;
        }
        $val = trim((string)file_get_contents($file));
        return is_numeric($val) ? (int)$val : 0;
    }

    public function setCycleIndex(int $idx): void
    {
        $file = $this->dataDir . "cycle_index.txt";
        file_put_contents($file, (string)$idx, LOCK_EX);
    }
}
