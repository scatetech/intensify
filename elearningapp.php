//
//  elearningapp.php
//  IntensifyDigital JSON Elearning Player  
//
//  Created by Stephen Sadler on 7/28/25.
//
<?php
// Define the campaigns with their IDs, corresponding bearer tokens, and custom titles
$campaigns = [
    ['id' => {campaignId}, 'token' => '{bearerToken}', 'title' => 'Prayers Before Bedtime'],
    ['id' => {campaignId}, 'token' => '{bearerToken}', 'title' => "Hero's Journey The Series"],
];
// Initialize an array to hold data for each campaign
$campaignsData = [];
// Loop through each campaign and fetch data
foreach ($campaigns as $campaignInfo) {
    $campaignId = $campaignInfo['id'];
    $bearerToken = $campaignInfo['token'];
    $campaignTitle = $campaignInfo['title'];
    // Initialize cURL session
    $ch = curl_init();
    // Set the URL for the API endpoint
    $url = "https://portal.intensifydigital.com/api/campaign/{$campaignId}/messages/sent";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer {$bearerToken}",
        "Content-Type: application/json"
    ));
    // Execute the cURL session and store the response
    $response = curl_exec($ch);
    // Check for cURL errors
    if (curl_errno($ch)) {
        $campaignsData[$campaignId] = ['messages' => [], 'title' => $campaignTitle, 'error' => 'cURL Error: ' . curl_error($ch)];
        curl_close($ch);
        continue;
    }
    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // Decode the response
    $data = json_decode($response, true);
    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        $campaignsData[$campaignId] = ['messages' => [], 'title' => $campaignTitle, 'error' => 'JSON Decode Error: ' . json_last_error_msg()];
        continue;
    }
    // Check if response is valid
    if ($httpCode !== 200 || !is_array($data)) {
        $campaignsData[$campaignId] = ['messages' => [], 'title' => $campaignTitle, 'error' => 'Invalid API response (HTTP ' . $httpCode . ')'];
        continue;
    }
    // Sort this campaign's data by scheduled_at descending (newest first)
    usort($data, function($a, $b) {
        return strtotime($b['scheduled_at'] ?? '1970-01-01') - strtotime($a['scheduled_at'] ?? '1970-01-01');
    });
    // Generate unique slugs for messages
    $slugs = [];
    foreach ($data as &$m) {
        $baseSlug = preg_replace('/[^a-z0-9 ]/i', '', substr($m['message'] ?? 'N/A', 0, 50));
        $baseSlug = trim(strtolower(str_replace(' ', '-', $baseSlug)), '-');
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
    // Store the data and title
    $campaignsData[$campaignId] = ['messages' => $data, 'title' => $campaignTitle, 'error' => null];
}
// Handle single post view if ?post=slug is in URL
$singlePost = null;
if (isset($_GET['post'])) {
    $requestedSlug = $_GET['post'];
    foreach ($campaignsData as $campaignId => $campaign) {
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
                #themeToggle { position: fixed; top: 10px; right: 10px; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; z-index: 1000; transition: background-color 0.3s, color 0.3s; }
                body.dark #themeToggle { background-color: #333; color: #fff; }
                body.light #themeToggle { background-color: #ddd; color: #000; }
                .theme-icon { width: 24px; height: 24px; }
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
    <title>Intensify TV Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; transition: background-color 0.3s, color 0.3s; }
        body.dark { background-color: #121212; color: #ffffff; }
        body.light { background-color: #f4f4f4; color: #000000; }
        .container { display: flex; height: 100vh; }
        .sidebar { width: 250px; height: 100%; overflow-y: auto; padding: 20px; position: fixed; left: 0; top: 0; transition: width 0.3s; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 5; }
        body.dark .sidebar { background: #1e1e1e; }
        body.light .sidebar { background: white; }
        .sidebar.collapsed { width: 0; padding: 0; overflow: hidden; }
        .sidebar details { margin-bottom: 20px; }
        .sidebar summary { font-weight: bold; cursor: pointer; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar li { display: flex; flex-direction: column; align-items: center; margin-bottom: 10px; cursor: pointer; padding: 10px; border-radius: 4px; transition: background-color 0.3s; text-align: center; }
        .sidebar li:hover { background-color: rgba(0,0,0,0.1); }
        body.dark .sidebar li:hover { background-color: rgba(255,255,255,0.1); }
        .sidebar .thumb { width: calc(100% - 20px); height: auto; object-fit: cover; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
        body.dark .sidebar .thumb { border-color: #333; }
        .sidebar .placeholder { width: calc(100% - 20px); height: 100px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; font-size: 12px; transition: background-color 0.3s, color 0.3s; border: 1px solid #ddd; border-radius: 4px; }
        body.dark .sidebar .placeholder { background: #333; color: #bbb; border-color: #333; }
        body.light .sidebar .placeholder { background: #ddd; color: #666; }
        .sidebar span { font-size: 14px; white-space: normal; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        #toggleSidebar { position: fixed; top: 10px; left: 250px; padding: 5px 10px; border: none; border-radius: 0 5px 5px 0; cursor: pointer; transition: background-color 0.3s, color 0.3s, left 0.3s; z-index: 1001; }
        .sidebar.collapsed + #toggleSidebar { left: 0; border-radius: 5px 0 0 5px; }
        body.dark #toggleSidebar { background-color: #333; color: #fff; }
        body.light #toggleSidebar { background-color: #ddd; color: #000; }
        .main { flex: 1; margin-left: 250px; padding: 20px; transition: margin-left 0.3s; display: flex; flex-direction: column; align-items: center; }
        .main.expanded { margin-left: 0; }
        .media-container { display: flex; align-items: center; justify-content: center; position: relative; width: 100%; max-width: 800px; margin-bottom: 20px; }
        .media-wrapper { position: relative; width: 100%; max-width: 800px; z-index: 1; }
        #mainImg, #mainVideo { max-width: 100%; height: auto; display: none; position: relative; z-index: 1; opacity: 1; transition: opacity 0.3s ease; }
        #noMedia { margin: 20px 0; font-size: 18px; display: block; }
        body.dark #noMedia { color: #bbb; }
        body.light #noMedia { color: #666; }
        .message-scroll { width: 100%; max-width: 800px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; text-align: left; padding: 10px; border-radius: 8px; transition: background-color 0.3s; z-index: 1; }
        body.dark .message-scroll { background-color: #1e1e1e; }
        body.light .message-scroll { background-color: #fefefe; }
        .arrow { font-size: 40px; cursor: pointer; background-color: rgba(0,0,0,0.5); padding: 10px; z-index: 10; transition: opacity 0.3s, color 0.3s; opacity: 0; margin: 0 10px; }
        body.dark .arrow { color: #fff; }
        body.light .arrow { color: #000; background-color: rgba(255,255,255,0.5); }
        .arrow:hover { background-color: rgba(0,0,0,0.8); }
        body.light .arrow:hover { background-color: rgba(255,255,255,0.8); }
        .media-container:hover .arrow { opacity: 1; }
        .prev { order: -1; }
        .next { order: 1; }
        .loading-spinner { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #ffffff; border-radius: 50%; animation: spin 1s linear infinite; z-index: 2; }
        body.light .loading-spinner { border: 4px solid rgba(0,0,0,0.1); border-top-color: #000000; }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        #themeToggle { position: fixed; top: 10px; right: 10px; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; z-index: 1000; transition: background-color 0.3s, color 0.3s; }
        body.dark #themeToggle { background-color: #333; color: #fff; }
        body.light #themeToggle { background-color: #ddd; color: #000; }
        .theme-icon { width: 24px; height: 24px; }
        .error { color: red; text-align: center; margin: 20px; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main { margin-left: 200px; }
            .sidebar.collapsed + #toggleSidebar { left: 0; }
            .arrow { display: none; }
            #toggleSidebar { left: 200px; border-radius: 0 5px 5px 0; }
        }
        @media (max-width: 480px) {
            .sidebar { width: 150px; }
            .main { margin-left: 150px; }
            .sidebar .thumb { height: auto; }
            .sidebar .placeholder { height: 80px; }
            .sidebar span { font-size: 12px; }
            #toggleSidebar { left: 150px; border-radius: 0 5px 5px 0; }
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
    <div class="container">
        <div id="sidebar" class="sidebar">
            <?php 
            $hasContent = false;
            foreach ($campaignsData as $campaignId => $campaign): 
                if (!empty($campaign['messages']) && is_array($campaign['messages'])) {
                    $hasContent = true;
                }
            ?>
                <?php if (!empty($campaign['error'])): ?>
                    <p class="error"><?php echo htmlspecialchars($campaign['error']); ?></p>
                <?php elseif (!empty($campaign['messages']) && is_array($campaign['messages'])): ?>
                    <details open>
                        <summary><?php echo htmlspecialchars($campaign['title']); ?></summary>
                        <ul>
                            <?php foreach ($campaign['messages'] as $localIndex => $message): ?>
                                <li data-index="<?php echo $localIndex; ?>" data-campaign="<?php echo $campaignId; ?>">
                                    <?php if (!empty($message['media']) && is_array($message['media'])): ?>
                                        <img src="<?php echo htmlspecialchars($message['media'][0]['thumbnail'] ?? ''); ?>" alt="Thumbnail" class="thumb">
                                    <?php else: ?>
                                        <div class="placeholder">No Media</div>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars(substr($message['message'] ?? 'N/A', 0, 60)) . '...'; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$hasContent): ?>
                <p class="error">No content available. Please check the API or campaign data.</p>
            <?php endif; ?>
        </div>
        <button id="toggleSidebar">&lt;</button>
        <div id="main" class="main">
            <div class="media-container">
                <span id="prevArrow" class="arrow prev">&#10094;</span>
                <div class="media-wrapper">
                    <img id="mainImg" class="modal-img" src="" alt="Full Image">
                    <video id="mainVideo" class="modal-video" controls></video>
                    <div class="loading-spinner"></div>
                    <p id="noMedia" class="no-media">Select a slide to view</p>
                </div>
                <span id="nextArrow" class="arrow next">&#10095;</span>
            </div>
            <div id="mainMessage" class="message-scroll"></div>
        </div>
    </div>
    <script>
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

        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        const toggleSidebar = document.getElementById('toggleSidebar');
        toggleSidebar.addEventListener('click', () => {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                main.classList.remove('expanded');
                toggleSidebar.textContent = '<';
                toggleSidebar.style.left = window.innerWidth <= 480 ? '150px' : (window.innerWidth <= 768 ? '200px' : '250px');
                toggleSidebar.style.borderRadius = '0 5px 5px 0';
            } else {
                sidebar.classList.add('collapsed');
                main.classList.add('expanded');
                toggleSidebar.textContent = '>';
                toggleSidebar.style.left = '0';
                toggleSidebar.style.borderRadius = '5px 0 0 5px';
            }
        });

        // Set initial sidebar state and toggle button position
        window.addEventListener('load', () => {
            // Ensure toggle button is positioned correctly based on screen size
            toggleSidebar.textContent = '<';
            toggleSidebar.style.left = window.innerWidth <= 480 ? '150px' : (window.innerWidth <= 768 ? '200px' : '250px');
            toggleSidebar.style.borderRadius = '0 5px 5px 0';
        });

        // Slide elements
        const mainImg = document.getElementById('mainImg');
        const mainVideo = document.getElementById('mainVideo');
        const noMediaElem = document.getElementById('noMedia');
        const mainMessage = document.getElementById('mainMessage');
        const prevArrow = document.getElementById('prevArrow');
        const nextArrow = document.getElementById('nextArrow');
        const mediaContainer = document.querySelector('.media-container');
        const loadingSpinner = document.querySelector('.loading-spinner');
        let campaignsSlides = <?php echo json_encode(array_map(function($c) { return $c['messages']; }, $campaignsData)); ?>; // Messages per campaign
        let allSlides = [];
        let currentIndex = 0;
        let touchStartX = 0;
        let touchEndX = 0;
        let arrowTimeout;

        // Function to show slide at given index with fade
        function showSlide(index, campaignId) {
            if (!allSlides || allSlides.length === 0) {
                noMediaElem.style.display = 'block';
                mainMessage.textContent = 'No slides available';
                prevArrow.style.display = 'none';
                nextArrow.style.display = 'none';
                return;
            }
            // Fade out current media
            const currentMedia = mainImg.style.display === 'block' ? mainImg : (mainVideo.style.display === 'block' ? mainVideo : null);
            if (currentMedia) {
                currentMedia.style.opacity = '0';
            }
            setTimeout(() => {
                currentIndex = (index + allSlides.length) % allSlides.length;
                const slide = allSlides[currentIndex];
                const postUrl = window.location.origin + window.location.pathname + '?post=' + slide.slug;
                mainMessage.innerHTML = '<a href="' + postUrl + '" style="text-decoration: none; color: inherit;">' + (slide.message || 'N/A') + '</a>';
                mainImg.style.display = 'none';
                mainVideo.style.display = 'none';
                noMediaElem.style.display = 'none';
                loadingSpinner.style.display = 'none';
                mainVideo.pause();
                if (slide.media && slide.media.length > 0) {
                    const mediaItem = slide.media[0];
                    loadingSpinner.style.display = 'block';
                    let mediaElement;
                    if (mediaItem.mime_type && mediaItem.mime_type.startsWith('video/')) {
                        mediaElement = mainVideo;
                        mediaElement.src = mediaItem.location;
                        mediaElement.addEventListener('loadeddata', () => {
                            loadingSpinner.style.display = 'none';
                            mediaElement.style.display = 'block';
                            mediaElement.style.opacity = '0';
                            setTimeout(() => { 
                                mediaElement.style.opacity = '1';
                                if (allSlides.length > 1 && window.innerWidth > 768) {
                                    prevArrow.style.opacity = '1';
                                    nextArrow.style.opacity = '1';
                                    startArrowTimeout();
                                }
                            }, 10);
                        }, {once: true});
                    } else {
                        mediaElement = mainImg;
                        mediaElement.src = mediaItem.location;
                        mediaElement.addEventListener('load', () => {
                            loadingSpinner.style.display = 'none';
                            mediaElement.style.display = 'block';
                            mediaElement.style.opacity = '0';
                            setTimeout(() => { 
                                mediaElement.style.opacity = '1';
                                if (allSlides.length > 1 && window.innerWidth > 768) {
                                    prevArrow.style.opacity = '1';
                                    nextArrow.style.opacity = '1';
                                    startArrowTimeout();
                                }
                            }, 10);
                        }, {once: true});
                    }
                } else {
                    noMediaElem.style.display = 'block';
                    prevArrow.style.opacity = '0';
                    nextArrow.style.opacity = '0';
                }
                // Store the current slide's campaign and index in localStorage
                localStorage.setItem('lastCampaign', campaignId);
                localStorage.setItem('lastIndex', currentIndex);
            }, 300); // Fade out duration
        }

        // Function to start the arrow hide timeout
        function startArrowTimeout() {
            clearTimeout(arrowTimeout);
            arrowTimeout = setTimeout(() => {
                prevArrow.style.opacity = '0';
                nextArrow.style.opacity = '0';
            }, 2000);
        }

        // Load initial slide or last viewed slide
        window.addEventListener('load', () => {
            // Ensure DOM is fully loaded before proceeding
            document.addEventListener('DOMContentLoaded', () => {
                const lastCampaign = localStorage.getItem('lastCampaign');
                const lastIndex = parseInt(localStorage.getItem('lastIndex')) || 0;
                let initialCampaignId = Object.keys(campaignsSlides)[0]; // Default to first campaign
                let initialIndex = 0;

                // Check if there is valid campaign data
                if (!initialCampaignId || !campaignsSlides[initialCampaignId] || campaignsSlides[initialCampaignId].length === 0) {
                    noMediaElem.style.display = 'block';
                    mainMessage.textContent = 'No slides available';
                    prevArrow.style.display = 'none';
                    nextArrow.style.display = 'none';
                    return;
                }

                if (lastCampaign && campaignsSlides[lastCampaign] && campaignsSlides[lastCampaign].length > 0) {
                    initialCampaignId = lastCampaign;
                    initialIndex = lastIndex >= 0 && lastIndex < campaignsSlides[initialCampaignId].length ? lastIndex : 0;
                }

                allSlides = campaignsSlides[initialCampaignId];
                currentIndex = initialIndex;

                // Simulate sidebar click behavior by finding the corresponding sidebar item
                const sidebarItem = document.querySelector(`.sidebar li[data-campaign="${initialCampaignId}"][data-index="${initialIndex}"]`);
                if (sidebarItem) {
                    // Trigger the click event to ensure consistent behavior
                    sidebarItem.click();
                } else {
                    // Fallback to showSlide if no sidebar item is found
                    showSlide(currentIndex, initialCampaignId);
                }
            }, { once: true });
        });

        // Add click events to sidebar items
        document.querySelectorAll('.sidebar li').forEach(item => {
            item.addEventListener('click', function() {
                const campaignId = this.dataset.campaign;
                if (!campaignsSlides[campaignId] || campaignsSlides[campaignId].length === 0) return;
                currentIndex = parseInt(this.dataset.index);
                allSlides = campaignsSlides[campaignId];
                showSlide(currentIndex, campaignId);
            });
        });

        // Previous arrow click
        prevArrow.addEventListener('click', function() {
            if (allSlides.length === 0) return;
            const campaignId = Object.keys(campaignsSlides).find(key => campaignsSlides[key] === allSlides) || Object.keys(campaignsSlides)[0];
            showSlide(currentIndex - 1, campaignId);
        });

        // Next arrow click
        nextArrow.addEventListener('click', function() {
            if (allSlides.length === 0) return;
            const campaignId = Object.keys(campaignsSlides).find(key => campaignsSlides[key] === allSlides) || Object.keys(campaignsSlides)[0];
            showSlide(currentIndex + 1, campaignId);
        });

        // Mouse movement to show arrows
        mediaContainer.addEventListener('mousemove', () => {
            if (allSlides.length > 1 && window.innerWidth > 768) {
                prevArrow.style.opacity = '1';
                nextArrow.style.opacity = '1';
                startArrowTimeout();
            }
        });

        // Swipe support for main area
        main.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        main.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            if (allSlides.length === 0) return;
            const campaignId = Object.keys(campaignsSlides).find(key => campaignsSlides[key] === allSlides) || Object.keys(campaignsSlides)[0];
            if (touchStartX - touchEndX > 50) {
                // Swipe left -> next
                showSlide(currentIndex + 1, campaignId);
            } else if (touchEndX - touchStartX > 50) {
                // Swipe right -> prev
                showSlide(currentIndex - 1, campaignId);
            }
        });
    </script>
</body>
</html>
