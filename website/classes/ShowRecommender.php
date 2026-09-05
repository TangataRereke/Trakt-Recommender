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

    private array $cycle = ['30', '40', '60', 'sleepy', 'sitcom'];
    private int $cycleIndex = 0;

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
        if (!empty($this->candidates)) {
            return;
        }

        $this->inLists = $this->state->getAllListShowIds();
        $this->skipped = $this->state->getSkippedShowIds();

        $fetched = $this->client->fetchCandidateShows();
        shuffle($fetched);

        $this->candidates = $fetched;
        $this->index = 0;
    }

    public function isEligible(array $show): bool
    {
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

    private function fallbackRelated(): array
    {
        $cycleCount = count($this->cycle);

        for ($attempt = 0; $attempt < $cycleCount; $attempt++) {
            $key = $this->cycle[$this->cycleIndex];
            $this->cycleIndex = ($this->cycleIndex + 1) % $cycleCount;

            $listShows = $this->state->getShowsInList($key);
            if (empty($listShows)) {
                continue;
            }

            $randomIndex = array_rand($listShows);
            $seedShow = $listShows[$randomIndex];
            $seedId = (int)($seedShow['id'] ?? 0);

            if ($seedId <= 0) {
                continue;
            }

            $relatedShows = $this->client->fetchRelatedShows($seedId, $seedShow);

            foreach ($relatedShows as $s) {
                if ($this->isEligible($s)) {
                    $this->client->populateShowDetails($s);
                    return $s;
                }
            }
        }

        return [
            'id' => 0,
            'title' => 'No more recommendations',
            'overview' => 'All lists exhausted.',
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

    public function nextShow(): array
    {
        $this->ensureLoaded();

        $total = count($this->candidates);
        while ($this->index < $total) {
            $s = $this->candidates[$this->index];
            $this->index++;

            if ($this->isEligible($s)) {
                $this->client->populateShowDetails($s);
                return $s;
            }
        }

        return $this->fallbackRelated();
    }
}
