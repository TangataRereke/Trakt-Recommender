<?php

class StateStore
{
    private string $dataDir;
    private array $lists = ['30', '40', '60', 'sleepy', 'sitcom'];

    public function __construct(?string $dataDir = null)
    {
        $this->dataDir = $dataDir ?? __DIR__ . '/../data/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
        $this->ensureFilesExist();
    }

    private function ensureFilesExist(): void
    {
        foreach ($this->lists as $key) {
            $file = $this->dataDir . "list_{$key}.txt";
            if (!file_exists($file)) {
                file_put_contents($file, "");
            }
        }
        $skippedFile = $this->dataDir . "skipped.txt";
        if (!file_exists($skippedFile)) {
            file_put_contents($skippedFile, "");
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
}
