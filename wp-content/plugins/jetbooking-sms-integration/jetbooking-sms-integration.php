<?php
/**
 * Plugin Name: JetBooking SMS Integration
 * Plugin URI: https://github.com/rez4156684-bot/payamakhotel
 * Description: یکپارچه‌سازی اطلاعات رزرو JetBooking با پیامک‌های ووکامرس - افزودن اطلاعات هتل، اتاق و تاریخ‌های ورود و خروج به پیامک‌ها
 * Version: 1.2.0
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
define('JETBOOKING_SMS_VERSION', '1.2.0');
define('JETBOOKING_SMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JETBOOKING_SMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JETBOOKING_SMS_DEBUG', false); // برای دیباگ، این را true کنید

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

        // اضافه کردن منوی تنظیمات در ووکامرس
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);

        // اضافه کردن اطلاعات رزرو به متادیتای سفارش
        add_action('woocommerce_checkout_create_order', array($this, 'save_booking_data_to_order'), 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_booking_data_to_order'), 10, 2);

        // همچنین در هنگام ایجاد سفارش (برای سفارش‌های دستی)
        add_action('woocommerce_new_order', array($this, 'save_booking_data_on_new_order'), 10, 1);

        // **فیلترهای مختلف برای افزونه‌های پیامکی مختلف**

        // برای افزونه WooCommerce SMS
        add_filter('woocommerce_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه Digits
        add_filter('digits_wc_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه Persian WooCommerce SMS
        add_filter('persianwoosms_sms_body', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه YITH WooCommerce SMS Notifications
        add_filter('ywsn_sms_message_content', array($this, 'add_booking_details_to_sms'), 999, 2);

        // فیلتر عمومی برای سایر افزونه‌های پیامکی
        add_filter('woocommerce_order_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه Kavenegar
        add_filter('kavenegar_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه SMS.ir
        add_filter('smsir_wc_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه Twilio
        add_filter('twilio_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه پیامک ایرانی
        add_filter('wp_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);
        add_filter('wp_sms_msg', array($this, 'add_booking_details_to_sms'), 999, 2);

        // برای افزونه Webservice SMS
        add_filter('webservice_sms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

        // اضافه کردن متغیرهای سفارشی به الگوی ایمیل (بعضی افزونه‌ها از این استفاده می‌کنند)
        add_filter('woocommerce_email_format_string_replace', array($this, 'add_custom_sms_variables'), 999, 2);

        // برای افزونه‌هایی که از متغیرهای ووکامرس استفاده می‌کنند
        add_filter('woocommerce_email_format_string', array($this, 'replace_booking_variables'), 999, 2);

        // Action برای دستکاری مستقیم محتوای پیامک قبل از ارسال
        add_action('woocommerce_order_status_changed', array($this, 'maybe_add_booking_to_sms'), 10, 3);
    }

    /**
     * اضافه کردن منوی مدیریت
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'JetBooking SMS',
            'JetBooking SMS',
            'manage_woocommerce',
            'jetbooking-sms-integration',
            array($this, 'admin_page')
        );
    }

    /**
     * صفحه مدیریت
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>افزونه یکپارچه‌سازی JetBooking با SMS</h1>

            <div class="card">
                <h2>✅ وضعیت افزونه</h2>
                <p style="font-size: 16px; color: green;">
                    <strong>افزونه فعال است و در حال کار می‌باشد.</strong>
                </p>
                <p>این افزونه به صورت خودکار کار می‌کند و نیازی به تنظیمات خاصی ندارد.</p>
            </div>

            <div class="card">
                <h2>📝 متغیرهای موجود</h2>
                <p>شما می‌توانید از متغیرهای زیر در الگوی پیامک افزونه پیامکی خود استفاده کنید:</p>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>متغیر</th>
                            <th>توضیحات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>{hotel_name}</code></td>
                            <td>نام هتل</td>
                        </tr>
                        <tr>
                            <td><code>{room_name}</code></td>
                            <td>نام اتاق</td>
                        </tr>
                        <tr>
                            <td><code>{checkin_date}</code></td>
                            <td>تاریخ ورود</td>
                        </tr>
                        <tr>
                            <td><code>{checkout_date}</code></td>
                            <td>تاریخ خروج</td>
                        </tr>
                        <tr>
                            <td><code>{nights}</code></td>
                            <td>تعداد شب اقامت</td>
                        </tr>
                        <tr>
                            <td><code>{booking_info}</code></td>
                            <td>اطلاعات کامل رزرو (شامل همه موارد بالا)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>💡 نحوه استفاده</h2>
                <ol>
                    <li>به تنظیمات افزونه پیامکی خود بروید</li>
                    <li>الگوی پیامک را ویرایش کنید</li>
                    <li>یکی از متغیرهای بالا را در متن پیامک قرار دهید</li>
                    <li>ذخیره کنید و سفارش تستی ثبت کنید</li>
                </ol>

                <h3>مثال:</h3>
                <pre style="background: #f5f5f5; padding: 15px; direction: rtl;">مشتری گرامی

رزرو شما تایید شد:

{booking_info}

شماره سفارش: {order_number}</pre>
            </div>

            <div class="card">
                <h2>🔍 تست کارکرد</h2>
                <p>برای تست کارکرد، یک سفارش آزمایشی ثبت کنید و پیامک ارسالی را بررسی کنید.</p>

                <?php
                // نمایش آخرین سفارش با اطلاعات رزرو
                $args = array(
                    'limit' => 5,
                    'orderby' => 'date',
                    'order' => 'DESC',
                );
                $orders = wc_get_orders($args);

                if (!empty($orders)) {
                    echo '<h3>آخرین سفارشات با اطلاعات رزرو:</h3>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>شماره سفارش</th><th>نام اتاق</th><th>تاریخ ورود</th><th>تاریخ خروج</th></tr></thead>';
                    echo '<tbody>';

                    foreach ($orders as $order) {
                        $booking_details = $order->get_meta('_jetbooking_details');
                        if (!empty($booking_details) && !empty($booking_details['room_name'])) {
                            echo '<tr>';
                            echo '<td>#' . $order->get_id() . '</td>';
                            echo '<td>' . esc_html($booking_details['room_name']) . '</td>';
                            echo '<td>' . esc_html($booking_details['checkin_date']) . '</td>';
                            echo '<td>' . esc_html($booking_details['checkout_date']) . '</td>';
                            echo '</tr>';
                        }
                    }

                    echo '</tbody></table>';
                }
                ?>
            </div>

            <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                <h2>⚠️ نکات مهم</h2>
                <ul>
                    <li>اگر متغیرها در پیامک نمایش داده نمی‌شوند، ممکن است افزونه پیامکی شما از فیلتر خاصی استفاده کند.</li>
                    <li>در این صورت، نام دقیق افزونه پیامکی خود را به ما اطلاع دهید تا فیلتر مربوطه را اضافه کنیم.</li>
                    <li>سفارش‌های قدیمی ممکن است اطلاعات رزرو را نداشته باشند. حتماً با سفارش جدید تست کنید.</li>
                </ul>
            </div>
        </div>
        <style>
            .card {
                background: white;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccc;
                border-radius: 5px;
            }
            .card h2 { margin-top: 0; }
            .card code {
                background: #f5f5f5;
                padding: 3px 8px;
                border-radius: 3px;
                font-family: monospace;
                direction: ltr;
                display: inline-block;
            }
            .widefat { margin: 15px 0; }
        </style>
        <?php
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
     * ذخیره اطلاعات رزرو در سفارش جدید
     */
    public function save_booking_data_on_new_order($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->save_booking_data_to_order($order, array());
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

        if (!empty($booking_details) && !empty($booking_details['room_name'])) {
            // ذخیره اطلاعات رزرو در متادیتای سفارش
            $order->update_meta_data('_jetbooking_details', $booking_details);
            $order->update_meta_data('_jetbooking_hotel_name', $booking_details['hotel_name']);
            $order->update_meta_data('_jetbooking_room_name', $booking_details['room_name']);
            $order->update_meta_data('_jetbooking_checkin_date', $booking_details['checkin_date']);
            $order->update_meta_data('_jetbooking_checkout_date', $booking_details['checkout_date']);
            $order->update_meta_data('_jetbooking_nights', $booking_details['nights']);
            $order->save();

            // لاگ برای دیباگ
            if (JETBOOKING_SMS_DEBUG) {
                error_log('JetBooking SMS: Booking details saved for order #' . $order->get_id());
                error_log('Booking details: ' . print_r($booking_details, true));
            }
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

            // بررسی اینکه آیا این محصول یک رزرو JetBooking/JetEngine است
            $is_booking = get_post_meta($product_id, '_apartment_booking', true);

            if ($is_booking === 'yes' || $this->is_jetbooking_product($product_id)) {
                // استخراج اطلاعات از متادیتای آیتم
                $item_meta = $item->get_meta_data();

                if (JETBOOKING_SMS_DEBUG) {
                    error_log('=== Item Meta Data for Order #' . $order->get_id() . ' ===');
                }

                foreach ($item_meta as $meta) {
                    $meta_data = $meta->get_data();
                    $key = $meta_data['key'];
                    $value = $meta_data['value'];

                    if (JETBOOKING_SMS_DEBUG) {
                        error_log('Key: ' . $key . ' = ' . print_r($value, true));
                    }

                    // استخراج اطلاعات بر اساس کلیدهای مختلف
                    // JetBooking / JetEngine Keys
                    $key_lower = strtolower($key);

                    // شناسایی نام هتل/واحد
                    if (in_array($key, array('apartment_unit', '_apartment_unit', 'jet_abaf_unit', '_jet_unit_id', 'jet_unit')) ||
                        strpos($key_lower, 'unit') !== false ||
                        strpos($key_lower, 'apartment') !== false) {
                        if (is_numeric($value)) {
                            $booking_details['hotel_name'] = $this->get_apartment_name($value);
                        } else {
                            $booking_details['hotel_name'] = $value;
                        }
                    }

                    // شناسایی تاریخ ورود
                    if (in_array($key, array('check_in_date', '_check_in_date', 'apartment_check_in', 'Check in', 'تاریخ ورود', 'checkin', '_checkin', 'check-in', '_check-in')) ||
                        strpos($key_lower, 'check') !== false && strpos($key_lower, 'in') !== false ||
                        strpos($key_lower, 'ورود') !== false ||
                        strpos($key_lower, 'start') !== false && strpos($key_lower, 'date') !== false) {
                        $booking_details['checkin_date'] = $this->format_date($value);
                    }

                    // شناسایی تاریخ خروج
                    if (in_array($key, array('check_out_date', '_check_out_date', 'apartment_check_out', 'Check out', 'تاریخ خروج', 'checkout', '_checkout', 'check-out', '_check-out')) ||
                        strpos($key_lower, 'check') !== false && strpos($key_lower, 'out') !== false ||
                        strpos($key_lower, 'خروج') !== false ||
                        strpos($key_lower, 'end') !== false && strpos($key_lower, 'date') !== false) {
                        $booking_details['checkout_date'] = $this->format_date($value);
                    }
                }

                // نام اتاق از نام محصول
                if (empty($booking_details['room_name'])) {
                    $booking_details['room_name'] = $item->get_name();
                }

                // محاسبه تعداد شب‌ها
                if (!empty($booking_details['checkin_date']) && !empty($booking_details['checkout_date'])) {
                    $booking_details['nights'] = $this->calculate_nights(
                        $booking_details['checkin_date'],
                        $booking_details['checkout_date']
                    );
                }

                // اگر اطلاعات پیدا شد، از حلقه خارج شو
                if (!empty($booking_details['room_name'])) {
                    break;
                }
            }
        }

        // اگر اطلاعاتی پیدا نشد، از تمام آیتم‌ها اطلاعات بگیر
        if (empty($booking_details['room_name'])) {
            foreach ($items as $item_id => $item) {
                $booking_details['room_name'] = $item->get_name();
                break; // فقط اولین آیتم
            }
        }

        // اگر نام هتل خالی است، از تنظیمات سایت استفاده کن
        if (empty($booking_details['hotel_name'])) {
            $booking_details['hotel_name'] = get_bloginfo('name');
        }

        if (JETBOOKING_SMS_DEBUG) {
            error_log('=== Final Booking Details ===');
            error_log(print_r($booking_details, true));
        }

        return $booking_details;
    }

    /**
     * بررسی اینکه آیا محصول یک محصول JetBooking/JetEngine است
     *
     * @param int $product_id شناسه محصول
     * @return bool
     */
    private function is_jetbooking_product($product_id) {
        // روش‌های مختلف شناسایی محصولات JetBooking/JetEngine
        $meta_keys = array(
            '_apartment_booking',
            'jet_abaf_apartment_booking',
            '_jet_booking_item',
            '_jet_engine_booking',
            '_is_booking',
        );

        foreach ($meta_keys as $meta_key) {
            $value = get_post_meta($product_id, $meta_key, true);
            if ($value === 'yes' || $value === '1' || $value === 1 || $value === true) {
                return true;
            }
        }

        // بررسی تمام متاها برای کلمات کلیدی مرتبط با booking
        $all_meta = get_post_meta($product_id);
        foreach ($all_meta as $key => $value) {
            $key_lower = strtolower($key);
            if (strpos($key_lower, 'booking') !== false ||
                strpos($key_lower, 'apartment') !== false ||
                strpos($key_lower, 'jet_') === 0) {
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
        if (is_string($date) && !is_numeric($date) && strpos($date, '/') !== false) {
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

        // تلاش برای تبدیل به timestamp
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            if (function_exists('parsidate')) {
                return parsidate('Y/m/d', $timestamp);
            }
            return date('Y/m/d', $timestamp);
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
    public function add_booking_details_to_sms($message, $order = null) {
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
            if (!empty($booking_details) && !empty($booking_details['room_name'])) {
                $order->update_meta_data('_jetbooking_details', $booking_details);
                $order->save();
            }
        }

        // اگر هنوز اطلاعات رزرو موجود نیست، پیامک را بدون تغییر برگردان
        if (empty($booking_details) || empty($booking_details['room_name'])) {
            return $message;
        }

        // لاگ برای دیباگ
        if (JETBOOKING_SMS_DEBUG) {
            error_log('JetBooking SMS: Processing SMS for order #' . $order->get_id());
            error_log('Original message: ' . $message);
        }

        // جایگزینی متغیرها در پیامک
        $message = $this->replace_booking_variables($message, $order);

        if (JETBOOKING_SMS_DEBUG) {
            error_log('Modified message: ' . $message);
        }

        return $message;
    }

    /**
     * جایگزینی متغیرهای رزرو در متن
     */
    public function replace_booking_variables($message, $order = null) {
        if (!$order) {
            return $message;
        }

        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return $message;
        }

        $booking_details = $order->get_meta('_jetbooking_details');

        if (empty($booking_details)) {
            $booking_details = $this->extract_booking_details($order);
        }

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

        // اگر پیامک اطلاعات رزرو را ندارد و متغیری هم استفاده نشده، آن را اضافه کن
        if (strpos($message, $booking_details['room_name']) === false &&
            strpos($message, '{') === false) {
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
    public function add_custom_sms_variables($replace, $order = null) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return $replace;
        }

        $booking_details = $order->get_meta('_jetbooking_details');

        if (empty($booking_details)) {
            $booking_details = $this->extract_booking_details($order);
        }

        if (!empty($booking_details) && !empty($booking_details['room_name'])) {
            $replace['{hotel_name}'] = $booking_details['hotel_name'] ?? '';
            $replace['{room_name}'] = $booking_details['room_name'] ?? '';
            $replace['{checkin_date}'] = $booking_details['checkin_date'] ?? '';
            $replace['{checkout_date}'] = $booking_details['checkout_date'] ?? '';
            $replace['{nights}'] = $booking_details['nights'] ?? 0;
            $replace['{booking_info}'] = $this->format_booking_info($booking_details);
        }

        return $replace;
    }

    /**
     * اضافه کردن اطلاعات رزرو به پیامک در زمان تغییر وضعیت
     */
    public function maybe_add_booking_to_sms($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // اطمینان از ذخیره اطلاعات رزرو
        $booking_details = $order->get_meta('_jetbooking_details');
        if (empty($booking_details)) {
            $this->save_booking_data_to_order($order, array());
        }
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
