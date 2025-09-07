//
//  socialapp.php
//  IntensifyDigital JSON Social Media Player  
//
//  Created by Stephen Sadler on 7/28/25.
//
<?php
// Add cache-busting headers to prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

//  Define the IntensifyDigital.com campaign with the ID {campaignId}, corresponding bearer token {bearerToken}, and custom title
$campaigns = [
    ['id' => {campaignId}, 'token' => '{bearerToken}', 'title' => 'TYPE_CHANNEL_NAME_HERE'],
];

// Function to fetch messages for a single campaign with pagination
function getCampaignMessages($campaignId, $bearerToken, $page = 1, $limit = 10, $order = 'desc') {
    $url = "https://portal.intensifydigital.com/api/campaign/{$campaignId}/messages/sent";
    $queryParams = [];
    $queryParams[] = "page={$page}";
    $queryParams[] = "limit={$limit}";
    $queryParams[] = "order={$order}";
    $url .= '?' . implode('&', $queryParams);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer {$bearerToken}",
        "Content-Type: application/json"
    ));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return ['error' => 'cURL Error for campaign ' . $campaignId . ': ' . curl_error($ch)];
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'JSON Decode Error for campaign ' . $campaignId . ': ' . json_last_error_msg()];
    }

    // Generate unique slugs
    $slugs = [];
    foreach ($data as &$m) {
        $baseSlug = preg_replace('/[^a-z0-9 ]/i', '', substr($m['message'] ?? 'N/A', 0, 50));
        $baseSlug = trim(strtolower(str_replace(' ', '-', $baseSlug)), '-');
        if ($baseSlug === '') $baseSlug = 'post';
        $timePart = preg_replace('/[^0-9]/', '', $m['scheduled_at'] ?? '0');
        $baseSlug = $timePart . '-' . $baseSlug;
        $slug = $baseSlug;
        $count = 1;
        while (isset($slugs[$slug])) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }
        $slugs[$slug] = true;
        $m['slug'] = $slug;
    }
    unset($m);

    return ['messages' => $data, 'total' => count($data)];
}

// Function to fetch initial or refresh data
function fetchCampaignData($page = 1, $limit = 10, $order = 'desc') {
    global $campaigns;
    $campaignsData = [];

    foreach ($campaigns as $campaignInfo) {
        $campaignId = $campaignInfo['id'];
        $bearerToken = $campaignInfo['token'];
        $campaignTitle = $campaignInfo['title'];

        $result = getCampaignMessages($campaignId, $bearerToken, $page, $limit, $order);

        if (isset($result['error'])) {
            $campaignsData[$campaignId] = ['error' => $result['error']];
        } else {
            $campaignsData[$campaignId] = [
                'messages' => $result['messages'],
                'title' => $campaignTitle,
                'total' => $result['total']
            ];
        }
    }

    return $campaignsData;
}

// Handle AJAX request for campaign data (refresh page 1)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_campaigns') {
    header('Content-Type: application/json');
    $result = fetchCampaignData(1, 10, 'desc');
    echo json_encode($result);
    exit;
}

// Handle AJAX request for load more
if (isset($_GET['action']) && $_GET['action'] === 'load_more') {
    header('Content-Type: application/json');
    $campaignId = $_GET['campaign_id'] ?? null;
    $page = (int) ($_GET['page'] ?? 2);
    $limit = 10;
    $order = 'desc';

    $campaignInfo = null;
    foreach ($campaigns as $c) {
        if ($c['id'] == $campaignId) {
            $campaignInfo = $c;
            break;
        }
    }

    if (!$campaignInfo) {
        echo json_encode(['error' => 'Campaign not found']);
        exit;
    }

    $result = getCampaignMessages($campaignId, $campaignInfo['token'], $page, $limit, $order);

    if (isset($result['error'])) {
        echo json_encode(['error' => $result['error']]);
    } else {
        echo json_encode(['messages' => $result['messages'], 'total' => $result['total']]);
    }
    exit;
}

// Fetch initial data for page load
$singlePostMode = isset($_GET['post']);
$campaignsData = $singlePostMode ? fetchCampaignData(1, null, 'desc') : fetchCampaignData(1, 10, 'desc');

// Handle single post view if ?post=slug is in URL
$singlePost = null;
if ($singlePostMode) {
    $requestedSlug = $_GET['post'];
    foreach ($campaignsData as $campaignId => $campaign) {
        if (isset($campaign['error'])) continue;
        foreach ($campaign['messages'] as $message) {
            if ($message['slug'] === $requestedSlug) {
                $singlePost = $message;
                break 2;
            }
        }
    }
    if ($singlePost) {
        // Single post view
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
                // Theme toggle logic for single post page
                const body = document.body;
                const themeToggle = document.getElementById('themeToggle');
                const sunIcon = document.getElementById('sunIcon');
                const moonIcon = document.getElementById('moonIcon');
                let currentTheme = localStorage.getItem('theme') || 'dark';
                body.classList.add(currentTheme);
                sunIcon.style.display = currentTheme === 'dark' ? 'block' : 'none';
                moonIcon.style.display = currentTheme === 'dark' ? 'none' : 'block';

                themeToggle.addEventListener('click', () => {
                    body.classList.toggle('dark');
                    body.classList.toggle('light');
                    currentTheme = body.classList.contains('dark') ? 'dark' : 'light';
                    sunIcon.style.display = currentTheme === 'dark' ? 'block' : 'none';
                    moonIcon.style.display = currentTheme === 'dark' ? 'none' : 'block';
                    localStorage.setItem('theme', currentTheme);
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
    <title>API Example: Social App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; transition: background-color 0.3s, color 0.3s; }
        body.dark { background-color: #121212; color: #ffffff; }
        body.light { background-color: #f4f4f4; color: #000000; }
        h1 { text-align: left; }
        h2 { text-align: left; margin-top: 40px; }
        .error { color: red; text-align: center; }

        /* Vertical tile column */
        .tile-column { display: flex; flex-direction: column; align-items: center; gap: 15px; padding: 10px 0; }

        /* Tile styles */
        .tile { flex: 0 0 auto; width: 250px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); cursor: pointer; transition: background-color 0.3s, color 0.3s; }
        body.dark .tile { background: #1e1e1e; }
        body.light .tile { background: white; }
        .tile img { width: 100%; height: 150px; object-fit: cover; }
        .tile .placeholder { width: 100%; height: 150px; display: flex; align-items: center; justify-content: center; transition: background-color 0.3s, color 0.3s; }
        body.dark .tile .placeholder { background: #333; color: #bbb; }
        body.light .tile .placeholder { background: #ddd; color: #666; }
        .tile .tile-content { padding: 10px; }
        .tile .tile-message { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tile .tile-scheduled { font-size: 12px; opacity: 0.7; }

        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; justify-content: center; align-items: center; transition: background-color 0.3s; }
        body.dark .modal { background-color: rgba(0,0,0,0.9); }
        body.light .modal { background-color: rgba(255,255,255,0.8); }
        .modal-content { margin: auto; padding: 20px; border: none; width: 90%; max-width: 800px; height: 100%; text-align: center; position: relative; transition: background-color 0.3s, color 0.3s; overflow-y: auto; }
        body.dark .modal-content { background-color: #1e1e1e; color: #ffffff; }
        body.light .modal-content { background-color: #fefefe; color: #000000; }
        .media-wrapper { position: relative; display: inline-block; margin: 0 auto; max-width: 100%; }
        .modal-img, .modal-video { max-width: 100%; height: auto; display: none; position: relative; z-index: 1; opacity: 1; transition: opacity 0.3s ease; }
        .no-media { margin: 20px 0; font-size: 18px; display: none; }
        body.dark .no-media { color: #bbb; }
        body.light .no-media { color: #666; }
        .modal-message { margin-top: 20px; white-space: pre-wrap; text-align: left; max-height: 200px; overflow-y: auto; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; top: 15px; right: 15px; z-index: 3; color: inherit; transition: color 0.3s; }
        body.dark .close { color: #fff; }
        body.light .close { color: #000; }
        .close:hover, .close:focus { color: #ddd; text-decoration: none; }
        
        /* Arrow styles */
        .arrow { position: absolute; top: 50%; transform: translateY(-50%); font-size: 40px; cursor: pointer; background-color: rgba(0,0,0,0.5); padding: 10px; z-index: 10; transition: color 0.3s; }
        body.dark .arrow { color: #fff; }
        body.light .arrow { color: #000; background-color: rgba(255,255,255,0.5); }
        .arrow:hover { background-color: rgba(0,0,0,0.8); }
        body.light .arrow:hover { background-color: rgba(255,255,255,0.8); }
        .prev { left: 10px; }
        .next { right: 10px; }

        /* Loading spinner styles */
        .loading-spinner { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #ffffff; border-radius: 50%; animation: spin 1s linear infinite; z-index: 2; }
        body.light .loading-spinner { border: 4px solid rgba(0,0,0,0.1); border-top-color: #000000; }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Theme toggle button */
        #themeToggle { position: fixed; top: 10px; right: 10px; padding: 10px; border: none; border-radius: 5px; cursor: pointer; z-index: 1000; transition: background-color 0.3s, color 0.3s; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        body.dark #themeToggle { background-color: #333; color: #fff; }
        body.light #themeToggle { background-color: #ddd; color: #000; }
        .theme-icon { width: 24px; height: 24px; fill: currentColor; }

        /* Sentinel for infinite scroll */
        .sentinel { height: 20px; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .tile { width: 200px; }
            .tile img, .tile .placeholder { height: 120px; }
            .modal-content { width: 95%; padding: 50px 10px 10px 10px; }
            .arrow { display: none !important; }
            .modal-content { overflow-y: auto; text-align: left; }
            #modalMobileView { overflow-y: auto; height: auto; }
            .post-item { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 20px; }
            body.dark .post-item { border-bottom: 1px solid #333; }
            .post-media img, .post-media video { max-width: 100%; height: auto; }
            .post-message { white-space: pre-wrap; }
            .close { top: 0; }
        }

        @media (max-width: 480px) {
            .tile img, .tile .placeholder { height: 250px; }
            .tile .tile-message { font-size: 14px; }
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
    
    <div id="campaigns-container">
    <?php foreach ($campaignsData as $campaignId => $campaign): ?>
        <?php if (isset($campaign['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($campaign['error']); ?></p>
        <?php else: ?>
            <?php $messages = $campaign['messages']; $title = $campaign['title']; ?>
            <?php if (empty($messages) || !is_array($messages)): ?>
                <p class="error">No messages found for channel <?php echo $campaignId; ?> or invalid response format.</p>
            <?php else: ?>
                <h2><?php echo htmlspecialchars($title); ?></h2>
                <div class="tile-column" data-campaign-id="<?php echo $campaignId; ?>" data-current-page="1" data-total-messages="<?php echo $campaign['total']; ?>">
                    <?php foreach ($messages as $localIndex => $message): ?>
                        <div class="tile" data-index="<?php echo $localIndex; ?>" data-campaign="<?php echo $campaignId; ?>">
                            <?php if (!empty($message['media']) && is_array($message['media'])): ?>
                                <img src="<?php echo htmlspecialchars($message['media'][0]['thumbnail'] ?? ''); ?>" alt="Thumbnail">
                            <?php else: ?>
                                <div class="placeholder">No Media</div>
                            <?php endif; ?>
                            <div class="tile-content">
                                <p class="tile-message"><?php echo htmlspecialchars(substr($message['message'] ?? 'N/A', 0, 50)) . '...'; ?></p>
                                <p class="tile-scheduled" data-utc="<?php echo htmlspecialchars($message['scheduled_at'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($message['scheduled_at'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="sentinel"></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <!-- Modal structure -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalDesktopView" class="media-wrapper">
                <img id="modalImg" class="modal-img" src="" alt="Full Image">
                <video id="modalVideo" class="modal-video" controls></video>
                <span id="prevArrow" class="arrow prev">&#10094;</span>
                <span id="nextArrow" class="arrow next">&#10095;</span>
            </div>
            <div class="loading-spinner"></div>
            <p id="noMedia" class="no-media">No media available</p>
            <p id="modalMessage" class="modal-message"></p>
        </div>
    </div>

    <script>
        // Convert UTC dates to local time zone
        function updateDates() {
            document.querySelectorAll('.tile-scheduled').forEach(elem => {
                const utcDate = elem.dataset.utc;
                if (utcDate && utcDate !== 'N/A') {
                    const localDate = new Date(utcDate).toLocaleString();
                    elem.textContent = localDate;
                }
            });
        }
        updateDates();

        // Theme toggle logic
        const body = document.body;
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');
        let currentTheme = localStorage.getItem('theme') || 'dark';
        body.classList.add(currentTheme);
        sunIcon.style.display = currentTheme === 'dark' ? 'block' : 'none';
        moonIcon.style.display = currentTheme === 'dark' ? 'none' : 'block';

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            body.classList.toggle('light');
            currentTheme = body.classList.contains('dark') ? 'dark' : 'light';
            sunIcon.style.display = currentTheme === 'dark' ? 'block' : 'none';
            moonIcon.style.display = currentTheme === 'dark' ? 'none' : 'block';
            localStorage.setItem('theme', currentTheme);
            console.log('Theme toggled to:', currentTheme);
        });

        // Modal elements
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

        let campaignsSlides = <?php echo json_encode(array_map(function($c) { return isset($c['messages']) ? $c['messages'] : []; }, $campaignsData)); ?>;
        let currentIndex = 0;
        let touchStartX = 0;
        let touchEndX = 0;

        // Function to show slide at given index with fade
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
                    if (mediaItem.mime_type && mediaItem.mime_type.startsWith('video/')) {
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

        // Add click events to tiles
        function addTileEventListeners() {
            document.querySelectorAll('.tile').forEach(tile => {
                tile.removeEventListener('click', tileClickHandler);
                tile.addEventListener('click', tileClickHandler);
            });
        }

        function tileClickHandler() {
            const campaignId = this.dataset.campaign;
            currentIndex = parseInt(this.dataset.index);
            allSlides = campaignsSlides[campaignId] || [];
            console.log('Tile clicked:', { campaignId, currentIndex, allSlides });
            if (allSlides.length === 0) return;
            modal.style.display = 'flex';
            themeToggle.style.display = 'none';
            mediaWrapper.style.display = 'inline-block';
            modalMessage.style.display = 'block';
            noMediaElem.style.display = 'block';
            showSlide(currentIndex);
            if (allSlides.length > 1) {
                prevArrow.style.display = 'block';
                nextArrow.style.display = 'block';
            } else {
                prevArrow.style.display = 'none';
                nextArrow.style.display = 'none';
            }
        }

        // Function to load more for a campaign
        function loadMore(campaignId, nextPage) {
            fetch(`?action=load_more&campaign_id=${campaignId}&page=${nextPage}&limit=10&order=desc`, {
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('Error loading more:', data.error);
                    return;
                }
                const messages = data.messages;
                const total = data.total;
                const column = document.querySelector(`.tile-column[data-campaign-id="${campaignId}"]`);
                const sentinel = column.querySelector('.sentinel');
                const baseIndex = parseInt(column.dataset.totalMessages || '0');

                if (messages.length === 0) {
                    observer.unobserve(sentinel);
                    return;
                }

                const newTilesHtml = messages.map((message, idx) => `
                    <div class="tile" data-index="${baseIndex + idx}" data-campaign="${campaignId}">
                        ${message.media && message.media.length > 0 ? `<img src="${message.media[0].thumbnail || ''}" alt="Thumbnail">` : `<div class="placeholder">No Media</div>`}
                        <div class="tile-content">
                            <p class="tile-message">${message.message ? message.message.substring(0, 50) + '...' : 'N/A'}</p>
                            <p class="tile-scheduled" data-utc="${message.scheduled_at || 'N/A'}">${message.scheduled_at || 'N/A'}</p>
                        </div>
                    </div>
                `).join('');

                sentinel.insertAdjacentHTML('beforebegin', newTilesHtml);

                campaignsSlides[campaignId].push(...messages);
                column.dataset.totalMessages = parseInt(column.dataset.totalMessages || '0') + messages.length;
                column.dataset.currentPage = nextPage;

                updateDates();
                addTileEventListeners();

                if (total < 10) {
                    observer.unobserve(sentinel);
                } else {
                    observer.observe(sentinel);
                }
            })
            .catch(error => {
                console.error('Load more error:', error);
            });
        }

        // Function to update with new posts from refresh
        function updateNewPosts(campaignsData) {
            Object.keys(campaignsData).forEach(campaignId => {
                const newMessages = campaignsData[campaignId].messages || [];
                const currentSlides = campaignsSlides[campaignId] || [];
                if (currentSlides.length === 0) return;

                let addThese = [];
                const existingFirst = currentSlides[0];

                for (let msg of newMessages) {
                    if (msg.scheduled_at === existingFirst.scheduled_at && msg.message === existingFirst.message) {
                        break;
                    }
                    addThese.push(msg);
                }

                if (addThese.length > 0) {
                    campaignsSlides[campaignId].unshift(...addThese);

                    const column = document.querySelector(`.tile-column[data-campaign-id="${campaignId}"]`);

                    const newTilesHtml = addThese.map((msg, idx) => `
                        <div class="tile" data-index="${idx}" data-campaign="${campaignId}">
                            ${msg.media && msg.media.length > 0 ? `<img src="${msg.media[0].thumbnail || ''}" alt="Thumbnail">` : `<div class="placeholder">No Media</div>`}
                            <div class="tile-content">
                                <p class="tile-message">${msg.message ? msg.message.substring(0, 50) + '...' : 'N/A'}</p>
                                <p class="tile-scheduled" data-utc="${msg.scheduled_at || 'N/A'}">${msg.scheduled_at || 'N/A'}</p>
                            </div>
                        </div>
                    `).join('');

                    column.insertAdjacentHTML('afterbegin', newTilesHtml);

                    const tiles = column.querySelectorAll('.tile');
                    tiles.forEach((tile, i) => {
                        tile.dataset.index = i;
                    });

                    column.dataset.totalMessages = parseInt(column.dataset.totalMessages || '0') + addThese.length;

                    addTileEventListeners();
                    updateDates();
                }
            });
        }

        // Fetch new data periodically for new posts
        function fetchNewCampaignData() {
            console.log('Fetching new campaign data at', new Date().toLocaleString());
            fetch('?action=fetch_campaigns', {
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                console.log('Fetch new response:', data);
                updateNewPosts(data);
            })
            .catch(error => {
                console.error('Fetch new error:', error);
            });
        }

        // Intersection Observer for infinite scroll
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const column = entry.target.parentElement;
                    const campaignId = column.dataset.campaignId;
                    const currentPage = parseInt(column.dataset.currentPage || '1');
                    loadMore(campaignId, currentPage + 1);
                }
            });
        }, { threshold: 0.1 });

        // Set up
        document.addEventListener('DOMContentLoaded', () => {
            addTileEventListeners();
            const sentinels = document.querySelectorAll('.sentinel');
            sentinels.forEach(sentinel => {
                observer.observe(sentinel);
            });
            fetchNewCampaignData(); // Initial new fetch
        });

        // Set interval for periodic refresh
        setInterval(fetchNewCampaignData, 300000);

        // Previous arrow click
        prevArrow.addEventListener('click', () => {
            showSlide(currentIndex - 1);
        });

        // Next arrow click
        nextArrow.addEventListener('click', () => {
            showSlide(currentIndex + 1);
        });

        // Swipe support for modal
        modal.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });

        modal.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchStartX - touchEndX > 50) {
                showSlide(currentIndex + 1);
            } else if (touchEndX - touchStartX > 50) {
                showSlide(currentIndex - 1);
            }
        });

        // Close modal
        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            themeToggle.style.display = 'block';
            modalVideo.pause();
        });
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
                themeToggle.style.display = 'block';
                modalVideo.pause();
            }
        });
    </script>
</body>
</html>
