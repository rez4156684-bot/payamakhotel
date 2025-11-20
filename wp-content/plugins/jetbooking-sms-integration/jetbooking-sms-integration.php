<?php
/**
 * Plugin Name: JetBooking SMS Integration
 * Plugin URI: https://github.com/rez4156684-bot/payamakhotel
 * Description: یکپارچه‌سازی اطلاعات رزرو JetBooking با پیامک‌های ووکامرس - افزودن اطلاعات هتل، اتاق و تاریخ‌های ورود و خروج به پیامک‌ها
 * Version: 1.0.0
 * Author: PayamakHotel Team
 * Author URI: https://github.com/rez4156684-bot
 * Text Domain: jetbooking-sms-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های افزونه
define('JETBOOKING_SMS_VERSION', '1.0.0');
define('JETBOOKING_SMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JETBOOKING_SMS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * کلاس اصلی افزونه یکپارچه‌سازی JetBooking با SMS
 */
class JetBooking_SMS_Integration {

    /**
     * نمونه تکی کلاس
     *
     * @var JetBooking_SMS_Integration
     */
    private static $instance = null;

    /**
     * دریافت نمونه تکی کلاس
     *
     * @return JetBooking_SMS_Integration
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * سازنده کلاس
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * راه‌اندازی هوک‌ها و فیلترها
     */
    private function init_hooks() {
        // بررسی وجود افزونه‌های مورد نیاز
        add_action('admin_init', array($this, 'check_required_plugins'));

        // اضافه کردن اطلاعات رزرو به متادیتای سفارش
        add_action('woocommerce_checkout_create_order', array($this, 'save_booking_data_to_order'), 10, 2);

        // فیلترهای مختلف برای افزونه‌های پیامکی مختلف
        // برای افزونه WooCommerce SMS
        add_filter('woocommerce_sms_message', array($this, 'add_booking_details_to_sms'), 10, 2);

        // برای افزونه Digits
        add_filter('digits_wc_sms_message', array($this, 'add_booking_details_to_sms'), 10, 2);

        // برای افزونه Persian WooCommerce SMS
        add_filter('persianwoosms_sms_body', array($this, 'add_booking_details_to_sms'), 10, 2);

        // برای افزونه YITH WooCommerce SMS Notifications
        add_filter('ywsn_sms_message_content', array($this, 'add_booking_details_to_sms'), 10, 2);

        // فیلتر عمومی برای سایر افزونه‌های پیامکی
        add_filter('woocommerce_order_sms_message', array($this, 'add_booking_details_to_sms'), 10, 2);

        // اضافه کردن متغیرهای سفارشی به الگوی پیامک
        add_filter('woocommerce_email_format_string_replace', array($this, 'add_custom_sms_variables'), 10, 2);
    }

    /**
     * بررسی نصب بودن افزونه‌های مورد نیاز
     */
    public function check_required_plugins() {
        $required_plugins = array(
            'woocommerce/woocommerce.php' => 'WooCommerce',
        );

        $missing_plugins = array();

        foreach ($required_plugins as $plugin_path => $plugin_name) {
            if (!is_plugin_active($plugin_path)) {
                $missing_plugins[] = $plugin_name;
            }
        }

        if (!empty($missing_plugins)) {
            add_action('admin_notices', function() use ($missing_plugins) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>افزونه یکپارچه‌سازی JetBooking با SMS:</strong>
                        برای استفاده از این افزونه، نصب و فعال‌سازی افزونه‌های زیر الزامی است:
                        <?php echo implode(', ', $missing_plugins); ?>
                    </p>
                </div>
                <?php
            });
        }
    }

    /**
     * ذخیره اطلاعات رزرو در سفارش
     *
     * @param WC_Order $order سفارش
     * @param array $data داده‌های فرم تسویه
     */
    public function save_booking_data_to_order($order, $data) {
        // استخراج اطلاعات رزرو از آیتم‌های سفارش
        $booking_details = $this->extract_booking_details($order);

        if (!empty($booking_details)) {
            // ذخیره اطلاعات رزرو در متادیتای سفارش
            $order->update_meta_data('_jetbooking_details', $booking_details);
            $order->update_meta_data('_jetbooking_hotel_name', $booking_details['hotel_name']);
            $order->update_meta_data('_jetbooking_room_name', $booking_details['room_name']);
            $order->update_meta_data('_jetbooking_checkin_date', $booking_details['checkin_date']);
            $order->update_meta_data('_jetbooking_checkout_date', $booking_details['checkout_date']);
            $order->update_meta_data('_jetbooking_nights', $booking_details['nights']);
        }
    }

    /**
     * استخراج اطلاعات رزرو از سفارش
     *
     * @param WC_Order $order سفارش
     * @return array اطلاعات رزرو
     */
    private function extract_booking_details($order) {
        $booking_details = array(
            'hotel_name' => '',
            'room_name' => '',
            'checkin_date' => '',
            'checkout_date' => '',
            'nights' => 0,
            'adults' => 0,
            'children' => 0,
        );

        // دریافت آیتم‌های سفارش
        $items = $order->get_items();

        foreach ($items as $item_id => $item) {
            $product_id = $item->get_product_id();

            // بررسی اینکه آیا این محصول یک رزرو JetBooking است
            $is_booking = get_post_meta($product_id, '_apartment_booking', true);

            if ($is_booking === 'yes' || $this->is_jetbooking_product($product_id)) {
                // استخراج اطلاعات از متادیتای آیتم
                $item_meta = $item->get_meta_data();

                foreach ($item_meta as $meta) {
                    $meta_data = $meta->get_data();
                    $key = $meta_data['key'];
                    $value = $meta_data['value'];

                    // استخراج اطلاعات بر اساس کلیدهای مختلف
                    switch ($key) {
                        case 'apartment_unit':
                        case '_apartment_unit':
                        case 'jet_abaf_unit':
                            $booking_details['hotel_name'] = $this->get_apartment_name($value);
                            break;

                        case 'check_in_date':
                        case '_check_in_date':
                        case 'apartment_check_in':
                            $booking_details['checkin_date'] = $this->format_date($value);
                            break;

                        case 'check_out_date':
                        case '_check_out_date':
                        case 'apartment_check_out':
                            $booking_details['checkout_date'] = $this->format_date($value);
                            break;
                    }
                }

                // نام اتاق از نام محصول
                $booking_details['room_name'] = $item->get_name();

                // محاسبه تعداد شب‌ها
                if (!empty($booking_details['checkin_date']) && !empty($booking_details['checkout_date'])) {
                    $booking_details['nights'] = $this->calculate_nights(
                        $booking_details['checkin_date'],
                        $booking_details['checkout_date']
                    );
                }
            }
        }

        // اگر نام هتل خالی است، از تنظیمات سایت استفاده کن
        if (empty($booking_details['hotel_name'])) {
            $booking_details['hotel_name'] = get_bloginfo('name');
        }

        return $booking_details;
    }

    /**
     * بررسی اینکه آیا محصول یک محصول JetBooking است
     *
     * @param int $product_id شناسه محصول
     * @return bool
     */
    private function is_jetbooking_product($product_id) {
        // روش‌های مختلف شناسایی محصولات JetBooking
        $meta_keys = array(
            '_apartment_booking',
            'jet_abaf_apartment_booking',
            '_jet_booking_item',
        );

        foreach ($meta_keys as $meta_key) {
            $value = get_post_meta($product_id, $meta_key, true);
            if ($value === 'yes' || $value === '1' || $value === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * دریافت نام هتل/آپارتمان
     *
     * @param int $unit_id شناسه واحد
     * @return string نام هتل
     */
    private function get_apartment_name($unit_id) {
        if (empty($unit_id)) {
            return '';
        }

        $unit = get_post($unit_id);

        if ($unit && !is_wp_error($unit)) {
            return $unit->post_title;
        }

        return '';
    }

    /**
     * فرمت کردن تاریخ
     *
     * @param string $date تاریخ
     * @return string تاریخ فرمت شده
     */
    private function format_date($date) {
        if (empty($date)) {
            return '';
        }

        // اگر تاریخ قبلاً فرمت شده است
        if (is_string($date) && !is_numeric($date)) {
            return $date;
        }

        // اگر تاریخ به صورت timestamp است
        if (is_numeric($date)) {
            // استفاده از تقویم شمسی اگر تابع موجود باشد
            if (function_exists('parsidate')) {
                return parsidate('Y/m/d', $date);
            }
            return date('Y/m/d', $date);
        }

        return $date;
    }

    /**
     * محاسبه تعداد شب‌ها
     *
     * @param string $checkin_date تاریخ ورود
     * @param string $checkout_date تاریخ خروج
     * @return int تعداد شب‌ها
     */
    private function calculate_nights($checkin_date, $checkout_date) {
        try {
            $checkin = new DateTime($checkin_date);
            $checkout = new DateTime($checkout_date);
            $interval = $checkin->diff($checkout);
            return $interval->days;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * اضافه کردن اطلاعات رزرو به متن پیامک
     *
     * @param string $message متن پیامک
     * @param WC_Order|int $order سفارش یا شناسه سفارش
     * @return string متن پیامک با اطلاعات رزرو
     */
    public function add_booking_details_to_sms($message, $order) {
        // دریافت شیء سفارش
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return $message;
        }

        // دریافت اطلاعات رزرو از متادیتای سفارش
        $booking_details = $order->get_meta('_jetbooking_details');

        // اگر اطلاعات رزرو موجود نیست، سعی کن آن را استخراج کن
        if (empty($booking_details)) {
            $booking_details = $this->extract_booking_details($order);
            if (!empty($booking_details)) {
                $order->update_meta_data('_jetbooking_details', $booking_details);
                $order->save();
            }
        }

        // اگر هنوز اطلاعات رزرو موجود نیست، پیامک را بدون تغییر برگردان
        if (empty($booking_details) || empty($booking_details['room_name'])) {
            return $message;
        }

        // ایجاد متن اطلاعات رزرو
        $booking_info = $this->format_booking_info($booking_details);

        // جایگزینی متغیرهای سفارشی در پیامک
        $message = str_replace('{hotel_name}', $booking_details['hotel_name'], $message);
        $message = str_replace('{room_name}', $booking_details['room_name'], $message);
        $message = str_replace('{checkin_date}', $booking_details['checkin_date'], $message);
        $message = str_replace('{checkout_date}', $booking_details['checkout_date'], $message);
        $message = str_replace('{nights}', $booking_details['nights'], $message);
        $message = str_replace('{booking_info}', $booking_info, $message);

        // اگر پیامک فقط شامل کلمه "رزرو" است، کل آن را با اطلاعات رزرو جایگزین کن
        if (trim($message) === 'رزرو' || trim($message) === 'reservation') {
            $message = $booking_info;
        }

        // اگر پیامک اطلاعات رزرو را ندارد، آن را اضافه کن
        if (strpos($message, $booking_details['room_name']) === false) {
            $message .= "\n\n" . $booking_info;
        }

        return $message;
    }

    /**
     * فرمت کردن اطلاعات رزرو برای نمایش در پیامک
     *
     * @param array $booking_details اطلاعات رزرو
     * @return string متن فرمت شده
     */
    private function format_booking_info($booking_details) {
        $info = '';

        if (!empty($booking_details['hotel_name'])) {
            $info .= "هتل: " . $booking_details['hotel_name'] . "\n";
        }

        if (!empty($booking_details['room_name'])) {
            $info .= "اتاق: " . $booking_details['room_name'] . "\n";
        }

        if (!empty($booking_details['checkin_date'])) {
            $info .= "ورود: " . $booking_details['checkin_date'] . "\n";
        }

        if (!empty($booking_details['checkout_date'])) {
            $info .= "خروج: " . $booking_details['checkout_date'];
        }

        if (!empty($booking_details['nights'])) {
            $info .= "\n" . "تعداد شب: " . $booking_details['nights'];
        }

        return $info;
    }

    /**
     * اضافه کردن متغیرهای سفارشی به الگوی پیامک
     *
     * @param array $replace آرایه جایگزینی
     * @param WC_Order $order سفارش
     * @return array
     */
    public function add_custom_sms_variables($replace, $order) {
        if (!is_a($order, 'WC_Order')) {
            return $replace;
        }

        $booking_details = $order->get_meta('_jetbooking_details');

        if (empty($booking_details)) {
            $booking_details = $this->extract_booking_details($order);
        }

        if (!empty($booking_details)) {
            $replace['{hotel_name}'] = $booking_details['hotel_name'] ?? '';
            $replace['{room_name}'] = $booking_details['room_name'] ?? '';
            $replace['{checkin_date}'] = $booking_details['checkin_date'] ?? '';
            $replace['{checkout_date}'] = $booking_details['checkout_date'] ?? '';
            $replace['{nights}'] = $booking_details['nights'] ?? 0;
            $replace['{booking_info}'] = $this->format_booking_info($booking_details);
        }

        return $replace;
    }
}

/**
 * راه‌اندازی افزونه
 */
function jetbooking_sms_integration_init() {
    return JetBooking_SMS_Integration::get_instance();
}

// راه‌اندازی افزونه پس از بارگذاری همه افزونه‌ها
add_action('plugins_loaded', 'jetbooking_sms_integration_init');

/**
 * فعال‌سازی افزونه
 */
register_activation_hook(__FILE__, function() {
    // بررسی نسخه PHP
    if (version_compare(PHP_VERSION, '7.2', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'این افزونه نیاز به PHP نسخه 7.2 یا بالاتر دارد. نسخه فعلی شما: ' . PHP_VERSION,
            'خطای فعال‌سازی افزونه',
            array('back_link' => true)
        );
    }

    // بررسی نصب بودن WooCommerce
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'این افزونه نیاز به نصب و فعال بودن افزونه WooCommerce دارد.',
            'خطای فعال‌سازی افزونه',
            array('back_link' => true)
        );
    }

    // پاک کردن کش
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
});

/**
 * غیرفعال‌سازی افزونه
 */
register_deactivation_hook(__FILE__, function() {
    // پاک کردن کش
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
});
