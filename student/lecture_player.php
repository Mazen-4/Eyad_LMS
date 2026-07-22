<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student']);
$conn = getDbConnection();

$studentGroupIds = array_values(array_unique(array_filter(array_map('intval', $user['group_ids'] ?? []))));
if (empty($studentGroupIds) && !empty($user['group_id'])) {
    $studentGroupIds = [(int)$user['group_id']];
}
$lectureId = (int)($_GET['lecture_id'] ?? 0);
$error = '';
$lecture = null;
$sourceType = 'empty';
$sourceUrl = '';

function normalizeDriveLinkUrl($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        if (preg_match('#/file/d/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#/drive/u/\d+/view\?usp=sharing&id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#[?&]id=([^&#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal';
        }

        if (preg_match('#/drive/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        if (preg_match('#/folders/([^/?#]+)#i', $value, $matches)) {
            return 'https://drive.google.com/drive/folders/' . $matches[1];
        }

        return $value;
    }

    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value)) {
        return 'https://drive.google.com/drive/folders/' . $value;
    }

    return $value;
}

function resolveLectureSource($value)
{
    $normalizedValue = normalizeDriveLinkUrl($value);
    if ($normalizedValue === '') {
        return ['type' => 'empty', 'url' => ''];
    }

    if (preg_match('/\.(mp4|webm|ogg|m4v|mov)(\?.*)?$/i', $normalizedValue)) {
        return ['type' => 'video', 'url' => $normalizedValue];
    }

    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/i', $normalizedValue, $matches)) {
        return ['type' => 'youtube', 'url' => 'https://www.youtube.com/embed/' . $matches[1]];
    }

    if (preg_match('#/file/d/([^/?#]+)#i', $normalizedValue, $matches)) {
        return ['type' => 'drive_file', 'url' => 'https://drive.google.com/file/d/' . $matches[1] . '/preview?rm=minimal'];
    }

    if (preg_match('#/drive/folders/([^/?#]+)#i', $normalizedValue, $matches)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $matches[1]];
    }

    if (preg_match('#/folders/([^/?#]+)#i', $normalizedValue, $matches)) {
        return ['type' => 'folder', 'url' => 'https://drive.google.com/drive/folders/' . $matches[1]];
    }

    if (preg_match('#^https?://#i', $normalizedValue)) {
        if (stripos($normalizedValue, 'drive.google.com') !== false) {
            return ['type' => 'drive_file', 'url' => $normalizedValue];
        }

        return ['type' => 'link', 'url' => $normalizedValue];
    }

    return ['type' => 'unsupported', 'url' => $normalizedValue];
}

function isVideoSourceUrl($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return false;
    }

    return preg_match('/\.(mp4|webm|ogg|m4v|mov)(\?.*)?$/i', $value) === 1;
}

function buildVideoProxyUrl($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    return 'proxy_media.php?url=' . rawurlencode($value);
}

if ($lectureId > 0 && !empty($studentGroupIds)) {
    $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
    $lectureStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.id = ? AND l.status = "active" AND lfa.group_id IN (' . $placeholders . ') LIMIT 1');
    $params = array_merge([(int)$lectureId], $studentGroupIds);
    bindPreparedParams($lectureStmt, $params);
    $lectureStmt->execute();
    $lecture = $lectureStmt->get_result()->fetch_assoc();
}

if (!$lecture) {
    $error = 'This session is not available for your group.';
} else {
    $lectureSource = resolveLectureSource($lecture['drive_folder_id'] ?? '');
    $sourceType = $lectureSource['type'];
    $sourceUrl = $lectureSource['url'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Player</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        .player-frame {
            width: 100%;
            min-height: 56vh;
            max-height: 75vh;
            border: 0;
            border-radius: 0.5rem;
            background: #000;
            display: block;
        }

        .security-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.97);
            color: #fff;
            z-index: 2147483647;
            pointer-events: none;
            text-align: center;
            padding: 1.5rem;
        }

        .security-overlay__message {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .player-frame {
                min-height: 45vh;
                max-height: 60vh;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/student_nav.php'; ?>

    <div id="security-overlay" class="security-overlay" aria-hidden="true">
        <div class="security-overlay__message">Recording not allowed</div>
    </div>

    <script>
        let overlayHideTimer = null;
        let activeSecurityContext = {
            doc: document,
            win: window,
            overlay: null
        };

        function showOverlay() {
            const overlay = activeSecurityContext.overlay || document.getElementById('security-overlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
        }

        function hideOverlay() {
            const overlay = activeSecurityContext.overlay || document.getElementById('security-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }

            if (overlayHideTimer) {
                clearTimeout(overlayHideTimer);
                overlayHideTimer = null;
            }
        }

        function initSecurityModule() {
            let cleanupHandlers = [];
            let devtoolsOpen = false;
            let lastWindowWidth = window.innerWidth;
            let lastWindowHeight = window.innerHeight;

            function ensureOverlay() {
                let overlay = document.getElementById('security-overlay');

                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.id = 'security-overlay';
                    overlay.setAttribute('aria-hidden', 'true');

                    const message = document.createElement('div');
                    message.className = 'security-overlay__message';
                    message.textContent = 'Recording not allowed';
                    overlay.appendChild(message);

                    if (document.body) {
                        document.body.appendChild(overlay);
                    } else {
                        document.documentElement.appendChild(overlay);
                    }

                    overlay.style.position = 'fixed';
                    overlay.style.inset = '0';
                    overlay.style.display = 'none';
                    overlay.style.alignItems = 'center';
                    overlay.style.justifyContent = 'center';
                    overlay.style.background = 'rgba(0, 0, 0, 0.97)';
                    overlay.style.color = '#fff';
                    overlay.style.zIndex = '2147483647';
                    overlay.style.pointerEvents = 'none';
                    overlay.style.textAlign = 'center';
                    overlay.style.padding = '1.5rem';
                    message.style.fontSize = '1.5rem';
                    message.style.fontWeight = '600';
                    message.style.letterSpacing = '0.02em';
                    message.style.textTransform = 'uppercase';
                }

                return overlay;
            }

            function detachSignals() {
                cleanupHandlers.forEach(function (entry) {
                    entry.target.removeEventListener(entry.type, entry.handler, entry.options);
                });
                cleanupHandlers = [];
            }

            function attachSignals(doc, win) {
                const overlay = ensureOverlay();
                activeSecurityContext = { doc: doc, win: win, overlay: overlay };

                // Signal 1: Page visibility
                const visibilityHandler = function () {
                    if (doc.visibilityState === 'hidden') {
                        showOverlay();
                    } else {
                        hideOverlay();
                    }
                };
                doc.addEventListener('visibilitychange', visibilityHandler, false);
                cleanupHandlers.push({ target: doc, type: 'visibilitychange', handler: visibilityHandler, options: false });

                // Signal 2: Window blur/focus
                const blurHandler = function () {
                    showOverlay();
                };
                win.addEventListener('blur', blurHandler, false);
                cleanupHandlers.push({ target: win, type: 'blur', handler: blurHandler, options: false });

                const focusHandler = function () {
                    hideOverlay();
                };
                win.addEventListener('focus', focusHandler, false);
                cleanupHandlers.push({ target: win, type: 'focus', handler: focusHandler, options: false });

                // Signal 3: Keyboard shortcuts
                const keydownHandler = function (event) {
                    const key = event.key;
                    const isPrintScreen = key === 'PrintScreen' || key === 'F13';
                    const isScreenshotShortcut = (event.key === '3' && event.metaKey && event.shiftKey) ||
                        (event.key === '4' && event.metaKey && event.shiftKey) ||
                        (event.key === 's' && event.metaKey && event.shiftKey) ||
                        (event.key === 'S' && event.metaKey && event.shiftKey) ||
                        (event.key === 's' && event.ctrlKey && event.shiftKey) ||
                        (event.key === 'S' && event.ctrlKey && event.shiftKey) ||
                        (event.key === 'p' && event.ctrlKey) ||
                        (event.key === 'P' && event.ctrlKey);

                    if (isPrintScreen || isScreenshotShortcut) {
                        event.preventDefault();
                        showOverlay();

                        if (overlayHideTimer) {
                            clearTimeout(overlayHideTimer);
                        }

                        overlayHideTimer = setTimeout(function () {
                            hideOverlay();
                        }, 3000);
                    }
                };
                doc.addEventListener('keydown', keydownHandler, true);
                cleanupHandlers.push({ target: doc, type: 'keydown', handler: keydownHandler, options: true });

                // Signal 4: Right-click blocking
                const contextmenuHandler = function (event) {
                    event.preventDefault();
                    showOverlay();
                };
                doc.addEventListener('contextmenu', contextmenuHandler, true);
                cleanupHandlers.push({ target: doc, type: 'contextmenu', handler: contextmenuHandler, options: true });

                // Signal 4b: Embedded player interaction
                const playerElements = Array.from(doc.querySelectorAll('video, iframe, .player-frame'));
                playerElements.forEach(function (element) {
                    ['touchstart', 'pointerdown', 'focus', 'keydown'].forEach(function (eventName) {
                        const playerHandler = function () {
                            showOverlay();
                        };
                        element.addEventListener(eventName, playerHandler, true);
                        cleanupHandlers.push({ target: element, type: eventName, handler: playerHandler, options: true });
                    });

                    const clickHandler = function (event) {
                        if (event.button === 0) {
                            event.stopPropagation();
                            showOverlay();
                            if (overlayHideTimer) {
                                clearTimeout(overlayHideTimer);
                            }
                            overlayHideTimer = setTimeout(function () {
                                hideOverlay();
                            }, 2000);
                        }
                    };
                    element.addEventListener('click', clickHandler, true);
                    cleanupHandlers.push({ target: element, type: 'click', handler: clickHandler, options: true });
                });
            }

            function syncSecurityContext() {
                const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement || null;
                let targetDoc = document;
                let targetWin = window;

                if (fullscreenElement && fullscreenElement.contentDocument) {
                    targetDoc = fullscreenElement.contentDocument;
                    targetWin = fullscreenElement.contentWindow || window;
                }

                detachSignals();
                attachSignals(targetDoc, targetWin);

                if (fullscreenElement) {
                    showOverlay();
                } else {
                    hideOverlay();
                }
            }

            function checkDevTools() {
                const currentWidth = window.innerWidth;
                const currentHeight = window.innerHeight;
                const widthDifference = Math.abs(currentWidth - lastWindowWidth);
                const heightDifference = Math.abs(currentHeight - lastWindowHeight);

                if ((widthDifference > 160 || heightDifference > 160) && !devtoolsOpen) {
                    devtoolsOpen = true;
                    showOverlay();
                } else if (widthDifference <= 160 && heightDifference <= 160 && devtoolsOpen) {
                    devtoolsOpen = false;
                    hideOverlay();
                }

                lastWindowWidth = currentWidth;
                lastWindowHeight = currentHeight;
            }

            // Signal 5: DevTools size difference
            setInterval(checkDevTools, 1000);

            document.addEventListener('fullscreenchange', syncSecurityContext, false);
            document.addEventListener('webkitfullscreenchange', syncSecurityContext, false);
            syncSecurityContext();
        }

        document.addEventListener('DOMContentLoaded', initSecurityModule);
    </script>

    <div class="container py-4">
        <a href="lectures.php" class="btn btn-outline-secondary mb-3">&larr; Back to Sessions</a>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-2"><?php echo htmlspecialchars($lecture['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if (!empty($lecture['description'])): ?>
                        <p class="text-muted"><?php echo htmlspecialchars($lecture['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <?php if (($sourceType === 'video' || $sourceType === 'drive_file') && $sourceUrl !== '' && isVideoSourceUrl($sourceUrl)): ?>
                        <div class="video-shell">
                            <video id="session-video" class="player-frame" preload="metadata" playsinline disablePictureInPicture controlsList="nodownload nofullscreen noplaybackrate">
                                <source src="<?php echo htmlspecialchars(buildVideoProxyUrl($sourceUrl), ENT_QUOTES, 'UTF-8'); ?>">
                                Your browser does not support the video player.
                            </video>
                            <div class="video-controls" role="group" aria-label="Video controls">
                                <button type="button" class="video-controls__button" id="video-toggle">Play</button>
                                <span id="video-time">0:00 / 0:00</span>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const video = document.getElementById('session-video');
                                const toggle = document.getElementById('video-toggle');
                                const timeLabel = document.getElementById('video-time');

                                if (!video || !toggle || !timeLabel) {
                                    return;
                                }

                                video.removeAttribute('controls');
                                video.controls = false;
                                video.setAttribute('controlsList', 'nodownload nofullscreen noplaybackrate');

                                const updateStatus = function () {
                                    const current = Number.isFinite(video.currentTime) ? video.currentTime : 0;
                                    const duration = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 0;
                                    const formatTime = function (value) {
                                        const safeValue = Math.max(0, Math.floor(value));
                                        const minutes = Math.floor(safeValue / 60);
                                        const seconds = safeValue % 60;
                                        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                                    };
                                    timeLabel.textContent = formatTime(current) + ' / ' + formatTime(duration);
                                    toggle.textContent = video.paused ? 'Play' : 'Pause';
                                };

                                toggle.addEventListener('click', function () {
                                    if (video.paused) {
                                        video.play().catch(function () {});
                                    } else {
                                        video.pause();
                                    }
                                });

                                video.addEventListener('play', updateStatus);
                                video.addEventListener('pause', updateStatus);
                                video.addEventListener('timeupdate', updateStatus);
                                video.addEventListener('loadedmetadata', updateStatus);
                                updateStatus();
                            });
                        </script>
                    <?php elseif ($sourceType === 'youtube' && $sourceUrl !== ''): ?>
                        <iframe class="player-frame" src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" allow="autoplay; fullscreen" allowfullscreen></iframe>
                    <?php elseif ($sourceType === 'drive_file' && $sourceUrl !== ''): ?>
                        <div style="position: relative; display: inline-block; width: 100%;">
                            <iframe class="player-frame" src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" allow="autoplay; fullscreen" allowfullscreen frameborder="0"></iframe>
                            <div style="position: absolute; top: 0; right: 0; width: 90px; height: 56px; z-index: 10;"></div>
                        </div>
                        <!-- <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary mt-3">Open source in Drive</a> -->
                    <?php elseif ($sourceType === 'folder' && $sourceUrl !== ''): ?>
                        <div class="alert alert-warning">
                            This session source points to a Drive folder, which cannot be embedded directly in this player. Please use a direct video file link or a public Drive file link instead.
                        </div>
                        <!-- <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary">Open source in Drive</a> -->
                    <?php elseif ($sourceType === 'link' && $sourceUrl !== ''): ?>
                        <div class="alert alert-info">
                            A session source was found, but it needs to be shared publicly or opened directly. Use the button below to access it.
                        </div>
                        <a href="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline-primary">Open lecture source</a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            This session does not have a video source yet. The admin can add a direct video link or upload a file later.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
