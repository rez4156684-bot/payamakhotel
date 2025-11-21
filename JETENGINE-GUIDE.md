# راهنمای تشخیص ساختار JetEngine

این راهنما برای کمک به شناسایی نحوه ذخیره اطلاعات رزرو توسط JetEngine است.

## 🔍 چگونه ساختار رزرو JetEngine را تشخیص دهیم؟

### روش 1: بررسی متادیتای سفارش

1. وارد داشبورد وردپرس شوید
2. به **ووکامرس > سفارشات** بروید
3. یک سفارش رزرو را باز کنید
4. به قسمت **Custom Fields** یا **متادیتا** بروید
5. لیست تمام فیلدها را یادداشت کنید

**فیلدهای معمول JetEngine:**
- `_jet_booking_*`
- `jet_abaf_*`
- `_cct_*`
- نام‌های سفارشی دیگر

---

### روش 2: استفاده از افزونه Query Monitor

1. افزونه **Query Monitor** را نصب کنید
2. یک رزرو تستی ثبت کنید
3. تمام متادیتای ثبت شده را ببینید

---

### روش 3: بررسی مستقیم دیتابیس

اگر به phpMyAdmin دسترسی دارید:

```sql
-- پیدا کردن یک سفارش رزرو
SELECT * FROM wp_posts
WHERE post_type = 'shop_order'
ORDER BY ID DESC
LIMIT 5;

-- دیدن متادیتای سفارش (شماره سفارش را جایگزین کنید)
SELECT * FROM wp_postmeta
WHERE post_id = XXXX
AND meta_key LIKE '%jet%'
OR meta_key LIKE '%booking%'
OR meta_key LIKE '%check%'
OR meta_key LIKE '%date%';

-- دیدن آیتم‌های سفارش
SELECT * FROM wp_woocommerce_order_items
WHERE order_id = XXXX;

-- متادیتای آیتم‌های سفارش
SELECT * FROM wp_woocommerce_order_itemmeta
WHERE order_item_id IN (
    SELECT order_item_id FROM wp_woocommerce_order_items
    WHERE order_id = XXXX
);
```

---

## 📋 اطلاعات مورد نیاز

لطفاً این اطلاعات را پیدا کرده و به من بدهید:

### از داشبورد وردپرس:

1. **نام دقیق محصولات رزرو:**
   - مثلاً: "اتاق دو تخته"، "سوئیت VIP"

2. **متادیتای سفارش:**
   ```
   به یک سفارش رزرو بروید و لیست Custom Fields را کپی کنید
   ```

3. **نام دقیق افزونه پیامکی:**
   - به **افزونه‌ها** بروید
   - افزونه پیامکی را پیدا کنید
   - نام دقیق آن را کپی کنید

---

## 🛠️ کد PHP برای تشخیص خودکار

این کد را در `functions.php` تم خود اضافه کنید تا اطلاعات را ببینید:

```php
// نمایش متادیتای سفارش برای ادمین
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    echo '<div class="order_data_column">';
    echo '<h3>اطلاعات رزرو (Debug)</h3>';

    // تمام متادیتای سفارش
    $order_meta = $order->get_meta_data();
    echo '<pre>';
    foreach ($order_meta as $meta) {
        $data = $meta->get_data();
        echo 'Key: ' . $data['key'] . ' = ';
        print_r($data['value']);
        echo "\n";
    }

    // آیتم‌های سفارش
    echo "\n\n=== Order Items ===\n";
    foreach ($order->get_items() as $item_id => $item) {
        echo "\nItem: " . $item->get_name() . "\n";
        $item_meta = $item->get_meta_data();
        foreach ($item_meta as $meta) {
            $data = $meta->get_data();
            echo '  ' . $data['key'] . ' = ';
            print_r($data['value']);
            echo "\n";
        }
    }
    echo '</pre>';
    echo '</div>';
});
```

بعد از اضافه کردن این کد:
1. یک سفارش رزرو را باز کنید
2. به پایین صفحه اسکرول کنید
3. یک باکس با عنوان "اطلاعات رزرو (Debug)" خواهید دید
4. **تمام محتوای آن را کپی کرده و به من بدهید**

---

## 🔍 اطلاعات افزونه پیامکی

برای یافتن فیلتر دقیق افزونه پیامکی:

### روش 1: بررسی کد افزونه

1. به `wp-content/plugins/` بروید
2. پوشه افزونه پیامکی را باز کنید
3. فایل‌های PHP را جستجو کنید برای:
   - `apply_filters(`
   - `do_action(`
   - `sms`
   - `message`

### روش 2: استفاده از این کد

```php
// نمایش تمام فیلترهای فعال
add_action('init', function() {
    global $wp_filter;

    // پیدا کردن فیلترهای مرتبط با SMS
    $sms_filters = array();
    foreach ($wp_filter as $filter_name => $filter) {
        if (stripos($filter_name, 'sms') !== false ||
            stripos($filter_name, 'message') !== false) {
            $sms_filters[] = $filter_name;
        }
    }

    if (is_admin()) {
        echo '<pre>SMS Filters Found:\n';
        print_r($sms_filters);
        echo '</pre>';
    }
});
```

---

## 📧 ارسال اطلاعات

لطفاً موارد زیر را برای من ارسال کنید:

### ✅ چک‌لیست:

- [ ] خروجی کد debug از متادیتای سفارش
- [ ] نام دقیق افزونه پیامکی
- [ ] اسکرین‌شات از صفحه تنظیمات پیامک
- [ ] متن فعلی الگوی پیامک
- [ ] نمونه پیامک ارسال شده (چه چیزی نمایش می‌دهد)
- [ ] لیست افزونه‌های فعال (مخصوصاً مرتبط با JetEngine/JetBooking)

---

## 🚀 بعد از دریافت اطلاعات

با این اطلاعات می‌توانم:

1. ✅ تشخیص دقیق نحوه ذخیره اطلاعات رزرو
2. ✅ شناسایی فیلتر دقیق افزونه پیامکی
3. ✅ بروزرسانی افزونه برای کار با ساختار دقیق سایت شما
4. ✅ تست و اطمینان از کارکرد 100%

---

## 💡 نکته مهم

اگر دسترسی FTP یا SSH دارید، می‌توانید این فایل‌ها را برای من ارسال کنید:

1. فایل اصلی افزونه پیامکی
2. لیست افزونه‌های نصب شده
3. تم فعال

این کار را آسان‌تر می‌کند!
