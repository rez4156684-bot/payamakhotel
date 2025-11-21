<?php
/**
 * Plugin Name: JetBooking SMS Integration
 * Plugin URI: https://github.com/rez4156684-bot/payamakhotel
 * Description: یکپارچه‌سازی اطلاعات رزرو JetBooking با پیامک‌های ووکامرس - افزودن اطلاعات هتل، اتاق و تاریخ‌های ورود و خروج به پیامک‌ها
 * Version: 1.4.0
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
define('JETBOOKING_SMS_VERSION', '1.4.0');
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

        // برای افزونه پیامک حرفه ای ووکامرس
        add_filter('pwsms_sms_body', array($this, 'add_booking_details_to_sms'), 999, 2);
        add_filter('pwsms_message', array($this, 'add_booking_details_to_sms'), 999, 2);

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
     * صفحه مدیریت پیشرفته با تب‌های مختلف
     */
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
        ?>
        <div class="wrap">
            <h1>🔧 افزونه یکپارچه‌سازی JetBooking با SMS - نسخه <?php echo JETBOOKING_SMS_VERSION; ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=jetbooking-sms-integration&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">📊 داشبورد</a>
                <a href="?page=jetbooking-sms-integration&tab=orders" class="nav-tab <?php echo $active_tab == 'orders' ? 'nav-tab-active' : ''; ?>">📦 سفارشات</a>
                <a href="?page=jetbooking-sms-integration&tab=filters" class="nav-tab <?php echo $active_tab == 'filters' ? 'nav-tab-active' : ''; ?>">🔌 فیلترها</a>
                <a href="?page=jetbooking-sms-integration&tab=test" class="nav-tab <?php echo $active_tab == 'test' ? 'nav-tab-active' : ''; ?>">🧪 تست</a>
                <a href="?page=jetbooking-sms-integration&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">📄 لاگ‌ها</a>
                <a href="?page=jetbooking-sms-integration&tab=guide" class="nav-tab <?php echo $active_tab == 'guide' ? 'nav-tab-active' : ''; ?>">📖 راهنما</a>
            </h2>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'dashboard':
                        $this->render_dashboard_tab();
                        break;
                    case 'orders':
                        $this->render_orders_tab();
                        break;
                    case 'filters':
                        $this->render_filters_tab();
                        break;
                    case 'test':
                        $this->render_test_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'guide':
                        $this->render_guide_tab();
                        break;
                }
                ?>
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
            .card h2 {
                margin-top: 0;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .card code {
                background: #f5f5f5;
                padding: 3px 8px;
                border-radius: 3px;
                font-family: monospace;
                direction: ltr;
                display: inline-block;
            }
            .widefat { margin: 15px 0; }
            .status-box {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 3px;
                margin: 5px;
                font-weight: bold;
            }
            .status-active { background: #d4edda; color: #155724; }
            .status-inactive { background: #f8d7da; color: #721c24; }
            .status-warning { background: #fff3cd; color: #856404; }
            .debug-output {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 15px;
                border-radius: 4px;
                font-family: monospace;
                direction: ltr;
                white-space: pre-wrap;
                max-height: 500px;
                overflow-y: auto;
            }
            .btn-primary {
                background: #0073aa;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
            }
            .btn-primary:hover {
                background: #005a87;
                color: white;
            }
            .alert {
                padding: 15px;
                margin: 15px 0;
                border-radius: 4px;
            }
            .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
            .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            .copy-btn {
                background: #28a745;
                color: white;
                padding: 5px 10px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
            }
            .copy-btn:hover {
                background: #218838;
            }
        </style>

        <script>
        function copyToClipboard(elementId) {
            var element = document.getElementById(elementId);
            var text = element.textContent || element.innerText;

            navigator.clipboard.writeText(text).then(function() {
                alert('کپی شد!');
            }, function() {
                // Fallback
                var textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('کپی شد!');
            });
        }
        </script>
        <?php
    }

    /**
     * تب داشبورد
     */
    private function render_dashboard_tab() {
        ?>
        <div class="card">
            <h2>✅ وضعیت سیستم</h2>
            <?php
            // بررسی افزونه‌های مورد نیاز
            $woocommerce_active = class_exists('WooCommerce');
            $jetbooking_active = defined('JET_ABAF_VERSION') || class_exists('Jet_Booking');
            $debug_mode = JETBOOKING_SMS_DEBUG;

            echo '<p><span class="status-box ' . ($woocommerce_active ? 'status-active' : 'status-inactive') . '">ووکامرس: ' . ($woocommerce_active ? 'فعال ✓' : 'غیرفعال ✗') . '</span></p>';
            echo '<p><span class="status-box ' . ($jetbooking_active ? 'status-active' : 'status-inactive') . '">JetBooking/JetABAF: ' . ($jetbooking_active ? 'فعال ✓' : 'غیرفعال ✗') . '</span></p>';
            echo '<p><span class="status-box ' . ($debug_mode ? 'status-warning' : 'status-active') . '">حالت Debug: ' . ($debug_mode ? 'فعال' : 'غیرفعال') . '</span></p>';
            ?>
        </div>

        <div class="card">
            <h2>📊 آمار کلی</h2>
            <?php
            $args = array(
                'limit' => -1,
                'meta_key' => '_jetbooking_details',
                'meta_compare' => 'EXISTS',
            );
            $orders_with_booking = count(wc_get_orders($args));

            $total_orders = count(wc_get_orders(array('limit' => -1)));
            ?>
            <p>تعداد کل سفارشات: <strong><?php echo $total_orders; ?></strong></p>
            <p>سفارشات با اطلاعات رزرو: <strong><?php echo $orders_with_booking; ?></strong></p>
        </div>

        <div class="card">
            <h2>📝 متغیرهای موجود</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>متغیر</th>
                        <th>توضیحات</th>
                        <th>مثال</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>{hotel_name}</code></td>
                        <td>نام هتل</td>
                        <td>هتل قشم</td>
                    </tr>
                    <tr>
                        <td><code>{room_name}</code></td>
                        <td>نام اتاق</td>
                        <td>سوئیت دو تخته</td>
                    </tr>
                    <tr>
                        <td><code>{checkin_date}</code></td>
                        <td>تاریخ ورود</td>
                        <td>1403/09/01</td>
                    </tr>
                    <tr>
                        <td><code>{checkout_date}</code></td>
                        <td>تاریخ خروج</td>
                        <td>1403/09/05</td>
                    </tr>
                    <tr>
                        <td><code>{nights}</code></td>
                        <td>تعداد شب</td>
                        <td>4</td>
                    </tr>
                    <tr>
                        <td><code>{booking_info}</code></td>
                        <td>اطلاعات کامل رزرو</td>
                        <td>همه موارد بالا</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>🚀 شروع سریع</h2>
            <ol>
                <li>مطمئن شوید JetBooking فعال است</li>
                <li>یک سفارش تستی ثبت کنید</li>
                <li>به تب <a href="?page=jetbooking-sms-integration&tab=orders">سفارشات</a> بروید و اطلاعات را ببینید</li>
                <li>در تنظیمات افزونه پیامکی، از متغیرهای بالا استفاده کنید</li>
            </ol>
        </div>
        <?php
    }

    /**
     * تب سفارشات
     */
    private function render_orders_tab() {
        ?>
        <div class="card">
            <h2>📦 آخرین سفارشات با اطلاعات رزرو</h2>
            <?php
            $args = array(
                'limit' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
            );
            $orders = wc_get_orders($args);

            if (!empty($orders)) {
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>ID سفارش</th>';
                echo '<th>مشتری</th>';
                echo '<th>نام اتاق</th>';
                echo '<th>نام هتل</th>';
                echo '<th>ورود</th>';
                echo '<th>خروج</th>';
                echo '<th>شب</th>';
                echo '<th>عملیات</th>';
                echo '</tr></thead><tbody>';

                foreach ($orders as $order) {
                    $booking_details = $order->get_meta('_jetbooking_details');

                    // اگر اطلاعات رزرو نداشت، سعی کن استخراج کن
                    if (empty($booking_details)) {
                        $booking_details = $this->extract_booking_details($order);
                    }

                    echo '<tr>';
                    echo '<td><a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '" target="_blank">#' . $order->get_id() . '</a></td>';
                    echo '<td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>';

                    if (!empty($booking_details) && !empty($booking_details['room_name'])) {
                        echo '<td>' . esc_html($booking_details['room_name']) . '</td>';
                        echo '<td>' . esc_html($booking_details['hotel_name']) . '</td>';
                        echo '<td>' . esc_html($booking_details['checkin_date']) . '</td>';
                        echo '<td>' . esc_html($booking_details['checkout_date']) . '</td>';
                        echo '<td>' . esc_html($booking_details['nights']) . '</td>';
                    } else {
                        echo '<td colspan="5" style="color: #dc3545;">⚠️ اطلاعات رزرو یافت نشد</td>';
                    }

                    echo '<td><a href="?page=jetbooking-sms-integration&tab=test&order_id=' . $order->get_id() . '" class="button">مشاهده جزئیات</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-info">هیچ سفارشی یافت نشد.</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * تب فیلترها
     */
    private function render_filters_tab() {
        global $wp_filter;
        ?>
        <div class="card">
            <h2>🔌 فیلترهای فعال مرتبط با SMS</h2>
            <p>این لیست فیلترهایی که افزونه ما به آن‌ها متصل شده است:</p>

            <?php
            $our_filters = array(
                'woocommerce_sms_message',
                'digits_wc_sms_message',
                'persianwoosms_sms_body',
                'ywsn_sms_message_content',
                'woocommerce_order_sms_message',
                'kavenegar_sms_message',
                'smsir_wc_message',
                'twilio_sms_message',
                'wp_sms_message',
                'wp_sms_msg',
                'pwsms_sms_body',
                'pwsms_message',
                'webservice_sms_message',
                'woocommerce_email_format_string_replace',
                'woocommerce_email_format_string',
            );

            echo '<table class="widefat striped">';
            echo '<thead><tr><th>نام فیلتر</th><th>وضعیت</th><th>تعداد Hooks</th></tr></thead>';
            echo '<tbody>';

            foreach ($our_filters as $filter_name) {
                $exists = isset($wp_filter[$filter_name]);
                $count = $exists ? count($wp_filter[$filter_name]->callbacks) : 0;

                echo '<tr>';
                echo '<td><code>' . esc_html($filter_name) . '</code></td>';
                echo '<td><span class="status-box ' . ($exists ? 'status-active' : 'status-inactive') . '">' . ($exists ? 'فعال ✓' : 'غیرفعال ✗') . '</span></td>';
                echo '<td>' . $count . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            ?>

            <h3 style="margin-top: 30px;">🔍 تمام فیلترهای SMS در سیستم</h3>
            <?php
            $sms_filters = array();
            foreach ($wp_filter as $filter_name => $filter) {
                if (stripos($filter_name, 'sms') !== false ||
                    stripos($filter_name, 'message') !== false ||
                    stripos($filter_name, 'pwsms') !== false) {
                    $sms_filters[] = $filter_name;
                }
            }

            if (!empty($sms_filters)) {
                echo '<div class="debug-output" id="all-sms-filters">';
                echo "یافت شد: " . count($sms_filters) . " فیلتر\n\n";
                foreach ($sms_filters as $filter) {
                    echo "- " . $filter . "\n";
                }
                echo '</div>';
                echo '<button class="copy-btn" onclick="copyToClipboard(\'all-sms-filters\')">📋 کپی لیست</button>';
            } else {
                echo '<div class="alert alert-warning">هیچ فیلتر SMS فعالی یافت نشد!</div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * تب تست
     */
    private function render_test_tab() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                ?>
                <div class="card">
                    <h2>🧪 جزئیات کامل سفارش #<?php echo $order_id; ?></h2>

                    <h3>📦 اطلاعات سفارش</h3>
                    <p><strong>مشتری:</strong> <?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></p>
                    <p><strong>تلفن:</strong> <?php echo $order->get_billing_phone(); ?></p>
                    <p><strong>وضعیت:</strong> <?php echo wc_get_order_status_name($order->get_status()); ?></p>
                    <p><strong>مبلغ:</strong> <?php echo $order->get_formatted_order_total(); ?></p>

                    <h3>🔍 متادیتای سفارش</h3>
                    <?php
                    $order_meta = $order->get_meta_data();
                    echo '<div class="debug-output" id="order-meta">';
                    echo "تعداد کل: " . count($order_meta) . "\n\n";
                    foreach ($order_meta as $meta) {
                        $data = $meta->get_data();
                        echo "Key: " . $data['key'] . "\n";
                        echo "Value: ";
                        if (is_array($data['value']) || is_object($data['value'])) {
                            echo print_r($data['value'], true);
                        } else {
                            echo $data['value'];
                        }
                        echo "\n\n";
                    }
                    echo '</div>';
                    echo '<button class="copy-btn" onclick="copyToClipboard(\'order-meta\')">📋 کپی متادیتا</button>';
                    ?>

                    <h3>📦 آیتم‌های سفارش</h3>
                    <?php
                    $items = $order->get_items();
                    foreach ($items as $item_id => $item) {
                        echo '<h4>محصول: ' . $item->get_name() . ' (ID: ' . $item->get_product_id() . ')</h4>';

                        $item_meta = $item->get_meta_data();
                        echo '<div class="debug-output" id="item-meta-' . $item_id . '">';
                        echo "تعداد متادیتا: " . count($item_meta) . "\n\n";
                        foreach ($item_meta as $meta) {
                            $data = $meta->get_data();
                            echo "Key: " . $data['key'] . "\n";
                            echo "Value: ";
                            if (is_array($data['value']) || is_object($data['value'])) {
                                echo print_r($data['value'], true);
                            } else {
                                echo $data['value'];
                            }
                            echo "\n\n";
                        }
                        echo '</div>';
                        echo '<button class="copy-btn" onclick="copyToClipboard(\'item-meta-' . $item_id . '\')">📋 کپی</button><br><br>';
                    }
                    ?>

                    <h3>✅ اطلاعات رزرو استخراج شده</h3>
                    <?php
                    $booking_details = $this->extract_booking_details($order);
                    echo '<div class="debug-output" id="booking-details">';
                    print_r($booking_details);
                    echo '</div>';
                    echo '<button class="copy-btn" onclick="copyToClipboard(\'booking-details\')">📋 کپی اطلاعات رزرو</button>';
                    ?>

                    <h3>📱 تست متن پیامک</h3>
                    <?php
                    $test_message = "مشتری گرامی\n\n{booking_info}\n\nسفارش: {order_number}";
                    $processed_message = $this->replace_booking_variables($test_message, $order);

                    echo '<p><strong>متن اولیه:</strong></p>';
                    echo '<div class="debug-output">' . esc_html($test_message) . '</div>';

                    echo '<p><strong>متن پردازش شده:</strong></p>';
                    echo '<div class="debug-output" id="processed-message">' . esc_html($processed_message) . '</div>';
                    echo '<button class="copy-btn" onclick="copyToClipboard(\'processed-message\')">📋 کپی پیامک</button>';
                    ?>

                    <p><a href="?page=jetbooking-sms-integration&tab=orders" class="btn-primary">← بازگشت به لیست سفارشات</a></p>
                </div>
                <?php
                return;
            }
        }
        ?>
        <div class="card">
            <h2>🧪 انتخاب سفارش برای تست</h2>
            <p>برای مشاهده جزئیات کامل یک سفارش و تست استخراج اطلاعات، سفارشی را از لیست زیر انتخاب کنید:</p>

            <?php
            $args = array(
                'limit' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
            );
            $orders = wc_get_orders($args);

            if (!empty($orders)) {
                echo '<table class="widefat striped">';
                echo '<thead><tr><th>ID</th><th>مشتری</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead>';
                echo '<tbody>';

                foreach ($orders as $order) {
                    echo '<tr>';
                    echo '<td>#' . $order->get_id() . '</td>';
                    echo '<td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>';
                    echo '<td>' . $order->get_date_created()->date('Y/m/d H:i') . '</td>';
                    echo '<td>' . wc_get_order_status_name($order->get_status()) . '</td>';
                    echo '<td><a href="?page=jetbooking-sms-integration&tab=test&order_id=' . $order->get_id() . '" class="button button-primary">🔍 تست و بررسی</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * تب لاگ‌ها
     */
    private function render_logs_tab() {
        ?>
        <div class="card">
            <h2>📄 فایل لاگ Debug</h2>

            <?php
            $log_file = WP_CONTENT_DIR . '/debug.log';

            if (file_exists($log_file)) {
                $log_content = file_get_contents($log_file);

                // فیلتر کردن فقط لاگ‌های مربوط به JetBooking
                $lines = explode("\n", $log_content);
                $filtered_lines = array();

                foreach ($lines as $line) {
                    if (stripos($line, 'jetbooking') !== false ||
                        stripos($line, 'booking') !== false ||
                        stripos($line, 'jetabaf') !== false) {
                        $filtered_lines[] = $line;
                    }
                }

                $filtered_content = implode("\n", array_slice($filtered_lines, -100)); // آخرین 100 خط

                echo '<p><strong>مسیر فایل:</strong> <code>' . $log_file . '</code></p>';
                echo '<p><strong>آخرین لاگ‌های مرتبط (100 خط):</strong></p>';

                if (!empty($filtered_content)) {
                    echo '<div class="debug-output" id="debug-log" style="max-height: 600px;">';
                    echo esc_html($filtered_content);
                    echo '</div>';
                    echo '<button class="copy-btn" onclick="copyToClipboard(\'debug-log\')">📋 کپی لاگ‌ها</button>';
                } else {
                    echo '<div class="alert alert-warning">هیچ لاگ مرتبطی یافت نشد. برای فعال کردن لاگ‌ها، JETBOOKING_SMS_DEBUG را true کنید.</div>';
                }

                echo '<hr>';
                echo '<h3>تمام لاگ‌ها (1000 خط آخر)</h3>';
                $all_lines = array_slice($lines, -1000);
                echo '<div class="debug-output" id="all-logs" style="max-height: 400px;">';
                echo esc_html(implode("\n", $all_lines));
                echo '</div>';
                echo '<button class="copy-btn" onclick="copyToClipboard(\'all-logs\')">📋 کپی همه</button>';

            } else {
                echo '<div class="alert alert-danger">فایل debug.log یافت نشد! برای فعال کردن: در wp-config.php این کدها را اضافه کنید:';
                echo '<pre>define(\'WP_DEBUG\', true);
define(\'WP_DEBUG_LOG\', true);
define(\'WP_DEBUG_DISPLAY\', false);</pre>';
                echo '</div>';
            }
            ?>

            <hr>

            <h3>⚙️ فعال کردن حالت Debug</h3>
            <p>برای دریافت لاگ‌های دقیق، در فایل افزونه خط 28 را تغییر دهید:</p>
            <pre>define('JETBOOKING_SMS_DEBUG', true);</pre>

            <?php if (JETBOOKING_SMS_DEBUG): ?>
                <div class="alert alert-success">✅ حالت Debug فعال است!</div>
            <?php else: ?>
                <div class="alert alert-warning">⚠️ حالت Debug غیرفعال است. برای دریافت لاگ‌های دقیق، آن را فعال کنید.</div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * تب راهنما
     */
    private function render_guide_tab() {
        ?>
        <div class="card">
            <h2>📖 راهنمای کامل استفاده</h2>

            <h3>1️⃣ مرحله اول: بررسی وضعیت</h3>
            <p>به تب <strong>داشبورد</strong> بروید و مطمئن شوید که:</p>
            <ul>
                <li>✅ ووکامرس فعال است</li>
                <li>✅ JetBooking یا JetABAF فعال است</li>
            </ul>

            <h3>2️⃣ مرحله دوم: بررسی فیلترها</h3>
            <p>به تب <strong>فیلترها</strong> بروید و ببینید کدام فیلترهای SMS در سیستم شما فعال هستند.</p>

            <h3>3️⃣ مرحله سوم: تست با یک سفارش</h3>
            <p>به تب <strong>تست</strong> بروید و یک سفارش را انتخاب کنید. اطلاعات زیر را بررسی کنید:</p>
            <ul>
                <li>متادیتای سفارش</li>
                <li>متادیتای آیتم‌های سفارش</li>
                <li>اطلاعات رزرو استخراج شده</li>
                <li>متن پیامک پردازش شده</li>
            </ul>

            <h3>4️⃣ مرحله چهارم: استفاده از متغیرها</h3>
            <p>در تنظیمات افزونه پیامکی خود، از این متغیرها استفاده کنید:</p>
            <ul>
                <li><code>{booking_info}</code> - برای نمایش تمام اطلاعات</li>
                <li><code>{hotel_name}</code> - برای نام هتل</li>
                <li><code>{room_name}</code> - برای نام اتاق</li>
                <li><code>{checkin_date}</code> - برای تاریخ ورود</li>
                <li><code>{checkout_date}</code> - برای تاریخ خروج</li>
                <li><code>{nights}</code> - برای تعداد شب</li>
            </ul>

            <h3>5️⃣ رفع مشکل</h3>
            <p>اگر متغیرها کار نمی‌کنند:</p>
            <ol>
                <li>حالت Debug را فعال کنید</li>
                <li>یک سفارش تستی ثبت کنید</li>
                <li>به تب <strong>لاگ‌ها</strong> بروید و لاگ‌ها را بررسی کنید</li>
                <li>به تب <strong>تست</strong> بروید و اطلاعات سفارش را کپی کنید</li>
                <li>اطلاعات را برای پشتیبانی ارسال کنید</li>
            </ol>
        </div>

        <div class="card" style="background: #e3f2fd; border-left: 4px solid #2196f3;">
            <h2>💡 نکات مهم</h2>
            <ul>
                <li>🔄 سفارش‌های قدیمی (قبل از نصب افزونه) ممکن است اطلاعات رزرو نداشته باشند</li>
                <li>⚙️ برای تست، حتماً یک سفارش <strong>جدید</strong> ثبت کنید</li>
                <li>📋 اگر مشکلی دارید، از تب <strong>تست</strong> اطلاعات کامل را کپی کرده و ارسال کنید</li>
                <li>🔍 برای دیباگ بهتر، حالت Debug را فعال کنید</li>
            </ul>
        </div>
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
                    // JetABAF Keys: jet_abaf_unit, apartment_unit
                    if (in_array($key, array('apartment_unit', '_apartment_unit', 'jet_abaf_unit', '_jet_abaf_unit', '_jet_unit_id', 'jet_unit')) ||
                        strpos($key_lower, 'unit') !== false ||
                        strpos($key_lower, 'apartment') !== false) {
                        if (is_numeric($value)) {
                            $booking_details['hotel_name'] = $this->get_apartment_name($value);
                        } else {
                            $booking_details['hotel_name'] = $value;
                        }
                    }

                    // شناسایی تاریخ ورود
                    // JetABAF Keys: apartment_check_in_date, check_in_date
                    if (in_array($key, array('check_in_date', '_check_in_date', 'apartment_check_in', 'apartment_check_in_date', '_apartment_check_in_date', 'Check in', 'تاریخ ورود', 'checkin', '_checkin', 'check-in', '_check-in')) ||
                        strpos($key_lower, 'check') !== false && strpos($key_lower, 'in') !== false ||
                        strpos($key_lower, 'ورود') !== false ||
                        strpos($key_lower, 'start') !== false && strpos($key_lower, 'date') !== false) {
                        $booking_details['checkin_date'] = $this->format_date($value);
                    }

                    // شناسایی تاریخ خروج
                    // JetABAF Keys: apartment_check_out_date, check_out_date
                    if (in_array($key, array('check_out_date', '_check_out_date', 'apartment_check_out', 'apartment_check_out_date', '_apartment_check_out_date', 'Check out', 'تاریخ خروج', 'checkout', '_checkout', 'check-out', '_check-out')) ||
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
