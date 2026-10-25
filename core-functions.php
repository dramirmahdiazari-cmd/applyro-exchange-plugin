<?php
/**
 * توابع اصلی سیستم مدیریت صراف‌ها
 */

if (!defined('ABSPATH')) {
    exit;
}

// ======================
// 1. تعریف نقش کاربری صراف
// ======================
add_action('init', 'wer_register_vendor_role');
function wer_register_vendor_role() {
    if (!get_role('exchange_vendor')) {
        add_role('exchange_vendor', 'صراف', [
            'read'         => true,
            'upload_files' => true,
            'edit_posts'   => false,
        ]);
    }
}

// ======================
// 2. توابع اصلی
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
 * دریافت اطلاعات صراف بر اساس ID صراف
 */
function wer_get_vendor_by_id($vendor_id) {
    if (!$vendor_id) {
        return false;
    }
    
    global $wpdb;
    
    $vendor = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exchanges 
         WHERE id = %d",
        $vendor_id
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
 * دریافت متن وضعیت کلاهبردار
 */
function wer_get_scammer_status_text($status) {
    $status_texts = [
        'clean'    => 'تمیز',
        'suspected' => 'مشکوک',
        'scammer'  => 'کلاهبردار'
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

/**
 * تابع برای بررسی و اضافه کردن ستون‌های مورد نیاز
 */
function wer_check_and_add_columns() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exchanges';
    
    // دریافت ستون‌های موجود
    $columns = $wpdb->get_col("DESC $table_name", 0);
    
    if (empty($columns)) {
        return false;
    }
    
    // ستون‌های مورد نیاز
    $required_columns = [
        'rejection_reason' => 'TEXT NULL',
        'email_verified' => 'TINYINT(1) DEFAULT 0',
        'rating' => 'DECIMAL(3,2) DEFAULT 0.00',
        'total_ratings' => 'INT DEFAULT 0',
        'logo' => 'VARCHAR(500) NULL',
        'whatsapp' => 'VARCHAR(50) NULL',
        'telegram' => 'VARCHAR(100) NULL',
        'instagram' => 'VARCHAR(200) NULL',
        'scammer_status' => 'ENUM("clean","suspected","scammer") DEFAULT "clean"',
        'scammer_reports_count' => 'INT DEFAULT 0',
        'total_reports' => 'INT DEFAULT 0',
        'last_scammer_report' => 'DATETIME NULL'
    ];
    
    $added = [];
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column $definition");
            $added[] = $column;
        }
    }
    
    if (!empty($added)) {
        error_log('WER: Added missing columns: ' . implode(', ', $added));
    }
    
    return $added;
}

/**
 * ایجاد جداول جدید برای امتیازدهی و گزارشات
 */
function wer_create_rating_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // جدول امتیازدهی
    $ratings_table = $wpdb->prefix . 'exchange_vendor_ratings';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$ratings_table'") != $ratings_table) {
        $sql = "CREATE TABLE $ratings_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            rating tinyint(1) NOT NULL DEFAULT 5,
            comment text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY user_id (user_id),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // جدول گزارش تخلف
    $reports_table = $wpdb->prefix . 'exchange_vendor_reports';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$reports_table'") != $reports_table) {
        $sql = "CREATE TABLE $reports_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            reporter_name varchar(100) NOT NULL,
            reporter_phone varchar(20) NOT NULL,
            report_text text NOT NULL,
            report_type enum('scam','bad_behavior','late_payment','fake_prices','other') DEFAULT 'other',
            ip_address varchar(45) DEFAULT NULL,
            status enum('pending','reviewed','resolved') DEFAULT 'pending',
            admin_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY status (status),
            KEY report_type (report_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * محاسبه امتیاز صراف
 */
function wer_calculate_vendor_rating($vendor_id) {
    global $wpdb;
    
    $ratings_table = $wpdb->prefix . 'exchange_vendor_ratings';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$ratings_table'") != $ratings_table) {
        return 0;
    }
    
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            AVG(rating) as average_rating,
            COUNT(*) as total_ratings
         FROM $ratings_table 
         WHERE vendor_id = %d",
        $vendor_id
    ));
    
    return $stats;
}

/**
 * دریافت تعداد گزارشات تخلف
 */
function wer_get_vendor_reports_count($vendor_id, $status = 'pending') {
    global $wpdb;
    
    $reports_table = $wpdb->prefix . 'exchange_vendor_reports';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$reports_table'") != $reports_table) {
        return 0;
    }
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $reports_table 
         WHERE vendor_id = %d AND status = %s",
        $vendor_id, $status
    ));
    
    return intval($count);
}

/**
 * دریافت امتیازها و نظرات صراف
 */
function wer_get_vendor_ratings($vendor_id, $limit = 10, $offset = 0) {
    global $wpdb;
    
    $ratings_table = $wpdb->prefix . 'exchange_vendor_ratings';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$ratings_table'") != $ratings_table) {
        return [];
    }
    
    $ratings = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, u.display_name 
         FROM $ratings_table r
         LEFT JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
         WHERE r.vendor_id = %d 
         ORDER BY r.created_at DESC
         LIMIT %d OFFSET %d",
        $vendor_id, $limit, $offset
    ));
    
    return $ratings;
}

/**
 * دریافت گزارشات تخلف صراف
 */
function wer_get_vendor_reports($vendor_id, $limit = 20, $offset = 0) {
    global $wpdb;
    
    $reports_table = $wpdb->prefix . 'exchange_vendor_reports';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$reports_table'") != $reports_table) {
        return [];
    }
    
    $reports = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $reports_table 
         WHERE vendor_id = %d 
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        $vendor_id, $limit, $offset
    ));
    
    return $reports;
}

// ======================
// 3. منوهای ادمین - نسخه به‌روز شده
// ======================
add_action('admin_menu', 'wer_add_admin_menus');
function wer_add_admin_menus() {
    add_menu_page(
        'مدیریت صراف‌ها',
        'صراف‌ها',
        'manage_options',
        'wer-vendors',
        'wer_vendors_admin_page',
        'dashicons-store',
        30
    );

    add_submenu_page(
        'wer-vendors',
        'تایید درخواست‌ها',
        'تایید درخواست‌ها',
        'manage_options',
        'wer-vendor-approval',
        'wer_vendor_approval_page'
    );

    add_submenu_page(
        'wer-vendors',
        'لیست صراف‌ها',
        'همه صراف‌ها',
        'manage_options',
        'wer-vendor-list',
        'wer_vendor_list_page'
    );

    add_submenu_page(
        'wer-vendors',
        'گزارشات',
        'گزارشات',
        'manage_options',
        'wer-vendor-reports',
        'wer_vendor_reports_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'گزارشات تخلف',
        'گزارشات تخلف',
        'manage_options',
        'wer-scam-reports',
        'wer_scam_reports_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'صرافان مشکوک',
        'صرافان مشکوک',
        'manage_options',
        'wer-suspected-vendors',
        'wer_suspected_vendors_page'
    );
}

// ======================
// 4. صفحه اصلی مدیریت صراف‌ها
// ======================
function wer_vendors_admin_page() {
    global $wpdb;

    // بررسی و اضافه کردن ستون‌های missing
    wer_check_and_add_columns();
    
    // ایجاد جداول جدید
    wer_create_rating_tables();
    
    $total_vendors     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges");
    $pending_vendors   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'pending'");
    $verified_vendors  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'verified'");
    $rejected_vendors  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'rejected'");
    
    // آمار جدید
    $scammer_vendors   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'scammer'");
    $suspected_vendors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'suspected'");
    $total_reports     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_reports");
    $pending_reports   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_reports WHERE status = 'pending'");

    ?>
    <div class="wrap">
        <h1>🚀 مدیریت سیستم صراف‌ها</h1>

        <div class="wer-admin-welcome">
            <p>به پنل مدیریت سیستم صراف‌ها خوش آمدید. از منوی سمت چپ می‌توانید بخش‌های مختلف را مدیریت کنید.</p>
        </div>

        <div class="wer-admin-stats">
            <div class="wer-stat-box">
                <div class="wer-stat-icon">📊</div>
                <div class="wer-stat-content">
                    <h3><?php echo $total_vendors; ?></h3>
                    <p>کل صراف‌ها</p>
                </div>
            </div>

            <div class="wer-stat-box">
                <div class="wer-stat-icon">⏳</div>
                <div class="wer-stat-content">
                    <h3><?php echo $pending_vendors; ?></h3>
                    <p>در انتظار تایید</p>
                </div>
            </div>

            <div class="wer-stat-box">
                <div class="wer-stat-icon">✅</div>
                <div class="wer-stat-content">
                    <h3><?php echo $verified_vendors; ?></h3>
                    <p>تایید شده</p>
                </div>
            </div>

            <div class="wer-stat-box">
                <div class="wer-stat-icon">❌</div>
                <div class="wer-stat-content">
                    <h3><?php echo $rejected_vendors; ?></h3>
                    <p>رد شده</p>
                </div>
            </div>
            
            <!-- آمار جدید -->
            <div class="wer-stat-box">
                <div class="wer-stat-icon">🚨</div>
                <div class="wer-stat-content">
                    <h3><?php echo $scammer_vendors; ?></h3>
                    <p>کلاهبردار</p>
                </div>
            </div>
            
            <div class="wer-stat-box">
                <div class="wer-stat-icon">⚠️</div>
                <div class="wer-stat-content">
                    <h3><?php echo $suspected_vendors; ?></h3>
                    <p>مشکوک</p>
                </div>
            </div>
            
            <div class="wer-stat-box">
                <div class="wer-stat-icon">📋</div>
                <div class="wer-stat-content">
                    <h3><?php echo $total_reports; ?></h3>
                    <p>گزارش تخلف</p>
                </div>
            </div>
            
            <div class="wer-stat-box">
                <div class="wer-stat-icon">⏳</div>
                <div class="wer-stat-content">
                    <h3><?php echo $pending_reports; ?></h3>
                    <p>گزارش در انتظار</p>
                </div>
            </div>
        </div>

        <div class="wer-admin-quick-actions">
            <h2>⚡ اقدامات سریع</h2>
            <div class="wer-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval'); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes-alt"></span>
                    تایید درخواست‌های جدید
                    <?php if ($pending_vendors > 0): ?>
                        <span class="update-count"><?php echo $pending_vendors; ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?php echo admin_url('admin.php?page=wer-scam-reports'); ?>" class="button button-danger button-large">
                    <span class="dashicons dashicons-warning"></span>
                    گزارشات تخلف
                    <?php if ($pending_reports > 0): ?>
                        <span class="update-count"><?php echo $pending_reports; ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?php echo admin_url('admin.php?page=wer-vendor-list'); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-list-view"></span>
                    مشاهده لیست کامل
                </a>

                <a href="<?php echo admin_url('users.php?role=exchange_vendor'); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-admin-users"></span>
                    مدیریت کاربران صراف
                </a>
            </div>
        </div>

        <div class="wer-admin-recent">
            <h2>📝 آخرین درخواست‌ها</h2>
            <?php
            $recent_vendors = $wpdb->get_results("
                SELECT e.*, u.user_email 
                FROM {$wpdb->prefix}exchanges e
                LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                ORDER BY e.created_at DESC
                LIMIT 5
            ");

            if ($recent_vendors):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام صراف</th>
                        <th>ایمیل</th>
                        <th>تلفن</th>
                        <th>وضعیت</th>
                        <th>امتیاز</th>
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_vendors as $vendor): 
                        $scammer_status = $vendor->scammer_status ?? 'clean';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                            <?php if ($vendor->exchange_name_en): ?>
                                <br><small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                            <?php endif; ?>
                            <?php if ($scammer_status === 'scammer'): ?>
                                <br><span class="scammer-badge" style="color: #dc3545; font-size: 11px;">🚨 کلاهبردار</span>
                            <?php elseif ($scammer_status === 'suspected'): ?>
                                <br><span class="suspected-badge" style="color: #ffc107; font-size: 11px;">⚠️ مشکوک</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($vendor->user_email); ?></td>
                        <td><?php echo esc_html($vendor->phone); ?></td>
                        <td>
                            <span class="wer-status-badge wer-status-<?php echo $vendor->status; ?>">
                                <?php 
                                $status_text = [
                                    'pending'  => 'در انتظار',
                                    'verified' => 'تایید شده',
                                    'rejected' => 'رد شده'
                                ];
                                echo $status_text[$vendor->status] ?? $vendor->status;
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($vendor->rating > 0): ?>
                                <span style="color: #ffc107; font-weight: bold;">★ <?php echo number_format($vendor->rating, 1); ?></span>
                                <br><small>(<?php echo $vendor->total_ratings ?? 0; ?> رأی)</small>
                            <?php else: ?>
                                <span style="color: #6c757d;">بدون امتیاز</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date_i18n('Y/m/d', strtotime($vendor->created_at)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $vendor->id); ?>" 
                               class="button button-small">مشاهده</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>هنوز هیچ صرافی ثبت‌نام نکرده است.</p>
            <?php endif; ?>
        </div>
        
        <!-- آخرین گزارشات تخلف -->
        <div class="wer-admin-recent" style="margin-top: 30px;">
            <h2>🚨 آخرین گزارشات تخلف</h2>
            <?php
            $recent_reports = $wpdb->get_results("
                SELECT r.*, e.exchange_name_fa 
                FROM {$wpdb->prefix}exchange_vendor_reports r
                LEFT JOIN {$wpdb->prefix}exchanges e ON r.vendor_id = e.id
                ORDER BY r.created_at DESC
                LIMIT 5
            ");

            if ($recent_reports):
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>صراف</th>
                        <th>گزارش‌دهنده</th>
                        <th>نوع تخلف</th>
                        <th>تاریخ</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_reports as $report): 
                        $type_labels = [
                            'scam' => 'کلاهبرداری',
                            'bad_behavior' => 'رفتار نامناسب',
                            'late_payment' => 'تأخیر در واریز',
                            'fake_prices' => 'قیمت غیرواقعی',
                            'other' => 'سایر'
                        ];
                        
                        $status_labels = [
                            'pending' => 'در انتظار',
                            'reviewed' => 'بررسی شده',
                            'resolved' => 'حل شده'
                        ];
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $report->vendor_id); ?>">
                                <?php echo esc_html($report->exchange_name_fa); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html($report->reporter_name); ?>
                            <br><small><?php echo esc_html($report->reporter_phone); ?></small>
                        </td>
                        <td><?php echo $type_labels[$report->report_type] ?? 'نامشخص'; ?></td>
                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($report->created_at)); ?></td>
                        <td>
                            <span class="wer-status-badge wer-status-<?php echo $report->status; ?>">
                                <?php echo $status_labels[$report->status] ?? $report->status; ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wer-scam-reports&report_id=' . $report->id); ?>" 
                               class="button button-small">مشاهده</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; color: #6c757d;">
                    هنوز هیچ گزارشی ثبت نشده است.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .wer-admin-welcome {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        border-left: 4px solid #4CAF50;
    }

    .wer-admin-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .wer-stat-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .wer-stat-icon {
        font-size: 32px;
    }

    .wer-stat-content h3 {
        margin: 0;
        font-size: 24px;
        color: #333;
    }

    .wer-stat-content p {
        margin: 5px 0 0 0;
        color: #666;
    }

    .wer-admin-quick-actions {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }

    .wer-action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .wer-action-buttons .button-large {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px 20px;
    }

    .wer-action-buttons .button-danger {
        background: #dc3545;
        border-color: #dc3545;
        color: white;
    }

    .wer-action-buttons .button-danger:hover {
        background: #c82333;
        border-color: #bd2130;
    }

    .wer-action-buttons .update-count {
        background: #d63638;
        color: white;
        border-radius: 10px;
        padding: 2px 8px;
        font-size: 12px;
        margin-right: 5px;
    }

    .wer-admin-recent {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .wer-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    .wer-status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .wer-status-verified {
        background: #d4edda;
        color: #155724;
    }

    .wer-status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .scammer-badge, .suspected-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
    }

    .scammer-badge {
        background: #f8d7da;
        color: #721c24;
    }

    .suspected-badge {
        background: #fff3cd;
        color: #856404;
    }

    @media (max-width: 768px) {
        .wer-admin-stats {
            grid-template-columns: 1fr;
        }

        .wer-action-buttons {
            flex-direction: column;
        }

        .wer-action-buttons .button-large {
            justify-content: center;
        }
    }
    </style>
    <?php
}

// ======================
// 5. صفحه تایید درخواست‌ها - نسخه کاملاً اصلاح شده
// ======================
function wer_vendor_approval_page() {
    global $wpdb;
    
    // بررسی و اضافه کردن ستون‌های missing قبل از هر کاری
    wer_check_and_add_columns();
    
    // ایجاد جداول جدید
    wer_create_rating_tables();

    // اعتبارسنجی و تایید درخواست - نسخه کاملاً اصلاح شده
    if (isset($_GET['action']) && isset($_GET['vendor_id']) && isset($_REQUEST['_wpnonce'])) {
        $vendor_id = intval($_GET['vendor_id']);
        $action = sanitize_text_field($_GET['action']);

        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'vendor_action')) {
            if (in_array($action, ['approve', 'reject'])) {
                $status = $action === 'approve' ? 'verified' : 'rejected';
                
                // آماده‌سازی داده‌های به‌روزرسانی
                $data = array(
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                );

                $where = array('id' => $vendor_id);
                
                // اگر رد می‌شود، دلیل رد را ذخیره کن
                if ($action === 'reject' && isset($_POST['admin_note'])) {
                    $data['rejection_reason'] = sanitize_textarea_field($_POST['admin_note']);
                }
                // اگر تایید می‌شود، دلیل رد را پاک کن
                elseif ($action === 'approve') {
                    $data['rejection_reason'] = '';
                    $data['email_verified'] = 1; // تایید ایمیل همزمان با تایید حساب
                }

                // آماده‌سازی فرمت‌ها برای به‌روزرسانی
                $formats = array();
                foreach ($data as $key => $value) {
                    $formats[] = '%s'; // همه فیلدها string هستند
                }

                // اجرای آپدیت
                $result = $wpdb->update($wpdb->prefix . 'exchanges', $data, $where, $formats);

                if ($result !== false) {
                    // ارسال ایمیل
                    wer_send_vendor_status_email($vendor_id, $action);
                    
                    // نمایش پیام موفقیت
                    echo '<div class="notice notice-success is-dismissible"><p>✅ وضعیت صراف با موفقیت به "' . ($status === 'verified' ? 'تایید شده' : 'رد شده') . '" تغییر یافت.</p></div>';
                    
                    // لاگ
                    error_log('WER: Vendor ' . $vendor_id . ' status changed to ' . $status . ' by admin.');
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ خطا در به‌روزرسانی وضعیت صراف: ' . $wpdb->last_error . '</p></div>';
                    error_log('WER: Database error on vendor update: ' . $wpdb->last_error);
                }
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>❌ توکن امنیتی نامعتبر است.</p></div>';
        }
    }

    // نمایش جزئیات یا لیست درخواست‌ها
    $vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
    if ($vendor_id) {
        wer_show_vendor_details($vendor_id);
    } else {
        wer_show_pending_vendors_list();
    }
}

// ======================
// 6. صفحه لیست همه صراف‌ها
// ======================
function wer_vendor_list_page() {
    global $wpdb;

    $vendors = $wpdb->get_results("
        SELECT e.*, u.user_email, u.user_registered 
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        ORDER BY e.created_at DESC
    ");

    ?>
    <div class="wrap">
        <h1>📋 لیست همه صراف‌ها</h1>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status_filter" id="status_filter">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار</option>
                    <option value="verified">تایید شده</option>
                    <option value="rejected">رد شده</option>
                    <option value="scammer">کلاهبردار</option>
                    <option value="suspected">مشکوک</option>
                </select>
                <button class="button action" id="filterBtn">فیلتر</button>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped" id="werVendorsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>نام صرافی</th>
                    <th>ایمیل</th>
                    <th>تلفن</th>
                    <th>کشور</th>
                    <th>وضعیت</th>
                    <th>امتیاز</th>
                    <th>گزارشات</th>
                    <th>تاریخ ثبت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vendors): ?>
                    <?php foreach ($vendors as $vendor): 
                        $scammer_status = $vendor->scammer_status ?? 'clean';
                        $total_reports = $vendor->total_reports ?? 0;
                    ?>
                        <tr data-status="<?php echo $vendor->status; ?>" data-scammer="<?php echo $scammer_status; ?>">
                            <td><?php echo $vendor->id; ?></td>
                            <td>
                                <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                <?php if ($vendor->exchange_name_en): ?>
                                    <br><small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                                <?php endif; ?>
                                <?php if ($scammer_status === 'scammer'): ?>
                                    <br><span class="scammer-badge" style="color: #dc3545; font-size: 11px;">🚨 کلاهبردار</span>
                                <?php elseif ($scammer_status === 'suspected'): ?>
                                    <br><span class="suspected-badge" style="color: #ffc107; font-size: 11px;">⚠️ مشکوک</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                            <td><?php echo esc_html($vendor->phone); ?></td>
                            <td><?php echo esc_html($vendor->country); ?></td>
                            <td>
                                <span class="wer-status-badge wer-status-<?php echo $vendor->status; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending'  => 'در انتظار',
                                        'verified' => 'تایید شده',
                                        'rejected' => 'رد شده'
                                    ];
                                    echo $status_text[$vendor->status] ?? $vendor->status;
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($vendor->rating > 0): ?>
                                    <span style="color: #ffc107; font-weight: bold;">★ <?php echo number_format($vendor->rating, 1); ?></span>
                                    <br><small>(<?php echo $vendor->total_ratings ?? 0; ?> رأی)</small>
                                <?php else: ?>
                                    <span style="color: #6c757d;">بدون امتیاز</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($total_reports > 0): ?>
                                    <span style="color: #dc3545; font-weight: bold;"><?php echo $total_reports; ?></span>
                                    <br><small>گزارش</small>
                                <?php else: ?>
                                    <span style="color: #28a745;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date_i18n('Y/m/d', strtotime($vendor->created_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $vendor->id); ?>"
                                   class="button button-small">👁️ مشاهده</a>
                                <a href="<?php echo get_edit_user_link($vendor->user_id); ?>"
                                   class="button button-small" target="_blank">✏️ ویرایش</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 30px;">
                            <p style="color: #666; font-size: 16px;">هنوز هیچ صرافی ثبت‌نام نکرده است.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelect = document.getElementById('status_filter');
        const filterBtn = document.getElementById('filterBtn');
        const tableRows = document.querySelectorAll('#werVendorsTable tbody tr');
        
        filterBtn.addEventListener('click', function() {
            const selectedStatus = filterSelect.value;
            
            tableRows.forEach(row => {
                if (!selectedStatus) {
                    row.style.display = '';
                    return;
                }
                
                if (selectedStatus === 'scammer' || selectedStatus === 'suspected') {
                    if (row.dataset.scammer === selectedStatus) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                } else if (row.dataset.status === selectedStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    </script>

    <style>
    .wer-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    .wer-status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .wer-status-verified {
        background: #d4edda;
        color: #155724;
    }

    .wer-status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .scammer-badge, .suspected-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
    }

    .scammer-badge {
        background: #f8d7da;
        color: #721c24;
    }

    .suspected-badge {
        background: #fff3cd;
        color: #856404;
    }
    </style>
    <?php
}

// ======================
// 7. صفحه گزارشات
// ======================
function wer_vendor_reports_page() {
    global $wpdb;

    $total_vendors    = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges");
    $pending_count    = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'pending'");
    $verified_count   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'verified'");
    $rejected_count   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'rejected'");
    $total_prices     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_prices");
    $today_prices     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_prices WHERE price_date = CURDATE()");
    $today_registrations = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE DATE(created_at) = %s",
        current_time('Y-m-d')
    ));
    
    // آمار جدید
    $scammer_vendors   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'scammer'");
    $suspected_vendors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'suspected'");
    $total_ratings     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_ratings");
    $today_ratings     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_ratings WHERE DATE(created_at) = CURDATE()");
    $total_reports     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_reports");
    $pending_reports   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_reports WHERE status = 'pending'");

    ?>
    <div class="wrap">
        <h1>📊 گزارشات سیستم صراف‌ها</h1>

        <div class="wer-reports-grid">
            <div class="wer-report-card">
                <h2>👥 آمار کاربران</h2>
                <div class="wer-report-stats">
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $total_vendors; ?></span>
                        <span class="wer-stat-label">کل صراف‌ها</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $pending_count; ?></span>
                        <span class="wer-stat-label">در انتظار</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $verified_count; ?></span>
                        <span class="wer-stat-label">تایید شده</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $rejected_count; ?></span>
                        <span class="wer-stat-label">رد شده</span>
                    </div>
                </div>
            </div>

            <div class="wer-report-card">
                <h2>💰 آمار قیمت‌ها</h2>
                <div class="wer-report-stats">
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $total_prices; ?></span>
                        <span class="wer-stat-label">کل قیمت‌ها</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $today_prices; ?></span>
                        <span class="wer-stat-label">امروز</span>
                    </div>
                </div>
            </div>

            <div class="wer-report-card">
                <h2>⭐ آمار امتیازدهی</h2>
                <div class="wer-report-stats">
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $total_ratings; ?></span>
                        <span class="wer-stat-label">کل امتیازها</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $today_ratings; ?></span>
                        <span class="wer-stat-label">امروز</span>
                    </div>
                </div>
            </div>

            <div class="wer-report-card">
                <h2>🚨 آمار گزارشات</h2>
                <div class="wer-report-stats">
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $total_reports; ?></span>
                        <span class="wer-stat-label">کل گزارشات</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $pending_reports; ?></span>
                        <span class="wer-stat-label">در انتظار</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="wer-reports-grid" style="margin-top: 20px;">
            <div class="wer-report-card">
                <h2>🌍 توزیع جغرافیایی</h2>
                <?php
                $countries = $wpdb->get_results("
                    SELECT country, COUNT(*) as count 
                    FROM {$wpdb->prefix}exchanges 
                    WHERE country IS NOT NULL AND country != ''
                    GROUP BY country
                    ORDER BY count DESC
                    LIMIT 5
                ");
                
                if ($countries):
                ?>
                <ul class="wer-country-list">
                    <?php foreach ($countries as $country): ?>
                        <li>
                            <span class="wer-country-name"><?php echo esc_html($country->country); ?></span>
                            <span class="wer-country-count"><?php echo $country->count; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">اطلاعاتی موجود نیست</p>
                <?php endif; ?>
            </div>

            <div class="wer-report-card">
                <h2>📈 فعالیت امروز</h2>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; color: #4CAF50; margin-bottom: 10px;"><?php echo $today_registrations; ?></div>
                    <div style="color: #666;">ثبت‌نام جدید امروز</div>
                </div>
            </div>
            
            <div class="wer-report-card">
                <h2>⚠️ صرافان مشکوک و کلاهبردار</h2>
                <div class="wer-report-stats">
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $scammer_vendors; ?></span>
                        <span class="wer-stat-label">کلاهبردار</span>
                    </div>
                    <div class="wer-report-stat">
                        <span class="wer-stat-number"><?php echo $suspected_vendors; ?></span>
                        <span class="wer-stat-label">مشکوک</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="wer-chart-card">
            <h2>📊 نمودار وضعیت صراف‌ها</h2>
            <canvas id="werStatusChart" height="100"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('werStatusChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['تایید شده', 'در انتظار', 'رد شده', 'کلاهبردار', 'مشکوک'],
                datasets: [{
                    data: [
                        <?php echo $verified_count; ?>, 
                        <?php echo $pending_count; ?>, 
                        <?php echo $rejected_count; ?>,
                        <?php echo $scammer_vendors; ?>,
                        <?php echo $suspected_vendors; ?>
                    ],
                    backgroundColor: [
                        '#4CAF50',
                        '#FFC107',
                        '#F44336',
                        '#DC3545',
                        '#FF9800'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true
                    }
                }
            }
        });
    });
    </script>

    <style>
    .wer-reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .wer-report-card, .wer-chart-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .wer-report-card h2, .wer-chart-card h2 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .wer-report-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .wer-report-stat {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .wer-stat-number {
        display: block;
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }

    .wer-stat-label {
        display: block;
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .wer-country-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .wer-country-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .wer-country-list li:last-child {
        border-bottom: none;
    }

    .wer-country-name {
        color: #333;
    }

    .wer-country-count {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .wer-reports-grid {
            grid-template-columns: 1fr;
        }

        .wer-report-stats {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

// ======================
// 8. صفحه گزارشات تخلف
// ======================
function wer_scam_reports_page() {
    global $wpdb;
    
    // بررسی و اضافه کردن ستون‌های missing
    wer_check_and_add_columns();
    
    // ایجاد جداول جدید
    wer_create_rating_tables();
    
    // عملیات بر روی گزارشات
    $action = $_GET['action'] ?? '';
    $report_id = intval($_GET['report_id'] ?? 0);
    $vendor_id = intval($_GET['vendor_id'] ?? 0);
    
    if ($action === 'update_report' && $report_id > 0 && wp_verify_nonce($_GET['_wpnonce'], 'update_report')) {
        $status = sanitize_text_field($_GET['status'] ?? 'pending');
        $notes = sanitize_textarea_field($_GET['notes'] ?? '');
        
        $wpdb->update(
            $wpdb->prefix . 'exchange_vendor_reports',
            [
                'status' => $status,
                'admin_notes' => $notes,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $report_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        echo '<div class="notice notice-success"><p>✅ وضعیت گزارش به‌روزرسانی شد.</p></div>';
    }
    
    if ($action === 'mark_scammer' && $vendor_id > 0 && wp_verify_nonce($_GET['_wpnonce'], 'mark_scammer')) {
        $reason = sanitize_textarea_field($_GET['reason'] ?? 'کلاهبردار تأیید شده');
        
        // به‌روزرسانی وضعیت صراف
        $wpdb->update(
            $wpdb->prefix . 'exchanges',
            [
                'scammer_status' => 'scammer',
                'status' => 'rejected',
                'rejection_reason' => 'کلاهبردار - ' . $reason,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $vendor_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        // به‌روزرسانی تمام گزارشات مربوطه
        $wpdb->update(
            $wpdb->prefix . 'exchange_vendor_reports',
            [
                'status' => 'reviewed',
                'admin_notes' => 'صراف به عنوان کلاهبردار علامت‌گذاری شد. ' . $reason,
                'updated_at' => current_time('mysql')
            ],
            ['vendor_id' => $vendor_id, 'status' => 'pending'],
            ['%s', '%s', '%s'],
            ['%d', '%s']
        );
        
        echo '<div class="notice notice-success"><p>✅ صراف به عنوان کلاهبردار علامت‌گذاری شد.</p></div>';
    }
    
    // دریافت گزارشات
    $reports = $wpdb->get_results("
        SELECT r.*, 
               e.exchange_name_fa, 
               e.phone as vendor_phone,
               e.scammer_status,
               e.status as vendor_status
        FROM {$wpdb->prefix}exchange_vendor_reports r
        LEFT JOIN {$wpdb->prefix}exchanges e ON r.vendor_id = e.id
        ORDER BY r.created_at DESC
        LIMIT 100
    ");
    
    // آمار
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_reports,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            COUNT(DISTINCT r.vendor_id) as unique_vendors,
            SUM(CASE WHEN e.scammer_status = 'scammer' THEN 1 ELSE 0 END) as scammer_vendors
        FROM {$wpdb->prefix}exchange_vendor_reports r
        LEFT JOIN {$wpdb->prefix}exchanges e ON r.vendor_id = e.id
    ");
    
    ?>
    <div class="wrap">
        <h1>🚨 گزارشات تخلف صراف‌ها</h1>
        
        <!-- آمار -->
        <div class="card" style="margin: 20px 0; padding: 20px;">
            <h3>📊 آمار گزارشات</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                    <div style="font-size: 24px; font-weight: bold; color: #6c757d;"><?php echo $stats->total_reports ?? 0; ?></div>
                    <div>کل گزارشات</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 10px;">
                    <div style="font-size: 24px; font-weight: bold; color: #856404;"><?php echo $stats->pending ?? 0; ?></div>
                    <div>در انتظار بررسی</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #cce5ff; border-radius: 10px;">
                    <div style="font-size: 24px; font-weight: bold; color: #004085;"><?php echo $stats->reviewed ?? 0; ?></div>
                    <div>بررسی شده</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 10px;">
                    <div style="font-size: 24px; font-weight: bold; color: #155724;"><?php echo $stats->resolved ?? 0; ?></div>
                    <div>حل شده</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 10px;">
                    <div style="font-size: 24px; font-weight: bold; color: #721c24;"><?php echo $stats->scammer_vendors ?? 0; ?></div>
                    <div>صراف کلاهبردار</div>
                </div>
            </div>
        </div>
        
        <!-- لیست گزارشات -->
        <div class="card">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>صراف</th>
                        <th>گزارش‌دهنده</th>
                        <th>تلفن گزارش‌دهنده</th>
                        <th>نوع تخلف</th>
                        <th>متن گزارش</th>
                        <th>تاریخ</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reports): ?>
                        <?php foreach ($reports as $report): 
                            $type_labels = [
                                'scam' => 'کلاهبرداری',
                                'bad_behavior' => 'رفتار نامناسب',
                                'late_payment' => 'تأخیر در واریز',
                                'fake_prices' => 'قیمت غیرواقعی',
                                'other' => 'سایر'
                            ];
                            
                            $status_labels = [
                                'pending' => '<span class="badge badge-warning">در انتظار</span>',
                                'reviewed' => '<span class="badge badge-info">بررسی شده</span>',
                                'resolved' => '<span class="badge badge-success">حل شده</span>'
                            ];
                            
                            $scammer_badge = $report->scammer_status === 'scammer' ? 
                                '<span class="badge badge-danger" style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-right: 5px;">کلاهبردار</span>' : '';
                        ?>
                        <tr>
                            <td><?php echo $report->id; ?></td>
                            <td>
                                <?php echo $scammer_badge; ?>
                                <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $report->vendor_id); ?>" target="_blank">
                                    <?php echo esc_html($report->exchange_name_fa); ?>
                                </a>
                                <br>
                                <small><?php echo esc_html($report->vendor_phone); ?></small>
                            </td>
                            <td><?php echo esc_html($report->reporter_name); ?></td>
                            <td><?php echo esc_html($report->reporter_phone); ?></td>
                            <td><?php echo $type_labels[$report->report_type] ?? 'نامشخص'; ?></td>
                            <td>
                                <div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #f8f9fa; border-radius: 5px;">
                                    <?php echo nl2br(esc_html($report->report_text)); ?>
                                </div>
                            </td>
                            <td><?php echo date_i18n('y/m/d H:i', strtotime($report->created_at)); ?></td>
                            <td><?php echo $status_labels[$report->status] ?? $report->status; ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <a href="<?php echo admin_url('admin.php?page=wer-scam-reports&action=update_report&report_id=' . $report->id . '&status=reviewed&_wpnonce=' . wp_create_nonce('update_report')); ?>" 
                                       class="button button-small button-primary">
                                        بررسی شده
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=wer-scam-reports&action=update_report&report_id=' . $report->id . '&status=resolved&_wpnonce=' . wp_create_nonce('update_report')); ?>" 
                                       class="button button-small button-success">
                                        حل شده
                                    </a>
                                    <?php if ($report->status === 'pending'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=wer-scam-reports&action=mark_scammer&vendor_id=' . $report->vendor_id . '&reason=' . urlencode('بر اساس گزارش کاربران') . '&_wpnonce=' . wp_create_nonce('mark_scammer')); ?>" 
                                           class="button button-small button-danger" 
                                           onclick="return confirm('⚠️ آیا مطمئن هستید که می‌خواهید این صراف را به عنوان کلاهبردار علامت‌گذاری کنید؟ این عمل غیرقابل بازگشت است!')">
                                            علامت‌گذاری کلاهبردار
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                هیچ گزارشی ثبت نشده است.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <style>
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-warning { background: #ffc107; color: #856404; }
    .badge-info { background: #17a2b8; color: white; }
    .badge-success { background: #28a745; color: white; }
    .badge-danger { background: #dc3545; color: white; }
    .button-danger { background: #dc3545; border-color: #dc3545; color: white; }
    .button-danger:hover { background: #c82333; border-color: #bd2130; }
    .button-success { background: #28a745; border-color: #28a745; color: white; }
    .button-success:hover { background: #218838; border-color: #1e7e34; }
    </style>
    <?php
}

// ======================
// 9. صفحه صرافان مشکوک
// ======================
function wer_suspected_vendors_page() {
    global $wpdb;
    
    // ایجاد جداول جدید
    wer_create_rating_tables();
    
    // عملیات بر روی صرافان
    $action = $_GET['action'] ?? '';
    $vendor_id = intval($_GET['vendor_id'] ?? 0);
    
    if ($action === 'remove_scammer' && $vendor_id > 0 && wp_verify_nonce($_GET['_wpnonce'], 'remove_scammer')) {
        $wpdb->update(
            $wpdb->prefix . 'exchanges',
            [
                'scammer_status' => 'clean',
                'scammer_reports_count' => 0,
                'last_scammer_report' => NULL,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $vendor_id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );
        
        echo '<div class="notice notice-success"><p>✅ علامت کلاهبردار از صراف حذف شد.</p></div>';
    }
    
    // دریافت صرافان مشکوک
    $suspicious_vendors = $wpdb->get_results("
        SELECT e.*, 
               COUNT(r.id) as report_count,
               SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_reports
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->prefix}exchange_vendor_reports r ON e.id = r.vendor_id
        WHERE e.scammer_status IN ('scammer', 'suspected')
        GROUP BY e.id
        ORDER BY e.scammer_status DESC, pending_reports DESC
        LIMIT 50
    ");
    
    ?>
    <div class="wrap">
        <h1>⚠️ صرافان مشکوک و کلاهبردار</h1>
        
        <div class="card" style="margin: 20px 0; padding: 20px;">
            <h3>🔍 فیلتر صرافان</h3>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=wer-suspected-vendors'); ?>" class="button <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'button-primary' : ''; ?>">
                    همه
                </a>
                <a href="<?php echo admin_url('admin.php?page=wer-suspected-vendors&status=scammer'); ?>" class="button <?php echo (isset($_GET['status']) && $_GET['status'] === 'scammer') ? 'button-primary' : ''; ?>" style="background: #dc3545; border-color: #dc3545; color: white;">
                    کلاهبردار
                </a>
                <a href="<?php echo admin_url('admin.php?page=wer-suspected-vendors&status=suspected'); ?>" class="button <?php echo (isset($_GET['status']) && $_GET['status'] === 'suspected') ? 'button-primary' : ''; ?>" style="background: #ffc107; border-color: #ffc107; color: #856404;">
                    مشکوک
                </a>
            </div>
        </div>
        
        <?php if ($suspicious_vendors): ?>
        <div class="card">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>صراف</th>
                        <th>تلفن</th>
                        <th>وضعیت</th>
                        <th>گزارشات کل</th>
                        <th>گزارشات در انتظار</th>
                        <th>امتیاز</th>
                        <th>آخرین گزارش</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $filter_status = $_GET['status'] ?? 'all';
                    foreach ($suspicious_vendors as $vendor): 
                        if ($filter_status !== 'all' && $vendor->scammer_status !== $filter_status) {
                            continue;
                        }
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $vendor->id); ?>" target="_blank">
                                <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                            </a>
                            <?php if ($vendor->exchange_name_en): ?>
                                <br><small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($vendor->phone); ?></td>
                        <td>
                            <?php if ($vendor->scammer_status === 'scammer'): ?>
                                <span class="badge badge-danger">🚨 کلاهبردار</span>
                            <?php elseif ($vendor->scammer_status === 'suspected'): ?>
                                <span class="badge badge-warning">⚠️ مشکوک</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $vendor->report_count; ?></td>
                        <td>
                            <?php if ($vendor->pending_reports > 0): ?>
                                <span style="color: #dc3545; font-weight: bold;"><?php echo $vendor->pending_reports; ?></span>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($vendor->rating > 0): ?>
                                <span style="color: #ffc107; font-weight: bold;">★ <?php echo number_format($vendor->rating, 1); ?></span>
                                <br><small>(<?php echo $vendor->total_ratings ?? 0; ?> رأی)</small>
                            <?php else: ?>
                                <span style="color: #6c757d;">بدون امتیاز</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($vendor->last_scammer_report): ?>
                                <?php echo date_i18n('y/m/d', strtotime($vendor->last_scammer_report)); ?>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $vendor->id); ?>" 
                                   class="button button-small">
                                    مشاهده جزئیات
                                </a>
                                <?php if ($vendor->scammer_status === 'scammer'): ?>
                                    <a href="<?php echo admin_url('admin.php?page=wer-suspected-vendors&action=remove_scammer&vendor_id=' . $vendor->id . '&_wpnonce=' . wp_create_nonce('remove_scammer')); ?>" 
                                       class="button button-small button-success"
                                       onclick="return confirm('آیا مطمئن هستید که می‌خواهید این صراف را از لیست کلاهبرداران حذف کنید؟')">
                                        حذف از کلاهبرداران
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo admin_url('admin.php?page=wer-scam-reports'); ?>" 
                                   class="button button-small button-secondary">
                                    مشاهده گزارشات
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <h3 style="color: #6c757d;">🎉 هیچ صراف مشکوک یا کلاهبردار یافت نشد!</h3>
                <p>همه صرافان در وضعیت تمیز قرار دارند.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
    }
    .badge-warning { background: #ffc107; color: #856404; }
    .badge-danger { background: #dc3545; color: white; }
    .button-success { background: #28a745; border-color: #28a745; color: white; }
    .button-success:hover { background: #218838; border-color: #1e7e34; }
    .button-secondary { background: #6c757d; border-color: #6c757d; color: white; }
    .button-secondary:hover { background: #5a6268; border-color: #545b62; }
    </style>
    <?php
}

// ======================
// 10. توابع کمکی
// ======================

/**
 * ارسال ایمیل وضعیت صراف
 */
function wer_send_vendor_status_email($vendor_id, $action) {
    global $wpdb;
    
    $vendor = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, u.user_email, u.display_name 
         FROM {$wpdb->prefix}exchanges e
         LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
         WHERE e.id = %d",
        $vendor_id
    ));
    
    if (!$vendor) {
        return;
    }
    
    $subject = $action === 'approve' 
        ? '✅ حساب صرافی شما تایید شد - ' . get_bloginfo('name')
        : '❌ درخواست حساب صرافی شما رد شد - ' . get_bloginfo('name');
    
    $message = $action === 'approve'
        ? '<div style="font-family: Tahoma; padding: 20px; background: #f8f9fa;">
            <h2>تبریک! حساب صرافی شما تایید شد</h2>
            <p>سلام ' . $vendor->exchange_name_fa . '،</p>
            <p>حساب صرافی شما با موفقیت تایید شد. اکنون می‌توانید:</p>
            <ul>
                <li>وارد داشبورد صراف شوید</li>
                <li>قیمت‌های روزانه را ثبت کنید</li>
                <li>پروفایل خود را تکمیل کنید</li>
            </ul>
            <p style="margin-top: 20px;">
                <a href="' . home_url('/vendor-dashboard') . '" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">
                    ورود به داشبورد صراف
                </a>
            </p>
        </div>'
        : '<div style="font-family: Tahoma; padding: 20px; background: #f8f9fa;">
            <h2>متأسفانه درخواست شما تأیید نشد</h2>
            <p>سلام ' . $vendor->exchange_name_fa . '،</p>
            <p>متأسفانه درخواست حساب صرافی شما تأیید نشد.</p>
            <p>لطفاً برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.</p>
        </div>';
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($vendor->user_email, $subject, $message, $headers);
}

/**
 * نمایش جزئیات صراف
 */
function wer_show_vendor_details($vendor_id) {
    global $wpdb;
    
    $vendor = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, u.user_email, u.display_name 
         FROM {$wpdb->prefix}exchanges e
         LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
         WHERE e.id = %d",
        $vendor_id
    ));
    
    if (!$vendor) {
        echo '<div class="notice notice-error"><p>صراف مورد نظر یافت نشد.</p></div>';
        return;
    }
    
    // دریافت اطلاعات اضافی
    $scammer_status = $vendor->scammer_status ?? 'clean';
    $total_reports = $vendor->total_reports ?? 0;
    $pending_reports = wer_get_vendor_reports_count($vendor_id, 'pending');
    
    ?>
    <div class="wrap">
        <h1>بررسی درخواست: <?php echo esc_html($vendor->exchange_name_fa); ?></h1>
        
        <?php if ($scammer_status === 'scammer'): ?>
            <div class="notice notice-error" style="border-left-color: #dc3545;">
                <p>🚨 <strong>هشدار:</strong> این صراف به عنوان کلاهبردار علامت‌گذاری شده است!</p>
            </div>
        <?php elseif ($scammer_status === 'suspected'): ?>
            <div class="notice notice-warning" style="border-left-color: #ffc107;">
                <p>⚠️ <strong>توجه:</strong> این صراف مشکوک گزارش شده است (<?php echo $pending_reports; ?> گزارش در انتظار).</p>
            </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 30px; margin-top: 20px;">
            <div style="flex: 2;">
                <div class="card">
                    <h2>📋 اطلاعات صراف</h2>
                    <table class="widefat">
                        <tr>
                            <th width="150">نام صرافی (فارسی):</th>
                            <td><?php echo esc_html($vendor->exchange_name_fa); ?></td>
                        </tr>
                        <tr>
                            <th>نام صرافی (انگلیسی):</th>
                            <td><?php echo esc_html($vendor->exchange_name_en); ?></td>
                        </tr>
                        <tr>
                            <th>نام کامل (فارسی):</th>
                            <td><?php echo esc_html($vendor->full_name_fa); ?></td>
                        </tr>
                        <tr>
                            <th>نام کامل (انگلیسی):</th>
                            <td><?php echo esc_html($vendor->full_name_en); ?></td>
                        </tr>
                        <tr>
                            <th>ایمیل:</th>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                        </tr>
                        <tr>
                            <th>تلفن:</th>
                            <td><?php echo esc_html($vendor->phone); ?></td>
                        </tr>
                        <tr>
                            <th>واتساپ:</th>
                            <td><?php echo esc_html($vendor->whatsapp); ?></td>
                        </tr>
                        <tr>
                            <th>تلگرام:</th>
                            <td><?php echo $vendor->telegram ? '@' . esc_html($vendor->telegram) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>اینستاگرام:</th>
                            <td><?php echo $vendor->instagram ? '@' . esc_html($vendor->instagram) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>کشور:</th>
                            <td><?php echo esc_html($vendor->country); ?></td>
                        </tr>
                        <tr>
                            <th>شهر:</th>
                            <td><?php echo esc_html($vendor->city); ?></td>
                        </tr>
                        <tr>
                            <th>امتیاز:</th>
                            <td>
                                <?php if ($vendor->rating > 0): ?>
                                    <span style="color: #ffc107; font-weight: bold;">★ <?php echo number_format($vendor->rating, 1); ?></span>
                                    <small>(<?php echo $vendor->total_ratings ?? 0; ?> رأی)</small>
                                <?php else: ?>
                                    <span style="color: #6c757d;">بدون امتیاز</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>گزارشات:</th>
                            <td>
                                <span style="color: <?php echo $total_reports > 0 ? '#dc3545' : '#28a745'; ?>; font-weight: bold;">
                                    <?php echo $total_reports; ?> گزارش
                                </span>
                                <?php if ($pending_reports > 0): ?>
                                    <small style="color: #856404;">(<?php echo $pending_reports; ?> در انتظار)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>وضعیت کلاهبردار:</th>
                            <td>
                                <?php 
                                $status_labels = [
                                    'clean' => '<span style="color: #28a745;">✅ تمیز</span>',
                                    'suspected' => '<span style="color: #ffc107;">⚠️ مشکوک</span>',
                                    'scammer' => '<span style="color: #dc3545;">🚨 کلاهبردار</span>'
                                ];
                                echo $status_labels[$scammer_status] ?? $scammer_status;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>تاریخ ثبت‌نام:</th>
                            <td><?php echo date_i18n('Y/m/d H:i', strtotime($vendor->created_at)); ?></td>
                        </tr>
                        <tr>
                            <th>وضعیت فعلی:</th>
                            <td>
                                <span class="wer-status-badge wer-status-<?php echo $vendor->status; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending'  => 'در انتظار تایید',
                                        'verified' => 'تایید شده',
                                        'rejected' => 'رد شده'
                                    ];
                                    echo $status_text[$vendor->status] ?? $vendor->status;
                                    ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div style="flex: 1;">
                <div class="card">
                    <h2>✅ عملیات تایید</h2>
                    <form method="get" style="margin-top: 20px;">
                        <input type="hidden" name="page" value="wer-vendor-approval">
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor->id; ?>">
                        <?php wp_nonce_field('vendor_action'); ?>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: bold;">نظر ادمین:</label>
                            <textarea name="admin_note" rows="4" style="width: 100%; padding: 10px;"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px; flex-direction: column;">
                            <button type="submit" name="action" value="approve" 
                                    class="button button-primary button-large" 
                                    style="background: #40c057; border-color: #40c057; text-align: center;">
                                <span style="font-size: 18px; margin-left: 5px;">✅</span>
                                تایید صراف
                            </button>
                            
                            <button type="submit" name="action" value="reject" 
                                    class="button button-large"
                                    style="background: #fa5252; border-color: #fa5252; color: white; text-align: center;">
                                <span style="font-size: 18px; margin-left: 5px;">❌</span>
                                رد درخواست
                            </button>
                            
                            <?php if ($scammer_status !== 'scammer' && $pending_reports >= 3): ?>
                                <a href="<?php echo admin_url('admin.php?page=wer-scam-reports&action=mark_scammer&vendor_id=' . $vendor->id . '&reason=' . urlencode('دارای ' . $pending_reports . ' گزارش در انتظار') . '&_wpnonce=' . wp_create_nonce('mark_scammer')); ?>" 
                                   class="button button-large"
                                   style="background: #dc3545; border-color: #dc3545; color: white; text-align: center;"
                                   onclick="return confirm('⚠️ آیا مطمئن هستید که می‌خواهید این صراف را به عنوان کلاهبردار علامت‌گذاری کنید؟ این عمل غیرقابل بازگشت است!')">
                                    <span style="font-size: 18px; margin-left: 5px;">🚨</span>
                                    علامت‌گذاری کلاهبردار
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <h3>🔗 لینک‌های مفید</h3>
                    <ul style="margin-top: 10px;">
                        <li><a href="<?php echo get_edit_user_link($vendor->user_id); ?>" target="_blank">✏️ ویرایش اطلاعات کاربر</a></li>
                        <li><a href="<?php echo home_url('/exchange/?vendor_id=' . $vendor->id); ?>" target="_blank">👁️ مشاهده پروفایل عمومی</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=wer-scam-reports'); ?>">🚨 گزارشات تخلف این صراف</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=wer-vendor-list'); ?>">📋 لیست همه صراف‌ها</a></li>
                        <li><a href="<?php echo admin_url('users.php?s=' . urlencode($vendor->exchange_name_fa)); ?>" target="_blank">🔍 جستجوی کاربر</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <style>
    .card {
        background: white;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        border-radius: 4px;
    }
    .card h2, .card h3 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .widefat {
        width: 100%;
        border-collapse: collapse;
    }
    .widefat th {
        text-align: right;
        padding: 10px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    .widefat td {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
    }
    </style>
    <?php
}

/**
 * نمایش لیست درخواست‌های در انتظار
 */
function wer_show_pending_vendors_list() {
    global $wpdb;
    
    $pending_vendors = $wpdb->get_results("
        SELECT e.*, u.user_email, u.display_name 
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        WHERE e.status = 'pending'
        ORDER BY e.created_at ASC
    ");
    
    ?>
    <div class="wrap">
        <h1>⏳ درخواست‌های در انتظار تایید</h1>
        
        <?php if ($pending_vendors): ?>
            <div class="wer-pending-list">
                <?php foreach ($pending_vendors as $vendor): 
                    $scammer_status = $vendor->scammer_status ?? 'clean';
                    $total_reports = $vendor->total_reports ?? 0;
                ?>
                    <div class="wer-pending-item" style="border-left-color: <?php echo $scammer_status === 'scammer' ? '#dc3545' : ($scammer_status === 'suspected' ? '#ffc107' : '#ffc107'); ?>;">
                        <div class="wer-pending-header">
                            <div class="wer-pending-title">
                                <h3><?php echo esc_html($vendor->exchange_name_fa); ?></h3>
                                <?php if ($vendor->exchange_name_en): ?>
                                    <small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                                <?php endif; ?>
                                <?php if ($scammer_status === 'scammer'): ?>
                                    <span class="scammer-badge">🚨 کلاهبردار</span>
                                <?php elseif ($scammer_status === 'suspected'): ?>
                                    <span class="suspected-badge">⚠️ مشکوک</span>
                                <?php endif; ?>
                                <?php if ($total_reports > 0): ?>
                                    <span style="color: #dc3545; font-size: 12px; margin-right: 10px;"><?php echo $total_reports; ?> گزارش</span>
                                <?php endif; ?>
                            </div>
                            <div class="wer-pending-date">
                                <?php echo date_i18n('Y/m/d', strtotime($vendor->created_at)); ?>
                            </div>
                        </div>
                        
                        <div class="wer-pending-info">
                            <div class="wer-info-row">
                                <span class="wer-info-label">📧 ایمیل:</span>
                                <span class="wer-info-value"><?php echo esc_html($vendor->user_email); ?></span>
                            </div>
                            <div class="wer-info-row">
                                <span class="wer-info-label">📞 تلفن:</span>
                                <span class="wer-info-value"><?php echo esc_html($vendor->phone); ?></span>
                            </div>
                            <div class="wer-info-row">
                                <span class="wer-info-label">🌍 کشور:</span>
                                <span class="wer-info-value"><?php echo esc_html($vendor->country); ?></span>
                            </div>
                        </div>
                        
                        <div class="wer-pending-actions">
                            <a href="<?php echo admin_url('admin.php?page=wer-vendor-approval&vendor_id=' . $vendor->id); ?>" 
                               class="button button-primary">
                                <span style="margin-left: 5px;">👁️</span>
                                بررسی و تصمیم‌گیری
                            </a>
                            
                            <a href="<?php echo get_edit_user_link($vendor->user_id); ?>" 
                               target="_blank" 
                               class="button">
                                <span style="margin-left: 5px;">👤</span>
                                مشاهده کاربر
                            </a>
                            
                            <?php if ($scammer_status === 'scammer'): ?>
                                <a href="<?php echo admin_url('admin.php?page=wer-scam-reports'); ?>" 
                                   class="button button-danger">
                                    <span style="margin-left: 5px;">🚨</span>
                                    مشاهده گزارشات
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notice notice-success">
                <p>🎉 هیچ درخواست در انتظار تایید وجود ندارد!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .wer-pending-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 20px;
    }
    
    .wer-pending-item {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-left: 4px solid #ffc107;
    }
    
    .wer-pending-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .wer-pending-title h3 {
        margin: 0 0 5px 0;
        color: #333;
    }
    
    .wer-pending-title small {
        color: #666;
        font-size: 13px;
    }
    
    .wer-pending-date {
        background: #f8f9fa;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        color: #666;
    }
    
    .wer-pending-info {
        margin-bottom: 15px;
    }
    
    .wer-info-row {
        display: flex;
        margin-bottom: 8px;
    }
    
    .wer-info-label {
        width: 80px;
        font-weight: bold;
        color: #555;
    }
    
    .wer-info-value {
        flex: 1;
        color: #333;
    }
    
    .wer-pending-actions {
        display: flex;
        gap: 10px;
    }
    
    .scammer-badge, .suspected-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
        margin-right: 10px;
    }
    
    .scammer-badge {
        background: #f8d7da;
        color: #721c24;
    }
    
    .suspected-badge {
        background: #fff3cd;
        color: #856404;
    }
    
    @media (max-width: 768px) {
        .wer-pending-header {
            flex-direction: column;
            gap: 10px;
        }
        
        .wer-pending-actions {
            flex-direction: column;
        }
        
        .wer-pending-actions .button {
            text-align: center;
        }
    }
    </style>
    <?php
}

// ======================
// 11. فعال‌سازی هوک برای بررسی خودکار ستون‌ها
// ======================
add_action('admin_init', 'wer_admin_init_check_columns');
function wer_admin_init_check_columns() {
    if (isset($_GET['page']) && strpos($_GET['page'], 'wer-') === 0) {
        wer_check_and_add_columns();
        wer_create_rating_tables();
    }
}

// ======================
// 12. تابع دیباگ برای رفع مشکلات دستی
// ======================
add_action('admin_init', 'wer_debug_fix_database');
function wer_debug_fix_database() {
    if (isset($_GET['wer_fix_db']) && current_user_can('manage_options')) {
        global $wpdb;
        
        // 1. بررسی وجود جدول exchanges
        $table_name = $wpdb->prefix . 'exchanges';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            echo '<div class="notice notice-error"><p>جدول exchanges وجود ندارد!</p></div>';
            return;
        }
        
        // 2. اضافه کردن ستون‌های missing
        $columns_added = wer_check_and_add_columns();
        
        // 3. ایجاد جداول جدید
        wer_create_rating_tables();
        
        if (!empty($columns_added)) {
            echo '<div class="notice notice-success"><p>✅ ستون‌های زیر اضافه شدند: ' . implode(', ', $columns_added) . '</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>✅ همه ستون‌ها موجود هستند.</p></div>';
        }
        
        // 4. بررسی وضعیت صراف‌ها
        $vendors = $wpdb->get_results("SELECT id, exchange_name_fa, status, scammer_status FROM $table_name");
        
        echo '<div class="notice notice-info"><p>بررسی وضعیت صراف‌ها:</p><ul>';
        foreach ($vendors as $vendor) {
            echo '<li>صراف #' . $vendor->id . ' - ' . $vendor->exchange_name_fa . ': ' . $vendor->status . ' (وضعیت کلاهبردار: ' . ($vendor->scammer_status ?? 'clean') . ')</li>';
        }
        echo '</ul></div>';
        
        // 4. دکمه رفع مشکلات
        echo '<form method="post">';
        echo '<input type="hidden" name="wer_fix_status" value="1">';
        wp_nonce_field('wer_fix_db_action', 'wer_fix_nonce');
        echo '<button type="submit" class="button button-primary">رفع مشکلات وضعیت‌ها</button>';
        echo '</form>';
    }
    
    if (isset($_POST['wer_fix_status']) && wp_verify_nonce($_POST['wer_fix_nonce'], 'wer_fix_db_action')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'exchanges';
        
        // تنظیم وضعیت برای مواردی که status خالی یا NULL است
        $result = $wpdb->query("UPDATE $table_name SET status = 'pending' WHERE status IS NULL OR status = ''");
        
        echo '<div class="notice notice-success"><p>✅ ' . $result . ' رکورد به‌روزرسانی شد.</p></div>';
    }
}

// ======================
// 13. تابع دیباگ برای رفع سریع مشکلات status
// ======================
add_action('admin_init', 'wer_fix_status_problem');
function wer_fix_status_problem() {
    // فقط برای ادمین اصلی
    if (!current_user_can('manage_options') || !isset($_GET['wer_fix_status'])) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'exchanges';
    
    // 1. بررسی رکوردهایی که status خالی دارند
    $empty_status = $wpdb->get_results("SELECT id, exchange_name_fa, status FROM $table_name WHERE status = '' OR status IS NULL");
    
    if (!empty($empty_status)) {
        foreach ($empty_status as $vendor) {
            $wpdb->update(
                $table_name,
                ['status' => 'pending'],
                ['id' => $vendor->id],
                ['%s'],
                ['%d']
            );
        }
        
        echo '<div class="notice notice-success"><p>✅ ' . count($empty_status) . ' رکورد با status خالی به "pending" تغییر یافت.</p></div>';
    }
    
    // 2. بررسی ساختار جدول
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    echo '<div class="notice notice-info"><p>ساختار جدول exchanges:</p><ul>';
    foreach ($columns as $col) {
        echo '<li>' . $col->Field . ' - ' . $col->Type . ' - Default: ' . $col->Default . '</li>';
    }
    echo '</ul></div>';
}