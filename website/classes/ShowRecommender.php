<?php

require_once __DIR__ . '/TVMazeClient.php';
require_once __DIR__ . '/StateStore.php';

class ShowRecommender
{
    private TVMazeClient $client;
    private StateStore $state;

    private array $candidates = [];
    private int $index = 0;

    private array $inLists = [];
    private array $skipped = [];

    // All TVMaze genres EXCEPT Romance, Drama, Anime for round-robin rotation
    private array $allRoundRobinGenres = [
        "Action", "Adult", "Adventure", "Children", "Comedy", "Crime",
        "DIY", "Espionage", "Family", "Fantasy", "Food", "History",
        "Horror", "Legal", "Medical", "Music", "Mystery", "Nature",
        "Science-Fiction", "Sports", "Supernatural", "Thriller", "Travel",
        "War", "Western"
    ];

    public function __construct(TVMazeClient $client, StateStore $state)
    {
        $this->client = $client;
        $this->state = $state;
    }

    public function markAsInList(int $showId): void
    {
        if (!in_array($showId, $this->inLists, true)) {
            $this->inLists[] = $showId;
        }
    }

    public function markAsSkipped(int $showId): void
    {
        if (!in_array($showId, $this->skipped, true)) {
            $this->skipped[] = $showId;
        }
    }

    private function ensureLoaded(): void
    {
        $this->inLists = $this->state->getAllListShowIds();
        $this->skipped = $this->state->getSkippedShowIds();

        if (empty($this->candidates)) {
            $fetched = $this->client->fetchCandidateShows();
            shuffle($fetched);
            $this->candidates = $fetched;
            $this->index = 0;
        }
    }

    public function isEligible(array $show): bool
    {
        $this->ensureLoaded();

        $id = (int)($show['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        $status = strtolower(trim((string)($show['status'] ?? '')));
        if ($status !== 'ended') {
            return false;
        }

        $lang = strtolower(trim((string)($show['language'] ?? '')));
        if ($lang !== 'english' && $lang !== 'en') {
            return false;
        }

        if (in_array($id, $this->inLists, true)) {
            return false;
        }

        if (in_array($id, $this->skipped, true)) {
            return false;
        }

        return true;
    }

    /**
     * Tries to find an eligible show for the current genre in round-robin rotation.
     * Removes active genres that have no shows left from rotation.
     */
    private function findShowByGenreRoundRobin(): ?array
    {
        $activeGenres = $this->state->getActiveGenres();
        if (empty($activeGenres)) {
            // Re-initialize from defaults if empty
            $activeGenres = $this->allRoundRobinGenres;
            $this->state->setActiveGenres($activeGenres);
        }

        $genreIndex = $this->state->getGenreIndex();
        $maxAttempts = count($activeGenres);
        $attempts = 0;

        while (!empty($activeGenres) && $attempts < $maxAttempts) {
            $genreIndex = $genreIndex % count($activeGenres);
            $currentGenre = $activeGenres[$genreIndex];

            // Search candidate pool first for a show matching currentGenre
            $foundCandidate = null;
            foreach ($this->candidates as $s) {
                if ($this->isEligible($s) && in_array($currentGenre, $s['genres'] ?? [], true)) {
                    $foundCandidate = $s;
                    break;
                }
            }

            // If not in candidate pool, search TVMaze specifically for currentGenre
            if ($foundCandidate === null) {
                $genreShows = $this->client->fetchShowsByGenre($currentGenre);
                shuffle($genreShows);
                foreach ($genreShows as $s) {
                    if ($this->isEligible($s) && in_array($currentGenre, $s['genres'] ?? [], true)) {
                        $foundCandidate = $s;
                        break;
                    }
                }
            }

            if ($foundCandidate !== null) {
                // Advance genre index for next request
                $nextGenreIndex = ($genreIndex + 1) % count($activeGenres);
                $this->state->setGenreIndex($nextGenreIndex);

                $this->client->populateShowDetails($foundCandidate);
                return $foundCandidate;
            } else {
                // No shows left for this genre! Remove genre from active genres
                array_splice($activeGenres, $genreIndex, 1);
                $this->state->setActiveGenres($activeGenres);

                if (empty($activeGenres)) {
                    $this->state->setGenreIndex(0);
                    break;
                }
                // Do not increment $genreIndex since array shifted, but increment attempt count
                $attempts++;
            }
        }

        return null;
    }

    public function nextShow(): array
    {
        $this->ensureLoaded();

        // 1. Try round-robin by genre
        $genreShow = $this->findShowByGenreRoundRobin();
        if ($genreShow !== null) {
            return $genreShow;
        }

        // 2. Fall back to general candidates pool if all active genres exhausted
        $total = count($this->candidates);
        while ($this->index < $total) {
            $s = $this->candidates[$this->index];
            $this->index++;

            if ($this->isEligible($s)) {
                $this->client->populateShowDetails($s);
                return $s;
            }
        }

        // 3. Fallback if completely exhausted
        return [
            'id' => 0,
            'title' => 'No more recommendations',
            'overview' => 'All genres and candidates exhausted.',
            'status' => 'Ended',
            'language' => 'English',
            'runtime' => 0,
            'posterUrl' => '',
            'genres' => [],
            'firstAired' => '',
            'seasonCount' => 0,
            'totalEpisodes' => 0
        ];
    }
}
