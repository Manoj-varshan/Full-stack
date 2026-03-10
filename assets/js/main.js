// ============================================================
// StreamVault � main.js (ES6 Module)
// Global: navbar scroll, search overlay, scroll animations
// ============================================================

// -- Navbar: Add 'scrolled' class on scroll ----------------
const navbar = document.getElementById('mainNav');
if (navbar) {
    const onScroll = () => {
        navbar.classList.toggle('scrolled', window.scrollY > 30);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // run on load
}

// -- Search Overlay -----------------------------------------
const searchToggle = document.getElementById('searchToggle');
const searchOverlay = document.getElementById('searchOverlay');
const searchClose = document.getElementById('searchClose');
const searchInput = document.getElementById('searchInput');

if (searchToggle && searchOverlay) {
    searchToggle.addEventListener('click', () => {
        searchOverlay.classList.add('active');
        setTimeout(() => searchInput?.focus(), 100);
    });

    searchClose?.addEventListener('click', closeSearch);

    searchOverlay.addEventListener('click', (e) => {
        if (e.target === searchOverlay) closeSearch();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSearch();
    });
}

function closeSearch() {
    searchOverlay?.classList.remove('active');
    if (searchInput) searchInput.value = '';
    const results = document.getElementById('searchResults');
    if (results) results.innerHTML = '';
}

// -- Live Search (ES6 debounce + fetch) --------------------
let searchTimer = null;

searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    const results = document.getElementById('searchResults');
    if (!results) return;

    if (q.length < 2) {
        results.innerHTML = '';
        return;
    }

    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/streamvault/api/search.php?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            renderSearchResults(data, results);
        } catch (err) {
            console.error('Search failed:', err);
        }
    }, 320);
});

function renderSearchResults(items, container) {
    if (!Array.isArray(items) || !items.length) {
        container.innerHTML = `<p style="grid-column:1/-1;color:var(--text-muted);text-align:center;">No results found.</p>`;
        return;
    }

    container.innerHTML = items.map(item => {
        const locked = item.locked;
        const href = locked ? '#' : `/streamvault/watch.php?id=${item.id}&info=1`;
        return `
      <div style="position:relative;cursor:pointer;" onclick="${locked ? "alert('Upgrade to Premium to access this content')" : `window.location='${href}'`}">
        <img src="${item.thumbnail_url ?? ''}" alt="${item.title ?? ''}"
             style="width:100%;aspect-ratio:2/3;object-fit:cover;border-radius:6px;display:block;"
             onerror="this.src='https://via.placeholder.com/150x225/1f1f1f/666?text=??'">
        ${locked ? `<div style="position:absolute;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;border-radius:6px;">??</div>` : ''}
        <div style="font-size:0.75rem;font-weight:600;margin-top:5px;color:#e5e5e5;line-height:1.3;">${item.title ?? ''}</div>
        <div style="font-size:0.68rem;color:#888;">${item.genre ?? ''} · ★${item.rating ?? ''}</div>
      </div>
    `;
    }).join('');
}

// -- Scroll-triggered fade-in animations ------------------
const fadeEls = document.querySelectorAll('.fade-in');
if (fadeEls.length && 'IntersectionObserver' in window) {
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    fadeEls.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(24px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        obs.observe(el);
    });
}

