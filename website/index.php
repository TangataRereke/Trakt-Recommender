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
            <button class="nav-btn active" data-tab="recommendations">Recommender</button>
            <button class="nav-btn" data-tab="list-30">30 Min</button>
            <button class="nav-btn" data-tab="list-40">40 Min</button>
            <button class="nav-btn" data-tab="list-60">60 Min</button>
            <button class="nav-btn" data-tab="list-sleepy">Sleepy</button>
            <button class="nav-btn" data-tab="list-sitcom">Sitcom</button>
        </nav>
    </header>

    <main class="container">
        <!-- TAB: RECOMMENDATIONS -->
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

        <!-- TABS: LISTS (30, 40, 60, Sleepy, Sitcom) -->
        <section id="tab-list-30" class="tab-content">
            <div class="list-header">
                <h2>30-Minute Shows List</h2>
                <span id="count-30" class="count-badge">0 shows</span>
            </div>
            <div id="grid-30" class="shows-grid"></div>
        </section>

        <section id="tab-list-40" class="tab-content">
            <div class="list-header">
                <h2>40-Minute Shows List</h2>
                <span id="count-40" class="count-badge">0 shows</span>
            </div>
            <div id="grid-40" class="shows-grid"></div>
        </section>

        <section id="tab-list-60" class="tab-content">
            <div class="list-header">
                <h2>60-Minute Shows List</h2>
                <span id="count-60" class="count-badge">0 shows</span>
            </div>
            <div id="grid-60" class="shows-grid"></div>
        </section>

        <section id="tab-list-sleepy" class="tab-content">
            <div class="list-header">
                <h2>Sleepy Shows List</h2>
                <span id="count-sleepy" class="count-badge">0 shows</span>
            </div>
            <div id="grid-sleepy" class="shows-grid"></div>
        </section>

        <section id="tab-list-sitcom" class="tab-content">
            <div class="list-header">
                <h2>Sitcom Shows List</h2>
                <span id="count-sitcom" class="count-badge">0 shows</span>
            </div>
            <div id="grid-sitcom" class="shows-grid"></div>
        </section>
    </main>

    <script>
        let currentShow = null;

        // Navigation tab switching
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

                btn.classList.add('active');
                const tabId = 'tab-' + btn.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');

                const key = btn.getAttribute('data-tab').replace('list-', '');
                if (['30', '40', '60', 'sleepy', 'sitcom'].includes(key)) {
                    loadListShows(key);
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
                    body: JSON.stringify({ show_id: currentShow.id })
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

        async function loadListShows(listKey) {
            const grid = document.getElementById('grid-' + listKey);
            const countBadge = document.getElementById('count-' + listKey);
            grid.innerHTML = '<p class="loading-text">Loading shows...</p>';

            try {
                const res = await fetch(`api.php?action=get_list&list_key=${listKey}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.shows)) {
                    countBadge.textContent = `${data.shows.length} show${data.shows.length === 1 ? '' : 's'}`;
                    renderListGrid(grid, data.shows, listKey);
                } else {
                    grid.innerHTML = '<p class="empty-state">No shows in this list yet.</p>';
                    countBadge.textContent = '0 shows';
                }
            } catch (err) {
                grid.innerHTML = `<p class="error-text">Error loading list: ${err.message}</p>`;
            }
        }

        function renderListGrid(container, shows, listKey) {
            if (shows.length === 0) {
                container.innerHTML = '<p class="empty-state">No shows added to this list yet.</p>';
                return;
            }

            container.innerHTML = shows.map(s => `
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
                        <button class="btn btn-remove" onclick="removeShowFromList('${listKey}', ${s.id})">Remove</button>
                    </div>
                </div>
            `).join('');
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
                    loadListShows(listKey);
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
