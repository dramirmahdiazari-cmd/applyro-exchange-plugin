<?php
/**
 * Plugin Name: WP Exchange Rates
 * Description: نمایش و مدیریت قیمت ارز از منابع مختلف
 * Version: 1.0.0
 * Author: Applyro.com
 * Text Domain: wp-exchange-rates
 */

if (!defined('ABSPATH')) exit;

// ======================
// تعریف ثابت‌ها
// ======================
define('WER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WER_PLUGIN_VERSION', '1.0.0');

// ======================
// توابع اصلی و مشترک - اینجا تعریف می‌شوند
// ======================

/**
 * دریافت اطلاعات صراف
 */
function wer_get_vendor_data($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    global $wpdb;
    
    // بررسی وجود جدول
    $table_name = $wpdb->prefix . 'exchanges';
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s", 
        $table_name
    ));
    
    if (!$table_exists) {
        return false;
    }
    
    $vendor = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exchanges 
         WHERE user_id = %d",
        $user_id
    ));
    
    return $vendor;
}

/**
 * بررسی اینکه آیا کاربر صراف است
 */
function wer_is_user_vendor($user_id = null, $approved_only = false) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    // بررسی role کاربر
    $user = get_userdata($user_id);
    if (!$user || !in_array('exchange_vendor', (array) $user->roles)) {
        return false;
    }
    
    // اگر فقط صراف‌های تایید شده مد نظر هستند
    if ($approved_only) {
        $vendor_data = wer_get_vendor_data($user_id);
        return ($vendor_data && $vendor_data->status === 'verified');
    }
    
    return true;
}

/**
 * ایجاد نقش صراف
 */
function wer_register_vendor_role() {
    if (!get_role('exchange_vendor')) {
        add_role('exchange_vendor', 'صراف', [
            'read'         => true,
            'upload_files' => true,
            'edit_posts'   => false,
        ]);
    }
}

/**
 * دریافت متن وضعیت
 */
function wer_get_status_text($status) {
    $status_texts = [
        'pending'  => 'در انتظار تایید',
        'verified' => 'تایید شده',
        'rejected' => 'رد شده'
    ];
    return $status_texts[$status] ?? $status;
}

/**
 * بررسی وجود جدول
 */
function wer_check_table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s", 
        $table_name
    )) === $table_name;
}

// ======================
// تابع به‌روزرسانی جدول‌ها
// ======================

/**
 * بررسی و اضافه کردن ستون‌های missing به جدول exchanges
 */
function wer_add_missing_columns() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exchanges';
    
    // لیست ستون‌های مورد نیاز
    $required_columns = [
        'rejection_reason' => 'TEXT',
        'email_verified' => 'TINYINT(1) DEFAULT 0'
    ];
    
    // دریافت ستون‌های موجود
    $existing_columns = $wpdb->get_col("DESC $table_name", 0);
    
    foreach ($required_columns as $column => $type) {
        if (!in_array($column, $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column $type");
        }
    }
}

/**
 * اجرای به‌روزرسانی جدول هنگام بارگذاری
 */
add_action('init', 'wer_check_and_update_tables');
function wer_check_and_update_tables() {
    // فقط در پنل ادمین اجرا شود
    if (is_admin() || current_user_can('manage_options')) {
        wer_add_missing_columns();
    }
}

// ======================
// فعال‌سازی پلاگین
// ======================
register_activation_hook(__FILE__, 'wer_activate_plugin');
register_deactivation_hook(__FILE__, 'wer_deactivate_plugin');

function wer_activate_plugin() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // جدول قیمت‌ها
    $table_name = $wpdb->prefix . 'exchange_prices';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source VARCHAR(100) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        price DECIMAL(18,2) NULL,
        run_time TINYINT NOT NULL,
        run_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_currency_date (currency, run_date)
    ) $charset_collate;";

    dbDelta($sql);
    
    // جدول صراف‌ها با ساختار کامل
    wer_create_exchanges_table();
    
    // جدول قیمت‌های صراف‌ها
    $vendor_prices_table = $wpdb->prefix . 'exchange_vendor_prices';
    $sql_vendor_prices = "CREATE TABLE IF NOT EXISTS $vendor_prices_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        exchange_id BIGINT UNSIGNED NOT NULL,
        currency VARCHAR(10) DEFAULT 'USD',
        buy_price DECIMAL(18,2),
        sell_price DECIMAL(18,2),
        volume DECIMAL(18,2),
        price_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY exchange_id (exchange_id),
        KEY price_date (price_date)
    ) $charset_collate;";
    
    dbDelta($sql_vendor_prices);
    
    // ایجاد نقش صراف
    wer_register_vendor_role();
}

/**
 * ایجاد جدول exchanges با ساختار کامل
 */
function wer_create_exchanges_table() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $table_name = $wpdb->prefix . 'exchanges';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        exchange_name_fa VARCHAR(200) NOT NULL,
        exchange_name_en VARCHAR(200),
        full_name_fa VARCHAR(200),
        full_name_en VARCHAR(200),
        country VARCHAR(100),
        city VARCHAR(100),
        logo VARCHAR(500),
        phone VARCHAR(50),
        whatsapp VARCHAR(50),
        telegram VARCHAR(100),
        instagram VARCHAR(200),
        status VARCHAR(20) DEFAULT 'pending',
        rating DECIMAL(3,2) DEFAULT 0.00,
        total_ratings INT DEFAULT 0,
        rejection_reason TEXT,
        email_verified TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // بررسی و اضافه کردن ستون‌های missing
    wer_add_missing_columns();
}

function wer_deactivate_plugin() {
    // حذف کرون‌ها
    wp_clear_scheduled_hook('wer_exchange_cron_hook');
}

// ======================
// بارگذاری فایل‌های اصلی - ترتیب مهم است!
// ======================

// اول فایل‌های اصلی سیستم
$required_files = [
    'includes/database.php',
    'includes/cron.php',
    'public/table.php',
    'admin-vendors.php',
    'vendors/registration-setup.php',
    'vendors/registration.php',
    'vendors/email-verification.php',
    'vendors/registration-ajax.php',
    'vendors/dashboard-templates.php',
    'vendors/dashboard-core.php',
    'vendors/dashboard-ajax.php',
    'vendors/dashboard-public.php',
    // 2.3 فایل‌های امتیازدهی و گزارشات (جدید)
    'vendors/dashboard-rating-reports.php',
// 2.4 فایل مدیریت برای ادمین
'admin/admin-reports.php',
'admin/admin-vendors.php',
    
];

foreach ($required_files as $file) {
    $file_path = WER_PLUGIN_PATH . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// ======================
// شورتکدهای اصلی
// ======================
add_shortcode('exchange_table', 'wer_render_exchange_table');


// ======================
// سیستم کرون
// ======================
add_action('wer_exchange_cron_hook', 'wer_run_exchange_cron');

function wer_schedule_cron() {
    if (!wp_next_scheduled('wer_exchange_cron_hook')) {
        wp_schedule_event(strtotime('10:00'), 'daily', 'wer_exchange_cron_hook');
        wp_schedule_event(strtotime('15:00'), 'daily', 'wer_exchange_cron_hook');
    }
}
add_action('wp', 'wer_schedule_cron');

// ======================
// شورتکد نمودار
// ======================
add_action('wp_enqueue_scripts', 'wer_enqueue_chart_scripts');

function wer_enqueue_chart_scripts() {
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
}

function wer_exchange_chart_shortcode($atts) {
    $currency = $atts['currency'] ?? 'RUB';
    
    ob_start();
    ?>
    <div style="width:100%; max-width:800px;">
        <canvas id="exchangeChart-<?php echo esc_attr($currency); ?>" height="400"></canvas>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        fetch('<?php echo admin_url("admin-ajax.php"); ?>?action=wer_get_currency_chart&currency=<?php echo $currency; ?>')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('exchangeChart-<?php echo $currency; ?>').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.dates,
                    datasets: [{
                        label: data.currency + ' میانگین قیمت روزانه (ریال)',
                        data: data.prices,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true }
                    }
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('exchange_chart', 'wer_exchange_chart_shortcode');

// ======================
// AJAX نمودار
// ======================
add_action('wp_ajax_wer_get_currency_chart', 'wer_get_currency_chart');
add_action('wp_ajax_nopriv_wer_get_currency_chart', 'wer_get_currency_chart');

function wer_get_currency_chart() {
    global $wpdb;

    $currency = sanitize_text_field($_GET['currency'] ?? 'RUB');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT run_date, AVG(price) as avg_price
             FROM {$wpdb->prefix}exchange_prices
             WHERE currency = %s
             GROUP BY run_date
             ORDER BY run_date ASC",
             $currency
        )
    );

    $dates = [];
    $prices = [];

    foreach ($rows as $row) {
        $dates[] = $row->run_date;
        $prices[] = floatval($row->avg_price);
    }

    wp_send_json([
        'dates' => $dates,
        'prices' => $prices,
        'currency' => $currency
    ]);
}

// ======================
// شورتکد داشبورد تعاملی
// ======================
function wer_exchange_dashboard_shortcode() {
    ?>
    <div id="wer-exchange-dashboard" style="max-width:800px; margin:0 auto; font-family:inherit;">
        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; justify-content:center;">
            <button class="wer-btn" data-currency="USD" style="padding:8px 15px; border-radius:5px; border:none; background:#1E90FF; color:white; cursor:pointer;">دلار</button>
            <button class="wer-btn" data-currency="EUR" style="padding:8px 15px; border-radius:5px; border:none; background:#32CD32; color:white; cursor:pointer;">یورو</button>
            <button class="wer-btn" data-currency="RUB" style="padding:8px 15px; border-radius:5px; border:none; background:#FF8C00; color:white; cursor:pointer;">روبل</button>
            <button class="wer-btn" data-currency="TRY" style="padding:8px 15px; border-radius:5px; border:none; background:#FF4500; color:white; cursor:pointer;">لیر</button>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; justify-content:center;">
            <button class="wer-range-btn" data-range="30" style="padding:6px 12px; border-radius:5px; border:none; background:#6A5ACD; color:white; cursor:pointer;">یک ماه</button>
            <button class="wer-range-btn" data-range="90" style="padding:6px 12px; border-radius:5px; border:none; background:#20B2AA; color:white; cursor:pointer;">سه ماه</button>
            <button class="wer-range-btn" data-range="365" style="padding:6px 12px; border-radius:5px; border:none; background:#FF6347; color:white; cursor:pointer;">یک سال</button>
            <button class="wer-range-btn" data-range="all" style="padding:6px 12px; border-radius:5px; border:none; background:#708090; color:white; cursor:pointer;">تمام داده‌ها</button>
        </div>

        <canvas id="wer-exchange-chart" style="width:100%; max-height:400px;"></canvas>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('wer-exchange-chart').getContext('2d');
        let exchangeChart = new Chart(ctx, { 
            type: 'line', 
            data: { labels: [], datasets: [] }, 
            options: {
                responsive: true,
                plugins: {
                    legend: { 
                        display: true,
                        labels: { font: { family: getComputedStyle(document.body).fontFamily } }
                    },
                    tooltip: { 
                        mode: 'index', 
                        intersect: false,
                        bodyFont: { family: getComputedStyle(document.body).fontFamily },
                        titleFont: { family: getComputedStyle(document.body).fontFamily }
                    }
                },
                interaction: { mode:'nearest', axis:'x', intersect:false },
                scales: {
                    x: { 
                        title: { display:true, text:'تاریخ', font:{ family:getComputedStyle(document.body).fontFamily } }, 
                        type:'time', 
                        time: { tooltipFormat:'YYYY-MM-DD' }
                    },
                    y: { 
                        title: { display:true, text:'قیمت (ریال)', font:{ family:getComputedStyle(document.body).fontFamily } } 
                    }
                }
            }
        });

        let selectedCurrency = null;
        let selectedRange = 'all';

        function loadCurrencyChart(currency, range){
            selectedCurrency = currency;
            selectedRange = range;

            fetch('<?php echo admin_url("admin-ajax.php"); ?>?action=wer_get_currency_chart&currency=' + currency)
            .then(res => res.json())
            .then(data => {
                let dates = data.dates;
                let prices = data.prices;

                if(range !== 'all'){
                    const days = parseInt(range);
                    const cutoff = new Date();
                    cutoff.setDate(cutoff.getDate() - days);

                    const filteredDates = [];
                    const filteredPrices = [];
                    for(let i=0;i<dates.length;i++){
                        if(new Date(dates[i]) >= cutoff){
                            filteredDates.push(dates[i]);
                            filteredPrices.push(prices[i]);
                        }
                    }
                    dates = filteredDates;
                    prices = filteredPrices;
                }

                exchangeChart.data.labels = dates;
                exchangeChart.data.datasets = [{
                    label: data.currency + ' میانگین قیمت روزانه (ریال)',
                    data: prices,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension:0.3,
                    pointRadius:3,
                    pointHoverRadius:6
                }];
                exchangeChart.update();
            }).catch(e => console.error(e));
        }

        document.querySelectorAll('#wer-exchange-dashboard .wer-btn').forEach(btn=>{
            btn.addEventListener('click', e=>{
                loadCurrencyChart(e.target.dataset.currency, selectedRange);
            });
        });

        document.querySelectorAll('#wer-exchange-dashboard .wer-range-btn').forEach(btn=>{
            btn.addEventListener('click', e=>{
                selectedRange = e.target.dataset.range;
                if(selectedCurrency) loadCurrencyChart(selectedCurrency, selectedRange);
            });
        });
    });
    </script>
    <?php
}
add_shortcode('wer_exchange_dashboard','wer_exchange_dashboard_shortcode');

// ======================
// بارگذاری اسکریپت‌ها
// ======================
add_action('wp_enqueue_scripts', 'wer_enqueue_dashboard_scripts');
function wer_enqueue_dashboard_scripts() {
    if(is_singular() && has_shortcode(get_post()->post_content, 'wer_exchange_dashboard')) {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('moment-js', 'https://cdn.jsdelivr.net/npm/moment@2.29.4/min/moment.min.js', [], null, true);
        wp_enqueue_script('chartjs-adapter-moment', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-moment', ['chart-js','moment-js'], null, true);
    }
}

// ======================
// اضافه کردن قابلیت‌های AJAX برای سیستم صراف‌ها
// ======================
add_action('wp_ajax_wer_submit_vendor_price', 'wer_ajax_submit_vendor_price');
add_action('wp_ajax_wer_get_vendor_price_history', 'wer_ajax_get_vendor_price_history');

// ======================
// ریدایرکت صراف‌ها بعد از لاگین
// ======================
if (!function_exists('wer_vendor_login_redirect')) {
    add_filter('login_redirect', 'wer_vendor_login_redirect', 10, 3);
    
    function wer_vendor_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('exchange_vendor', $user->roles)) {
                $vendor_data = wer_get_vendor_data($user->ID);
                if ($vendor_data && $vendor_data->status === 'rejected') {
                    return home_url('/vendor-dashboard');
                }
                return admin_url('admin.php?page=vendor-dashboard');
            }
        }
        return $redirect_to;
    }
}

// ======================
// رجیستر کردن نقش صراف در init
// ======================
add_action('init', 'wer_register_vendor_role');