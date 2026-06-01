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