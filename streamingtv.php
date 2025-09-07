//
//  streamingtv.php
//  IntensifyDigital JSON Streaming TV Player  
//
//  Created by Stephen Sadler on 7/28/25.
//
<?php
// Define the IntensifyDigital.com campaigns with their IDs {campaignId}, corresponding bearer tokens {bearerToken}, and custom titles
$campaigns = [
    ['id' => {campaignId}, 'token' => '{bearerToken}', 'title' => 'TYPE_CHANNEL_NAME_HERE'],
    ['id' => {campaignId}, 'token' => '{bearerToken}', 'title' => "TYPE_CHANNEL_NAME_HERE"],
];

// Handle AJAX requests for loading more messages
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['campaign']) && isset($_GET['page'])) {
    $campaignId = filter_var($_GET['campaign'], FILTER_VALIDATE_INT);
    $page = filter_var($_GET['page'], FILTER_VALIDATE_INT);
    if (!$campaignId || !$page) {
        echo json_encode(['error' => 'Invalid campaign or page']);
        exit;
    }

    $bearerToken = null;
    foreach ($campaigns as $campaignInfo) {
        if ($campaignInfo['id'] == $campaignId) {
            $bearerToken = $campaignInfo['token'];
            break;
        }
    }

    if ($bearerToken === null) {
        echo json_encode(['error' => 'Campaign not found']);
        exit;
    }

    // Initialize cURL session
    $ch = curl_init();
    $url = "https://portal.intensifydigital.com/api/campaign/{$campaignId}/messages/sent?limit=10&page={$page}&order=desc";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$bearerToken}",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['error' => curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => json_last_error_msg()]);
        exit;
    }

    // Generate slugs using campaign ID and message ID
    foreach ($data as &$m) {
        $m['slug'] = $campaignId . '-' . ($m['id'] ?? uniqid());
    }

    echo json_encode($data);
    exit;
}

// Initialize an array to hold data for each campaign (initial page only)
$campaignsData = [];
foreach ($campaigns as $campaignInfo) {
    $campaignId = $campaignInfo['id'];
    $bearerToken = $campaignInfo['token'];
    $campaignTitle = $campaignInfo['title'];

    // Initialize cURL session
    $ch = curl_init();
    $url = "https://portal.intensifydigital.com/api/campaign/{$campaignId}/messages/sent?limit=10&page=1&order=desc";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$bearerToken}",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $campaignsData[$campaignId] = ['messages' => [], 'title' => $campaignTitle, 'error' => curl_error($ch)];
        curl_close($ch);
        continue;
    }

    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $campaignsData[$campaignId] = ['messages' => [], 'title' => $campaignTitle, 'error' => json_last_error_msg()];
        continue;
    }

    // Generate slugs using campaign ID and message ID
    foreach ($data as &$m) {
        $m['slug'] = $campaignId . '-' . ($m['id'] ?? uniqid());
    }

    $campaignsData[$campaignId] = ['messages' => $data, 'title' => $campaignTitle, 'error' => null];
}

// Handle single post view if ?post=slug is in URL
$singlePost = null;
if (isset($_GET['post'])) {
    $requestedSlug = filter_var($_GET['post'], FILTER_SANITIZE_STRING);
    $slugParts = explode('-', $requestedSlug, 2);
    if (count($slugParts) === 2) {
        $campaignId = filter_var($slugParts[0], FILTER_VALIDATE_INT);
        $messageId = $slugParts[1];
        $bearerToken = null;
        $campaignTitle = '';

        foreach ($campaigns as $campaignInfo) {
            if ($campaignInfo['id'] == $campaignId) {
                $bearerToken = $campaignInfo['token'];
                $campaignTitle = $campaignInfo['title'];
                break;
            }
        }

        if ($bearerToken !== null) {
            $ch = curl_init();
            $url = "https://portal.intensifydigital.com/api/campaign/{$campaignId}/messages/sent?id={$messageId}";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$bearerToken}",
                "Content-Type: application/json"
            ]);

            $response = curl_exec($ch);

            if (!curl_errno($ch)) {
                $data = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($data) && is_array($data)) {
                    $singlePost = $data[0];
                    $singlePost['slug'] = $requestedSlug;
                }
            }
            curl_close($ch);
        }
    }

    if ($singlePost) {
        $pageTitle = substr($singlePost['message'], 0, 60) . (strlen($singlePost['message']) > 60 ? '...' : '');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($pageTitle); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; transition: background-color 0.3s, color 0.3s; }
                body.dark { background-color: #121212; color: #ffffff; }
                body.dark .single-post { background-color: #1e1e1e; color: #ffffff; }
                body.light { background-color: #f4f4f4; color: #000000; }
                body.light .single-post { background-color: #fefefe; color: #000000; }
                .single-post { margin: auto; padding: 20px; border: none; width: 90%; max-width: 800px; text-align: center; position: relative; transition: background-color 0.3s, color 0.3s; }
                .single-post img, .single-post video { max-width: 100%; height: auto; display: block; margin: 0 auto; }
                .single-post p { white-space: pre-wrap; text-align: left; margin-top: 20px; }
                #themeToggle { position: fixed; top: 10px; right: 10px; padding: 10px; border: none; border-radius: 5px; cursor: pointer; z-index: 1000; transition: background-color 0.3s, color 0.3s; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
                body.dark #themeToggle { background-color: #333; color: #fff; }
                body.light #themeToggle { background-color: #ddd; color: #000; }
                .theme-icon { width: 24px; height: 24px; fill: currentColor; }
                .back-link { display: block; text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>
            <button id="themeToggle" aria-label="Toggle theme">
                <svg id="sunIcon" class="theme-icon" style="display: none;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="5" fill="currentColor"/>
                    <line x1="12" y1="6" x2="12" y2="1" stroke="currentColor" stroke-width="2"/>
                    <line x1="12" y1="18" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
                    <line x1="6" y1="12" x2="1" y2="12" stroke="currentColor" stroke-width="2"/>
                    <line x1="18" y1="12" x2="23" y2="12" stroke="currentColor" stroke-width="2"/>
                    <line x1="7.5" y1="7.5" x2="4.3" y2="4.3" stroke="currentColor" stroke-width="2"/>
                    <line x1="16.5" y1="7.5" x2="19.7" y2="4.3" stroke="currentColor" stroke-width="2"/>
                    <line x1="7.5" y1="16.5" x2="4.3" y2="19.7" stroke="currentColor" stroke-width="2"/>
                    <line x1="16.5" y1="16.5" x2="19.7" y2="19.7" stroke="currentColor" stroke-width="2"/>
                </svg>
                <svg id="moonIcon" class="theme-icon" viewBox="0 0 24 24">
                    <path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
            <div class="single-post">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if (!empty($singlePost['media']) && is_array($singlePost['media'])): ?>
                    <?php $mediaItem = $singlePost['media'][0]; ?>
                    <?php if (strpos($mediaItem['mime_type'], 'video/') === 0): ?>
                        <video controls src="<?php echo htmlspecialchars($mediaItem['location']); ?>"></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($mediaItem['location']); ?>" alt="Full Image">
                    <?php endif; ?>
                <?php else: ?>
                    <p>No media available</p>
                <?php endif; ?>
                <p><?php echo htmlspecialchars($singlePost['message'] ?? 'N/A'); ?></p>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="back-link">Back to Feed</a>
            </div>
            <script>
                const body = document.body;
                const themeToggle = document.getElementById('themeToggle');
                const sunIcon = document.getElementById('sunIcon');
                const moonIcon = document.getElementById('moonIcon');
                const currentTheme = localStorage.getItem('theme') || 'dark';
                body.classList.add(currentTheme);
                sunIcon.style.display = currentTheme === 'dark' ? 'block' : 'none';
                moonIcon.style.display = currentTheme === 'dark' ? 'none' : 'block';

                themeToggle.addEventListener('click', () => {
                    if (body.classList.contains('dark')) {
                        body.classList.replace('dark', 'light');
                        sunIcon.style.display = 'none';
                        moonIcon.style.display = 'block';
                        localStorage.setItem('theme', 'light');
                    } else {
                        body.classList.replace('light', 'dark');
                        sunIcon.style.display = 'block';
                        moonIcon.style.display = 'none';
                        localStorage.setItem('theme', 'dark');
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// Feed view (default)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Example: Streaming TV</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; transition: background-color 0.3s, color 0.3s; }
        body.dark { background-color: #121212; color: #ffffff; }
        body.light { background-color: #f4f4f4; color: #000000; }
        h1 { text-align: left; }
        h2 { text-align: left; margin-top: 40px; }
        .error { color: red; text-align: center; }

        .tile-row { display: flex; overflow-x: auto; gap: 15px; padding: 10px 0; cursor: grab; user-select: none; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory; }
        .tile-row:active { cursor: grabbing; }
        .tile-row::-webkit-scrollbar { height: 8px; }
        .tile-row::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        .tile-row::-webkit-scrollbar-thumb:hover { background: #555; }

        .tile { flex: 0 0 250px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); cursor: pointer; transition: background-color 0.3s, color 0.3s; scroll-snap-align: start; }
        body.dark .tile { background: #1e1e1e; }
        body.light .tile { background: white; }
        .tile img { width: 100%; height: 150px; object-fit: cover; }
        .tile .placeholder { width: 100%; height: 150px; display: flex; align-items: center; justify-content: center; transition: background-color 0.3s, color 0.3s; }
        body.dark .tile .placeholder { background: #333; color: #bbb; }
        body.light .tile .placeholder { background: #ddd; color: #666; }
        .tile .tile-content { padding: 10px; }
        .tile .tile-message { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tile .tile-scheduled { font-size: 12px; opacity: 0.7; }

        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; justify-content: center; align-items: center; touch-action: pan-y; transition: background-color 0.3s; }
        body.dark .modal { background-color: rgba(0,0,0,0.9); }
        body.light .modal { background-color: rgba(255,255,255,0.8); }
        .modal-content { margin: auto; padding: 20px; border: none; width: 90%; max-width: 800px; height: 100%; text-align: center; position: relative; touch-action: none; transition: background-color 0.3s, color 0.3s; overflow-y: auto; }
        body.dark .modal-content { background-color: #1e1e1e; color: #ffffff; }
        body.light .modal-content { background-color: #fefefe; color: #000000; }
        .media-wrapper { position: relative; display: inline-block; margin: 0 auto; max-width: 100%; touch-action: none; }
        .modal-img, .modal-video { max-width: 100%; height: auto; display: none; position: relative; z-index: 1; opacity: 1; transition: opacity 0.3s ease; }
        .no-media { margin: 20px 0; font-size: 18px; display: none; }
        body.dark .no-media { color: #bbb; }
        body.light .no-media { color: #666; }
        .modal-message { margin-top: 20px; white-space: pre-wrap; text-align: left; max-height: 200px; overflow-y: auto; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; top: 15px; right: 15px; z-index: 3; color: #fff; }
        .close:hover, .close:focus { color: #ddd; text-decoration: none; }

        .arrow { position: absolute; top: 50%; transform: translateY(-50%); font-size: 40px; cursor: pointer; background-color: rgba(0,0,0,0.5); padding: 10px; z-index: 10; transition: color 0.3s; }
        body.dark .arrow { color: #fff; }
        body.light .arrow { color: #000; background-color: rgba(255,255,255,0.5); }
        .arrow:hover { background-color: rgba(0,0,0,0.8); }
        body.light .arrow:hover { background-color: rgba(255,255,255,0.8); }
        .prev { left: 10px; }
        .next { right: 10px; }

        .loading-spinner { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #ffffff; border-radius: 50%; animation: spin 1s linear infinite; z-index: 2; }
        body.light .loading-spinner { border: 4px solid rgba(0,0,0,0.1); border-top-color: #000000; }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        #themeToggle { position: fixed; top: 10px; right: 10px; padding: 10px; border: none; border-radius: 5px; cursor: pointer; z-index: 1000; transition: background-color 0.3s, color 0.3s; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        body.dark #themeToggle { background-color: #333; color: #fff; }
        body.light #themeToggle { background-color: #ddd; color: #000; }
        .theme-icon { width: 24px; height: 24px; fill: currentColor; }

        @media (max-width: 768px) {
            .tile { flex: 0 0 200px; }
            .tile img, .tile .placeholder { height: 120px; }
            .modal-content { width: 95%; padding: 50px 10px 10px 10px; }
            .arrow { display: none !important; }
            .modal-content { overflow-y: auto; text-align: left; }
            .post-item { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 20px; }
            body.dark .post-item { border-bottom: 1px solid #333; }
            .post-media img, .post-media video { max-width: 100%; height: auto; }
            .post-message { white-space: pre-wrap; }
            .close { top: 0; }
        }

        @media (max-width: 480px) {
            .tile { flex: 0 0 150px; }
            .tile img, .tile .placeholder { height: 100px; }
            .tile .tile-message { font-size: 12px; }
            .tile .tile-scheduled { font-size: 10px; }
        }
    </style>
</head>
<body>
    <button id="themeToggle" aria-label="Toggle theme">
        <svg id="sunIcon" class="theme-icon" style="display: none;" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="5" fill="currentColor"/>
            <line x1="12" y1="6" x2="12" y2="1" stroke="currentColor" stroke-width="2"/>
            <line x1="12" y1="18" x2="12" y2="23" stroke="currentColor" stroke-width="2"/>
            <line x1="6" y1="12" x2="1" y2="12" stroke="currentColor" stroke-width="2"/>
            <line x1="18" y1="12" x2="23" y2="12" stroke="currentColor" stroke-width="2"/>
            <line x1="7.5" y1="7.5" x2="4.3" y2="4.3" stroke="currentColor" stroke-width="2"/>
            <line x1="16.5" y1="7.5" x2="19.7" y2="4.3" stroke="currentColor" stroke-width="2"/>
            <line x1="7.5" y1="16.5" x2="4.3" y2="19.7" stroke="currentColor" stroke-width="2"/>
            <line x1="16.5" y1="16.5" x2="19.7" y2="19.7" stroke="currentColor" stroke-width="2"/>
        </svg>
        <svg id="moonIcon" class="theme-icon" viewBox="0 0 24 24">
            <path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
    </button>

    <?php foreach ($campaignsData as $campaignId => $campaign): ?>
        <?php $messages = $campaign['messages']; $title = $campaign['title']; ?>
        <?php if ($campaign['error']): ?>
            <p class="error">Error for channel <?php echo htmlspecialchars($campaignId); ?>: <?php echo htmlspecialchars($campaign['error']); ?></p>
        <?php elseif (empty($messages) || !is_array($messages)): ?>
            <p class="error">No messages found for channel <?php echo htmlspecialchars($campaignId); ?> or invalid response format.</p>
        <?php else: ?>
            <h2><?php echo htmlspecialchars($title); ?></h2>
            <div class="tile-row" data-campaign="<?php echo htmlspecialchars($campaignId); ?>" data-page="1" data-has-more="<?php echo count($messages) === 10 ? 'true' : 'false'; ?>" data-loading="false">
                <?php foreach ($messages as $localIndex => $message): ?>
                    <div class="tile" data-index="<?php echo $localIndex; ?>" data-campaign="<?php echo htmlspecialchars($campaignId); ?>">
                        <?php if (!empty($message['media']) && is_array($message['media'])): ?>
                            <img src="<?php echo htmlspecialchars($message['media'][0]['thumbnail'] ?? ''); ?>" alt="Thumbnail">
                        <?php else: ?>
                            <div class="placeholder">No Media</div>
                        <?php endif; ?>
                        <div class="tile-content">
                            <p class="tile-message"><?php echo htmlspecialchars(substr($message['message'] ?? 'N/A', 0, 50)) . '...'; ?></p>
                            <p class="tile-scheduled" data-utc="<?php echo htmlspecialchars($message['scheduled_at'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($message['scheduled_at'] ?? 'N/A'); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div id="imageModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalDesktopView" class="media-wrapper">
                <img id="modalImg" class="modal-img" src="" alt="Full Image">
                <video id="modalVideo" class="modal-video" controls></video>
                <span id="prevArrow" class="arrow prev">&#10094;</span>
                <span id="nextArrow" class="arrow next">&#10095;</span>
                <div class="loading-spinner"></div>
            </div>
            <p id="noMedia" class="no-media">No media available</p>
            <p id="modalMessage" class="modal-message"></p>
        </div>
    </div>

    <script>
        // Convert UTC dates to local time zone
        document.querySelectorAll('.tile-scheduled').forEach(elem => {
            const utcDate = elem.dataset.utc;
            if (utcDate && utcDate !== 'N/A') {
                elem.textContent = new Date(utcDate).toLocaleString();
            }
        });

        // Theme toggle logic
        const body = document.body;
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');
        const currentTheme = localStorage.getItem('theme') || 'dark';
        body.classList.add(currentTheme);
        sunIcon.style.display = currentTheme === 'dark' ? 'block' : 'none';
        moonIcon.style.display = currentTheme === 'dark' ? 'none' : 'block';

        themeToggle.addEventListener('click', () => {
            if (body.classList.contains('dark')) {
                body.classList.replace('dark', 'light');
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
                localStorage.setItem('theme', 'light');
            } else {
                body.classList.replace('light', 'dark');
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
                localStorage.setItem('theme', 'dark');
            }
        });

        // Drag scrolling with momentum for tile rows
        const tileRows = document.querySelectorAll('.tile-row');
        tileRows.forEach(tileRow => {
            let isDragging = false;
            let startX, scrollLeft, velocity = 0;
            let prevTime = performance.now();
            let animationFrame;

            function animateScroll() {
                if (Math.abs(velocity) < 0.1) return;
                tileRow.scrollLeft += velocity;
                velocity *= 0.95;
                animationFrame = requestAnimationFrame(animateScroll);
            }

            tileRow.addEventListener('mousedown', e => {
                isDragging = true;
                startX = e.pageX - tileRow.offsetLeft;
                scrollLeft = tileRow.scrollLeft;
                velocity = 0;
                cancelAnimationFrame(animationFrame);
                prevTime = performance.now();
            });

            tileRow.addEventListener('mouseleave', () => {
                if (isDragging) animateScroll();
                isDragging = false;
            });

            tileRow.addEventListener('mouseup', () => {
                if (isDragging) animateScroll();
                isDragging = false;
            });

            tileRow.addEventListener('mousemove', e => {
                if (!isDragging) return;
                e.preventDefault();
                const x = e.pageX - tileRow.offsetLeft;
                const walk = (x - startX) * 2;
                const currentTime = performance.now();
                velocity = (scrollLeft - tileRow.scrollLeft) / (currentTime - prevTime) * 16;
                prevTime = currentTime;
                tileRow.scrollLeft = scrollLeft - walk;
            });

            // Load more on scroll
            tileRow.addEventListener('scroll', () => {
                if (tileRow.scrollLeft + tileRow.clientWidth >= tileRow.scrollWidth - 50 && tileRow.dataset.hasMore === 'true' && tileRow.dataset.loading === 'false') {
                    loadMore(tileRow);
                }
            });
        });

        // Load more messages
        let campaignsSlides = <?php echo json_encode(array_map(function($c) { return $c['messages']; }, $campaignsData)); ?>;
        function loadMore(row) {
            row.dataset.loading = 'true';
            const campaign = row.dataset.campaign;
            const nextPage = parseInt(row.dataset.page) + 1;
            fetch(`<?php echo basename($_SERVER['PHP_SELF']); ?>?ajax=1&campaign=${campaign}&page=${nextPage}`)
                .then(response => response.json())
                .then(newMessages => {
                    if (newMessages.error) {
                        console.error(newMessages.error);
                        return;
                    }
                    if (newMessages.length < 10) {
                        row.dataset.hasMore = 'false';
                    }
                    row.dataset.page = nextPage;

                    const currentLength = campaignsSlides[campaign].length;
                    newMessages.forEach((message, i) => {
                        const tile = document.createElement('div');
                        tile.className = 'tile';
                        tile.dataset.index = currentLength + i;
                        tile.dataset.campaign = campaign;

                        if (message.media && message.media.length > 0) {
                            const img = document.createElement('img');
                            img.src = message.media[0].thumbnail || '';
                            img.alt = 'Thumbnail';
                            tile.appendChild(img);
                        } else {
                            const placeholder = document.createElement('div');
                            placeholder.className = 'placeholder';
                            placeholder.textContent = 'No Media';
                            tile.appendChild(placeholder);
                        }

                        const content = document.createElement('div');
                        content.className = 'tile-content';
                        const messageP = document.createElement('p');
                        messageP.className = 'tile-message';
                        messageP.textContent = (message.message || 'N/A').substring(0, 50) + '...';
                        content.appendChild(messageP);

                        const scheduledP = document.createElement('p');
                        scheduledP.className = 'tile-scheduled';
                        scheduledP.dataset.utc = message.scheduled_at || 'N/A';
                        scheduledP.textContent = message.scheduled_at || 'N/A';
                        if (scheduledP.dataset.utc && scheduledP.dataset.utc !== 'N/A') {
                            scheduledP.textContent = new Date(scheduledP.dataset.utc).toLocaleString();
                        }
                        content.appendChild(scheduledP);

                        tile.appendChild(content);
                        row.appendChild(tile);

                        tile.addEventListener('click', () => {
                            currentIndex = parseInt(tile.dataset.index);
                            allSlides = campaignsSlides[tile.dataset.campaign];
                            modal.style.display = 'flex';
                            themeToggle.style.display = 'none';
                            mediaWrapper.style.display = 'inline-block';
                            modalMessage.style.display = 'block';
                            noMediaElem.style.display = 'block';
                            showSlide(currentIndex);
                            updateArrows();
                        });
                    });

                    campaignsSlides[campaign] = campaignsSlides[campaign].concat(newMessages);
                })
                .catch(error => console.error('Error loading more:', error))
                .finally(() => {
                    row.dataset.loading = 'false';
                });
        }

        // Modal handling
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImg');
        const modalVideo = document.getElementById('modalVideo');
        const noMediaElem = document.getElementById('noMedia');
        const modalMessage = document.getElementById('modalMessage');
        const closeBtn = document.getElementsByClassName('close')[0];
        const prevArrow = document.getElementById('prevArrow');
        const nextArrow = document.getElementById('nextArrow');
        const mediaWrapper = document.getElementById('modalDesktopView');
        const loadingSpinner = document.querySelector('.loading-spinner');

        let allSlides = [];
        let currentIndex = 0;
        let touchStartX = 0;
        let touchEndX = 0;

        function showSlide(index) {
            const currentMedia = modalImg.style.display === 'block' ? modalImg : (modalVideo.style.display === 'block' ? modalVideo : null);
            if (currentMedia) {
                currentMedia.style.opacity = '0';
            }

            setTimeout(() => {
                currentIndex = (index + allSlides.length) % allSlides.length;
                const slide = allSlides[currentIndex];
                const postUrl = window.location.origin + window.location.pathname + '?post=' + slide.slug;
                modalMessage.innerHTML = '<a href="' + postUrl + '" style="text-decoration: none; color: inherit;">' + (slide.message || 'N/A') + '</a>';

                modalImg.style.display = 'none';
                modalVideo.style.display = 'none';
                noMediaElem.style.display = 'none';
                loadingSpinner.style.display = 'none';
                modalVideo.pause();

                if (slide.media && slide.media.length > 0) {
                    mediaWrapper.style.display = 'inline-block';
                    const mediaItem = slide.media[0];
                    loadingSpinner.style.display = 'block';
                    let mediaElement;
                    if (mediaItem.mime_type.startsWith('video/')) {
                        mediaElement = modalVideo;
                        mediaElement.src = mediaItem.location;
                        mediaElement.addEventListener('loadeddata', () => {
                            loadingSpinner.style.display = 'none';
                            mediaElement.style.display = 'block';
                            mediaElement.style.opacity = '0';
                            setTimeout(() => { mediaElement.style.opacity = '1'; }, 10);
                        }, {once: true});
                    } else {
                        mediaElement = modalImg;
                        mediaElement.src = mediaItem.location;
                        mediaElement.addEventListener('load', () => {
                            loadingSpinner.style.display = 'none';
                            mediaElement.style.display = 'block';
                            mediaElement.style.opacity = '0';
                            setTimeout(() => { mediaElement.style.opacity = '1'; }, 10);
                        }, {once: true});
                    }
                } else {
                    noMediaElem.style.display = 'block';
                }
            }, 300);
        }

        function updateArrows() {
            if (allSlides.length > 1) {
                prevArrow.style.display = 'block';
                nextArrow.style.display = 'block';
            } else {
                prevArrow.style.display = 'none';
                nextArrow.style.display = 'none';
            }
        }

        document.querySelectorAll('.tile').forEach(tile => {
            tile.addEventListener('click', () => {
                currentIndex = parseInt(tile.dataset.index);
                allSlides = campaignsSlides[tile.dataset.campaign];
                modal.style.display = 'flex';
                themeToggle.style.display = 'none';
                mediaWrapper.style.display = 'inline-block';
                modalMessage.style.display = 'block';
                noMediaElem.style.display = 'block';
                showSlide(currentIndex);
                updateArrows();
            });
        });

        prevArrow.addEventListener('click', () => showSlide(currentIndex - 1));
        nextArrow.addEventListener('click', () => showSlide(currentIndex + 1));

        modal.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        });

        modal.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchStartX - touchEndX > 50) {
                showSlide(currentIndex + 1);
            } else if (touchEndX - touchStartX > 50) {
                showSlide(currentIndex - 1);
            }
        });

        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            themeToggle.style.display = 'block';
            modalVideo.pause();
        });

        window.addEventListener('click', event => {
            if (event.target === modal) {
                modal.style.display = 'none';
                themeToggle.style.display = 'block';
                modalVideo.pause();
            }
        });
    </script>
</body>
</html>
