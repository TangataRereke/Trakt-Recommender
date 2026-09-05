<?php

class TVMazeClient
{
    private const BASE_URL = 'https://api.tvmaze.com';

    private function get(string $endpoint, array $queryParams = []): ?array
    {
        $url = self::BASE_URL . $endpoint;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PHP-TVMazeClient/1.0\r\nAccept: application/json\r\n",
                'timeout' => 8
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            return null;
        }

        $data = json_decode($result, true);
        return is_array($data) ? $data : null;
    }

    public function parseShow(array $obj): array
    {
        // Handle search wrapper if present
        $showObj = isset($obj['show']) && is_array($obj['show']) ? $obj['show'] : $obj;

        $id = (int)($showObj['id'] ?? 0);
        $title = (string)($showObj['name'] ?? '');
        $status = (string)($showObj['status'] ?? '');
        $language = (string)($showObj['language'] ?? '');

        // Summary / Overview clean HTML
        $summaryHtml = (string)($showObj['summary'] ?? '');
        $overview = trim(strip_tags($summaryHtml));

        // Poster
        $posterUrl = '';
        if (isset($showObj['image']) && is_array($showObj['image'])) {
            $posterUrl = $showObj['image']['original'] ?? ($showObj['image']['medium'] ?? '');
        }

        // Genres
        $genres = [];
        if (isset($showObj['genres']) && is_array($showObj['genres'])) {
            foreach ($showObj['genres'] as $g) {
                $genres[] = (string)$g;
            }
        }

        // Premiered / First Aired
        $firstAired = (string)($showObj['premiered'] ?? '');

        // Default runtime
        $runtime = (int)($showObj['averageRuntime'] ?? ($showObj['runtime'] ?? 0));

        return [
            'id' => $id,
            'title' => $title,
            'overview' => $overview,
            'status' => $status,
            'language' => $language,
            'runtime' => $runtime,
            'posterUrl' => $posterUrl,
            'genres' => $genres,
            'firstAired' => $firstAired,
            'seasonCount' => 0,
            'totalEpisodes' => 0
        ];
    }

    public function fetchCandidateShows(): array
    {
        $shows = [];
        // Fetch 2 pages of TVMaze index (e.g., page 0 and page 1) or randomly pick page
        $pages = [0, 1, rand(2, 5)];
        $pages = array_unique($pages);

        foreach ($pages as $p) {
            $data = $this->get("/shows", ['page' => $p]);
            if ($data && is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $shows[] = $this->parseShow($item);
                    }
                }
            }
        }

        return $shows;
    }

    public function fetchRelatedShows(int $showId, array $seedShow = []): array
    {
        $results = [];

        // 1. Search by seed show title directly
        if (!empty($seedShow['title'])) {
            $data = $this->get("/search/shows", ['q' => $seedShow['title']]);
            if ($data && is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $parsed = $this->parseShow($item);
                        if ($parsed['id'] > 0 && $parsed['id'] !== $showId) {
                            $results[$parsed['id']] = $parsed;
                        }
                    }
                }
            }
        }

        // 2. Search by key title words to broaden related show search
        if (!empty($seedShow['title'])) {
            $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $seedShow['title']);
            $words = array_filter(
                explode(' ', (string)$cleanTitle),
                fn($w) => strlen($w) > 3 && !in_array(strtolower($w), ['the', 'that', 'this', 'from', 'with', 'show'], true)
            );

            foreach ($words as $word) {
                $data = $this->get("/search/shows", ['q' => $word]);
                if ($data && is_array($data)) {
                    foreach ($data as $item) {
                        if (is_array($item)) {
                            $parsed = $this->parseShow($item);
                            if ($parsed['id'] > 0 && $parsed['id'] !== $showId) {
                                $results[$parsed['id']] = $parsed;
                            }
                        }
                    }
                }
            }
        }

        return array_values($results);
    }

    public function populateShowDetails(array &$show): void
    {
        $showId = (int)($show['id'] ?? 0);
        if ($showId <= 0) {
            return;
        }

        $episodes = $this->get("/shows/{$showId}/episodes");
        if (!$episodes || !is_array($episodes)) {
            return;
        }

        $seasons = [];
        $totalEpisodes = 0;

        foreach ($episodes as $ep) {
            if (!is_array($ep)) continue;
            $seasonNum = (int)($ep['season'] ?? 0);
            if ($seasonNum <= 0) continue; // Skip Specials (season 0)

            $totalEpisodes++;
            if (!isset($seasons[$seasonNum])) {
                $seasons[$seasonNum] = [];
            }
            $seasons[$seasonNum][] = $ep;
        }

        if (empty($seasons)) {
            return;
        }

        ksort($seasons);
        $seasonNumbers = array_keys($seasons);
        $firstSeasonNum = reset($seasonNumbers);
        $lastSeasonNum = end($seasonNumbers);

        $sampledRuntimes = [];

        // First 2 episodes of first season
        $firstSeasonEps = $seasons[$firstSeasonNum];
        $countFirst = min(2, count($firstSeasonEps));
        for ($i = 0; $i < $countFirst; $i++) {
            $rt = (int)($firstSeasonEps[$i]['runtime'] ?? 0);
            if ($rt > 0) {
                $sampledRuntimes[] = $rt;
            }
        }

        // Last 2 episodes of final season (if distinct from first season)
        if ($lastSeasonNum !== $firstSeasonNum) {
            $lastSeasonEps = $seasons[$lastSeasonNum];
            $totalLast = count($lastSeasonEps);
            $start = max(0, $totalLast - 2);
            for ($i = $start; $i < $totalLast; $i++) {
                $rt = (int)($lastSeasonEps[$i]['runtime'] ?? 0);
                if ($rt > 0) {
                    $sampledRuntimes[] = $rt;
                }
            }
        }

        // Calculate median runtime if samples exist
        if (!empty($sampledRuntimes)) {
            sort($sampledRuntimes);
            $n = count($sampledRuntimes);
            if ($n % 2 === 0) {
                $medianRuntime = (int)round(($sampledRuntimes[$n / 2 - 1] + $sampledRuntimes[$n / 2]) / 2);
            } else {
                $medianRuntime = (int)$sampledRuntimes[(int)floor($n / 2)];
            }
            $show['runtime'] = $medianRuntime;
        }

        $show['seasonCount'] = count($seasons);
        $show['totalEpisodes'] = $totalEpisodes;
    }
}
