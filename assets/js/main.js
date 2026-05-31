// CSRF токен (будем получать из скрытого поля на странице)
let csrfToken = '';

// Функция для получения CSRF токена
function getCSRFToken() {
    // Ищем по ID (глобальный токен из header)
    const csrfById = document.getElementById('csrf_token_global');
    if (csrfById) {
        return csrfById.value;
    }
    
    // Ищем по name (в формах)
    const csrfByName = document.querySelector('input[name="csrf_token"]');
    if (csrfByName) {
        return csrfByName.value;
    }
    
    return '';
}

/**
 * Эффект прокрутки для навбара
 */
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
});

/**
 * Добавление товара в корзину
 */
function addToCart(productId, quantity = 1) {
    const token = getCSRFToken();
    if (!token) {
        console.error('CSRF token not found');
        showNotification('Ошибка безопасности', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', token);
    
    fetch('/api/cart-add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartBadge(data.cartCount, data.cartTotal);
            
            // Заменяем кнопку на контрол количества
            const qty = data.currentQty || 1;
            replaceButtonWithQuantityControl(productId, qty);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Произошла ошибка', 'error');
    });
}

/**
 * Удаление товара из корзины
 */
function removeFromCart(productId) {
    const token = getCSRFToken();
    if (!token) {
        console.error('CSRF token not found');
        showNotification('Ошибка безопасности', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('csrf_token', token);
    
    fetch('/api/cart-remove.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartBadge(data.cartCount, data.cartTotal);
            
            // Удаляем строку из таблицы
            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (row) {
                row.remove();
            }
            
            // Обновляем итоговую сумму
            const cartTotalElement = document.getElementById('cartTotal');
            if (cartTotalElement) {
                cartTotalElement.textContent = formatPrice(data.cartTotal);
            }
            
            // Если корзина пуста, перезагружаем страницу
            if (data.cartCount === 0) {
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Произошла ошибка', 'error');
    });
}

/**
 * Обновление количества товара в корзине
 */
function updateQuantity(productId, quantity) {
    if (quantity < 1) {
        removeFromCart(productId);
        return;
    }
    
    const token = getCSRFToken();
    if (!token) {
        console.error('CSRF token not found');
        showNotification('Ошибка безопасности', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', token);
    
    fetch('/api/cart-update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем input количества
            const qtyInput = document.getElementById(`qty-${productId}`);
            if (qtyInput) {
                qtyInput.value = quantity;
            }
            
            // Обновляем кнопки +/- с новыми значениями
            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (row) {
                const buttons = row.querySelectorAll('.quantity-control button');
                if (buttons.length >= 2) {
                    // Кнопка минус
                    buttons[0].setAttribute('onclick', `updateQuantity(${productId}, ${Math.max(1, quantity - 1)})`);
                    // Кнопка плюс
                    buttons[1].setAttribute('onclick', `updateQuantity(${productId}, ${quantity + 1})`);
                }
            }
            
            // Обновляем сумму товара
            const itemTotalElement = document.querySelector(`.item-total[data-product-id="${productId}"]`);
            if (itemTotalElement) {
                itemTotalElement.textContent = formatPrice(data.itemTotal);
            }
            
            // Обновляем итоговую сумму
            const cartTotalElement = document.getElementById('cartTotal');
            if (cartTotalElement) {
                cartTotalElement.textContent = formatPrice(data.cartTotal);
            }
            
            // Обновляем badge
            updateCartBadge(data.cartCount, data.cartTotal);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Произошла ошибка', 'error');
    });
}

/**
 * Обновление badge корзины
 */
function updateCartBadge(count, total = null) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-block';
            
            // Добавляем анимацию bump
            badge.classList.remove('bump');
            void badge.offsetWidth; // Trigger reflow
            badge.classList.add('bump');
            
            // Обновляем сумму корзины если передана
            if (total !== null) {
                const cartLink = badge.closest('.cart-link');
                if (cartLink) {
                    let totalBadge = cartLink.querySelector('.cart-total-badge');
                    if (!totalBadge) {
                        totalBadge = document.createElement('div');
                        totalBadge.className = 'cart-total-badge';
                        totalBadge.style.cssText = 'font-size: 0.7rem; color: var(--primary-color); font-weight: 600; text-align: center; margin-top: 2px;';
                        cartLink.appendChild(totalBadge);
                    }
                    // Форматируем сумму
                    const formattedTotal = total.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}).replace('.', ',') + ' ₽';
                    totalBadge.textContent = formattedTotal;
                }
            }
        } else {
            badge.style.display = 'none';
            // Скрываем сумму если корзина пуста
            const cartLink = badge.closest('.cart-link');
            if (cartLink) {
                const totalBadge = cartLink.querySelector('.cart-total-badge');
                if (totalBadge) {
                    totalBadge.remove();
                }
            }
        }
    });
}

/**
 * Показ уведомления
 */
function showNotification(message, type = 'success') {
    // Создаем контейнер для уведомлений если его нет
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    // Создаем уведомление
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger'} me-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Автоматически скрываем через 3 секунды
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

/**
 * Форматирование цены
 */
function formatPrice(price) {
    return parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
}

/**
 * Замена кнопки "В корзину" на контрол +/-
 */
function replaceButtonWithQuantityControl(productId, currentQty = 1) {
    // Ищем все кнопки с этим product ID
    const buttons = document.querySelectorAll(`.add-to-cart-btn[data-product-id="${productId}"]`);
    
    buttons.forEach(button => {
        // Создаем контрол количества
        const controlDiv = document.createElement('div');
        controlDiv.className = 'quantity-control-inline';
        controlDiv.innerHTML = `
            <button type="button" class="btn-qty-minus" onclick="updateProductQuantity(${productId}, -1)">-</button>
            <span class="qty-display" id="qty-display-${productId}">${currentQty}</span>
            <button type="button" class="btn-qty-plus" onclick="updateProductQuantity(${productId}, 1)">+</button>
        `;
        
        // Заменяем кнопку на контрол
        button.parentNode.replaceChild(controlDiv, button);
    });
}

/**
 * Обновление количества товара (для кнопок +/- на карточках)
 */
function updateProductQuantity(productId, delta) {
    const display = document.getElementById(`qty-display-${productId}`);
    if (!display) return;
    
    let currentQty = parseInt(display.textContent) || 0;
    let newQty = currentQty + delta;
    
    if (newQty < 1) {
        // Если количество 0, возвращаем кнопку "В корзину"
        restoreAddToCartButton(productId);
        removeFromCart(productId);
        return;
    }
    
    // Обновляем отображение
    display.textContent = newQty;
    
    // Отправляем на сервер
    const token = getCSRFToken();
    if (!token) {
        console.error('CSRF token not found');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', newQty);
    formData.append('csrf_token', token);
    
    fetch('/api/cart-update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartBadge(data.cartCount, data.cartTotal);
            showNotification('Количество обновлено', 'success');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

/**
 * Восстановление кнопки "В корзину"
 */
function restoreAddToCartButton(productId) {
    const controls = document.querySelectorAll('.quantity-control-inline');
    
    controls.forEach(control => {
        const minusBtn = control.querySelector('.btn-qty-minus');
        if (minusBtn) {
            const onclickAttr = minusBtn.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(productId)) {
                // Создаем кнопку "В корзину"
                const addBtn = document.createElement('button');
                addBtn.className = 'btn btn-primary w-100 add-to-cart-btn';
                addBtn.setAttribute('data-product-id', productId);
                addBtn.setAttribute('data-product-name', '');
                addBtn.innerHTML = '<i class="fas fa-cart-plus me-2"></i>В корзину';
                
                // Добавляем обработчик клика
                addBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    addToCart(productId, 1);
                });
                
                // Заменяем контрол на кнопку
                control.parentNode.replaceChild(addBtn, control);
            }
        }
    });
}

/**
 * Обработчики событий для кнопок "В корзину"
 */
document.addEventListener('click', function(e) {
    const button = e.target.closest('.add-to-cart-btn');
    if (button) {
        e.preventDefault();
        const productId = button.dataset.productId;
        const productName = button.dataset.productName;
        
        addToCart(productId, 1);
    }
});

/**
 * Анимация появления элементов при скролле
 */
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-fade-in');
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Наблюдаем за элементами с классом animate-fade-in
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-fade-in');
    elements.forEach(el => observer.observe(el));
});

/**
 * Инновационная анимация кнопки при клике
 */
document.addEventListener('click', function(e) {
    const button = e.target.closest('.add-to-cart-btn');
    if (button) {
        // Создаем ripple эффект
        const ripple = document.createElement('span');
        ripple.style.position = 'absolute';
        ripple.style.borderRadius = '50%';
        ripple.style.background = 'rgba(255, 255, 255, 0.6)';
        ripple.style.transform = 'scale(0)';
        ripple.style.animation = 'ripple 0.6s linear';
        ripple.style.pointerEvents = 'none';
        
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        
        button.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    }
});

// Добавляем keyframes для ripple анимации
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
