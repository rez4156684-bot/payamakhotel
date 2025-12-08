/**
 * Hotel Booking Widget JavaScript
 * اسکریپت‌های ویجیت رزرو هتل
 * نسخه: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * کلاس اصلی ویجیت رزرو هتل
     */
    class HotelBookingWidget {
        constructor(element) {
            this.$element = $(element);
            this.$card = this.$element.find('.hbw-hotel-card');
            this.productId = this.$card.data('product-id');

            // المان‌های فرم
            this.$checkinInput = this.$element.find('.hbw-checkin');
            this.$checkoutInput = this.$element.find('.hbw-checkout');
            this.$nightsCount = this.$element.find('.hbw-nights-count');
            this.$totalPrice = this.$element.find('.hbw-total-price');
            this.$form = this.$element.find('.hbw-booking-form');

            // داده‌ها
            this.pricePerNight = this.extractPrice();
            this.checkinDate = null;
            this.checkoutDate = null;

            this.init();
        }

        /**
         * راه‌اندازی اولیه
         */
        init() {
            this.initDatePickers();
            this.initGallery();
            this.initFormValidation();
            this.bindEvents();
        }

        /**
         * استخراج قیمت هر شب از صفحه
         */
        extractPrice() {
            const priceText = this.$element.find('.hbw-price').text();
            // حذف کاماها و استخراج عدد
            const price = parseFloat(priceText.replace(/[^\d.]/g, ''));
            return isNaN(price) ? 0 : price;
        }

        /**
         * راه‌اندازی تقویم‌های انتخاب تاریخ
         */
        initDatePickers() {
            const self = this;

            // تنظیمات پایه Flatpickr
            const baseConfig = {
                locale: 'fa',
                minDate: 'today',
                dateFormat: 'Y/m/d',
                disableMobile: false,
                allowInput: false,
            };

            // تقویم ورود
            if (this.$checkinInput.length) {
                this.checkinPicker = flatpickr(this.$checkinInput[0], {
                    ...baseConfig,
                    onChange: function(selectedDates, dateStr) {
                        self.checkinDate = selectedDates[0];

                        // تنظیم حداقل تاریخ خروج (یک روز بعد از ورود)
                        if (self.checkoutPicker) {
                            const minCheckout = new Date(self.checkinDate);
                            minCheckout.setDate(minCheckout.getDate() + 1);
                            self.checkoutPicker.set('minDate', minCheckout);

                            // اگر تاریخ خروج قبل از ورود است، پاک کن
                            if (self.checkoutDate && self.checkoutDate <= self.checkinDate) {
                                self.checkoutPicker.clear();
                                self.checkoutDate = null;
                            }
                        }

                        self.calculateBooking();
                    }
                });
            }

            // تقویم خروج
            if (this.$checkoutInput.length) {
                this.checkoutPicker = flatpickr(this.$checkoutInput[0], {
                    ...baseConfig,
                    onChange: function(selectedDates, dateStr) {
                        self.checkoutDate = selectedDates[0];
                        self.calculateBooking();
                    }
                });
            }
        }

        /**
         * محاسبه تعداد شب‌ها و قیمت کل
         */
        calculateBooking() {
            if (!this.checkinDate || !this.checkoutDate) {
                this.$nightsCount.text('0');
                this.updateTotalPrice(0);
                return;
            }

            // محاسبه تعداد شب‌ها
            const timeDiff = this.checkoutDate.getTime() - this.checkinDate.getTime();
            const nights = Math.ceil(timeDiff / (1000 * 3600 * 24));

            // بروزرسانی نمایش
            this.$nightsCount.text(nights > 0 ? nights : 0);

            // محاسبه قیمت کل
            const totalPrice = nights > 0 ? this.pricePerNight * nights : 0;
            this.updateTotalPrice(totalPrice);
        }

        /**
         * بروزرسانی نمایش قیمت کل
         */
        updateTotalPrice(amount) {
            // فرمت قیمت با توجه به تنظیمات ووکامرس
            const formattedPrice = this.formatPrice(amount);
            this.$totalPrice.html(formattedPrice);
        }

        /**
         * فرمت کردن قیمت
         */
        formatPrice(amount) {
            if (typeof hbwData !== 'undefined' && hbwData.currency) {
                // استفاده از فرمت ووکامرس
                const formatted = new Intl.NumberFormat('fa-IR').format(amount);
                return `<span class="woocommerce-Price-amount amount">${formatted} ${hbwData.currency}</span>`;
            }

            // فرمت پیش‌فرض
            return new Intl.NumberFormat('fa-IR', {
                style: 'currency',
                currency: 'IRR'
            }).format(amount);
        }

        /**
         * راه‌اندازی گالری تصاویر
         */
        initGallery() {
            const $thumbs = this.$element.find('.hbw-thumb');
            const $mainImage = this.$element.find('.hbw-main-image');

            if ($thumbs.length === 0 || $mainImage.length === 0) {
                return;
            }

            $thumbs.on('click', function() {
                const newSrc = $(this).attr('src');
                const newAlt = $(this).attr('alt');

                // تغییر تصویر اصلی
                $mainImage.attr('src', newSrc).attr('alt', newAlt);

                // تغییر کلاس active
                $thumbs.removeClass('active');
                $(this).addClass('active');
            });
        }

        /**
         * اتصال رویدادها
         */
        bindEvents() {
            const self = this;

            // رویداد ارسال فرم
            this.$form.on('submit', function(e) {
                if (!self.validateForm()) {
                    e.preventDefault();
                    return false;
                }

                // اضافه کردن loading state
                self.$card.addClass('hbw-loading');
            });

            // رویداد تغییر تعداد مهمان
            this.$element.find('select[name="adults"], select[name="children"]').on('change', function() {
                // در آینده می‌توان قیمت را بر اساس تعداد مهمان تغییر داد
                console.log('Guest count changed');
            });
        }

        /**
         * اعتبارسنجی فرم قبل از ارسال
         */
        initFormValidation() {
            // اعتبارسنجی HTML5
            if (this.$form.length && this.$form[0].checkValidity) {
                this.$form[0].setAttribute('novalidate', 'novalidate');
            }
        }

        /**
         * بررسی صحت فرم
         */
        validateForm() {
            let isValid = true;
            let errorMessage = '';

            // بررسی انتخاب تاریخ ورود
            if (!this.checkinDate) {
                isValid = false;
                errorMessage = 'لطفاً تاریخ ورود را انتخاب کنید';
                this.$checkinInput.focus();
            }

            // بررسی انتخاب تاریخ خروج
            if (!this.checkoutDate) {
                isValid = false;
                errorMessage = 'لطفاً تاریخ خروج را انتخاب کنید';
                if (isValid !== false) {
                    this.$checkoutInput.focus();
                }
            }

            // بررسی صحت بازه تاریخی
            if (this.checkinDate && this.checkoutDate && this.checkoutDate <= this.checkinDate) {
                isValid = false;
                errorMessage = 'تاریخ خروج باید بعد از تاریخ ورود باشد';
                this.$checkoutInput.focus();
            }

            // نمایش پیام خطا
            if (!isValid && errorMessage) {
                this.showNotification(errorMessage, 'error');
            }

            return isValid;
        }

        /**
         * نمایش اعلان
         */
        showNotification(message, type = 'info') {
            // ساخت المان اعلان
            const $notification = $('<div>', {
                class: `hbw-notification hbw-notification-${type}`,
                text: message
            });

            // اضافه کردن به DOM
            this.$card.prepend($notification);

            // انیمیشن ورود
            $notification.fadeIn(300);

            // حذف خودکار بعد از 5 ثانیه
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        /**
         * پاک کردن فرم
         */
        reset() {
            if (this.checkinPicker) {
                this.checkinPicker.clear();
            }
            if (this.checkoutPicker) {
                this.checkoutPicker.clear();
            }
            this.checkinDate = null;
            this.checkoutDate = null;
            this.$nightsCount.text('0');
            this.updateTotalPrice(0);
        }
    }

    /**
     * راه‌اندازی ویجیت‌های موجود در صفحه
     */
    function initHotelBookingWidgets() {
        $('.hbw-hotel-card').each(function() {
            if (!$(this).data('hbw-initialized')) {
                new HotelBookingWidget($(this).parent());
                $(this).data('hbw-initialized', true);
            }
        });
    }

    /**
     * راه‌اندازی اولیه
     */
    $(document).ready(function() {
        initHotelBookingWidgets();
    });

    /**
     * راه‌اندازی مجدد برای Elementor Preview
     */
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/hotel-booking-widget.default', function($scope) {
            initHotelBookingWidgets();
        });
    });

    /**
     * Export برای استفاده در سایر قسمت‌ها
     */
    window.HotelBookingWidget = HotelBookingWidget;

})(jQuery);

/**
 * استایل‌های اعلان (Inline CSS)
 */
document.addEventListener('DOMContentLoaded', function() {
    // اضافه کردن استایل‌های اعلان به head
    const style = document.createElement('style');
    style.textContent = `
        .hbw-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            max-width: 400px;
            display: none;
            animation: slideInRight 0.3s ease;
        }

        .hbw-notification-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .hbw-notification-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .hbw-notification-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media screen and (max-width: 599px) {
            .hbw-notification {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    `;
    document.head.appendChild(style);
});

/**
 * تابع کمکی برای محاسبه تفاوت روزها
 */
function daysDifference(date1, date2) {
    const oneDay = 24 * 60 * 60 * 1000;
    return Math.round(Math.abs((date1 - date2) / oneDay));
}

/**
 * تابع کمکی برای فرمت تاریخ شمسی
 */
function formatPersianDate(date) {
    if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
        return new Intl.DateTimeFormat('fa-IR').format(date);
    }
    return date.toLocaleDateString('fa-IR');
}
