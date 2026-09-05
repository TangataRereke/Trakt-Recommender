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

    private function findRelatedShow(): ?array
    {
        $cycleCount = count($this->cycle);
        $this->cycleIndex = $this->state->getCycleIndex() % $cycleCount;

        for ($attempt = 0; $attempt < $cycleCount; $attempt++) {
            $key = $this->cycle[$this->cycleIndex];
            $nextCycleIndex = ($this->cycleIndex + 1) % $cycleCount;

            $listShows = $this->state->getShowsInList($key);
            if (empty($listShows)) {
                $this->cycleIndex = $nextCycleIndex;
                continue;
            }

            // Shuffle list shows to vary seed show pick
            shuffle($listShows);
            foreach ($listShows as $seedShow) {
                $seedId = (int)($seedShow['id'] ?? 0);
                if ($seedId <= 0) continue;

                $relatedShows = $this->client->fetchRelatedShows($seedId, $seedShow);
                shuffle($relatedShows);

                foreach ($relatedShows as $s) {
                    if ($this->isEligible($s)) {
                        $this->client->populateShowDetails($s);
                        $this->cycleIndex = $nextCycleIndex;
                        $this->state->setCycleIndex($this->cycleIndex);
                        return $s;
                    }
                }
            }

            $this->cycleIndex = $nextCycleIndex;
        }

        $this->state->setCycleIndex($this->cycleIndex);
        return null;
    }

    public function nextShow(): array
    {
        $this->ensureLoaded();

        // 1. Try finding a show related to items in user's lists first
        $related = $this->findRelatedShow();
        if ($related !== null) {
            return $related;
        }

        // 2. Fall back to candidates pool
        $total = count($this->candidates);
        while ($this->index < $total) {
            $s = $this->candidates[$this->index];
            $this->index++;

            if ($this->isEligible($s)) {
                $this->client->populateShowDetails($s);
                return $s;
            }
        }

        // 3. Fallback if exhausted
        return [
            'id' => 0,
            'title' => 'No more recommendations',
            'overview' => 'All lists and candidates exhausted.',
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
