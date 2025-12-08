<?php
/**
 * Plugin Name: Hotel Booking Widget for Elementor
 * Plugin URI: https://github.com/rez4156684-bot/payamakhotel
 * Description: ویجیت حرفه‌ای Elementor برای نمایش و رزرو محصولات هتل در ووکامرس - شبیه سایت‌های اقامت24 و فلای‌تودی
 * Version: 1.0.0
 * Author: PayamakHotel Team
 * Author URI: https://github.com/rez4156684-bot
 * Text Domain: hotel-booking-widget
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * Elementor tested up to: 3.20.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های افزونه
define('HBW_VERSION', '1.0.0');
define('HBW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HBW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HBW_PLUGIN_FILE', __FILE__);

/**
 * کلاس اصلی افزونه
 */
final class Hotel_Booking_Widget_Elementor {

    /**
     * نمونه تکی کلاس
     */
    private static $instance = null;

    /**
     * دریافت نمونه تکی
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * سازنده
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * راه‌اندازی افزونه
     */
    public function init() {
        // بررسی وجود Elementor
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', array($this, 'admin_notice_missing_elementor'));
            return;
        }

        // بررسی وجود WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'admin_notice_missing_woocommerce'));
            return;
        }

        // بررسی نسخه Elementor
        if (!version_compare(ELEMENTOR_VERSION, '3.0.0', '>=')) {
            add_action('admin_notices', array($this, 'admin_notice_minimum_elementor_version'));
            return;
        }

        // ثبت ویجیت‌ها
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // ثبت دسته‌بندی سفارشی
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_categories'));

        // بارگذاری اسکریپت‌ها و استایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'enqueue_widget_scripts'));

        // اضافه کردن منوی تنظیمات
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * اعلان عدم وجود Elementor
     */
    public function admin_notice_missing_elementor() {
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        $message = sprintf(
            esc_html__('برای استفاده از "%1$s" باید افزونه "%2$s" نصب و فعال باشد.', 'hotel-booking-widget'),
            '<strong>' . esc_html__('Hotel Booking Widget', 'hotel-booking-widget') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'hotel-booking-widget') . '</strong>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    /**
     * اعلان عدم وجود WooCommerce
     */
    public function admin_notice_missing_woocommerce() {
        $message = sprintf(
            esc_html__('برای استفاده از "%1$s" باید افزونه "%2$s" نصب و فعال باشد.', 'hotel-booking-widget'),
            '<strong>' . esc_html__('Hotel Booking Widget', 'hotel-booking-widget') . '</strong>',
            '<strong>' . esc_html__('WooCommerce', 'hotel-booking-widget') . '</strong>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    /**
     * اعلان نسخه حداقلی Elementor
     */
    public function admin_notice_minimum_elementor_version() {
        $message = sprintf(
            esc_html__('برای استفاده از "%1$s" باید "%2$s" حداقل نسخه %3$s باشد.', 'hotel-booking-widget'),
            '<strong>' . esc_html__('Hotel Booking Widget', 'hotel-booking-widget') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'hotel-booking-widget') . '</strong>',
            '3.0.0'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    /**
     * ثبت ویجیت‌ها
     */
    public function register_widgets($widgets_manager) {
        require_once HBW_PLUGIN_DIR . 'widgets/hotel-booking-widget.php';
        $widgets_manager->register(new \Hotel_Booking_Widget\Widgets\Hotel_Booking_Widget());
    }

    /**
     * اضافه کردن دسته‌بندی سفارشی برای ویجیت‌ها
     */
    public function add_elementor_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'hotel-booking',
            array(
                'title' => esc_html__('رزرو هتل', 'hotel-booking-widget'),
                'icon' => 'fa fa-hotel',
            )
        );
    }

    /**
     * بارگذاری اسکریپت‌ها و استایل‌ها
     */
    public function enqueue_scripts() {
        // استایل‌های اصلی
        wp_enqueue_style(
            'hotel-booking-widget-style',
            HBW_PLUGIN_URL . 'assets/css/hotel-booking-widget.css',
            array(),
            HBW_VERSION
        );

        // کتابخانه تقویم - استفاده از Flatpickr
        wp_enqueue_style(
            'flatpickr-style',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            array(),
            '4.6.13'
        );

        wp_enqueue_script(
            'flatpickr-script',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            array(),
            '4.6.13',
            true
        );

        // تقویم شمسی
        wp_enqueue_script(
            'flatpickr-fa',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fa.js',
            array('flatpickr-script'),
            '4.6.13',
            true
        );
    }

    /**
     * بارگذاری اسکریپت‌های ویجیت
     */
    public function enqueue_widget_scripts() {
        wp_enqueue_script(
            'hotel-booking-widget-script',
            HBW_PLUGIN_URL . 'assets/js/hotel-booking-widget.js',
            array('jquery', 'flatpickr-script'),
            HBW_VERSION,
            true
        );

        // ارسال داده‌ها به جاوااسکریپت
        wp_localize_script('hotel-booking-widget-script', 'hbwData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hbw_nonce'),
            'currency' => get_woocommerce_currency_symbol(),
        ));
    }

    /**
     * اضافه کردن منوی مدیریت
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Hotel Booking Widget', 'hotel-booking-widget'),
            __('Hotel Widget', 'hotel-booking-widget'),
            'manage_woocommerce',
            'hotel-booking-widget',
            array($this, 'admin_page')
        );
    }

    /**
     * صفحه تنظیمات
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('تنظیمات ویجیت رزرو هتل', 'hotel-booking-widget'); ?></h1>

            <div class="card">
                <h2><?php echo esc_html__('نحوه استفاده', 'hotel-booking-widget'); ?></h2>
                <ol>
                    <li>به ویرایشگر Elementor بروید</li>
                    <li>از بخش ویجیت‌ها، دسته "رزرو هتل" را پیدا کنید</li>
                    <li>ویجیت "رزرو هتل" را به صفحه خود اضافه کنید</li>
                    <li>محصول هتل مورد نظر را انتخاب کنید</li>
                    <li>تنظیمات ظاهری را سفارشی‌سازی کنید</li>
                </ol>

                <h3><?php echo esc_html__('ویژگی‌ها', 'hotel-booking-widget'); ?></h3>
                <ul>
                    <li>✅ طراحی مدرن و حرفه‌ای شبیه سایت‌های بزرگ</li>
                    <li>✅ کاملاً ریسپانسیو برای موبایل</li>
                    <li>✅ تقویم شمسی فارسی</li>
                    <li>✅ نمایش قیمت پویا</li>
                    <li>✅ گالری تصاویر</li>
                    <li>✅ یکپارچه با WooCommerce</li>
                    <li>✅ سفارشی‌سازی کامل با Elementor</li>
                </ul>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('پشتیبانی', 'hotel-booking-widget'); ?></h2>
                <p><?php echo esc_html__('نسخه:', 'hotel-booking-widget'); ?> <strong><?php echo HBW_VERSION; ?></strong></p>
                <p><?php echo esc_html__('برای گزارش مشکل یا درخواست ویژگی جدید:', 'hotel-booking-widget'); ?></p>
                <a href="https://github.com/rez4156684-bot/payamakhotel/issues" target="_blank" class="button button-primary">
                    <?php echo esc_html__('گزارش مشکل', 'hotel-booking-widget'); ?>
                </a>
            </div>
        </div>

        <style>
            .card {
                background: white;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccc;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .card h2, .card h3 {
                margin-top: 0;
            }
        </style>
        <?php
    }
}

// راه‌اندازی افزونه
Hotel_Booking_Widget_Elementor::get_instance();
