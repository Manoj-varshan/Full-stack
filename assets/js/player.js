// ============================================================
// StreamVault - player.js (ES6)
// Real YouTube IFrame API progress tracking
// ============================================================

// CONTENT_ID, PROFILE_ID, SIM_DURATION, YT_VIDEO_ID, PREV_PROGRESS
// injected via inline <script> in watch.php

let ytPlayer = null;
let progressTimer = null;
let lastSavedAt = 0;
const SAVE_INTERVAL = 15; // save every 15 seconds of real playback

// -- 1. Load YouTube IFrame API ----------------------------
const tag = document.createElement('script');
tag.src = 'https://www.youtube.com/iframe_api';
document.head.appendChild(tag);

// -- 2. YouTube API Ready callback (called by YouTube) -----
window.onYouTubeIframeAPIReady = function () {
    if (typeof YT_VIDEO_ID === 'undefined' || !YT_VIDEO_ID) return;

    ytPlayer = new YT.Player('ytPlayer', {
        width: '100%',
        height: '100%',
        videoId: YT_VIDEO_ID,
        playerVars: {
            autoplay: 1,
            rel: 0,
            modestbranding: 1,
            start: typeof PREV_PROGRESS !== 'undefined' ? PREV_PROGRESS : 0,
        },
        events: {
            onReady: onPlayerReady,
            onStateChange: onPlayerStateChange,
        }
    });
};

// -- 3. Player Ready ---------------------------------------
function onPlayerReady(event) {
    startProgressTracking();
}

// -- 4. State Change (play / pause / end) ------------------
function onPlayerStateChange(event) {
    if (event.data === YT.PlayerState.PLAYING) {
        startProgressTracking();
    } else {
        stopProgressTracking();
        saveProgress();
    }
}

// -- 5. Poll real playback position and save ---------------
function startProgressTracking() {
    stopProgressTracking();
    progressTimer = setInterval(() => {
        if (!ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;

        const current = Math.floor(ytPlayer.getCurrentTime());
        const duration = Math.floor(ytPlayer.getDuration());

        if (current - lastSavedAt >= SAVE_INTERVAL || current === 0) {
            lastSavedAt = current;
            saveProgressData(current, duration);
        }
    }, 1000);
}

function stopProgressTracking() {
    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }
}

async function saveProgress() {
    if (!ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;
    const current = Math.floor(ytPlayer.getCurrentTime());
    const duration = Math.floor(ytPlayer.getDuration());
    await saveProgressData(current, duration);
}

async function saveProgressData(progressSeconds, durationSeconds) {
    if (!PROFILE_ID || !CONTENT_ID || durationSeconds <= 0) return;

    const fd = new FormData();
    fd.append('content_id', CONTENT_ID);
    fd.append('progress_seconds', progressSeconds);
    fd.append('duration_seconds', durationSeconds);

    try {
        await fetch('/streamvault/api/watch_progress.php', { method: 'POST', body: fd });
    } catch { }
}

// -- 6. Save on page close (best-effort) -------------------
window.addEventListener('beforeunload', () => {
    stopProgressTracking();
    if (!ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;

    const fd = new FormData();
    fd.append('content_id', CONTENT_ID);
    fd.append('progress_seconds', Math.floor(ytPlayer.getCurrentTime()));
    fd.append('duration_seconds', Math.floor(ytPlayer.getDuration() || SIM_DURATION));
    navigator.sendBeacon('/streamvault/api/watch_progress.php', fd);
});

// -- 7. Ad System (Free users — repeats every 30s) --------
const adOverlay = document.getElementById('adOverlay');
const skipAdBtn = document.getElementById('skipAdBtn');
const countdown = document.getElementById('skipCountdown');

function startAdCountdown() {
    let secondsLeft = 15;
    if (countdown) countdown.textContent = secondsLeft;
    if (skipAdBtn) {
        skipAdBtn.disabled = true;
        skipAdBtn.textContent = 'Skip Ad in ' + secondsLeft + 's';
        skipAdBtn.style.background = '';
    }

    const timer = setInterval(() => {
        secondsLeft--;
        if (countdown) countdown.textContent = secondsLeft;
        if (skipAdBtn) skipAdBtn.textContent = 'Skip Ad in ' + secondsLeft + 's';
        if (secondsLeft <= 0) {
            clearInterval(timer);
            if (skipAdBtn) {
                skipAdBtn.disabled = false;
                skipAdBtn.textContent = 'Skip Ad >';
                skipAdBtn.style.background = 'rgba(255,255,255,0.2)';
            }
        }
    }, 1000);
}

if (adOverlay && skipAdBtn) {
    // Start first ad countdown immediately
    startAdCountdown();

    // Re-show ad every 30 seconds after it's dismissed
    setInterval(() => {
        if (adOverlay.style.display === 'none' || adOverlay.style.display === '') {
            adOverlay.style.display = 'flex';
            startAdCountdown();
        }
    }, 30000);

    skipAdBtn.addEventListener('click', () => {
        if (!skipAdBtn.disabled) adOverlay.style.display = 'none';
    });
}

// -- 8. Watchlist Button -----------------------------------
document.querySelectorAll('.watchlist-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const inList = btn.dataset.inList === '1';
        const fd = new FormData();
        fd.append('content_id', id);
        fd.append('action', inList ? 'remove' : 'add');

        try {
            const res = await fetch('/streamvault/api/watchlist.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                btn.dataset.inList = inList ? '0' : '1';
                btn.textContent = inList ? '+ My List' : '\u2713 In List';
            }
        } catch (e) { console.error('Watchlist error', e); }
    });
});

// -- 9. Download Button ------------------------------------
const downloadBtn = document.getElementById('downloadBtn');
downloadBtn?.addEventListener('click', async () => {
    downloadBtn.disabled = true;
    downloadBtn.textContent = 'Downloading...';

    const fd = new FormData();
    fd.append('content_id', CONTENT_ID);

    try {
        const res = await fetch('/streamvault/api/download.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            downloadBtn.textContent = data.already ? '\u2713 Already Downloaded' : '\u2713 Downloaded!';
            downloadBtn.style.color = '#7C3AED';

            // Show link to downloads page
            const dlLink = document.createElement('a');
            dlLink.href = '/streamvault/downloads.php';
            dlLink.className = 'ctrl-btn';
            dlLink.style.color = '#7C3AED';
            dlLink.style.background = 'rgba(124,58,237,0.1)';
            dlLink.textContent = 'My Downloads';
            downloadBtn.parentNode.appendChild(dlLink);

            setTimeout(() => {
                if (typeof data.remaining === 'number' && data.remaining >= 0) {
                    downloadBtn.textContent = `\u2b07 Download (${data.remaining} left)`;
                    downloadBtn.style.color = '';
                }
                downloadBtn.disabled = false;
            }, 3000);
        } else {
            downloadBtn.textContent = 'Failed: ' + (data.message ?? 'Error');
            downloadBtn.style.color = 'var(--accent)';
            setTimeout(() => {
                downloadBtn.textContent = '\u2b07 Download';
                downloadBtn.style.color = '';
                downloadBtn.disabled = false;
            }, 3000);
        }
    } catch (e) {
        downloadBtn.textContent = '\u2b07 Download';
        downloadBtn.disabled = false;
    }
});
