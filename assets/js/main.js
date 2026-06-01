function getCSRFToken() {
    const csrfById = document.getElementById('csrf_token_global');
    if (csrfById) return csrfById.value;
    const csrfByName = document.querySelector('input[name="csrf_token"]');
    if (csrfByName) return csrfByName.value;
    return '';
}

/* ---------- THEME TOGGLE ---------- */
(function() {
    const root = document.documentElement;

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        try { localStorage.setItem('theme', theme); } catch(e) {}

        document.querySelectorAll('.theme-switch').forEach(sw => {
            sw.checked = (theme === 'dark');
        });
        document.querySelectorAll('.theme-label').forEach(lbl => {
            lbl.textContent = theme === 'dark' ? 'Светлая тема' : 'Тёмная тема';
        });
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', theme === 'dark' ? '#1e293b' : '#6366f1');
    }

    function toggleTheme(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        const current = root.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
        return false;
    }

    function init() {
        const saved = localStorage.getItem('theme');
        const current = saved || root.getAttribute('data-theme') || 'light';
        applyTheme(current);

        // Триггер в дропдауне (авторизованный пользователь)
        const toggleItem = document.getElementById('themeToggle');
        if (toggleItem) {
            toggleItem.addEventListener('click', toggleTheme);
        }

        // Триггер в навбаре (гость)
        const toggleGuest = document.getElementById('themeToggleGuest');
        if (toggleGuest) {
            toggleGuest.addEventListener('click', toggleTheme);
        }

        // Прямой клик по самому ползунку (input checkbox)
        document.querySelectorAll('.theme-switch').forEach(sw => {
            sw.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                toggleTheme();
            });
            sw.addEventListener('change', function(e) {
                e.stopPropagation();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.toggleTheme = toggleTheme;
    window.applyTheme  = applyTheme;
})();

/* ---------- NAVBAR SCROLL EFFECT ---------- */
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
    }
});

/* ---------- ДОБАВИТЬ В КОРЗИНУ ---------- */
function addToCart(productId, quantity = 1) {
    const token = getCSRFToken();
    if (!token) {
        showNotification('Ошибка безопасности', 'error');
        return;
    }
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', token);

    fetch('/api/cart-add.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                updateCartBadge(data.cartCount, data.cartTotal);
                const qty = data.currentQty || 1;
                replaceButtonWithQuantityControl(productId, qty);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showNotification('Произошла ошибка', 'error');
        });
}

/* ---------- УДАЛИТЬ ИЗ КОРЗИНЫ ---------- */
function removeFromCart(productId) {
    const token = getCSRFToken();
    if (!token) { showNotification('Ошибка безопасности', 'error'); return; }

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('csrf_token', token);

    fetch('/api/cart-remove.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                updateCartBadge(data.cartCount, data.cartTotal);

                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) row.remove();

                const cartTotalElement = document.getElementById('cartTotal');
                if (cartTotalElement) cartTotalElement.textContent = formatPrice(data.cartTotal);

                if (data.cartCount === 0) {
                    setTimeout(() => window.location.reload(), 800);
                }
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showNotification('Произошла ошибка', 'error');
        });
}

/* ---------- ОБНОВИТЬ КОЛ-ВО (таблица корзины) ---------- */
const _pendingCartUpdates = new Set();

function updateQuantity(productId, quantity) {
    if (quantity < 1) { removeFromCart(productId); return; }
    if (_pendingCartUpdates.has(productId)) return;
    _pendingCartUpdates.add(productId);

    const token = getCSRFToken();
    if (!token) {
        _pendingCartUpdates.delete(productId);
        showNotification('Ошибка безопасности', 'error');
        return;
    }

    const row = document.querySelector(`tr[data-product-id="${productId}"]`);
    const buttons = row ? row.querySelectorAll('.quantity-control button') : [];
    buttons.forEach(b => b.disabled = true);

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', token);

    fetch('/api/cart-update.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // 1. Input количества
                const qtyInput = document.getElementById(`qty-${productId}`);
                if (qtyInput) qtyInput.value = quantity;

                // 2. Перепривязка onclick на кнопках +/−
                if (row) {
                    const decreaseBtn = row.querySelector('.btn-qty-decrease');
                    const increaseBtn = row.querySelector('.btn-qty-increase');
                    if (decreaseBtn) {
                        decreaseBtn.setAttribute('onclick', `updateQuantity(${productId}, ${quantity - 1})`);
                    }
                    if (increaseBtn) {
                        increaseBtn.setAttribute('onclick', `updateQuantity(${productId}, ${quantity + 1})`);
                    }
                }

                // 3. Сумма по товару
                const itemTotal = document.querySelector(`.item-total[data-product-id="${productId}"]`);
                if (itemTotal) {
                    itemTotal.textContent = formatPrice(data.itemTotal);
                    flashUpdate(itemTotal);
                }

                // 4. Общая сумма
                const cartTotalElement = document.getElementById('cartTotal');
                if (cartTotalElement) {
                    cartTotalElement.textContent = formatPrice(data.cartTotal);
                    flashUpdate(cartTotalElement);
                }

                // 5. Счётчик "Товары (N шт.)"
                const itemsCount = document.getElementById('cartItemsCount');
                if (itemsCount) {
                    itemsCount.textContent = data.cartCount;
                    flashUpdate(itemsCount);
                }

                // 6. Бейдж в навбаре
                updateCartBadge(data.cartCount, data.cartTotal);
            } else {
                showNotification(data.message || 'Ошибка обновления', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showNotification('Произошла ошибка', 'error');
        })
        .finally(() => {
            _pendingCartUpdates.delete(productId);
            // Все кнопки снова активны — никаких disabled
            if (row) {
                const decreaseBtn = row.querySelector('.btn-qty-decrease');
                const increaseBtn = row.querySelector('.btn-qty-increase');
                if (decreaseBtn) decreaseBtn.disabled = false;
                if (increaseBtn) increaseBtn.disabled = false;
            }
        });
}

/* ---------- ПОДСВЕТКА ОБНОВЛЁННОГО ЭЛЕМЕНТА ---------- */
function flashUpdate(element) {
    if (!element) return;
    element.classList.remove('cart-flash');
    void element.offsetWidth;
    element.classList.add('cart-flash');
}

/* ---------- ПОДСВЕТКА ОБНОВЛЁННОГО ЭЛЕМЕНТА ---------- */
function flashUpdate(element) {
    if (!element) return;
    element.classList.remove('cart-flash');
    void element.offsetWidth; // перезапуск анимации
    element.classList.add('cart-flash');
}

/* ---------- BADGE КОРЗИНЫ ---------- */
function updateCartBadge(count, total = null) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-block';
            badge.classList.remove('bump');
            void badge.offsetWidth;
            badge.classList.add('bump');

            if (total !== null) {
                const cartLink = badge.closest('.cart-link');
                if (cartLink) {
                    let totalBadge = cartLink.querySelector('.cart-total-badge');
                    if (!totalBadge) {
                        totalBadge = document.createElement('div');
                        totalBadge.className = 'cart-total-badge';
                        cartLink.appendChild(totalBadge);
                    }
                    totalBadge.textContent = formatPrice(total);
                }
            }
        } else {
            badge.style.display = 'none';
            const cartLink = badge.closest('.cart-link');
            if (cartLink) {
                const totalBadge = cartLink.querySelector('.cart-total-badge');
                if (totalBadge) totalBadge.remove();
            }
        }
    });
}

/* ---------- УВЕДОМЛЕНИЯ ---------- */
function showNotification(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger'} me-2"></i>
            <span>${message}</span>
        </div>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/* ---------- ФОРМАТИРОВАНИЕ ЦЕНЫ ---------- */
function formatPrice(price) {
    return parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
}

/* ---------- ЗАМЕНА КНОПКИ НА КОНТРОЛ +/- ---------- */
function replaceButtonWithQuantityControl(productId, currentQty = 1) {
    const buttons = document.querySelectorAll(`.add-to-cart-btn[data-product-id="${productId}"]`);
    buttons.forEach(button => {
        const controlDiv = document.createElement('div');
        controlDiv.className = 'quantity-control-inline';
        controlDiv.innerHTML = `
            <button type="button" class="btn-qty-minus" onclick="updateProductQuantity(${productId}, -1)">-</button>
            <span class="qty-display" id="qty-display-${productId}">${currentQty}</span>
            <button type="button" class="btn-qty-plus" onclick="updateProductQuantity(${productId}, 1)">+</button>
        `;
        button.parentNode.replaceChild(controlDiv, button);
    });
}

/* ---------- +/- НА КАРТОЧКАХ ТОВАРОВ ---------- */
/* ---------- +/- НА КАРТОЧКАХ ТОВАРОВ ---------- */
function updateProductQuantity(productId, delta) {
    if (_pendingCartUpdates.has(productId)) return;

    const display = document.getElementById(`qty-display-${productId}`);
    if (!display) return;

    let currentQty = parseInt(display.textContent) || 1;
    let newQty = currentQty + delta;

    if (newQty < 1) {
        restoreAddToCartButton(productId);
        removeFromCart(productId);
        return;
    }

    _pendingCartUpdates.add(productId);

    // Блокируем кнопки на время запроса
    const controls = document.querySelectorAll(`.quantity-control-inline`);
    let targetControl = null;
    controls.forEach(c => {
        const minus = c.querySelector('.btn-qty-minus');
        if (minus && minus.getAttribute('onclick')?.includes(`${productId},`)) {
            targetControl = c;
        }
    });
    if (targetControl) {
        targetControl.querySelectorAll('button').forEach(b => b.disabled = true);
    }

    // Оптимистично обновляем display
    display.textContent = newQty;
    flashUpdate(display);

    const token = getCSRFToken();
    if (!token) {
        _pendingCartUpdates.delete(productId);
        if (targetControl) targetControl.querySelectorAll('button').forEach(b => b.disabled = false);
        return;
    }

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', newQty);
    formData.append('csrf_token', token);

    fetch('/api/cart-update.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCartBadge(data.cartCount, data.cartTotal);
            } else {
                // Откат при ошибке
                display.textContent = currentQty;
                showNotification(data.message || 'Ошибка', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            display.textContent = currentQty;
            showNotification('Произошла ошибка', 'error');
        })
        .finally(() => {
            _pendingCartUpdates.delete(productId);
            if (targetControl) targetControl.querySelectorAll('button').forEach(b => b.disabled = false);
        });
}

/* ---------- ВОССТАНОВИТЬ КНОПКУ "В КОРЗИНУ" ---------- */
function restoreAddToCartButton(productId) {
    const controls = document.querySelectorAll('.quantity-control-inline');
    controls.forEach(control => {
        const minusBtn = control.querySelector('.btn-qty-minus');
        if (minusBtn) {
            const onclickAttr = minusBtn.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(productId)) {
                const addBtn = document.createElement('button');
                addBtn.className = 'btn btn-primary w-100 add-to-cart-btn';
                addBtn.setAttribute('data-product-id', productId);
                addBtn.innerHTML = '<i class="fas fa-cart-plus me-2"></i>В корзину';
                addBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    addToCart(productId, 1);
                });
                control.parentNode.replaceChild(addBtn, control);
            }
        }
    });
}

/* ---------- ДЕЛЕГАТ КЛИК ПО "В КОРЗИНУ" ---------- */
document.addEventListener('click', function(e) {
    const button = e.target.closest('.add-to-cart-btn');
    if (button) {
        e.preventDefault();
        const productId = button.dataset.productId;
        addToCart(productId, 1);
    }
});

/* ---------- АНИМАЦИЯ ПОЯВЛЕНИЯ ПРИ СКРОЛЛЕ ---------- */
const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-fade-in');
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('[data-animate]');
    elements.forEach(el => observer.observe(el));
});

/* ---------- RIPPLE НА КНОПКАХ "В КОРЗИНУ" ---------- */
document.addEventListener('click', function(e) {
    const button = e.target.closest('.add-to-cart-btn');
    if (button) {
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,0.6);
            transform: scale(0); pointer-events: none;
            animation: ripple 0.6s linear;
        `;
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        button.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    }
});
/* ============================================
   ЖИВОЙ ПОИСК ТОВАРОВ
   ============================================ */
(function() {
    const searchInput = document.getElementById('liveSearchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchResults = document.getElementById('searchResults');
    const searchClear = document.getElementById('searchClear');
    
    if (!searchInput || !searchDropdown || !searchResults) {
        return; // На странице нет поиска — выходим
    }
    
    let debounceTimer = null;
    let currentRequestController = null;
    let activeIndex = -1;
    let currentResults = [];
    
    const SITE_URL = window.SITE_URL || '';
    const DEBOUNCE_MS = 300;
    const MIN_QUERY_LENGTH = 2;
    
    // Подсветка совпадений в названии
    function highlightMatch(text, query) {
        if (!query) return escapeHtml(text);
        const escapedText = escapeHtml(text);
        const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp('(' + escapedQuery + ')', 'gi');
        return escapedText.replace(regex, '<mark>$1</mark>');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Рендер dropdown
    function renderResults(data, query) {
        currentResults = data.results || [];
        activeIndex = -1;
        
        if (currentResults.length === 0) {
            searchResults.innerHTML = `
                <div class="search-state">
                    <i class="fas fa-search"></i>
                    <div>По запросу <strong>"${escapeHtml(query)}"</strong> ничего не найдено</div>
                </div>
            `;
        } else {
            searchResults.innerHTML = currentResults.map((item, idx) => `
                <a href="${escapeHtml(item.url)}" class="search-result-item" data-index="${idx}">
                    <img src="${escapeHtml(SITE_URL + item.image)}" 
                         alt="${escapeHtml(item.name)}" 
                         class="search-result-image"
                         onerror="this.src='${SITE_URL}/assets/images/placeholder.jpg'">
                    <div class="search-result-info">
                        <div class="search-result-name">${highlightMatch(item.name, query)}</div>
                        <div class="search-result-meta">
                            <span class="search-result-price">${escapeHtml(item.price)}</span>
                            ${item.category ? `<span class="search-result-category">• ${escapeHtml(item.category)}</span>` : ''}
                        </div>
                    </div>
                </a>
            `).join('');
        }
        
        openDropdown();
    }
    
    function renderLoading() {
        searchResults.innerHTML = `
            <div class="search-state search-state-loading">
                <i class="fas fa-circle-notch"></i>
                <div>Ищем...</div>
            </div>
        `;
        openDropdown();
    }
    
    function openDropdown() {
        searchDropdown.classList.add('open');
    }
    
    function closeDropdown() {
        searchDropdown.classList.remove('open');
        activeIndex = -1;
    }
    
    // Сам поиск
    async function doSearch(query) {
        // Отменяем предыдущий запрос если есть
        if (currentRequestController) {
            currentRequestController.abort();
        }
        
        currentRequestController = new AbortController();
        
        try {
            const response = await fetch(
                `${SITE_URL}/api/search.php?q=${encodeURIComponent(query)}`,
                { signal: currentRequestController.signal }
            );
            
            if (!response.ok) throw new Error('Network error');
            
            const data = await response.json();
            renderResults(data, query);
            
        } catch (err) {
            if (err.name === 'AbortError') return;
            
            searchResults.innerHTML = `
                <div class="search-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>Ошибка поиска. Попробуйте ещё раз.</div>
                </div>
            `;
            openDropdown();
        }
    }
    
    // Обработка ввода
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Показать/скрыть кнопку очистки
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
            closeDropdown();
            return;
        }
        
        // Меньше минимума — закрываем
        if (query.length < MIN_QUERY_LENGTH) {
            closeDropdown();
            return;
        }
        
        // Debounce
        clearTimeout(debounceTimer);
        renderLoading();
        debounceTimer = setTimeout(() => doSearch(query), DEBOUNCE_MS);
    });
    
    // Фокус — открыть если есть результаты
    searchInput.addEventListener('focus', function() {
        if (currentResults.length > 0 && searchInput.value.trim().length >= MIN_QUERY_LENGTH) {
            openDropdown();
        }
    });
    
    // Очистка
    searchClear.addEventListener('click', function() {
        searchInput.value = '';
        searchClear.classList.remove('visible');
        closeDropdown();
        searchInput.focus();
    });
    
    // Навигация стрелками
    searchInput.addEventListener('keydown', function(e) {
        const items = searchResults.querySelectorAll('.search-result-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length === 0) return;
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
            updateActiveItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length === 0) return;
            activeIndex = Math.max(activeIndex - 1, -1);
            updateActiveItem(items);
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                window.location.href = items[activeIndex].href;
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
            searchInput.blur();
        }
    });
    
    function updateActiveItem(items) {
        items.forEach((item, idx) => {
            if (idx === activeIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                item.classList.remove('active');
            }
        });
    }
    
    // Клик вне поиска — закрываем
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
            closeDropdown();
        }
    });
    
})();
/* ============================================
   СКЕЛЕТОН-ЛОАДЕРЫ
   ============================================ */
(function() {
    
    // === 1. Пульсация картинок товаров пока грузятся ===
    function initImageSkeletons() {
        const images = document.querySelectorAll('.product-card img, .product-image');
        
        images.forEach(img => {
            if (img.complete && img.naturalHeight !== 0) {
                // Уже загружено (из кэша)
                img.classList.add('loaded');
            } else {
                img.addEventListener('load', () => img.classList.add('loaded'));
                img.addEventListener('error', () => img.classList.add('loaded'));
            }
        });
    }
    
    // === 2. Прелоадер при переходе по фильтрам / пагинации ===
    function initPageTransitionLoader() {
        // Создаём оверлей-полоску
        const overlay = document.createElement('div');
        overlay.className = 'page-transition-overlay';
        document.body.appendChild(overlay);
        
        const showLoader = () => {
            overlay.classList.add('active');
            document.body.classList.add('page-loading');
        };
        
        // 2.1 Кнопка "Применить фильтры" на каталоге
        const filterBtn = document.querySelector('button[onclick="applyFilters()"]');
        if (filterBtn) {
            filterBtn.addEventListener('click', showLoader);
        }
        
        // 2.2 Enter в поле поиска каталога
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') showLoader();
            });
        }
        
        // 2.3 Клики по пагинации
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', showLoader);
        });
        
        // 2.4 Смена категории через select
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            // На случай если кто-то захочет автоприменять (на будущее)
            // Не вешаем сейчас — у тебя сейчас фильтры применяются кнопкой
        }
    }
    
    // Запуск
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initImageSkeletons();
            initPageTransitionLoader();
        });
    } else {
        initImageSkeletons();
        initPageTransitionLoader();
    }
    
})();