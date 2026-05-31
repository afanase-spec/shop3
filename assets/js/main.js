(function initTheme() {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = saved || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
})();

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);

    const btn = document.querySelector('.theme-toggle i');
    if (btn) {
        btn.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('.theme-toggle i');
    if (btn) {
        const current = document.documentElement.getAttribute('data-theme');
        btn.className = current === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
});

// ============= CSRF =============
function getCSRFToken() {
    const el = document.getElementById('csrf_token_global') || document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
}

// ============= ТОСТ-УВЕДОМЛЕНИЯ =============
function ensureToastContainer() {
    let c = document.querySelector('.toast-container');
    if (!c) {
        c = document.createElement('div');
        c.className = 'toast-container';
        document.body.appendChild(c);
    }
    return c;
}

function showNotification(message, type = 'success') {
    const container = ensureToastContainer();
    const icons = { success: 'fa-check', error: 'fa-times', warning: 'fa-exclamation', info: 'fa-info' };
    const toast = document.createElement('div');
    toast.className = `toast-custom ${type}`;
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${icons[type] || 'fa-info'}"></i></div>
        <div class="toast-message">${message}</div>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ============= ФОРМАТ ЦЕНЫ =============
function formatPrice(price) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(price);
}

// ============= КОРЗИНА: API-обёртка =============
async function cartRequest(url, data) {
    const formData = new FormData();
    Object.entries(data).forEach(([k, v]) => formData.append(k, v));
    formData.append('csrf_token', getCSRFToken());
    const res = await fetch(url, { method: 'POST', body: formData });
    return res.json();
}

async function addToCart(productId, quantity = 1) {
    try {
        const data = await cartRequest('/api/cart-add.php', { product_id: productId, quantity });
        if (data.success) {
            updateCartBadge(data.cart_count, data.cart_total);
            showNotification('Товар добавлен в корзину', 'success');
            replaceButtonWithQuantityControl(productId, quantity);
        } else {
            showNotification(data.message || 'Ошибка добавления', 'error');
        }
    } catch (e) { showNotification('Ошибка сети', 'error'); }
}

async function removeFromCart(productId) {
    try {
        const data = await cartRequest('/api/cart-remove.php', { product_id: productId });
        if (data.success) {
            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (row) row.remove();
            updateCartBadge(data.cart_count, data.cart_total);
            const totalEl = document.getElementById('cart-grand-total');
            if (totalEl) totalEl.textContent = formatPrice(data.cart_total);
            if (data.cart_count === 0) location.reload();
            showNotification('Товар удалён', 'success');
        }
    } catch (e) { showNotification('Ошибка сети', 'error'); }
}

async function updateQuantity(productId, quantity) {
    try {
        const data = await cartRequest('/api/cart-update.php', { product_id: productId, quantity });
        if (data.success) {
            updateCartBadge(data.cart_count, data.cart_total);
            const sub = document.querySelector(`tr[data-product-id="${productId}"] .item-subtotal`);
            if (sub) sub.textContent = formatPrice(data.item_subtotal);
            const totalEl = document.getElementById('cart-grand-total');
            if (totalEl) totalEl.textContent = formatPrice(data.cart_total);
        }
    } catch (e) { showNotification('Ошибка сети', 'error'); }
}

async function updateProductQuantity(productId, delta) {
    const ctrl = document.querySelector(`.qty-control[data-product-id="${productId}"]`);
    if (!ctrl) return;
    const valEl = ctrl.querySelector('.qty-value');
    const newQty = parseInt(valEl.textContent) + delta;
    if (newQty < 1) {
        await cartRequest('/api/cart-remove.php', { product_id: productId });
        restoreAddToCartButton(productId);
        return;
    }
    valEl.textContent = newQty;
    await updateQuantity(productId, newQty);
}

function updateCartBadge(count, total) {
    const link = document.querySelector('.cart-link');
    if (!link) return;
    let badge = link.querySelector('.cart-badge');
    let totalBadge = link.querySelector('.cart-total-badge');

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'cart-badge badge';
            link.appendChild(badge);
        }
        badge.textContent = count;
        badge.classList.add('bump');
        setTimeout(() => badge.classList.remove('bump'), 300);

        if (!totalBadge) {
            totalBadge = document.createElement('div');
            totalBadge.className = 'cart-total-badge';
            link.appendChild(totalBadge);
        }
        totalBadge.textContent = formatPrice(total);
    } else {
        if (badge) badge.remove();
        if (totalBadge) totalBadge.remove();
    }
}

function replaceButtonWithQuantityControl(productId, qty) {
    const btn = document.querySelector(`.add-to-cart-btn[data-product-id="${productId}"]`);
    if (!btn) return;
    const wrap = document.createElement('div');
    wrap.className = 'qty-control';
    wrap.dataset.productId = productId;
    wrap.innerHTML = `
        <button type="button" onclick="updateProductQuantity('${productId}', -1)">−</button>
        <span class="qty-value">${qty}</span>
        <button type="button" onclick="updateProductQuantity('${productId}', 1)">+</button>
    `;
    btn.replaceWith(wrap);
}

function restoreAddToCartButton(productId) {
    const ctrl = document.querySelector(`.qty-control[data-product-id="${productId}"]`);
    if (!ctrl) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn add-to-cart-btn';
    btn.dataset.productId = productId;
    btn.innerHTML = '<i class="fas fa-shopping-cart"></i> В корзину';
    ctrl.replaceWith(btn);
}

// ============= СКРОЛЛ-ЭФФЕКТ НАВБАРА =============
window.addEventListener('scroll', () => {
    const nav = document.querySelector('.navbar');
    if (!nav) return;
    nav.classList.toggle('scrolled', window.scrollY > 30);
});

// ============= ДЕЛЕГИРОВАННЫЕ КЛИКИ =============
document.addEventListener('click', e => {
    const addBtn = e.target.closest('.add-to-cart-btn');
    if (addBtn) {
        const pid = addBtn.dataset.productId;
        if (pid) addToCart(pid, 1);
    }

    const wishBtn = e.target.closest('.wishlist-btn');
    if (wishBtn) {
        wishBtn.classList.toggle('active');
        const active = wishBtn.classList.contains('active');
        showNotification(active ? 'Добавлено в избранное' : 'Убрано из избранного', active ? 'success' : 'info');
        // TODO: AJAX к /api/wishlist.php когда добавим
    }
});

// ============= АНИМАЦИИ ПОЯВЛЕНИЯ (AOS-lite) =============
document.addEventListener('DOMContentLoaded', () => {
    const items = document.querySelectorAll('[data-animate]');
    if (!('IntersectionObserver' in window) || items.length === 0) {
        items.forEach(i => i.classList.add('in-view'));
        return;
    }
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(en => {
            if (en.isIntersecting) {
                const delay = en.target.dataset.animateDelay || 0;
                setTimeout(() => en.target.classList.add('in-view'), delay);
                obs.unobserve(en.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(i => obs.observe(i));
});

// ============= LAZY-LOAD КАРТИНОК =============
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('img:not([loading])').forEach(img => img.loading = 'lazy');
});

// ============= ПОИСК С АВТОКОМПЛИТОМ =============
let searchTimer;
function initNavbarSearch() {
    const input = document.getElementById('navbar-search-input');
    const results = document.getElementById('navbar-search-results');
    if (!input || !results) return;

    input.addEventListener('input', e => {
        clearTimeout(searchTimer);
        const q = e.target.value.trim();
        if (q.length < 2) { results.classList.remove('show'); return; }
        searchTimer = setTimeout(() => fetchSearch(q, results), 250);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.navbar-search')) results.classList.remove('show');
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = input.value.trim();
            if (q) window.location.href = `/catalog.php?search=${encodeURIComponent(q)}`;
        }
    });
}

async function fetchSearch(query, container) {
    try {
        const res = await fetch(`/api/search.php?q=${encodeURIComponent(query)}`);
        const data = await res.json();
        if (data.success && data.results.length > 0) {
            container.innerHTML = data.results.map(p => `
                <a href="/product.php?id=${p.id}" class="search-result-item">
                    <img src="${p.image || '/assets/images/placeholder.png'}" alt="">
                    <div>
                        <div style="font-weight:600">${p.name}</div>
                        <div style="font-size:0.85rem;color:var(--primary);font-weight:700">${formatPrice(p.price)}</div>
                    </div>
                </a>
            `).join('');
        } else {
            container.innerHTML = `<div class="search-result-item" style="cursor:default;color:var(--text-muted)">Ничего не найдено</div>`;
        }
        container.classList.add('show');
    } catch (e) {
        container.innerHTML = `<div class="search-result-item" style="cursor:default;color:var(--text-muted)">Поиск временно недоступен</div>`;
        container.classList.add('show');
    }
}

document.addEventListener('DOMContentLoaded', initNavbarSearch);