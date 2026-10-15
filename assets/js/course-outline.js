(function () {
    'use strict';

    var outline = document.querySelector('[data-course-outline]');
    if (!outline) {
        return;
    }

    var modal = document.querySelector('[data-video-modal]');
    var videoFrame = modal ? modal.querySelector('[data-video-frame]') : null;
    var videoElement = modal ? modal.querySelector('[data-video-element]') : null;
    var modalCompleteButton = modal ? modal.querySelector('[data-video-complete]') : null;
    var status = document.querySelector('[data-outline-status]');
    var csrfToken = outline.dataset.csrfToken;
    var apiUrl = outline.dataset.apiUrl;
    var activeVideoRow = null;

    function syncModalCompleteButton() {
        if (!modalCompleteButton) {
            return;
        }

        var rowButton = activeVideoRow ? activeVideoRow.querySelector('[data-complete]') : null;
        if (!rowButton) {
            modalCompleteButton.hidden = true;
            modalCompleteButton.disabled = true;
            modalCompleteButton.setAttribute('aria-pressed', 'false');
            modalCompleteButton.textContent = 'Mark as complete';
            return;
        }

        modalCompleteButton.hidden = false;
        modalCompleteButton.textContent = rowButton.textContent;
        modalCompleteButton.disabled = rowButton.disabled;
        modalCompleteButton.setAttribute('aria-pressed', rowButton.getAttribute('aria-pressed'));
        modalCompleteButton.dataset.itemId = rowButton.dataset.itemId;
    }

    function setStatus(message, isError) {
        if (status) {
            status.textContent = message;
            status.classList.toggle('d-none', message === '');
            status.classList.toggle('alert-danger', Boolean(isError));
            status.classList.toggle('alert-info', !isError);
        }
    }

    function updateProgress() {
        var allRows = Array.prototype.slice.call(outline.querySelectorAll('[data-item-row]'));
        var rowCompletedTotal = allRows.filter(function (row) {
            return row.dataset.completed === 'true';
        }).length;
        var hasCourseTotals = outline.dataset.courseTotal !== undefined;
        var totalItems = hasCourseTotals ? Number(outline.dataset.courseTotal) : allRows.length;
        var completedTotal = hasCourseTotals
            ? Number(outline.dataset.courseCompleted)
            : rowCompletedTotal;
        var overallPercent = totalItems > 0
            ? Math.round((completedTotal / totalItems) * 100)
            : 0;

        var progress = document.querySelector('[data-course-progress]');
        var progressLabel = document.querySelector('[data-course-progress-label]');
        var completedLabel = document.querySelector('[data-course-completed]');
        var totalLabel = document.querySelector('[data-course-total]');

        if (progress) {
            progress.setAttribute('aria-valuenow', String(overallPercent));
            var bar = progress.querySelector('.progress-bar');
            if (bar) {
                bar.style.width = overallPercent + '%';
            }
        }
        if (progressLabel) {
            progressLabel.textContent = overallPercent + '% complete';
        }
        if (completedLabel) {
            completedLabel.textContent = String(completedTotal);
        }
        if (totalLabel) {
            totalLabel.textContent = String(totalItems);
        }

        outline.querySelectorAll('[data-session]').forEach(function (session) {
            var rows = Array.prototype.slice.call(session.querySelectorAll('[data-item-row]'));
            var completed = rows.filter(function (row) {
                return row.dataset.completed === 'true';
            }).length;
            var percent = rows.length ? Math.round((completed / rows.length) * 100) : 0;
            var progressBadge = session.querySelector('[data-session-progress]');
            var itemCount = session.querySelector('[data-session-count]');

            if (progressBadge) {
                progressBadge.textContent = percent + '%';
            }
            if (itemCount) {
                itemCount.textContent = rows.length + (rows.length === 1 ? ' item' : ' items');
            }
        });
    }

    function closeVideoModal() {
        if (!modal) {
            return;
        }
        if (videoFrame) {
            videoFrame.src = '';
            videoFrame.hidden = true;
        }
        if (videoElement) {
            videoElement.pause();
            videoElement.removeAttribute('src');
            videoElement.load();
            videoElement.hidden = true;
        }
        if (modal.open) {
            modal.close();
        }
    }

    function openVideo(button) {
        if (!modal || typeof modal.showModal !== 'function') {
            setStatus('Video playback is not supported by this browser.', true);
            return;
        }

        activeVideoRow = button.closest('[data-item-row]');
        if (activeVideoRow) {
            modalCompleteButton && (modalCompleteButton.dataset.itemId = activeVideoRow.dataset.itemId || '');
        }

        var rawUrl = button.dataset.videoUrl || '';
        var kind = button.dataset.videoKind || '';
        var parsedUrl;

        try {
            parsedUrl = new URL(rawUrl, window.location.href);
        } catch (error) {
            setStatus('This video link is invalid.', true);
            return;
        }

        if (parsedUrl.protocol !== 'https:' && parsedUrl.protocol !== 'http:') {
            setStatus('This video link is not allowed.', true);
            return;
        }

        if (kind === 'file' && videoElement) {
            if (!/\.(mp4|webm|ogg|m4v|mov)(\?.*)?$/i.test(parsedUrl.href)) {
                setStatus('This video format is not supported.', true);
                return;
            }
            videoElement.src = parsedUrl.href;
            videoElement.hidden = false;
        } else if (
            kind === 'embed'
            && videoFrame
            && parsedUrl.protocol === 'https:'
            && ['drive.google.com', 'www.youtube-nocookie.com'].indexOf(parsedUrl.hostname) !== -1
        ) {
            videoFrame.src = parsedUrl.href;
            videoFrame.hidden = false;
        } else {
            setStatus('This video link cannot be played in the modal.', true);
            return;
        }

        modal.showModal();
        syncModalCompleteButton();
        setStatus('');
    }

    function markItemComplete(button) {
        var itemId = Number(button.dataset.itemId);
        if (!Number.isInteger(itemId) || itemId < 1) {
            setStatus('Could not save progress: invalid item.', true);
            return;
        }

        button.disabled = true;
        syncModalCompleteButton();
        setStatus('Saving progress…');

        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ item_id: itemId })
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return {};
                }).then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.error || 'Could not save progress.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                if (!payload.ok || Number(payload.item_id) !== itemId || payload.completed !== true) {
                    throw new Error('The server did not confirm item completion.');
                }

                var row = button.closest('[data-item-row]');
                if (row) {
                    var wasCompleted = row.dataset.completed === 'true';
                    row.dataset.completed = 'true';
                    row.classList.add('is-completed');
                    if (!wasCompleted && outline.dataset.courseTotal !== undefined) {
                        outline.dataset.courseCompleted = String(Number(outline.dataset.courseCompleted || 0) + 1);
                    }
                }

                button.textContent = 'Completed';
                button.disabled = true;
                button.setAttribute('aria-pressed', 'true');

                if (modalCompleteButton && modalCompleteButton.dataset.itemId === String(itemId)) {
                    modalCompleteButton.textContent = 'Completed';
                    modalCompleteButton.disabled = true;
                    modalCompleteButton.setAttribute('aria-pressed', 'true');
                }

                syncModalCompleteButton();
                updateProgress();
                setStatus('Progress saved.');
            })
            .catch(function (error) {
                button.disabled = false;
                syncModalCompleteButton();
                setStatus(error.message || 'Could not save progress. Please try again.', true);
            });
    }

    outline.addEventListener('click', function (event) {
        var toggle = event.target.closest('.outline-session__toggle');
        if (toggle && outline.contains(toggle)) {
            var panelId = toggle.getAttribute('aria-controls');
            var panel = panelId ? document.getElementById(panelId) : null;
            if (panel) {
                var expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!expanded));
                panel.hidden = expanded;
            }
            return;
        }

        var videoButton = event.target.closest('[data-video-open]');
        if (videoButton && outline.contains(videoButton)) {
            openVideo(videoButton);
            return;
        }

        var completeButton = event.target.closest('[data-complete]');
        if (!completeButton || !outline.contains(completeButton) || completeButton.disabled) {
            return;
        }

        markItemComplete(completeButton);
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            var modalComplete = event.target.closest('[data-video-complete]');
            if (modalComplete && modal.contains(modalComplete)) {
                var targetButton = activeVideoRow ? activeVideoRow.querySelector('[data-complete]') : null;
                if (!targetButton && modalComplete.dataset.itemId) {
                    targetButton = outline.querySelector('[data-complete][data-item-id="' + modalComplete.dataset.itemId + '"]');
                }
                if (targetButton && !targetButton.disabled) {
                    markItemComplete(targetButton);
                } else if (modalComplete && !modalComplete.disabled) {
                    markItemComplete(modalComplete);
                }
                return;
            }

            var fullscreen = event.target.closest('[data-video-fullscreen]');
            if (fullscreen && modal.contains(fullscreen)) {
                var activePlayer = videoFrame && !videoFrame.hidden ? videoFrame : videoElement;
                var requestFullscreen = activePlayer && (
                    activePlayer.requestFullscreen
                    || activePlayer.webkitRequestFullscreen
                );

                if (requestFullscreen) {
                    try {
                        var fullscreenResult = requestFullscreen.call(activePlayer);
                        if (fullscreenResult && typeof fullscreenResult.catch === 'function') {
                            fullscreenResult.catch(function () {
                                setStatus('Fullscreen is unavailable for this video in the current browser.', true);
                            });
                        }
                    } catch (error) {
                        setStatus('Fullscreen is unavailable for this video in the current browser.', true);
                    }
                } else if (activePlayer === videoElement && videoElement.webkitEnterFullscreen) {
                    videoElement.webkitEnterFullscreen();
                } else {
                    setStatus('Fullscreen is unavailable for this video in the current browser.', true);
                }
                return;
            }

            if (event.target === modal || event.target.closest('[data-video-close]')) {
                closeVideoModal();
            }
        });
        modal.addEventListener('close', function () {
            if (videoFrame) {
                videoFrame.src = '';
                videoFrame.hidden = true;
            }
            if (videoElement) {
                videoElement.pause();
                videoElement.removeAttribute('src');
                videoElement.load();
                videoElement.hidden = true;
            }
            activeVideoRow = null;
            syncModalCompleteButton();
        });
    }

    updateProgress();
})();
