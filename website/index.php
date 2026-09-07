<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Show Recommender & Lists</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <header class="navbar">
        <div class="logo">
            <span class="logo-icon">📺</span> TV Show Recommender
        </div>
        <nav class="nav-tabs">
            <button class="nav-btn active" data-tab="recommendations">Recommendations</button>
            <button class="nav-btn" data-tab="watch-next">Watch Next</button>
            <button class="nav-btn" data-tab="view-shows">View Shows</button>
            <button class="nav-btn" data-tab="find-next-show">Find Next Show</button>
        </nav>
    </header>

    <main class="container">
        <!-- TAB 1: RECOMMENDATIONS -->
        <section id="tab-recommendations" class="tab-content active">
            <div id="recommender-card" class="card recommender-box">
                <div id="loading-spinner" class="spinner-overlay" style="display: none;">
                    <div class="spinner"></div>
                    <p>Finding recommendation...</p>
                </div>

                <div class="recommender-layout">
                    <div class="poster-container">
                        <img id="rec-poster" src="" alt="Show Poster" class="poster-img" style="display:none;">
                        <div id="rec-poster-placeholder" class="poster-placeholder">No Image</div>
                    </div>

                    <div class="details-container">
                        <h2 id="rec-title" class="show-title">Loading...</h2>
                        <div id="rec-runtime" class="badge-runtime">Runtime: --</div>

                        <p id="rec-overview" class="show-overview">Please wait while we load candidate shows...</p>

                        <div class="meta-group">
                            <p><strong>Genres:</strong> <span id="rec-genres">--</span></p>
                            <p><strong>First Aired:</strong> <span id="rec-aired">--</span></p>
                            <p><strong>Seasons / Episodes:</strong> <span id="rec-episodes">--</span></p>
                        </div>

                        <div class="action-buttons">
                            <button class="btn btn-add" onclick="addCurrentToList('30')">Add to 30-min list</button>
                            <button class="btn btn-add" onclick="addCurrentToList('40')">Add to 40-min list</button>
                            <button class="btn btn-add" onclick="addCurrentToList('60')">Add to 60-min list</button>
                            <button class="btn btn-add" onclick="addCurrentToList('sleepy')">Add to Sleepy list</button>
                            <button class="btn btn-add" onclick="addCurrentToList('sitcom')">Add to Sitcom list</button>
                            <button class="btn btn-skip" onclick="skipCurrentShow()">Skip</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 2: WATCH NEXT -->
        <section id="tab-watch-next" class="tab-content">
            <div class="list-header">
                <h2>Watch Next Shows</h2>
                <span id="count-watch-next" class="count-badge">0 shows</span>
            </div>
            <div id="grid-watch-next" class="shows-grid"></div>
        </section>

        <!-- TAB 3: VIEW SHOWS -->
        <section id="tab-view-shows" class="tab-content">
            <div class="list-selector-bar">
                <label for="view-list-select" class="selector-label">Select List:</label>
                <select id="view-list-select" class="custom-select" onchange="onViewListChange()">
                    <option value="30">30-Minute Shows</option>
                    <option value="40">40-Minute Shows</option>
                    <option value="60">60-Minute Shows</option>
                    <option value="sleepy">Sleepy Shows</option>
                    <option value="sitcom">Sitcom Shows</option>
                </select>
                <span id="count-view-list" class="count-badge">0 shows</span>
            </div>
            <div id="grid-view-list" class="shows-grid"></div>
        </section>

        <!-- TAB 4: FIND NEXT SHOW -->
        <section id="tab-find-next-show" class="tab-content">
            <div class="list-selector-bar">
                <label for="find-list-select" class="selector-label">Select List:</label>
                <select id="find-list-select" class="custom-select" onchange="onFindListChange()">
                    <option value="30">30-Minute Shows</option>
                    <option value="40">40-Minute Shows</option>
                    <option value="60">60-Minute Shows</option>
                    <option value="sleepy">Sleepy Shows</option>
                    <option value="sitcom">Sitcom Shows</option>
                </select>
                <span id="find-counter" class="count-badge">Show 0 of 0</span>
            </div>

            <div id="find-single-show-card" class="card single-show-box">
                <div id="find-empty-message" class="empty-state" style="display: none;">
                    No shows in this list. Select a different list or add shows from Recommendations!
                </div>

                <div id="find-show-content" class="single-show-layout" style="display: none;">
                    <h2 id="find-show-title" class="find-title-top">Show Title</h2>

                    <div class="find-media-details">
                        <div class="find-poster-container">
                            <img id="find-poster" src="" alt="Show Poster" class="poster-img">
                            <div id="find-poster-placeholder" class="poster-placeholder">No Image</div>
                        </div>

                        <div class="find-details-container">
                            <div class="meta-group">
                                <p><strong>Runtime:</strong> <span id="find-runtime">--</span></p>
                                <p><strong>First Aired:</strong> <span id="find-aired">--</span></p>
                                <p><strong>Seasons / Episodes:</strong> <span id="find-episodes">--</span></p>
                                <p><strong>Genres:</strong> <span id="find-genres">--</span></p>
                            </div>

                            <div class="full-synopsis-box">
                                <h3>Full Synopsis</h3>
                                <p id="find-synopsis" class="full-synopsis-text">Show overview text goes here...</p>
                            </div>

                            <div class="find-nav-buttons">
                                <button id="btn-find-prev" class="btn btn-nav" onclick="navFindShow(-1)">◀ Previous</button>
                                <button id="btn-find-watch-next" class="btn btn-watch-next" onclick="toggleFindWatchNext()">☆ Watch Next</button>
                                <button id="btn-find-next" class="btn btn-nav" onclick="navFindShow(1)">Next ▶</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        let currentShow = null;

        // "Find Next Show" state
        let findListShows = [];
        let findCurrentIndex = 0;
        let findWatchNextIds = [];

        // Navigation tab switching
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

                btn.classList.add('active');
                const tabKey = btn.getAttribute('data-tab');
                const tabId = 'tab-' + tabKey;
                document.getElementById(tabId).classList.add('active');

                if (tabKey === 'watch-next') {
                    loadWatchNextShows();
                } else if (tabKey === 'view-shows') {
                    onViewListChange();
                } else if (tabKey === 'find-next-show') {
                    onFindListChange();
                }
            });
        });

        async function loadNextRecommendation() {
            showSpinner(true);
            try {
                const res = await fetch('api.php?action=recommend');
                const data = await res.json();
                if (data.success && data.show) {
                    currentShow = data.show;
                    displayRecommendation(data.show);
                } else {
                    displayRecommendation({
                        title: 'No recommendations available',
                        overview: 'Unable to fetch recommendations at this time.',
                        runtime: 0
                    });
                }
            } catch (err) {
                console.error(err);
                displayRecommendation({
                    title: 'Error loading recommendation',
                    overview: err.message,
                    runtime: 0
                });
            } finally {
                showSpinner(false);
            }
        }

        function displayRecommendation(show) {
            document.getElementById('rec-title').textContent = show.title || 'Untitled';

            const runtimeText = (show.runtime && show.runtime > 0)
                ? `Max runtime: ${show.runtime} min`
                : 'Runtime: unknown';
            document.getElementById('rec-runtime').textContent = runtimeText;

            document.getElementById('rec-overview').textContent = show.overview || 'No overview available.';

            const genresText = (show.genres && show.genres.length > 0) ? show.genres.join(', ') : 'unknown';
            document.getElementById('rec-genres').textContent = genresText;

            document.getElementById('rec-aired').textContent = show.firstAired || 'unknown';

            let epText = 'unknown';
            if (show.seasonCount > 0 || show.totalEpisodes > 0) {
                epText = `Seasons: ${show.seasonCount || '?'} | Episodes: ${show.totalEpisodes || '?'}`;
            }
            document.getElementById('rec-episodes').textContent = epText;

            const posterImg = document.getElementById('rec-poster');
            const posterPlaceholder = document.getElementById('rec-poster-placeholder');

            if (show.posterUrl) {
                posterImg.src = show.posterUrl;
                posterImg.style.display = 'block';
                posterPlaceholder.style.display = 'none';
            } else {
                posterImg.style.display = 'none';
                posterPlaceholder.style.display = 'flex';
            }
        }

        async function addCurrentToList(listKey) {
            if (!currentShow || !currentShow.id || currentShow.id <= 0) {
                alert('No valid show selected.');
                return;
            }
            showSpinner(true);
            try {
                const res = await fetch('api.php?action=add_to_list', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ list_key: listKey, show: currentShow })
                });
                const data = await res.json();
                if (data.success) {
                    await loadNextRecommendation();
                } else {
                    alert('Error adding show to list.');
                    showSpinner(false);
                }
            } catch (err) {
                console.error(err);
                showSpinner(false);
            }
        }

        async function skipCurrentShow() {
            if (!currentShow || !currentShow.id || currentShow.id <= 0) {
                await loadNextRecommendation();
                return;
            }
            showSpinner(true);
            try {
                const res = await fetch('api.php?action=skip', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ show: currentShow, show_id: currentShow.id })
                });
                const data = await res.json();
                if (data.success) {
                    await loadNextRecommendation();
                } else {
                    alert('Error skipping show.');
                    showSpinner(false);
                }
            } catch (err) {
                console.error(err);
                showSpinner(false);
            }
        }

        /* VIEW SHOWS TAB */
        async function onViewListChange() {
            const listKey = document.getElementById('view-list-select').value;
            const grid = document.getElementById('grid-view-list');
            const countBadge = document.getElementById('count-view-list');
            grid.innerHTML = '<p class="loading-text">Loading shows...</p>';

            try {
                const res = await fetch(`api.php?action=get_list&list_key=${listKey}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.shows)) {
                    countBadge.textContent = `${data.shows.length} show${data.shows.length === 1 ? '' : 's'}`;
                    const watchNextIds = data.watch_next_ids || [];
                    renderListGrid(grid, data.shows, listKey, watchNextIds);
                } else {
                    grid.innerHTML = '<p class="empty-state">No shows in this list yet.</p>';
                    countBadge.textContent = '0 shows';
                }
            } catch (err) {
                grid.innerHTML = `<p class="error-text">Error loading list: ${err.message}</p>`;
            }
        }

        function renderListGrid(container, shows, listKey, watchNextIds = []) {
            if (shows.length === 0) {
                container.innerHTML = '<p class="empty-state">No shows added to this list yet.</p>';
                return;
            }

            container.innerHTML = shows.map(s => {
                const isWatchNext = watchNextIds.includes(Number(s.id));
                const jsonShowStr = escapeHtml(JSON.stringify(s));
                return `
                <div class="show-card">
                    <div class="card-poster">
                        ${s.posterUrl ? `<img src="${s.posterUrl}" alt="${escapeHtml(s.title)}">` : '<div class="poster-placeholder">No Image</div>'}
                    </div>
                    <div class="card-body">
                        <h3 class="card-title">${escapeHtml(s.title)}</h3>
                        <div class="card-meta">
                            <span>⏱ ${s.runtime ? s.runtime + ' min' : 'N/A'}</span>
                            <span>📅 ${s.firstAired ? s.firstAired.substring(0, 4) : 'N/A'}</span>
                        </div>
                        <p class="card-episodes">Seasons: ${s.seasonCount || '?'} | Episodes: ${s.totalEpisodes || '?'}</p>
                        <p class="card-genres">${s.genres ? escapeHtml(s.genres.join(', ')) : ''}</p>
                        <p class="card-overview">${escapeHtml(s.overview || '')}</p>
                        <button class="btn btn-watch-next ${isWatchNext ? 'active' : ''}" data-show="${jsonShowStr}" onclick="handleToggleWatchNext(this)">
                            ${isWatchNext ? '★ Watch Next' : '☆ Watch Next'}
                        </button>
                        <button class="btn btn-remove" onclick="removeShowFromList('${listKey}', ${s.id})">Remove</button>
                    </div>
                </div>
            `;
            }).join('');
        }

        /* FIND NEXT SHOW TAB */
        async function onFindListChange() {
            const listKey = document.getElementById('find-list-select').value;
            try {
                const res = await fetch(`api.php?action=get_list&list_key=${listKey}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.shows)) {
                    findListShows = data.shows;
                    findWatchNextIds = (data.watch_next_ids || []).map(Number);
                } else {
                    findListShows = [];
                    findWatchNextIds = [];
                }
            } catch (err) {
                console.error(err);
                findListShows = [];
                findWatchNextIds = [];
            }
            findCurrentIndex = 0;
            renderFindShow();
        }

        function renderFindShow() {
            const emptyMsg = document.getElementById('find-empty-message');
            const showContent = document.getElementById('find-show-content');
            const counter = document.getElementById('find-counter');

            if (findListShows.length === 0) {
                emptyMsg.style.display = 'block';
                showContent.style.display = 'none';
                counter.textContent = 'Show 0 of 0';
                return;
            }

            emptyMsg.style.display = 'none';
            showContent.style.display = 'block';

            if (findCurrentIndex < 0) findCurrentIndex = 0;
            if (findCurrentIndex >= findListShows.length) findCurrentIndex = findListShows.length - 1;

            const show = findListShows[findCurrentIndex];
            counter.textContent = `Show ${findCurrentIndex + 1} of ${findListShows.length}`;

            // Title at the top
            document.getElementById('find-show-title').textContent = show.title || 'Untitled';

            // Image
            const posterImg = document.getElementById('find-poster');
            const posterPlaceholder = document.getElementById('find-poster-placeholder');
            if (show.posterUrl) {
                posterImg.src = show.posterUrl;
                posterImg.style.display = 'block';
                posterPlaceholder.style.display = 'none';
            } else {
                posterImg.style.display = 'none';
                posterPlaceholder.style.display = 'flex';
            }

            // Meta
            document.getElementById('find-runtime').textContent = show.runtime ? `${show.runtime} min` : 'unknown';
            document.getElementById('find-aired').textContent = show.firstAired || 'unknown';

            let epText = 'unknown';
            if (show.seasonCount > 0 || show.totalEpisodes > 0) {
                epText = `Seasons: ${show.seasonCount || '?'} | Episodes: ${show.totalEpisodes || '?'}`;
            }
            document.getElementById('find-episodes').textContent = epText;

            const genresText = (show.genres && show.genres.length > 0) ? show.genres.join(', ') : 'unknown';
            document.getElementById('find-genres').textContent = genresText;

            // Full Synopsis (Uncut)
            document.getElementById('find-synopsis').textContent = show.overview || 'No synopsis available.';

            // Nav & Watch Next buttons
            const prevBtn = document.getElementById('btn-find-prev');
            const nextBtn = document.getElementById('btn-find-next');
            const watchNextBtn = document.getElementById('btn-find-watch-next');

            prevBtn.disabled = (findCurrentIndex === 0);
            nextBtn.disabled = (findCurrentIndex === findListShows.length - 1);

            const isWatchNext = findWatchNextIds.includes(Number(show.id));
            if (isWatchNext) {
                watchNextBtn.classList.add('active');
                watchNextBtn.innerHTML = '★ Watch Next';
            } else {
                watchNextBtn.classList.remove('active');
                watchNextBtn.innerHTML = '☆ Watch Next';
            }
        }

        function navFindShow(delta) {
            findCurrentIndex += delta;
            renderFindShow();
        }

        async function toggleFindWatchNext() {
            if (findListShows.length === 0) return;
            const show = findListShows[findCurrentIndex];
            try {
                const res = await fetch('api.php?action=toggle_watch_next', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ show: show })
                });
                const data = await res.json();
                if (data.success) {
                    const idNum = Number(show.id);
                    if (data.is_watch_next) {
                        if (!findWatchNextIds.includes(idNum)) findWatchNextIds.push(idNum);
                    } else {
                        findWatchNextIds = findWatchNextIds.filter(id => id !== idNum);
                    }
                    renderFindShow();
                } else {
                    alert('Error toggling Watch Next.');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function handleToggleWatchNext(btn) {
            try {
                const show = JSON.parse(btn.getAttribute('data-show'));
                const res = await fetch('api.php?action=toggle_watch_next', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ show: show })
                });
                const data = await res.json();
                if (data.success) {
                    if (data.is_watch_next) {
                        btn.classList.add('active');
                        btn.innerHTML = '★ Watch Next';
                    } else {
                        btn.classList.remove('active');
                        btn.innerHTML = '☆ Watch Next';
                    }
                } else {
                    alert('Error updating Watch Next state.');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function loadWatchNextShows() {
            const grid = document.getElementById('grid-watch-next');
            const countBadge = document.getElementById('count-watch-next');
            grid.innerHTML = '<p class="loading-text">Loading Watch Next shows...</p>';

            try {
                const res = await fetch('api.php?action=get_watch_next');
                const data = await res.json();
                if (data.success && Array.isArray(data.shows)) {
                    countBadge.textContent = `${data.shows.length} show${data.shows.length === 1 ? '' : 's'}`;
                    renderWatchNextGrid(grid, data.shows);
                } else {
                    grid.innerHTML = '<p class="empty-state">No shows marked as Watch Next yet.</p>';
                    countBadge.textContent = '0 shows';
                }
            } catch (err) {
                grid.innerHTML = `<p class="error-text">Error loading Watch Next page: ${err.message}</p>`;
            }
        }

        function renderWatchNextGrid(container, shows) {
            if (shows.length === 0) {
                container.innerHTML = '<p class="empty-state">No shows marked as Watch Next yet.</p>';
                return;
            }

            container.innerHTML = shows.map(s => {
                const jsonShowStr = escapeHtml(JSON.stringify(s));
                return `
                <div class="show-card">
                    <div class="card-poster">
                        ${s.posterUrl ? `<img src="${s.posterUrl}" alt="${escapeHtml(s.title)}">` : '<div class="poster-placeholder">No Image</div>'}
                    </div>
                    <div class="card-body">
                        <h3 class="card-title">${escapeHtml(s.title)}</h3>
                        <div class="card-meta">
                            <span>⏱ ${s.runtime ? s.runtime + ' min' : 'N/A'}</span>
                            <span>📅 ${s.firstAired ? s.firstAired.substring(0, 4) : 'N/A'}</span>
                        </div>
                        <p class="card-episodes">Seasons: ${s.seasonCount || '?'} | Episodes: ${s.totalEpisodes || '?'}</p>
                        <p class="card-genres">${s.genres ? escapeHtml(s.genres.join(', ')) : ''}</p>
                        <p class="card-overview">${escapeHtml(s.overview || '')}</p>
                        <button class="btn btn-watch-next active" data-show="${jsonShowStr}" onclick="handleToggleWatchNext(this); loadWatchNextShows();">
                            ★ Watch Next
                        </button>
                    </div>
                </div>
            `;
            }).join('');
        }

        async function removeShowFromList(listKey, showId) {
            if (!confirm('Are you sure you want to remove this show from the list?')) return;
            try {
                const res = await fetch('api.php?action=remove_from_list', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ list_key: listKey, show_id: showId })
                });
                const data = await res.json();
                if (data.success) {
                    onViewListChange();
                } else {
                    alert('Error removing show.');
                }
            } catch (err) {
                console.error(err);
            }
        }

        function showSpinner(show) {
            document.getElementById('loading-spinner').style.display = show ? 'flex' : 'none';
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Initialize on load
        loadNextRecommendation();
    </script>
</body>
</html>
