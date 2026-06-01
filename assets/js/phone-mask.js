/**
 * Маска телефона: +7(9XX) XXX-XX-XX
 * Применяется ко всем input с атрибутом data-phone-mask
 */
(function() {
    'use strict';

    const MAX_DIGITS = 10; // 9XXXXXXXXX
    const PREFIX = '+7(9';

    /**
     * Извлекает только цифры абонентского номера (без 7/8 и без ведущей 9).
     * Возвращает строку до 9 цифр (XX XXX XX XX).
     */
    function extractSubscriberDigits(value) {
        let digits = value.replace(/\D/g, '');
        // Отбрасываем код страны 7 или 8
        if (digits.length > 0 && (digits[0] === '7' || digits[0] === '8')) {
            digits = digits.substring(1);
        }
        // Отбрасываем ведущую 9 (она зашита в маске)
        if (digits.length > 0 && digits[0] === '9') {
            digits = digits.substring(1);
        }
        return digits.substring(0, MAX_DIGITS - 1); // максимум 9 цифр после 9
    }

    /**
     * Форматирует цифры абонента (до 9 шт) в маску +7(9XX) XXX-XX-XX
     */
    function formatPhone(subDigits) {
        let result = PREFIX; // +7(9
        if (subDigits.length > 0) result += subDigits.substring(0, 2);
        if (subDigits.length >= 2) result += ')';
        if (subDigits.length > 2) result += ' ' + subDigits.substring(2, 5);
        if (subDigits.length > 5) result += '-' + subDigits.substring(5, 7);
        if (subDigits.length > 7) result += '-' + subDigits.substring(7, 9);
        return result;
    }

    /**
     * Возвращает позицию курсора в конце последней значимой цифры
     */
    function getCaretEnd(input) {
        return input.value.length;
    }

    /**
     * Применяет маску к одному input.
     */
    function applyMask(input) {
        if (!input.placeholder) {
            input.placeholder = '+7(9XX) XXX-XX-XX';
        }
        input.setAttribute('maxlength', '17');
        input.setAttribute('inputmode', 'tel');

        // При фокусе — если пусто, подставляем префикс
        input.addEventListener('focus', function() {
            if (this.value === '' || this.value.replace(/\D/g, '').length <= 1) {
                this.value = PREFIX;
                setTimeout(() => this.setSelectionRange(this.value.length, this.value.length), 0);
            }
        });

        // При блюре — если только префикс или меньше, очищаем
        input.addEventListener('blur', function() {
            const sub = extractSubscriberDigits(this.value);
            if (sub.length === 0) {
                this.value = '';
            }
        });

        // KEYDOWN — обрабатываем Backspace и Delete ВРУЧНУЮ
        input.addEventListener('keydown', function(e) {
            // Разрешённые служебные клавиши
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Home', 'End'].includes(e.key)) {
                return;
            }

            // Backspace — стираем последнюю цифру абонента
            if (e.key === 'Backspace') {
                e.preventDefault();
                const sub = extractSubscriberDigits(this.value);
                if (sub.length === 0) {
                    // нечего стирать дальше — оставляем префикс
                    this.value = PREFIX;
                } else {
                    const newSub = sub.substring(0, sub.length - 1);
                    this.value = formatPhone(newSub);
                }
                const pos = this.value.length;
                setTimeout(() => this.setSelectionRange(pos, pos), 0);
                return;
            }

            // Delete — то же самое что Backspace для простоты
            if (e.key === 'Delete') {
                e.preventDefault();
                const sub = extractSubscriberDigits(this.value);
                if (sub.length === 0) {
                    this.value = PREFIX;
                } else {
                    const newSub = sub.substring(0, sub.length - 1);
                    this.value = formatPhone(newSub);
                }
                const pos = this.value.length;
                setTimeout(() => this.setSelectionRange(pos, pos), 0);
                return;
            }

            // Ctrl/Cmd + A/C/V/X — разрешаем
            if ((e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x'].includes(e.key.toLowerCase())) {
                return;
            }

            // Только цифры
            if (!/^\d$/.test(e.key)) {
                e.preventDefault();
                return;
            }

            // Цифра нажата — добавляем к концу
            e.preventDefault();
            const sub = extractSubscriberDigits(this.value);
            if (sub.length >= MAX_DIGITS - 1) {
                // уже 9 цифр — не добавляем
                return;
            }
            const newSub = sub + e.key;
            this.value = formatPhone(newSub);
            const pos = this.value.length;
            setTimeout(() => this.setSelectionRange(pos, pos), 0);
        });

        // Paste — фильтруем и форматируем
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            const sub = extractSubscriberDigits(pasted);
            this.value = formatPhone(sub);
            const pos = this.value.length;
            setTimeout(() => this.setSelectionRange(pos, pos), 0);
        });

        // На случай если значение было предзаполнено (например при ошибке формы)
        if (input.value) {
            const sub = extractSubscriberDigits(input.value);
            input.value = sub.length > 0 ? formatPhone(sub) : '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('[data-phone-mask]');
        inputs.forEach(applyMask);
    });

    window.PhoneMask = { 
        apply: applyMask, 
        format: formatPhone, 
        extractDigits: extractSubscriberDigits 
    };
})();