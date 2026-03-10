// ============================================================
// StreamVault — browse.js (ES6 Module)
// Horizontal carousel arrows + watchlist AJAX buttons
// ============================================================

// -- Carousel Arrow Navigation ------------------------------
document.querySelectorAll('.carousel-wrapper').forEach(wrapper => {
    const track = wrapper.querySelector('.carousel-track');
    const leftBtn = wrapper.querySelector('.carousel-arrow.left');
    const rightBtn = wrapper.querySelector('.carousel-arrow.right');

    if (!track) return;

    const scrollAmount = () => track.clientWidth * 0.75;

    leftBtn?.addEventListener('click', () => track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' }));
    rightBtn?.addEventListener('click', () => track.scrollBy({ left: scrollAmount(), behavior: 'smooth' }));

    // Show/hide arrows based on scroll position
    const updateArrows = () => {
        if (leftBtn) leftBtn.style.display = track.scrollLeft <= 0 ? 'none' : '';
        if (rightBtn) rightBtn.style.display = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4 ? 'none' : '';
    };
    track.addEventListener('scroll', updateArrows, { passive: true });
    updateArrows();
});

// -- Watchlist Buttons (AJAX) -------------------------------
async function toggleWatchlist(btn) {
    const id = btn.dataset.id;
    const inList = btn.dataset.inList === '1';
    const action = inList ? 'remove' : 'add';

    const fd = new FormData();
    fd.append('content_id', id);
    fd.append('action', action);

    try {
        btn.textContent = '…';
        btn.disabled = true;

        const res = await fetch('/streamvault/api/watchlist.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            btn.dataset.inList = inList ? '0' : '1';
            btn.textContent = inList ? '+' : '?';
            btn.title = inList ? 'Add to My List' : 'Remove from My List';
        }
    } catch (e) {
        console.error('Watchlist error:', e);
        btn.textContent = inList ? '?' : '+';
    } finally {
        btn.disabled = false;
    }
}

// Delegate watchlist clicks
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.watchlist-btn');
    if (btn) toggleWatchlist(btn);
});

