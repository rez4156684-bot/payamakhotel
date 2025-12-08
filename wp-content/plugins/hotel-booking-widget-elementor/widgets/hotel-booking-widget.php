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
 * ویجیت رزرو هتل - یکپارچه با WC Hotel Reservation System
 */
class Hotel_Booking_Widget extends Widget_Base {

    public function get_name() {
        return 'hotel-booking-widget';
    }

    public function get_title() {
        return __('رزرو هتل (یکپارچه)', 'hotel-booking-widget');
    }

    public function get_icon() {
        return 'eicon-site-identity';
    }

    public function get_categories() {
        return array('hotel-booking');
    }

    public function get_keywords() {
        return array('hotel', 'booking', 'هتل', 'رزرو', 'اتاق', 'reservation');
    }

    protected function register_controls() {
        // بخش محتوا
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('محتوا', 'hotel-booking-widget'),
                'tab' => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'product_id',
            array(
                'label' => __('محصول هتل', 'hotel-booking-widget'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_hotel_products(),
                'default' => '',
                'label_block' => true,
                'description' => __('فقط محصولاتی که سیستم رزرو فعال دارند', 'hotel-booking-widget'),
            )
        );

        $this->add_control(
            'use_current_product',
            array(
                'label' => __('استفاده از محصول جاری', 'hotel-booking-widget'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('بله', 'hotel-booking-widget'),
                'label_off' => __('خیر', 'hotel-booking-widget'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('برای استفاده در قالب Single Product', 'hotel-booking-widget'),
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
            'hide_default_elements',
            array(
                'label' => __('مخفی کردن المان‌های پیش‌فرض', 'hotel-booking-widget'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('بله', 'hotel-booking-widget'),
                'label_off' => __('خیر', 'hotel-booking-widget'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('مخفی کردن عکس، نام، قیمت و دکمه پیش‌فرض ووکامرس', 'hotel-booking-widget'),
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
                    '{{WRAPPER}} .hbw-integrated-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .hbw-integrated-card',
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
                    'size' => 16,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .hbw-integrated-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .hbw-integrated-card',
            )
        );

        $this->end_controls_section();
    }

    /**
     * دریافت لیست محصولات هتل
     */
    private function get_hotel_products() {
        $products = array('' => __('انتخاب محصول', 'hotel-booking-widget'));

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_enable_hotel_reservation',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
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

        // تشخیص محصول
        if ($settings['use_current_product'] === 'yes' && is_singular('product')) {
            global $post;
            $product_id = $post->ID;
        } else {
            $product_id = $settings['product_id'];
        }

        if (empty($product_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="hbw-notice">' . __('لطفاً یک محصول هتل انتخاب کنید', 'hotel-booking-widget') . '</div>';
            }
            return;
        }

        // بررسی فعال بودن سیستم رزرو
        $reservation_enabled = get_post_meta($product_id, '_enable_hotel_reservation', true);
        if ($reservation_enabled !== 'yes') {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="hbw-notice">' . __('سیستم رزرو برای این محصول فعال نیست', 'hotel-booking-widget') . '</div>';
            }
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            echo '<div class="hbw-notice">' . __('محصول یافت نشد', 'hotel-booking-widget') . '</div>';
            return;
        }

        // مخفی کردن المان‌های پیش‌فرض
        if ($settings['hide_default_elements'] === 'yes') {
            $this->hide_default_woocommerce_elements();
        }

        $this->render_integrated_hotel_card($product, $product_id, $settings);
    }

    /**
     * مخفی کردن المان‌های پیش‌فرض ووکامرس
     */
    private function hide_default_woocommerce_elements() {
        ?>
        <style>
            /* مخفی کردن المان‌های پیش‌فرض ووکامرس */
            .product .woocommerce-product-gallery,
            .product .entry-title,
            .product .price,
            .product .product_meta,
            .product form.cart,
            .product .quantity,
            .product .single_add_to_cart_button,
            .product .woocommerce-product-rating,
            .product .woocommerce-breadcrumb {
                display: none !important;
            }

            /* نگه داشتن تب‌های محصول */
            .woocommerce-tabs {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * رندر کارت یکپارچه هتل
     */
    private function render_integrated_hotel_card($product, $product_id, $settings) {
        // دریافت داده‌های هتل از افزونه Hotel Reservation
        $rooms = get_post_meta($product_id, '_hotel_rooms', true) ?: [];
        $amenities = get_post_meta($product_id, '_hotel_amenities', true) ?: [];
        $check_in = get_post_meta($product_id, '_hotel_check_in_time', true) ?: '14:00';
        $check_out = get_post_meta($product_id, '_hotel_check_out_time', true) ?: '12:00';
        $rating = get_post_meta($product_id, '_hotel_rating', true);
        $rules = get_post_meta($product_id, '_hotel_rules', true);

        // اطلاعات محصول ووکامرس
        $product_name = $product->get_name();
        $product_image = wp_get_attachment_url($product->get_image_id());
        $product_gallery = $product->get_gallery_image_ids();
        $product_description = $product->get_short_description();
        if (empty($product_description)) {
            $product_description = $product->get_description();
        }
        ?>

        <div class="hbw-integrated-card" data-product-id="<?php echo esc_attr($product_id); ?>">

            <?php if ($settings['show_gallery'] === 'yes' && $product_image): ?>
            <!-- گالری تصاویر -->
            <div class="hbw-gallery">
                <div class="hbw-gallery-main">
                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" class="hbw-main-image">
                    <div class="hbw-gallery-badge">
                        <?php if ($rating): ?>
                            <span class="hbw-badge-rating">
                                <?php
                                if (is_numeric($rating) && $rating > 0) {
                                    echo str_repeat('⭐', intval($rating));
                                } else {
                                    echo esc_html($rating);
                                }
                                ?>
                            </span>
                        <?php endif; ?>
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
                        <div class="hbw-check-times">
                            <span>ورود: <?php echo esc_html($check_in); ?></span>
                            <span>خروج: <?php echo esc_html($check_out); ?></span>
                        </div>
                    </div>

                    <?php if ($settings['show_description'] === 'yes' && $product_description): ?>
                    <div class="hbw-description">
                        <?php echo wp_kses_post($product_description); ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($amenities)): ?>
                    <!-- امکانات از افزونه Hotel Reservation -->
                    <div class="hbw-amenities">
                        <h3 class="hbw-amenities-title">امکانات هتل</h3>
                        <div class="hbw-amenities-grid">
                            <?php foreach ($amenities as $amenity): ?>
                                <div class="hbw-amenity-item">
                                    <span class="hbw-amenity-icon"><?php echo isset($amenity['icon']) ? $amenity['icon'] : '✓'; ?></span>
                                    <span class="hbw-amenity-text"><?php echo esc_html(isset($amenity['text']) ? $amenity['text'] : $amenity); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($rules): ?>
                    <!-- قوانین هتل -->
                    <div class="hbw-rules">
                        <h3 class="hbw-rules-title">قوانین و مقررات</h3>
                        <div class="hbw-rules-content">
                            <?php echo nl2br(esc_html($rules)); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- بخش رزرو - استفاده از سیستم اصلی -->
                <div class="hbw-booking-section">
                    <div class="hbw-booking-notice">
                        <p>⬇️ برای مشاهده اتاق‌ها و رزرو، به پایین صفحه بروید</p>
                    </div>

                    <?php if (!empty($rooms)): ?>
                    <div class="hbw-rooms-preview">
                        <h4>اتاق‌های موجود (<?php echo count($rooms); ?>)</h4>
                        <ul>
                            <?php foreach ($rooms as $room): ?>
                                <li>
                                    <strong><?php echo esc_html($room['name']); ?></strong>
                                    <span><?php echo number_format($room['base_price']); ?> تومان/شب</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- اضافه کردن استایل‌های یکپارچه -->
        <style>
            .hbw-integrated-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
                margin-bottom: 30px;
            }

            .hbw-gallery {
                position: relative;
                width: 100%;
            }

            .hbw-gallery-main {
                position: relative;
                width: 100%;
                height: 400px;
                overflow: hidden;
            }

            .hbw-main-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.5s ease;
            }

            .hbw-gallery-main:hover .hbw-main-image {
                transform: scale(1.05);
            }

            .hbw-gallery-badge {
                position: absolute;
                top: 20px;
                right: 20px;
                z-index: 2;
            }

            .hbw-badge-rating {
                background: rgba(255, 255, 255, 0.95);
                color: #FFD700;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 16px;
                font-weight: 600;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }

            .hbw-gallery-thumbnails {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
                padding: 12px;
                background: #f9fafb;
            }

            .hbw-thumb {
                width: 100%;
                height: 80px;
                object-fit: cover;
                border-radius: 8px;
                cursor: pointer;
                border: 2px solid transparent;
                transition: all 0.3s ease;
            }

            .hbw-thumb:hover,
            .hbw-thumb.active {
                border-color: #667eea;
                transform: translateY(-2px);
            }

            .hbw-card-content {
                padding: 30px;
            }

            .hbw-header {
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f3f4f6;
            }

            .hbw-title {
                font-size: 28px;
                font-weight: 700;
                color: #1f2937;
                margin: 0 0 12px 0;
                line-height: 1.3;
            }

            .hbw-check-times {
                display: flex;
                gap: 20px;
                color: #6b7280;
                font-size: 14px;
            }

            .hbw-check-times span {
                background: #f3f4f6;
                padding: 6px 12px;
                border-radius: 6px;
            }

            .hbw-description {
                margin-bottom: 24px;
                padding-bottom: 24px;
                border-bottom: 1px solid #e5e7eb;
            }

            .hbw-description p {
                color: #4b5563;
                line-height: 1.8;
                margin: 0;
                font-size: 15px;
            }

            .hbw-amenities {
                margin-bottom: 24px;
            }

            .hbw-amenities-title,
            .hbw-rules-title {
                font-size: 18px;
                font-weight: 600;
                color: #1f2937;
                margin: 0 0 16px 0;
            }

            .hbw-amenities-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 12px;
            }

            .hbw-amenity-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 12px;
                background: #f9fafb;
                border-radius: 8px;
                transition: all 0.3s ease;
            }

            .hbw-amenity-item:hover {
                background: #f3f4f6;
                transform: translateX(-3px);
            }

            .hbw-amenity-icon {
                font-size: 20px;
            }

            .hbw-amenity-text {
                font-size: 14px;
                color: #1f2937;
            }

            .hbw-rules {
                margin-top: 24px;
                padding: 20px;
                background: #fef3c7;
                border-radius: 12px;
                border: 1px solid #fbbf24;
            }

            .hbw-rules-content {
                color: #92400e;
                font-size: 14px;
                line-height: 1.6;
            }

            .hbw-booking-notice {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 20px;
                border-radius: 12px;
                text-align: center;
                margin-bottom: 20px;
            }

            .hbw-booking-notice p {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
            }

            .hbw-rooms-preview {
                background: #f9fafb;
                padding: 20px;
                border-radius: 12px;
            }

            .hbw-rooms-preview h4 {
                margin: 0 0 15px 0;
                color: #1f2937;
            }

            .hbw-rooms-preview ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .hbw-rooms-preview li {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .hbw-rooms-preview li:last-child {
                border-bottom: none;
            }

            .hbw-rooms-preview span {
                color: #10b981;
                font-weight: 600;
            }

            /* ریسپانسیو */
            @media screen and (max-width: 767px) {
                .hbw-gallery-main {
                    height: 250px;
                }

                .hbw-gallery-thumbnails {
                    grid-template-columns: repeat(3, 1fr);
                }

                .hbw-card-content {
                    padding: 20px;
                }

                .hbw-title {
                    font-size: 22px;
                }

                .hbw-amenities-grid {
                    grid-template-columns: 1fr;
                }

                .hbw-check-times {
                    flex-direction: column;
                    gap: 8px;
                }
            }

            @media screen and (max-width: 599px) {
                .hbw-gallery-thumbnails {
                    display: none;
                }

                .hbw-card-content {
                    padding: 15px;
                }
            }
        </style>

        <!-- اضافه کردن JavaScript برای گالری -->
        <script>
        jQuery(document).ready(function($) {
            // تعویض تصویر اصلی
            $('.hbw-thumb').on('click', function() {
                var newSrc = $(this).attr('src');
                var $card = $(this).closest('.hbw-integrated-card');
                $card.find('.hbw-main-image').attr('src', newSrc);
                $card.find('.hbw-thumb').removeClass('active');
                $(this).addClass('active');
            });
        });
        </script>

        <?php
    }
}
