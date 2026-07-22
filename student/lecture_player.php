<?php
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin(['student', 'admin']);
$conn = getDbConnection();

$isAdmin = ($user['role'] === 'admin');
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

if ($lectureId > 0) {
    if ($isAdmin) {
        $lectureStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id FROM lectures l WHERE l.id = ? AND l.status = "active" LIMIT 1');
        $lectureStmt->bind_param('i', $lectureId);
    } elseif (!empty($studentGroupIds)) {
        $placeholders = implode(', ', array_fill(0, count($studentGroupIds), '?'));
        $lectureStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id FROM lectures l INNER JOIN lecture_folder_access lfa ON lfa.lecture_id = l.id WHERE l.id = ? AND l.status = "active" AND lfa.group_id IN (' . $placeholders . ') LIMIT 1');
        $params = array_merge([(int)$lectureId], $studentGroupIds);
        bindPreparedParams($lectureStmt, $params);
    } else {
        $lectureStmt = $conn->prepare('SELECT l.id, l.title, l.description, l.drive_folder_id FROM lectures l WHERE l.id = ? AND l.status = "active" LIMIT 1');
        $lectureStmt->bind_param('i', $lectureId);
    }
    $lectureStmt->execute();
    $lecture = $lectureStmt->get_result()->fetch_assoc();
}

if ($lecture) {
    $conn->query("CREATE TABLE IF NOT EXISTS lecture_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        lecture_id INT NOT NULL,
        watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY student_id (student_id),
        KEY lecture_id (lecture_id)
    )");

    $recentViewStmt = $conn->prepare('SELECT id FROM lecture_views WHERE student_id = ? AND lecture_id = ? AND watched_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1');
    $recentViewStmt->bind_param('ii', $user['id'], $lectureId);
    $recentViewStmt->execute();
    $recentView = $recentViewStmt->get_result()->fetch_assoc();

    if (!$recentView) {
        $insertViewStmt = $conn->prepare('INSERT INTO lecture_views (student_id, lecture_id) VALUES (?, ?)');
        $insertViewStmt->bind_param('ii', $user['id'], $lectureId);
        $insertViewStmt->execute();
    }

    $lectureSource = resolveLectureSource($lecture['drive_folder_id'] ?? '');
    $sourceType = $lectureSource['type'];
    $sourceUrl = $lectureSource['url'];
} else {
    $error = 'This session is not available for your group.';
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
        // Prevent iframe content from opening new windows via window.open
        window.open = function() {
            return null;
        };

        (function() {
            var overlay = null;
            var hideTimer = null;

            function getOverlay() {
                if (!overlay) overlay = document.getElementById('security-overlay');
                return overlay;
            }

            window.addEventListener('keydown', function(e) {
                if (e.key === 'PrintScreen' || e.key === 'F13') {
                    var o = getOverlay();
                    if (o) {
                        o.style.display = 'flex';
                    }
                    if (hideTimer) clearTimeout(hideTimer);
                    hideTimer = setTimeout(function() {
                        var o2 = getOverlay();
                        if (o2) o2.style.display = 'none';
                    }, 1500);
                }
            }, true);
        })();

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
                    (document.body || document.documentElement).appendChild(overlay);
                    Object.assign(overlay.style, {
                        position: 'fixed', inset: '0', display: 'none',
                        alignItems: 'center', justifyContent: 'center',
                        background: 'rgba(0,0,0,0.97)', color: '#fff',
                        zIndex: '2147483647', pointerEvents: 'none',
                        textAlign: 'center', padding: '1.5rem'
                    });
                    Object.assign(message.style, {
                        fontSize: '1.5rem', fontWeight: '600',
                        letterSpacing: '0.02em', textTransform: 'uppercase'
                    });
                }
                return overlay;
            }

            function showOverlay(autohideMs) {
                const overlay = ensureOverlay();
                overlay.style.display = 'flex';
                if (overlayHideTimer) clearTimeout(overlayHideTimer);
                if (autohideMs) {
                    overlayHideTimer = setTimeout(hideOverlay, autohideMs);
                }
            }

            function hideOverlay() {
                const overlay = document.getElementById('security-overlay');
                if (overlay) overlay.style.display = 'none';
                if (overlayHideTimer) { clearTimeout(overlayHideTimer); overlayHideTimer = null; }
            }

            function detachSignals() {
                cleanupHandlers.forEach(function(e) {
                    e.target.removeEventListener(e.type, e.handler, e.options);
                });
                cleanupHandlers = [];
            }

            function on(target, type, handler, options) {
                target.addEventListener(type, handler, options);
                cleanupHandlers.push({ target, type, handler, options });
            }

            function attachSignals() {
                // Signal 1: Tab hidden (reliable, covers recording apps switching focus)
                on(document, 'visibilitychange', function() {
                    if (document.visibilityState === 'hidden') showOverlay();
                    else hideOverlay();
                }, false);

                // Signal 2: Window blur — show on any focus loss
                on(window, 'blur', function() {
                    showOverlay();
                }, false);
                on(window, 'focus', function() {
                    hideOverlay();
                }, false);

                // Signal 3: Screenshot / print keyboard shortcuts
                on(document, 'keydown', function(e) {
                    const isPrintScreen = e.key === 'PrintScreen' || e.key === 'F13';
                    const isMacScreenshot =
                        e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4' || e.key === 's' || e.key === 'S');
                    const isWinScreenshot =
                        e.ctrlKey && e.shiftKey && (e.key === 's' || e.key === 'S');
                    const isPrint = e.ctrlKey && (e.key === 'p' || e.key === 'P');

                    if (isPrintScreen) {
                        showOverlay(1000);
                        return;
                    }

                    if (isMacScreenshot || isWinScreenshot || isPrint) {
                        e.preventDefault();
                        showOverlay(3000);
                    }
                }, true);

                // Signal 4: Right-click — block and auto-hide after 2s
                on(document, 'contextmenu', function(e) {
                    e.preventDefault();
                    showOverlay(2000);
                }, true);

                // Signal 5: Block drag-to-download
                on(document, 'dragstart', function(e) {
                    e.preventDefault();
                }, true);
            }

            function checkDevTools() {
                const w = window.innerWidth;
                const h = window.innerHeight;
                const triggered = Math.abs(w - lastWindowWidth) > 160 && Math.abs(h - lastWindowHeight) > 160;
                if (triggered && !devtoolsOpen) { devtoolsOpen = true; showOverlay(); }
                else if (!triggered && devtoolsOpen) { devtoolsOpen = false; hideOverlay(); }
                lastWindowWidth = w;
                lastWindowHeight = h;
            }

            setInterval(checkDevTools, 1000);

            document.addEventListener('fullscreenchange', function() {
                detachSignals();
                attachSignals();
            }, false);
            document.addEventListener('webkitfullscreenchange', function() {
                detachSignals();
                attachSignals();
            }, false);

            attachSignals();
        }

        document.addEventListener('DOMContentLoaded', function () {
            initSecurityModule();
            initWidevine();
        });

        function initWidevine() {
            const video = document.getElementById('lecture-video');
            if (!video || !window.navigator.requestMediaKeySystemAccess) {
                return;
            }

            const keySystem = 'com.widevine.alpha';
            const config = [{
                initDataTypes: ['cenc'],
                videoCapabilities: [{ contentType: 'video/mp4; codecs="avc1.42E01E"' }],
            }];

            navigator.requestMediaKeySystemAccess(keySystem, config)
                .then(function (mediaKeySystemAccess) {
                    return mediaKeySystemAccess.createMediaKeys();
                })
                .then(function (mediaKeys) {
                    return video.setMediaKeys(mediaKeys);
                })
                .then(function () {
                    video.addEventListener('encrypted', function (event) {
                        const initData = event.initData;
                        if (!initData) {
                            return;
                        }

                        const session = video.mediaKeys.createSession();
                        session.addEventListener('message', function (messageEvent) {
                            fetch('widevine_license.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/octet-stream' },
                                body: messageEvent.message,
                            })
                                .then(function (response) {
                                    return response.arrayBuffer();
                                })
                                .then(function (license) {
                                    return session.update(license);
                                })
                                .catch(function () {
                                    console.error('Widevine license request failed.');
                                });
                        });

                        session.generateRequest(event.initDataType, initData).catch(function (error) {
                            console.error('Widevine generateRequest failed:', error);
                        });
                    });
                })
                .catch(function (error) {
                    console.error('Widevine initialization failed:', error);
                });
        }
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
                        <video id="lecture-video" class="player-frame" controls preload="metadata" playsinline crossorigin="anonymous">
                            <source src="<?php echo htmlspecialchars(buildVideoProxyUrl($sourceUrl), ENT_QUOTES, 'UTF-8'); ?>">
                            Your browser does not support the video player.
                        </video>
                    <?php elseif ($sourceType === 'youtube' && $sourceUrl !== ''): ?>
                        <iframe class="player-frame" src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" sandbox="allow-scripts allow-same-origin" allow="autoplay; fullscreen" allowfullscreen></iframe>
                    <?php elseif ($sourceType === 'drive_file' && $sourceUrl !== ''): ?>
                        <iframe class="player-frame" src="<?php echo htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8'); ?>" sandbox="allow-scripts allow-same-origin" allow="autoplay; fullscreen" allowfullscreen frameborder="0"></iframe>
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
