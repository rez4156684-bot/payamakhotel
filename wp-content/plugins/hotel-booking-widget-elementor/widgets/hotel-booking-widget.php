<?php
namespace Hotel_Booking_Widget\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجیت رزرو هتل
 */
class Hotel_Booking_Widget extends Widget_Base {

    /**
     * نام ویجیت
     */
    public function get_name() {
        return 'hotel-booking-widget';
    }

    /**
     * عنوان ویجیت
     */
    public function get_title() {
        return __('رزرو هتل', 'hotel-booking-widget');
    }

    /**
     * آیکون ویجیت
     */
    public function get_icon() {
        return 'eicon-site-identity';
    }

    /**
     * دسته‌بندی ویجیت
     */
    public function get_categories() {
        return array('hotel-booking');
    }

    /**
     * کلمات کلیدی
     */
    public function get_keywords() {
        return array('hotel', 'booking', 'هتل', 'رزرو', 'اتاق');
    }

    /**
     * ثبت کنترل‌ها
     */
    protected function register_controls() {
        // بخش محتوا
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('محتوا', 'hotel-booking-widget'),
                'tab' => Controls_Manager::TAB_CONTENT,
            )
        );

        // انتخاب محصول
        $this->add_control(
            'product_id',
            array(
                'label' => __('محصول هتل', 'hotel-booking-widget'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_woocommerce_products(),
                'default' => '',
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_gallery',
            array(
                'label' => __('نمایش گالری', 'hotel-booking-widget'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('بله', 'hotel-booking-widget'),
                'label_off' => __('خیر', 'hotel-booking-widget'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_description',
            array(
                'label' => __('نمایش توضیحات', 'hotel-booking-widget'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('بله', 'hotel-booking-widget'),
                'label_off' => __('خیر', 'hotel-booking-widget'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_amenities',
            array(
                'label' => __('نمایش امکانات', 'hotel-booking-widget'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('بله', 'hotel-booking-widget'),
                'label_off' => __('خیر', 'hotel-booking-widget'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        // استایل کارت
        $this->start_controls_section(
            'card_style_section',
            array(
                'label' => __('استایل کارت', 'hotel-booking-widget'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('رنگ پس‌زمینه', 'hotel-booking-widget'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .hbw-hotel-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .hbw-hotel-card',
            )
        );

        $this->add_control(
            'card_border_radius',
            array(
                'label' => __('گردی گوشه', 'hotel-booking-widget'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),
                'default' => array(
                    'size' => 12,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .hbw-hotel-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .hbw-hotel-card',
            )
        );

        $this->end_controls_section();

        // استایل دکمه
        $this->start_controls_section(
            'button_style_section',
            array(
                'label' => __('استایل دکمه', 'hotel-booking-widget'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'button_background',
            array(
                'label' => __('رنگ پس‌زمینه دکمه', 'hotel-booking-widget'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0066cc',
                'selectors' => array(
                    '{{WRAPPER}} .hbw-booking-form button[type="submit"]' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('رنگ متن دکمه', 'hotel-booking-widget'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .hbw-booking-form button[type="submit"]' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hbw-booking-form button[type="submit"]',
            )
        );

        $this->end_controls_section();
    }

    /**
     * دریافت لیست محصولات ووکامرس
     */
    private function get_woocommerce_products() {
        $products = array('' => __('انتخاب محصول', 'hotel-booking-widget'));

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $products[get_the_ID()] = get_the_title();
            }
            wp_reset_postdata();
        }

        return $products;
    }

    /**
     * رندر ویجیت
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $product_id = $settings['product_id'];

        if (empty($product_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="hbw-notice">' . __('لطفاً یک محصول انتخاب کنید', 'hotel-booking-widget') . '</div>';
            }
            return;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            echo '<div class="hbw-notice">' . __('محصول یافت نشد', 'hotel-booking-widget') . '</div>';
            return;
        }

        $this->render_hotel_card($product, $settings);
    }

    /**
     * رندر کارت هتل
     */
    private function render_hotel_card($product, $settings) {
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        $product_image = wp_get_attachment_url($product->get_image_id());
        $product_gallery = $product->get_gallery_image_ids();
        $product_description = $product->get_short_description();
        ?>

        <div class="hbw-hotel-card" data-product-id="<?php echo esc_attr($product_id); ?>">

            <?php if ($settings['show_gallery'] === 'yes' && $product_image): ?>
            <!-- گالری تصاویر -->
            <div class="hbw-gallery">
                <div class="hbw-gallery-main">
                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" class="hbw-main-image">
                    <div class="hbw-gallery-badge">
                        <span class="hbw-badge-featured">⭐ پیشنهاد ویژه</span>
                    </div>
                </div>

                <?php if (!empty($product_gallery)): ?>
                <div class="hbw-gallery-thumbnails">
                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" class="hbw-thumb active">
                    <?php
                    $count = 0;
                    foreach ($product_gallery as $gallery_image_id):
                        if ($count >= 3) break;
                        $gallery_image = wp_get_attachment_url($gallery_image_id);
                        ?>
                        <img src="<?php echo esc_url($gallery_image); ?>" alt="<?php echo esc_attr($product_name); ?>" class="hbw-thumb">
                    <?php
                        $count++;
                    endforeach;
                    ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- محتوای کارت -->
            <div class="hbw-card-content">

                <!-- اطلاعات اصلی -->
                <div class="hbw-info-section">
                    <div class="hbw-header">
                        <h2 class="hbw-title"><?php echo esc_html($product_name); ?></h2>
                        <div class="hbw-rating">
                            <span class="hbw-stars">★★★★★</span>
                            <span class="hbw-rating-text">(۴.۸ از ۵)</span>
                        </div>
                    </div>

                    <?php if ($settings['show_description'] === 'yes' && $product_description): ?>
                    <div class="hbw-description">
                        <p><?php echo wp_kses_post($product_description); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($settings['show_amenities'] === 'yes'): ?>
                    <!-- امکانات -->
                    <div class="hbw-amenities">
                        <h3 class="hbw-amenities-title">امکانات اتاق</h3>
                        <div class="hbw-amenities-grid">
                            <?php
                            // امکانات پیش‌فرض - در نسخه بعدی می‌توان از متافیلد استفاده کرد
                            $default_amenities = array(
                                array('icon' => '📶', 'text' => 'وای‌فای رایگان'),
                                array('icon' => '❄️', 'text' => 'تهویه مطبوع'),
                                array('icon' => '📺', 'text' => 'تلویزیون'),
                                array('icon' => '🚿', 'text' => 'حمام اختصاصی'),
                                array('icon' => '☕', 'text' => 'چای و قهوه'),
                                array('icon' => '🅿️', 'text' => 'پارکینگ رایگان'),
                            );

                            foreach ($default_amenities as $amenity):
                            ?>
                                <div class="hbw-amenity-item">
                                    <span class="hbw-amenity-icon"><?php echo $amenity['icon']; ?></span>
                                    <span class="hbw-amenity-text"><?php echo esc_html($amenity['text']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- فرم رزرو -->
                <div class="hbw-booking-section">
                    <div class="hbw-booking-card">
                        <div class="hbw-price-section">
                            <div class="hbw-price-wrapper">
                                <span class="hbw-price"><?php echo wc_price($product_price); ?></span>
                                <span class="hbw-price-unit">/ هر شب</span>
                            </div>
                        </div>

                        <form class="hbw-booking-form" method="post" action="<?php echo esc_url(wc_get_cart_url()); ?>">

                            <!-- انتخاب تاریخ -->
                            <div class="hbw-date-section">
                                <div class="hbw-date-group">
                                    <label for="checkin-<?php echo esc_attr($product_id); ?>" class="hbw-label">
                                        📅 تاریخ ورود
                                    </label>
                                    <input
                                        type="text"
                                        id="checkin-<?php echo esc_attr($product_id); ?>"
                                        name="checkin_date"
                                        class="hbw-date-input hbw-checkin"
                                        placeholder="انتخاب تاریخ ورود"
                                        required
                                        readonly
                                    >
                                </div>

                                <div class="hbw-date-divider">→</div>

                                <div class="hbw-date-group">
                                    <label for="checkout-<?php echo esc_attr($product_id); ?>" class="hbw-label">
                                        📅 تاریخ خروج
                                    </label>
                                    <input
                                        type="text"
                                        id="checkout-<?php echo esc_attr($product_id); ?>"
                                        name="checkout_date"
                                        class="hbw-date-input hbw-checkout"
                                        placeholder="انتخاب تاریخ خروج"
                                        required
                                        readonly
                                    >
                                </div>
                            </div>

                            <!-- انتخاب تعداد مهمان -->
                            <div class="hbw-guests-section">
                                <div class="hbw-guest-group">
                                    <label for="adults-<?php echo esc_attr($product_id); ?>" class="hbw-label">
                                        👤 بزرگسال
                                    </label>
                                    <select id="adults-<?php echo esc_attr($product_id); ?>" name="adults" class="hbw-select">
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="hbw-guest-group">
                                    <label for="children-<?php echo esc_attr($product_id); ?>" class="hbw-label">
                                        👶 کودک
                                    </label>
                                    <select id="children-<?php echo esc_attr($product_id); ?>" name="children" class="hbw-select">
                                        <?php for ($i = 0; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- نمایش خلاصه -->
                            <div class="hbw-summary">
                                <div class="hbw-summary-row">
                                    <span class="hbw-summary-label">تعداد شب:</span>
                                    <span class="hbw-summary-value hbw-nights-count">0</span>
                                </div>
                                <div class="hbw-summary-row">
                                    <span class="hbw-summary-label">قیمت هر شب:</span>
                                    <span class="hbw-summary-value"><?php echo wc_price($product_price); ?></span>
                                </div>
                                <div class="hbw-summary-row hbw-summary-total">
                                    <span class="hbw-summary-label">جمع کل:</span>
                                    <span class="hbw-summary-value hbw-total-price"><?php echo wc_price(0); ?></span>
                                </div>
                            </div>

                            <!-- دکمه رزرو -->
                            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                            <input type="hidden" name="quantity" value="1">

                            <button type="submit" class="hbw-booking-button">
                                <span class="hbw-button-icon">🔒</span>
                                <span class="hbw-button-text">رزرو قطعی</span>
                            </button>

                            <div class="hbw-booking-note">
                                ✓ رزرو آنی و بدون پرداخت
                            </div>
                        </form>
                    </div>

                    <!-- ویژگی‌های اضافی -->
                    <div class="hbw-features">
                        <div class="hbw-feature-item">
                            <span class="hbw-feature-icon">✓</span>
                            <span class="hbw-feature-text">لغو رایگان تا ۲۴ ساعت قبل</span>
                        </div>
                        <div class="hbw-feature-item">
                            <span class="hbw-feature-icon">✓</span>
                            <span class="hbw-feature-text">بدون نیاز به پرداخت آنلاین</span>
                        </div>
                        <div class="hbw-feature-item">
                            <span class="hbw-feature-icon">✓</span>
                            <span class="hbw-feature-text">تضمین بهترین قیمت</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php
    }
}
