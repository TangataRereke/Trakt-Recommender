<?php
header('Content-Type: application/json');

require_once __DIR__ . '/classes/StateStore.php';
require_once __DIR__ . '/classes/TVMazeClient.php';
require_once __DIR__ . '/classes/ShowRecommender.php';

session_start();

$state = new StateStore();
$client = new TVMazeClient();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'recommend':
        $recommender = new ShowRecommender($client, $state);
        $show = $recommender->nextShow();
        echo json_encode(['success' => true, 'show' => $show], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'add_to_list':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];
        $listKey = $input['list_key'] ?? $_POST['list_key'] ?? '';
        $show = $input['show'] ?? null;

        if (!$listKey || !$show || !isset($show['id'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $ok = $state->addShowToList($listKey, $show);
        echo json_encode(['success' => $ok, 'list_key' => $listKey]);
        break;

    case 'skip':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];
        $showId = (int)($input['show_id'] ?? $_POST['show_id'] ?? 0);

        if ($showId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid show ID']);
            exit;
        }

        $ok = $state->skipShow($showId);
        echo json_encode(['success' => $ok, 'show_id' => $showId]);
        break;

    case 'get_list':
        $listKey = $_GET['list_key'] ?? '';
        if (!$listKey) {
            echo json_encode(['success' => false, 'error' => 'Missing list_key']);
            exit;
        }
        $shows = $state->getShowsInList($listKey);
        $watchNextShows = $state->getWatchNextShows();
        $watchNextIds = array_map(fn($s) => (int)($s['id'] ?? 0), $watchNextShows);
        echo json_encode(['success' => true, 'list_key' => $listKey, 'shows' => $shows, 'watch_next_ids' => $watchNextIds], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'get_watch_next':
        $shows = $state->getWatchNextShows();
        $watchNextIds = array_map(fn($s) => (int)($s['id'] ?? 0), $shows);
        echo json_encode(['success' => true, 'shows' => $shows, 'watch_next_ids' => $watchNextIds], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'toggle_watch_next':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];
        $show = $input['show'] ?? null;

        if (!$show || !isset($show['id'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $isWatchNext = $state->toggleWatchNext($show);
        echo json_encode(['success' => true, 'is_watch_next' => $isWatchNext, 'show_id' => (int)$show['id']]);
        break;

    case 'remove_from_list':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];
        $listKey = $input['list_key'] ?? $_POST['list_key'] ?? '';
        $showId = (int)($input['show_id'] ?? $_POST['show_id'] ?? 0);

        if (!$listKey || $showId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $ok = $state->removeShowFromList($listKey, $showId);
        echo json_encode(['success' => $ok, 'list_key' => $listKey, 'show_id' => $showId]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
