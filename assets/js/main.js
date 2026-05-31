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
    }

    function init() {
        applyTheme(root.getAttribute('data-theme') || localStorage.getItem('theme') || 'light');

        // Прямые обработчики на все возможные триггеры
        const ids = ['themeToggle', 'themeToggleGuest'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('click', toggleTheme);
            }
        });

        // КЛЮЧЕВОЙ ФИКС: input.theme-switch может перехватить клик сам — вешаем на него change
        document.querySelectorAll('.theme-switch').forEach(sw => {
            sw.addEventListener('change', function(e) {
                e.stopPropagation();
                applyTheme(sw.checked ? 'dark' : 'light');
            });
            sw.addEventListener('click', function(e) {
                e.stopPropagation(); // не даём Bootstrap закрыть dropdown
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.toggleTheme = toggleTheme;
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
function updateQuantity(productId, quantity) {
    if (quantity < 1) { removeFromCart(productId); return; }

    const token = getCSRFToken();
    if (!token) { showNotification('Ошибка безопасности', 'error'); return; }

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', token);

    fetch('/api/cart-update.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const qtyInput = document.getElementById(`qty-${productId}`);
                if (qtyInput) qtyInput.value = quantity;

                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    const buttons = row.querySelectorAll('.quantity-control button');
                    if (buttons.length >= 2) {
                        buttons[0].setAttribute('onclick', `updateQuantity(${productId}, ${Math.max(1, quantity - 1)})`);
                        buttons[1].setAttribute('onclick', `updateQuantity(${productId}, ${quantity + 1})`);
                    }
                }

                const itemTotal = document.querySelector(`.item-total[data-product-id="${productId}"]`);
                if (itemTotal) itemTotal.textContent = formatPrice(data.itemTotal);

                const cartTotalElement = document.getElementById('cartTotal');
                if (cartTotalElement) cartTotalElement.textContent = formatPrice(data.cartTotal);

                updateCartBadge(data.cartCount, data.cartTotal);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showNotification('Произошла ошибка', 'error');
        });
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
function updateProductQuantity(productId, delta) {
    const display = document.getElementById(`qty-display-${productId}`);
    if (!display) return;

    let currentQty = parseInt(display.textContent) || 0;
    let newQty = currentQty + delta;

    if (newQty < 1) {
        restoreAddToCartButton(productId);
        removeFromCart(productId);
        return;
    }

    display.textContent = newQty;

    const token = getCSRFToken();
    if (!token) return;

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', newQty);
    formData.append('csrf_token', token);

    fetch('/api/cart-update.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCartBadge(data.cartCount, data.cartTotal);
                showNotification('Количество обновлено', 'success');
            }
        })
        .catch(err => console.error(err));
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