<?php
/**
 * مدیریت صراف‌ها - برای ادمین سایت (نسخه ادغام شده و اصلاح شده)
 */

if (!defined('ABSPATH')) exit;

// اضافه کردن منوی مدیریت
add_action('admin_menu', 'wer_add_vendor_admin_menu');

function wer_add_vendor_admin_menu() {
    // فقط برای مدیران
    if (!current_user_can('manage_options')) return;
    
    add_menu_page(
        'مدیریت صراف‌ها',
        'صراف‌ها',
        'manage_options',
        'wer-vendors',
        'wer_render_vendors_admin_page',
        'dashicons-store',
        30
    );
    
    add_submenu_page(
        'wer-vendors',
        'درخواست‌های در انتظار',
        'در انتظار تایید',
        'manage_options',
        'wer-vendors-pending',
        'wer_render_pending_vendors_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'همه صراف‌ها',
        'همه صراف‌ها',
        'manage_options',
        'wer-vendors-all',
        'wer_render_all_vendors_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'صرافان مشکوک',
        'صرافان مشکوک',
        'manage_options',
        'wer-vendors-suspicious',
        'wer_render_suspicious_vendors_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'صرافان کلاهبردار',
        'صرافان کلاهبردار',
        'manage_options',
        'wer-vendors-scammers',
        'wer_render_scammers_vendors_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'گزارشات تخلف',
        'گزارشات تخلف',
        'manage_options',
        'wer-vendors-reports',
        'wer_render_vendor_reports_page'
    );
    
    add_submenu_page(
        'wer-vendors',
        'تاریخچه تاییدها',
        'تاریخچه تایید',
        'manage_options',
        'wer-vendors-history',
        'wer_render_approval_history_page'
    );
        add_submenu_page(
        'wer-vendors',
        'حذف صراف',
        '🗑️ حذف صراف',
        'manage_options',
        'wer-vendors-delete',
        'wer_render_delete_vendor_page'
    );

}

// صفحه اصلی مدیریت صراف‌ها
function wer_render_vendors_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // آمار کلی
    $total_vendors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges");
    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'pending'");
    $approved_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'verified'");
    $rejected_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'rejected'");
    
    // آمار گزارشات
    $scammer_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'scammer'");
    $suspected_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'suspected'");
    $total_reports = $wpdb->get_var("SELECT SUM(scammer_reports_count) FROM {$wpdb->prefix}exchanges");
    
    // آخرین درخواست‌ها
    $recent_vendors = $wpdb->get_results("
        SELECT e.*, u.user_email 
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    
    // آخرین گزارشات تخلف
    $recent_reports = $wpdb->get_results("
        SELECT r.*, e.exchange_name_fa 
        FROM {$wpdb->prefix}exchange_vendor_reports r
        LEFT JOIN {$wpdb->prefix}exchanges e ON r.vendor_id = e.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    ?>
    
    <div class="wrap">
        <h1>🚀 مدیریت صراف‌ها</h1>
        
        <!-- آمار کلی -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; font-weight: bold; color: #2196F3;"><?php echo $total_vendors; ?></div>
                <div style="color: #666;">کل صراف‌ها</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; font-weight: bold; color: #FF9800;"><?php echo $pending_count; ?></div>
                <div style="color: #666;">در انتظار تایید</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; font-weight: bold; color: #4CAF50;"><?php echo $approved_count; ?></div>
                <div style="color: #666;">تایید شده</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; font-weight: bold; color: #F44336;"><?php echo $rejected_count; ?></div>
                <div style="color: #666;">رد شده</div>
            </div>
        </div>
        
        <!-- آمار گزارشات -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #dc3545;">
                <div style="font-size: 32px; font-weight: bold; color: #dc3545;"><?php echo $scammer_count; ?></div>
                <div style="color: #666;">صراف کلاهبردار</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #ffc107;">
                <div style="font-size: 32px; font-weight: bold; color: #ffc107;"><?php echo $suspected_count; ?></div>
                <div style="color: #666;">صراف مشکوک</div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #6c757d;">
                <div style="font-size: 32px; font-weight: bold; color: #6c757d;"><?php echo $total_reports ?: 0; ?></div>
                <div style="color: #666;">گزارش تخلف</div>
            </div>
        </div>
        
        <!-- لینک‌های سریع -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 30px 0;">
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending'); ?>" 
               style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; display: block;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 40px;">⏳</div>
                    <div>
                        <h3 style="margin: 0 0 10px 0;">درخواست‌های در انتظار</h3>
                        <p style="margin: 0; color: #666;">بررسی و تایید صراف‌های جدید</p>
                        <?php if ($pending_count > 0): ?>
                            <div style="margin-top: 10px; display: inline-block; background: #FF9800; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                <?php echo $pending_count; ?> درخواست جدید
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports'); ?>" 
               style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; display: block; border-left: 4px solid #dc3545;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 40px;">⚠️</div>
                    <div>
                        <h3 style="margin: 0 0 10px 0;">گزارشات تخلف</h3>
                        <p style="margin: 0; color: #666;">مدیریت گزارشات کاربران</p>
                        <?php if ($total_reports > 0): ?>
                            <div style="margin-top: 10px; display: inline-block; background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                <?php echo $total_reports; ?> گزارش جدید
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 30px 0;">
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-scammers'); ?>" 
               style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; display: block; border-left: 4px solid #dc3545;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 40px;">🚫</div>
                    <div>
                        <h3 style="margin: 0 0 10px 0;">صرافان کلاهبردار</h3>
                        <p style="margin: 0; color: #666;">مدیریت صرافان علامت‌گذاری شده</p>
                        <?php if ($scammer_count > 0): ?>
                            <div style="margin-top: 10px; display: inline-block; background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                <?php echo $scammer_count; ?> صراف کلاهبردار
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-all'); ?>" 
               style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; display: block;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 40px;">📋</div>
                    <div>
                        <h3 style="margin: 0 0 10px 0;">لیست همه صراف‌ها</h3>
                        <p style="margin: 0; color: #666;">مدیریت و مشاهده همه صراف‌ها</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- آخرین درخواست‌ها و گزارشات -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
            <!-- آخرین درخواست‌ها -->
            <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>📝 آخرین درخواست‌ها</h2>
                
                <?php if ($recent_vendors): ?>
                    <table style="width: 100%; margin-top: 15px; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #f0f0f0;">
                                <th style="text-align: right; padding: 10px; font-weight: bold;">نام صراف</th>
                                <th style="text-align: right; padding: 10px; font-weight: bold;">وضعیت</th>
                                <th style="text-align: right; padding: 10px; font-weight: bold;">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_vendors as $vendor): ?>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 10px;">
                                        <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                        <br><small><?php echo esc_html($vendor->user_email); ?></small>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?php 
                                        $status_text = [
                                            'pending' => '⏳ در انتظار',
                                            'verified' => '✅ تایید شده',
                                            'rejected' => '❌ رد شده'
                                        ];
                                        $status_class = [
                                            'pending' => 'pending',
                                            'verified' => 'approved',
                                            'rejected' => 'rejected'
                                        ];
                                        ?>
                                        <span class="vendor-status-badge <?php echo $status_class[$vendor->status] ?? ''; ?>">
                                            <?php echo $status_text[$vendor->status] ?? $vendor->status; ?>
                                        </span>
                                        <?php if ($vendor->scammer_status === 'scammer'): ?>
                                            <br><small style="color: #dc3545;">🚫 کلاهبردار</small>
                                        <?php elseif ($vendor->scammer_status === 'suspected'): ?>
                                            <br><small style="color: #ffc107;">⚠️ مشکوک</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; color: #666;">
                                        <?php echo date_i18n('y/m/d', strtotime($vendor->created_at)); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-all'); ?>" class="button">
                            مشاهده همه صراف‌ها
                        </a>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 30px; color: #666;">
                        هنوز هیچ صرافی ثبت‌نام نکرده است.
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- آخرین گزارشات -->
            <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>⚠️ آخرین گزارشات تخلف</h2>
                
                <?php if ($recent_reports): ?>
                    <table style="width: 100%; margin-top: 15px; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #f0f0f0;">
                                <th style="text-align: right; padding: 10px; font-weight: bold;">صراف</th>
                                <th style="text-align: right; padding: 10px; font-weight: bold;">نوع</th>
                                <th style="text-align: right; padding: 10px; font-weight: bold;">تاریخ</th>
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
                            ?>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 10px;">
                                        <strong><?php echo esc_html($report->exchange_name_fa); ?></strong>
                                        <br><small><?php echo substr($report->reporter_phone, 0, 4) . '****'; ?></small>
                                    </td>
                                    <td style="padding: 10px;">
                                        <span style="color: #dc3545; font-weight: bold;">
                                            <?php echo $type_labels[$report->report_type] ?? 'نامشخص'; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; color: #666;">
                                        <?php echo date_i18n('y/m/d', strtotime($report->created_at)); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports'); ?>" class="button button-danger">
                            مشاهده همه گزارشات
                        </a>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 30px; color: #666;">
                        هیچ گزارشی ثبت نشده است.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
    .vendor-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }
    .vendor-status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }
    .vendor-status-badge.approved {
        background: #d4edda;
        color: #155724;
    }
    .vendor-status-badge.rejected {
        background: #f8d7da;
        color: #721c24;
    }
    .button-danger {
        background: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    .button-danger:hover {
        background: #c82333;
        border-color: #bd2130;
    }
    </style>
    
    <?php
}

// صفحه درخواست‌های در انتظار
function wer_render_pending_vendors_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // پردازش عملیات تایید/رد
    if (isset($_GET['action']) && isset($_GET['vendor_id']) && wp_verify_nonce($_GET['_wpnonce'], 'vendor_action')) {
        $vendor_id = intval($_GET['vendor_id']);
        $action = sanitize_text_field($_GET['action']);
        
        if (in_array($action, ['approve', 'reject'])) {
            $status = $action === 'approve' ? 'verified' : 'rejected';
            $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_textarea_field($_POST['rejection_reason']) : '';
            
            $wpdb->update(
                $wpdb->prefix . 'exchanges',
                [
                    'status' => $status,
                    'rejection_reason' => $rejection_reason,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $vendor_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            // ارسال ایمیل به صراف
            wer_send_vendor_status_notification($vendor_id, $status, $rejection_reason);
            
            echo '<div class="notice notice-success"><p>عملیات با موفقیت انجام شد.</p></div>';
        }
    }
    
    // پردازش عملیات علامت‌گذاری کلاهبردار
    if (isset($_POST['mark_scammer_submit'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'mark_scammer_action')) {
            wp_die('اعتبارسنجی فرم نامعتبر است.');
        }
        
        $vendor_id = intval($_POST['vendor_id']);
        $reason = sanitize_textarea_field($_POST['scammer_reason']);
        
        if (wer_mark_vendor_as_scammer($vendor_id, $reason)) {
            echo '<div class="notice notice-success"><p>صراف به عنوان کلاهبردار علامت‌گذاری شد.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>خطا در علامت‌گذاری صراف.</p></div>';
        }
    }
    
    // پردازش عملیات حذف علامت کلاهبردار
    if (isset($_GET['remove_scammer']) && isset($_GET['vendor_id']) && wp_verify_nonce($_GET['_wpnonce'], 'remove_scammer_action')) {
        $vendor_id = intval($_GET['vendor_id']);
        
        if (wer_remove_scammer_mark($vendor_id)) {
            echo '<div class="notice notice-success"><p>علامت کلاهبردار با موفقیت حذف شد.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>خطا در حذف علامت کلاهبردار.</p></div>';
        }
    }
    
    // نمایش جزئیات یک صراف خاص
    $vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
    
    if ($vendor_id > 0) {
        wer_render_vendor_details_page($vendor_id);
        return;
    }
    
    // نمایش لیست درخواست‌های در انتظار
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
        
        <?php if (empty($pending_vendors)): ?>
            <div class="notice notice-success">
                <p>🎉 هیچ درخواست در انتظار تایید وجود ندارد!</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام صراف</th>
                        <th>ایمیل</th>
                        <th>تلفن</th>
                        <th>کشور</th>
                        <th>تاریخ ثبت‌نام</th>
                        <th>وضعیت ایمیل</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_vendors as $vendor): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                <?php if ($vendor->exchange_name_en): ?>
                                    <br><small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                            <td><?php echo esc_html($vendor->phone); ?></td>
                            <td><?php echo esc_html($vendor->country); ?></td>
                            <td><?php echo date_i18n('Y/m/d', strtotime($vendor->created_at)); ?></td>
                            <td>
                                <?php if ($vendor->email_verified): ?>
                                    <span style="color: #4CAF50; font-weight: bold;">✅ تایید شده</span>
                                <?php else: ?>
                                    <span style="color: #FF9800; font-weight: bold;">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id); ?>" 
                                   class="button button-primary">
                                    بررسی و تصمیم‌گیری
                                </a>
                                <a href="<?php echo get_edit_user_link($vendor->user_id); ?>" 
                                   target="_blank" class="button">
                                    مشاهده کاربر
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php
}

// صفحه جزئیات یک صراف خاص
function wer_render_vendor_details_page($vendor_id) {
    global $wpdb;
    
    $vendor = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, u.user_email, u.display_name, u.user_registered 
         FROM {$wpdb->prefix}exchanges e
         LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
         WHERE e.id = %d",
        $vendor_id
    ));
    
    if (!$vendor) {
        echo '<div class="notice notice-error"><p>صراف مورد نظر یافت نشد.</p></div>';
        return;
    }
    
    // دریافت مدارک صراف
    $documents = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exchange_vendor_docs 
         WHERE exchange_id = %d",
        $vendor_id
    ));
    
    // آمار امتیازات
    $rating_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_ratings,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
         FROM {$wpdb->prefix}exchange_vendor_ratings 
         WHERE vendor_id = %d",
        $vendor_id
    ));
    
    // آمار گزارشات
    $report_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_reports,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
            SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_reports,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports
         FROM {$wpdb->prefix}exchange_vendor_reports 
         WHERE vendor_id = %d",
        $vendor_id
    ));
    ?>
    
    <div class="wrap">
        <h1>بررسی درخواست: <?php echo esc_html($vendor->exchange_name_fa); ?></h1>
        
        <!-- هشدار برای صرافان مشکوک یا کلاهبردار -->
        <?php if ($vendor->scammer_status === 'scammer'): ?>
            <div style="background: #dc3545; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: white;">🚫 این صراف به عنوان کلاهبردار علامت‌گذاری شده است</h3>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">
                    وضعیت: رد شده | دلیل: <?php echo esc_html($vendor->rejection_reason ?: 'کلاهبردار تأیید شده'); ?>
                </p>
            </div>
        <?php elseif ($vendor->scammer_status === 'suspected'): ?>
            <div style="background: #ffc107; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #856404;">⚠️ این صراف مشکوک گزارش شده است</h3>
                <p style="margin: 10px 0 0 0;">
                    <?php echo $vendor->scammer_reports_count ?: 0; ?> گزارش تخلف ثبت شده است
                </p>
            </div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 30px; margin-top: 20px; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 300px;">
                <div class="card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h2>📋 اطلاعات صراف</h2>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; width: 150px;">نام صرافی (فارسی):</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->exchange_name_fa); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">نام صرافی (انگلیسی):</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->exchange_name_en); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">ایمیل:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->user_email); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">تلفن:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->phone); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">واتساپ:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->whatsapp); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">تلگرام:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->telegram); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">اینستاگرام:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->instagram); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">کشور:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->country); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">شهر:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($vendor->city); ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">وضعیت ایمیل:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">
                                <?php echo $vendor->email_verified ? '✅ تایید شده' : '⏳ در انتظار'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">وضعیت کلاهبردار:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">
                                <?php 
                                $scammer_status_text = [
                                    'clean' => '✅ تمیز',
                                    'suspected' => '⚠️ مشکوک',
                                    'scammer' => '🚫 کلاهبردار'
                                ];
                                echo $scammer_status_text[$vendor->scammer_status] ?? 'نامشخص';
                                ?>
                                <?php if ($vendor->scammer_reports_count > 0): ?>
                                    (<?php echo $vendor->scammer_reports_count; ?> گزارش)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th style="text-align: right; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">تاریخ ثبت‌نام:</th>
                            <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">
                                <?php echo date_i18n('Y/m/d H:i', strtotime($vendor->created_at)); ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- آمار امتیازات و گزارشات -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <!-- آمار امتیازات -->
                    <div class="card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <h3>⭐ امتیازات</h3>
                        <?php if ($rating_stats && $rating_stats->total_ratings > 0): ?>
                            <div style="text-align: center; margin: 15px 0;">
                                <div style="font-size: 32px; font-weight: bold; color: #FFC107;">
                                    <?php echo number_format($rating_stats->avg_rating, 1); ?>
                                </div>
                                <div style="color: #666;">از ۵ امتیاز</div>
                                <div style="margin-top: 10px; color: #6c757d;">
                                    (<?php echo $rating_stats->total_ratings; ?> رأی)
                                </div>
                            </div>
                            <div style="font-size: 12px; color: #6c757d;">
                                <div style="display: flex; align-items: center; margin: 5px 0;">
                                    <div style="width: 60px;">۵ ستاره:</div>
                                    <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px;">
                                        <div style="background: #28a745; height: 100%; width: <?php echo ($rating_stats->five_star / $rating_stats->total_ratings) * 100; ?>%; border-radius: 4px;"></div>
                                    </div>
                                    <div style="width: 30px; text-align: left;"><?php echo $rating_stats->five_star; ?></div>
                                </div>
                                <div style="display: flex; align-items: center; margin: 5px 0;">
                                    <div style="width: 60px;">۴ ستاره:</div>
                                    <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px;">
                                        <div style="background: #28a745; height: 100%; width: <?php echo ($rating_stats->four_star / $rating_stats->total_ratings) * 100; ?>%; border-radius: 4px;"></div>
                                    </div>
                                    <div style="width: 30px; text-align: left;"><?php echo $rating_stats->four_star; ?></div>
                                </div>
                                <div style="display: flex; align-items: center; margin: 5px 0;">
                                    <div style="width: 60px;">۳ ستاره:</div>
                                    <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px;">
                                        <div style="background: #ffc107; height: 100%; width: <?php echo ($rating_stats->three_star / $rating_stats->total_ratings) * 100; ?>%; border-radius: 4px;"></div>
                                    </div>
                                    <div style="width: 30px; text-align: left;"><?php echo $rating_stats->three_star; ?></div>
                                </div>
                                <div style="display: flex; align-items: center; margin: 5px 0;">
                                    <div style="width: 60px;">۲ ستاره:</div>
                                    <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px;">
                                        <div style="background: #fd7e14; height: 100%; width: <?php echo ($rating_stats->two_star / $rating_stats->total_ratings) * 100; ?>%; border-radius: 4px;"></div>
                                    </div>
                                    <div style="width: 30px; text-align: left;"><?php echo $rating_stats->two_star; ?></div>
                                </div>
                                <div style="display: flex; align-items: center; margin: 5px 0;">
                                    <div style="width: 60px;">۱ ستاره:</div>
                                    <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px;">
                                        <div style="background: #dc3545; height: 100%; width: <?php echo ($rating_stats->one_star / $rating_stats->total_ratings) * 100; ?>%; border-radius: 4px;"></div>
                                    </div>
                                    <div style="width: 30px; text-align: left;"><?php echo $rating_stats->one_star; ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #6c757d; padding: 20px 0;">
                                هنوز امتیازی ثبت نشده است.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- آمار گزارشات -->
                    <div class="card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <h3>⚠️ گزارشات تخلف</h3>
                        <?php if ($report_stats && $report_stats->total_reports > 0): ?>
                            <div style="text-align: center; margin: 15px 0;">
                                <div style="font-size: 32px; font-weight: bold; color: #dc3545;">
                                    <?php echo $report_stats->total_reports; ?>
                                </div>
                                <div style="color: #666;">گزارش تخلف</div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                                <div style="background: #fff3cd; padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 18px; font-weight: bold;"><?php echo $report_stats->pending_reports; ?></div>
                                    <div style="font-size: 12px; color: #856404;">در انتظار</div>
                                </div>
                                <div style="background: #cce5ff; padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 18px; font-weight: bold;"><?php echo $report_stats->reviewed_reports; ?></div>
                                    <div style="font-size: 12px; color: #004085;">بررسی شده</div>
                                </div>
                                <div style="background: #d4edda; padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 18px; font-weight: bold;"><?php echo $report_stats->resolved_reports; ?></div>
                                    <div style="font-size: 12px; color: #155724;">حل شده</div>
                                </div>
                            </div>
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports&vendor_id=' . $vendor_id); ?>" 
                                   class="button button-small">
                                    مشاهده گزارشات
                                </a>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #6c757d; padding: 20px 0;">
                                هیچ گزارشی ثبت نشده است.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- مدارک صراف -->
                <?php if ($documents): ?>
                    <div class="card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
                        <h2>📎 مدارک آپلود شده</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                            <?php foreach ($documents as $doc): ?>
                                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center;">
                                    <div style="font-size: 32px; margin-bottom: 10px;">
                                        <?php 
                                        $ext = pathinfo($doc->file_name, PATHINFO_EXTENSION);
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                            echo '🖼️';
                                        } elseif ($ext == 'pdf') {
                                            echo '📄';
                                        } else {
                                            echo '📎';
                                        }
                                        ?>
                                    </div>
                                    <div style="font-size: 12px; word-break: break-all;"><?php echo esc_html($doc->file_name); ?></div>
                                    <a href="<?php echo esc_url($doc->file_url); ?>" 
                                       target="_blank" 
                                       style="display: inline-block; margin-top: 10px; padding: 5px 10px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                        مشاهده
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; min-width: 250px;">
                <div class="card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h2>✅ عملیات تایید</h2>
                    <form method="post" action="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id . '&action=approve&_wpnonce=' . wp_create_nonce('vendor_action')); ?>">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: bold;">نظر ادمین:</label>
                            <textarea name="rejection_reason" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            <p style="color: #666; font-size: 12px; margin-top: 5px;">
                                (برای رد کردن درخواست، دلیل را وارد کنید)
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: 10px; flex-direction: column;">
                            <button type="submit" name="action" value="approve" 
                                    class="button button-primary button-large" 
                                    style="background: #4CAF50; border-color: #4CAF50;">
                                ✅ تایید صراف
                            </button>
                            
                            <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id . '&action=reject&_wpnonce=' . wp_create_nonce('vendor_action')); ?>" 
                               class="button button-large"
                               onclick="return confirm('آیا از رد این درخواست اطمینان دارید؟')"
                               style="background: #F44336; border-color: #F44336; color: white;">
                                ❌ رد درخواست
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- عملیات مدیریت کلاهبردار -->
                <div class="card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
                    <h3>🚫 مدیریت کلاهبردار</h3>
                    
                    <?php if ($vendor->scammer_status !== 'scammer'): ?>
                        <form method="post" action="">
                            <?php wp_nonce_field('mark_scammer_action', '_wpnonce'); ?>
                            <input type="hidden" name="mark_scammer_submit" value="1">
                            <input type="hidden" name="vendor_id" value="<?php echo $vendor->id; ?>">
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">دلیل:</label>
                                <textarea name="scammer_reason" rows="3" required 
                                          placeholder="دلیل علامت‌گذاری به عنوان کلاهبردار..." 
                                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            </div>
                            
                            <button type="submit" 
                                    class="button button-large"
                                    style="background: #dc3545; border-color: #dc3545; color: white; width: 100%;"
                                    onclick="return confirm('⚠️ آیا مطمئن هستید؟ این صراف به عنوان کلاهبردار علامت‌گذاری و حسابش مسدود می‌شود.')">
                                🚫 علامت‌گذاری کلاهبردار
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 6px; margin-bottom: 15px;">
                            <strong style="color: #721c24;">این صراف قبلاً کلاهبردار علامت‌گذاری شده است.</strong>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&remove_scammer=1&vendor_id=' . $vendor->id . '&_wpnonce=' . wp_create_nonce('remove_scammer_action')); ?>" 
                           class="button button-large"
                           style="background: #28a745; border-color: #28a745; color: white; width: 100%;"
                           onclick="return confirm('آیا از حذف علامت کلاهبردار اطمینان دارید؟')">
                            ✅ حذف علامت کلاهبردار
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($vendor->scammer_status === 'suspected'): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 6px; text-align: center;">
                            <small style="color: #856404;">
                                این صراف مشکوک علامت‌گذاری شده است.<br>
                                <?php echo $vendor->scammer_reports_count; ?> گزارش تخلف ثبت شده.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- لینک‌های مفید -->
                <div class="card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
                    <h3>🔗 لینک‌های مفید</h3>
                    <ul style="margin-top: 10px; padding-left: 0; list-style: none;">
                        <li style="margin-bottom: 10px;">
                            <a href="<?php echo get_edit_user_link($vendor->user_id); ?>" target="_blank">
                                ✏️ ویرایش اطلاعات کاربر
                            </a>
                        </li>
                        <li style="margin-bottom: 10px;">
                            <a href="<?php echo home_url('/vendor-dashboard'); ?>" target="_blank">
                                👁️ مشاهده داشبورد صراف
                            </a>
                        </li>
                        <li style="margin-bottom: 10px;">
                            <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports&vendor_id=' . $vendor->id); ?>">
                                📋 گزارشات این صراف
                            </a>
                        </li>
                        <li style="margin-bottom: 10px;">
                            <a href="<?php echo home_url('/exchange/?vendor_id=' . $vendor->id); ?>" target="_blank">
                                🌐 پروفایل عمومی صراف
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

// صفحه لیست همه صراف‌ها
function wer_render_all_vendors_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // پردازش علامت‌گذاری کلاهبردار
    if (isset($_GET['action']) && $_GET['action'] === 'mark_scammer' && isset($_GET['vendor_id']) && wp_verify_nonce($_GET['_wpnonce'], 'mark_scammer_action')) {
        $vendor_id = intval($_GET['vendor_id']);
        $reason = 'علامت‌گذاری مستقیم از لیست صراف‌ها';
        
        if (wer_mark_vendor_as_scammer($vendor_id, $reason)) {
            echo '<div class="notice notice-success"><p>صراف به عنوان کلاهبردار علامت‌گذاری شد.</p></div>';
        }
    }
    
    // دریافت فیلترها
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $scammer_filter = isset($_GET['scammer']) ? sanitize_text_field($_GET['scammer']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // ساخت کوئری بر اساس فیلترها
    $where = [];
    $params = [];
    
    if ($status_filter && in_array($status_filter, ['pending', 'verified', 'rejected'])) {
        $where[] = "e.status = %s";
        $params[] = $status_filter;
    }
    
    if ($scammer_filter && in_array($scammer_filter, ['clean', 'suspected', 'scammer'])) {
        $where[] = "e.scammer_status = %s";
        $params[] = $scammer_filter;
    }
    
    if ($search) {
        $where[] = "(e.exchange_name_fa LIKE %s OR e.phone LIKE %s OR u.user_email LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // دریافت صراف‌ها
    $vendors = $wpdb->get_results($wpdb->prepare("
        SELECT e.*, u.user_email 
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        $where_clause
        ORDER BY e.created_at DESC
        LIMIT 100
    ", $params));
    
    // آمار فیلتر
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges");
    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'pending'");
    $verified_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'verified'");
    $rejected_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE status = 'rejected'");
    $scammer_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exchanges WHERE scammer_status = 'scammer'");
    ?>
    
    <div class="wrap">
        <h1>📋 لیست همه صراف‌ها</h1>
        
        <!-- فیلترها -->
        <div class="card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="wer-vendors-all">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">وضعیت:</label>
                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>>در انتظار</option>
                            <option value="verified" <?php selected($status_filter, 'verified'); ?>>تایید شده</option>
                            <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>رد شده</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">وضعیت کلاهبردار:</label>
                        <select name="scammer" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">همه</option>
                            <option value="clean" <?php selected($scammer_filter, 'clean'); ?>>تمیز</option>
                            <option value="suspected" <?php selected($scammer_filter, 'suspected'); ?>>مشکوک</option>
                            <option value="scammer" <?php selected($scammer_filter, 'scammer'); ?>>کلاهبردار</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">جستجو:</label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" 
                               placeholder="نام صراف، تلفن یا ایمیل..." 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="button button-primary">
                            🔍 اعمال فیلتر
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-all'); ?>" class="button">
                            پاک کردن فیلتر
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- آمار فیلتر -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-all'); ?>" 
               class="button <?php echo !$status_filter && !$scammer_filter && !$search ? 'button-primary' : ''; ?>">
                همه (<?php echo $total_count; ?>)
            </a>
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-all&status=pending'); ?>" 
               class="button <?php echo $status_filter === 'pending' ? 'button-primary' : ''; ?>">
                در انتظار (<?php echo $pending_count; ?>)
            </a>
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-all&status=verified'); ?>" 
               class="button <?php echo $status_filter === 'verified' ? 'button-primary' : ''; ?>">
                تایید شده (<?php echo $verified_count; ?>)
            </a>
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-all&status=rejected'); ?>" 
               class="button <?php echo $status_filter === 'rejected' ? 'button-primary' : ''; ?>">
                رد شده (<?php echo $rejected_count; ?>)
            </a>
            <a href="<?php echo admin_url('admin.php?page=wer-vendors-all&scammer=scammer'); ?>" 
               class="button button-danger <?php echo $scammer_filter === 'scammer' ? 'button-primary' : ''; ?>">
                کلاهبردار (<?php echo $scammer_count; ?>)
            </a>
        </div>
        
        <?php if (empty($vendors)): ?>
            <div class="notice notice-info">
                <p>هیچ صرافی با این فیلترها یافت نشد.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام صراف</th>
                        <th>ایمیل</th>
                        <th>تلفن</th>
                        <th>کشور</th>
                        <th>وضعیت</th>
                        <th>کلاهبردار</th>
                        <th>امتیاز</th>
                        <th>تاریخ ثبت‌نام</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                <?php if ($vendor->exchange_name_en): ?>
                                    <br><small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                            <td><?php echo esc_html($vendor->phone); ?></td>
                            <td><?php echo esc_html($vendor->country); ?></td>
                            <td>
                                <?php 
                                $status_text = [
                                    'pending' => '⏳ در انتظار',
                                    'verified' => '✅ تایید شده',
                                    'rejected' => '❌ رد شده'
                                ];
                                $status_class = [
                                    'pending' => 'pending',
                                    'verified' => 'approved',
                                    'rejected' => 'rejected'
                                ];
                                ?>
                                <span class="vendor-status-badge <?php echo $status_class[$vendor->status] ?? ''; ?>">
                                    <?php echo $status_text[$vendor->status] ?? $vendor->status; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $scammer_text = [
                                    'clean' => '<span style="color: #28a745;">✅</span>',
                                    'suspected' => '<span style="color: #ffc107;">⚠️</span>',
                                    'scammer' => '<span style="color: #dc3545;">🚫</span>'
                                ];
                                echo $scammer_text[$vendor->scammer_status] ?? '';
                                
                                if ($vendor->scammer_reports_count > 0): 
                                    echo ' <span style="color: #666; font-size: 12px;">(' . $vendor->scammer_reports_count . ')</span>';
                                endif;
                                ?>
                            </td>
                            <td>
                                <?php if ($vendor->total_ratings > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span style="color: #FFC107; font-weight: bold;"><?php echo number_format($vendor->rating, 1); ?></span>
                                        <span style="color: #6c757d; font-size: 12px;">(<?php echo $vendor->total_ratings; ?>)</span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #6c757d;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date_i18n('Y/m/d', strtotime($vendor->created_at)); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-direction: column;">
                                    <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id); ?>" 
                                       class="button button-small">
                                        مشاهده
                                    </a>
                                    <?php if ($vendor->scammer_status !== 'scammer'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-all&action=mark_scammer&vendor_id=' . $vendor->id . '&_wpnonce=' . wp_create_nonce('mark_scammer_action')); ?>" 
                                           class="button button-small button-danger"
                                           onclick="return confirm('آیا این صراف را به عنوان کلاهبردار علامت‌گذاری کنید؟')"
                                           style="background: #dc3545; border-color: #dc3545; color: white; font-size: 11px;">
                                            🚫 علامت‌گذاری
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php
}

// صفحه صرافان مشکوک
function wer_render_suspicious_vendors_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // پردازش علامت‌گذاری کلاهبردار
    if (isset($_GET['action']) && $_GET['action'] === 'mark_scammer' && isset($_GET['vendor_id']) && wp_verify_nonce($_GET['_wpnonce'], 'mark_scammer_action')) {
        $vendor_id = intval($_GET['vendor_id']);
        $reason = 'علامت‌گذاری از صفحه صرافان مشکوک';
        
        if (wer_mark_vendor_as_scammer($vendor_id, $reason)) {
            echo '<div class="notice notice-success"><p>صراف به عنوان کلاهبردار علامت‌گذاری شد.</p></div>';
        }
    }
    
    $suspicious_vendors = $wpdb->get_results("
        SELECT e.*, u.user_email,
               (SELECT COUNT(*) FROM {$wpdb->prefix}exchange_vendor_reports r 
                WHERE r.vendor_id = e.id AND r.status = 'pending') as pending_reports,
               (SELECT MAX(created_at) FROM {$wpdb->prefix}exchange_vendor_reports r 
                WHERE r.vendor_id = e.id) as last_report_date
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        WHERE e.scammer_status IN ('suspected', 'clean')
          AND e.scammer_reports_count > 0
        ORDER BY e.scammer_reports_count DESC
        LIMIT 50
    ");
    ?>
    
    <div class="wrap">
        <h1>⚠️ صرافان مشکوک (با گزارش تخلف)</h1>
        
        <?php if (empty($suspicious_vendors)): ?>
            <div class="notice notice-success">
                <p>🎉 هیچ صراف مشکوکی یافت نشد!</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام صراف</th>
                        <th>ایمیل</th>
                        <th>تلفن</th>
                        <th>وضعیت</th>
                        <th>گزارشات کل</th>
                        <th>گزارشات در انتظار</th>
                        <th>آخرین گزارش</th>
                        <th>امتیاز</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suspicious_vendors as $vendor): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                <?php if ($vendor->scammer_status === 'suspected'): ?>
                                    <span style="color: #ffc107; margin-right: 5px;">⚠️</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                            <td><?php echo esc_html($vendor->phone); ?></td>
                            <td>
                                <?php 
                                $status_text = [
                                    'pending' => '⏳ در انتظار',
                                    'verified' => '✅ تایید شده',
                                    'rejected' => '❌ رد شده'
                                ];
                                echo $status_text[$vendor->status] ?? $vendor->status;
                                ?>
                            </td>
                            <td>
                                <span style="font-weight: bold; color: #dc3545;">
                                    <?php echo $vendor->scammer_reports_count; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($vendor->pending_reports > 0): ?>
                                    <span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;">
                                        <?php echo $vendor->pending_reports; ?> جدید
                                    </span>
                                <?php else: ?>
                                    <span style="color: #6c757d;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vendor->last_report_date): ?>
                                    <?php echo date_i18n('y/m/d', strtotime($vendor->last_report_date)); ?>
                                <?php else: ?>
                                    <span style="color: #6c757d;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vendor->total_ratings > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span style="color: #FFC107;"><?php echo number_format($vendor->rating, 1); ?></span>
                                        <span style="color: #6c757d; font-size: 12px;">(<?php echo $vendor->total_ratings; ?>)</span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #6c757d;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-direction: column;">
                                    <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id); ?>" 
                                       class="button button-small">
                                        مشاهده
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=wer-vendors-suspicious&action=mark_scammer&vendor_id=' . $vendor->id . '&_wpnonce=' . wp_create_nonce('mark_scammer_action')); ?>" 
                                       class="button button-small button-danger"
                                       onclick="return confirm('آیا این صراف را به عنوان کلاهبردار علامت‌گذاری کنید؟')"
                                       style="background: #dc3545; border-color: #dc3545; color: white; font-size: 11px;">
                                        🚫 علامت‌گذاری
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php
}

// صفحه صرافان کلاهبردار
function wer_render_scammers_vendors_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // پردازش حذف علامت کلاهبردار
    if (isset($_GET['action']) && $_GET['action'] === 'remove_scammer' && isset($_GET['vendor_id']) && wp_verify_nonce($_GET['_wpnonce'], 'remove_scammer_action')) {
        $vendor_id = intval($_GET['vendor_id']);
        
        if (wer_remove_scammer_mark($vendor_id)) {
            echo '<div class="notice notice-success"><p>علامت کلاهبردار با موفقیت حذف شد.</p></div>';
        }
    }
    
    $scammer_vendors = $wpdb->get_results("
        SELECT e.*, u.user_email 
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        WHERE e.scammer_status = 'scammer'
        ORDER BY e.updated_at DESC
    ");
    ?>
    
    <div class="wrap">
        <h1>🚫 صرافان کلاهبردار</h1>
        
        <?php if (empty($scammer_vendors)): ?>
            <div class="notice notice-success">
                <p>🎉 هیچ صراف کلاهبرداری یافت نشد!</p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>⚠️ این صرافان به دلیل تخلفات تأیید شده، به عنوان کلاهبردار علامت‌گذاری شده‌اند.</p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام صراف</th>
                        <th>ایمیل</th>
                        <th>تلفن</th>
                        <th>وضعیت حساب</th>
                        <th>تعداد گزارشات</th>
                        <th>دلیل علامت‌گذاری</th>
                        <th>تاریخ علامت‌گذاری</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scammer_vendors as $vendor): ?>
                        <tr>
                            <td>
                                <strong style="color: #dc3545;"><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                <span style="color: #dc3545; margin-right: 5px;">🚫</span>
                            </td>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                            <td><?php echo esc_html($vendor->phone); ?></td>
                            <td>
                                <?php if ($vendor->status === 'rejected'): ?>
                                    <span style="color: #dc3545; font-weight: bold;">❌ رد شده</span>
                                <?php else: ?>
                                    <span style="color: #ffc107;">⚠️ <?php echo $vendor->status; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: bold; color: #dc3545;">
                                    <?php echo $vendor->scammer_reports_count; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html($vendor->rejection_reason ?: 'کلاهبردار تأیید شده'); ?>
                            </td>
                            <td>
                                <?php echo date_i18n('Y/m/d H:i', strtotime($vendor->updated_at)); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-direction: column;">
                                    <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id); ?>" 
                                       class="button button-small">
                                        مشاهده
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=wer-vendors-scammers&action=remove_scammer&vendor_id=' . $vendor->id . '&_wpnonce=' . wp_create_nonce('remove_scammer_action')); ?>" 
                                       class="button button-small"
                                       onclick="return confirm('آیا از حذف علامت کلاهبردار اطمینان دارید؟')"
                                       style="background: #28a745; border-color: #28a745; color: white; font-size: 11px;">
                                        ✅ حذف علامت
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php
}

// صفحه گزارشات تخلف
function wer_render_vendor_reports_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // پردازش فرم علامت‌گذاری کلاهبردار
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_scammer_submit'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'mark_scammer_action')) {
            wp_die('اعتبارسنجی فرم نامعتبر است.');
        }
        
        $vendor_id = intval($_POST['vendor_id']);
        $reason = sanitize_textarea_field($_POST['scammer_reason']);
        
        if (wer_mark_vendor_as_scammer($vendor_id, $reason)) {
            echo '<div class="notice notice-success"><p>صراف به عنوان کلاهبردار علامت‌گذاری شد.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>خطا در علامت‌گذاری صراف.</p></div>';
        }
    }
    
    // پردازش بروزرسانی گزارش
    if (isset($_GET['action']) && $_GET['action'] === 'update_report' && isset($_GET['report_id']) && wp_verify_nonce($_GET['_wpnonce'], 'update_report_action')) {
        $report_id = intval($_GET['report_id']);
        $status = sanitize_text_field($_GET['status'] ?? 'reviewed');
        
        $wpdb->update(
            $wpdb->prefix . 'exchange_vendor_reports',
            [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $report_id],
            ['%s', '%s'],
            ['%d']
        );
        
        echo '<div class="notice notice-success"><p>وضعیت گزارش به‌روزرسانی شد.</p></div>';
    }
    
    // فیلترها
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $vendor_filter = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // ساخت کوئری
    $where = [];
    $params = [];
    
    if ($status_filter && in_array($status_filter, ['pending', 'reviewed', 'resolved'])) {
        $where[] = "r.status = %s";
        $params[] = $status_filter;
    }
    
    if ($vendor_filter) {
        $where[] = "r.vendor_id = %d";
        $params[] = $vendor_filter;
    }
    
    if ($search) {
        $where[] = "(e.exchange_name_fa LIKE %s OR r.reporter_name LIKE %s OR r.reporter_phone LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // دریافت گزارشات
    $reports = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, 
               e.exchange_name_fa, 
               e.phone as vendor_phone,
               e.scammer_status,
               e.status as vendor_status,
               e.rating,
               e.total_ratings
        FROM {$wpdb->prefix}exchange_vendor_reports r
        LEFT JOIN {$wpdb->prefix}exchanges e ON r.vendor_id = e.id
        $where_clause
        ORDER BY r.created_at DESC
        LIMIT 100
    ", $params));
    
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
    
    // اگر صراف خاصی فیلتر شده باشد، اطلاعات آن را بگیر
    $vendor_info = null;
    if ($vendor_filter) {
        $vendor_info = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, u.user_email 
             FROM {$wpdb->prefix}exchanges e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.id = %d",
            $vendor_filter
        ));
    }
    ?>
    
    <div class="wrap">
        <h1>⚠️ گزارشات تخلف صراف‌ها</h1>
        
        <!-- هدر صراف خاص -->
        <?php if ($vendor_info): ?>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-right: 4px solid #dc3545;">
                <h3 style="margin: 0 0 10px 0;">گزارشات صراف: <?php echo esc_html($vendor_info->exchange_name_fa); ?></h3>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div>
                        <strong>تلفن:</strong> <?php echo esc_html($vendor_info->phone); ?>
                    </div>
                    <div>
                        <strong>ایمیل:</strong> <?php echo esc_html($vendor_info->user_email); ?>
                    </div>
                    <div>
                        <strong>وضعیت کلاهبردار:</strong>
                        <?php 
                        $scammer_text = [
                            'clean' => '<span style="color: #28a745;">✅ تمیز</span>',
                            'suspected' => '<span style="color: #ffc107;">⚠️ مشکوک</span>',
                            'scammer' => '<span style="color: #dc3545;">🚫 کلاهبردار</span>'
                        ];
                        echo $scammer_text[$vendor_info->scammer_status] ?? '';
                        ?>
                        <?php if ($vendor_info->scammer_reports_count > 0): ?>
                            <span style="color: #666;">(<?php echo $vendor_info->scammer_reports_count; ?> گزارش)</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor_info->id); ?>" class="button button-small">
                            مشاهده کامل صراف
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports'); ?>" class="button button-small">
                            مشاهده همه گزارشات
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- آمار -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #6c757d;"><?php echo $stats->total_reports ?? 0; ?></div>
                <div>کل گزارشات</div>
            </div>
            <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #856404;"><?php echo $stats->pending ?? 0; ?></div>
                <div>در انتظار بررسی</div>
            </div>
            <div style="text-align: center; padding: 20px; background: #cce5ff; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #004085;"><?php echo $stats->reviewed ?? 0; ?></div>
                <div>بررسی شده</div>
            </div>
            <div style="text-align: center; padding: 20px; background: #d4edda; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #155724;"><?php echo $stats->resolved ?? 0; ?></div>
                <div>حل شده</div>
            </div>
            <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #721c24;"><?php echo $stats->scammer_vendors ?? 0; ?></div>
                <div>صراف کلاهبردار</div>
            </div>
        </div>
        
        <!-- فیلترها -->
        <div class="card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="wer-vendors-reports">
                <?php if ($vendor_filter): ?>
                    <input type="hidden" name="vendor_id" value="<?php echo $vendor_filter; ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">وضعیت:</label>
                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>>در انتظار بررسی</option>
                            <option value="reviewed" <?php selected($status_filter, 'reviewed'); ?>>بررسی شده</option>
                            <option value="resolved" <?php selected($status_filter, 'resolved'); ?>>حل شده</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">جستجو:</label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" 
                               placeholder="صراف، گزارش‌دهنده یا تلفن..." 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="button button-primary">
                            🔍 اعمال فیلتر
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports' . ($vendor_filter ? '&vendor_id=' . $vendor_filter : '')); ?>" class="button">
                            پاک کردن فیلتر
                        </a>
                    </div>
                </div>
            </form>
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
                                <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $report->vendor_id); ?>" target="_blank">
                                    <?php echo esc_html($report->exchange_name_fa); ?>
                                </a>
                                <br>
                                <small><?php echo esc_html($report->vendor_phone); ?></small>
                            </td>
                            <td><?php echo esc_html($report->reporter_name); ?></td>
                            <td><?php echo esc_html($report->reporter_phone); ?></td>
                            <td><?php echo $type_labels[$report->report_type] ?? 'نامشخص'; ?></td>
                            <td>
                                <div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #f8f9fa; border-radius: 5px; font-size: 12px;">
                                    <?php echo nl2br(esc_html($report->report_text)); ?>
                                </div>
                            </td>
                            <td><?php echo date_i18n('y/m/d H:i', strtotime($report->created_at)); ?></td>
                            <td><?php echo $status_labels[$report->status] ?? $report->status; ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <button class="button button-small view-report" data-id="<?php echo $report->id; ?>" data-vendor-name="<?php echo esc_attr($report->exchange_name_fa); ?>" data-reporter="<?php echo esc_attr($report->reporter_name); ?>" data-phone="<?php echo esc_attr($report->reporter_phone); ?>" data-type="<?php echo $type_labels[$report->report_type] ?? 'نامشخص'; ?>" data-text="<?php echo esc_attr($report->report_text); ?>" data-date="<?php echo date_i18n('y/m/d H:i', strtotime($report->created_at)); ?>">
                                        مشاهده جزئیات
                                    </button>
                                    <?php if ($report->status === 'pending'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports&action=update_report&report_id=' . $report->id . '&status=reviewed&_wpnonce=' . wp_create_nonce('update_report_action')); ?>" 
                                           class="button button-small button-primary">
                                            بررسی شده
                                        </a>
                                        <a href="<?php echo admin_url('admin.php?page=wer-vendors-reports&action=update_report&report_id=' . $report->id . '&status=resolved&_wpnonce=' . wp_create_nonce('update_report_action')); ?>" 
                                           class="button button-small button-success">
                                            حل شده
                                        </a>
                                        <?php if ($report->scammer_status !== 'scammer'): ?>
                                            <button class="button button-small button-danger mark-scammer-btn" 
                                                    data-vendor-id="<?php echo $report->vendor_id; ?>"
                                                    data-vendor-name="<?php echo esc_attr($report->exchange_name_fa); ?>">
                                                علامت‌گذاری کلاهبردار
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                هیچ گزارشی یافت نشد.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- صرافان مشکوک -->
        <div class="card" style="margin-top: 30px; padding: 20px;">
            <h3>🚨 صرافان مشکوک (با بیشترین گزارش)</h3>
            <?php
            $suspicious_vendors = $wpdb->get_results("
                SELECT e.*, 
                       COUNT(r.id) as report_count,
                       SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_reports
                FROM {$wpdb->prefix}exchanges e
                LEFT JOIN {$wpdb->prefix}exchange_vendor_reports r ON e.id = r.vendor_id
                WHERE e.scammer_status IN ('clean', 'suspected')
                GROUP BY e.id
                HAVING report_count > 0
                ORDER BY pending_reports DESC, report_count DESC
                LIMIT 10
            ");
            
            if ($suspicious_vendors):
            ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>صراف</th>
                        <th>تلفن</th>
                        <th>وضعیت</th>
                        <th>گزارشات کل</th>
                        <th>گزارشات در انتظار</th>
                        <th>امتیاز</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suspicious_vendors as $vendor): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id); ?>" target="_blank">
                                <?php echo esc_html($vendor->exchange_name_fa); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($vendor->phone); ?></td>
                        <td>
                            <?php if ($vendor->scammer_status === 'suspected'): ?>
                                <span class="badge badge-warning">مشکوک</span>
                            <?php else: ?>
                                <span class="badge badge-success">تمیز</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $vendor->report_count; ?></td>
                        <td><?php echo $vendor->pending_reports; ?></td>
                        <td><?php echo number_format($vendor->rating, 1); ?> (<?php echo $vendor->total_ratings; ?>)</td>
                        <td>
                            <button class="button button-small button-danger mark-scammer-btn" 
                                    data-vendor-id="<?php echo $vendor->id; ?>"
                                    data-vendor-name="<?php echo esc_attr($vendor->exchange_name_fa); ?>">
                                علامت‌گذاری کلاهبردار
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; color: #6c757d;">
                    هیچ صراف مشکوکی یافت نشد.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- مودال برای نمایش جزئیات گزارش -->
    <div id="reportDetailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3>جزئیات گزارش</h3>
            <div id="reportDetailContent" style="margin-top: 20px;">
                <!-- محتوای جزئیات گزارش در اینجا نمایش داده می‌شود -->
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <button type="button" class="button button-secondary" onclick="closeReportDetailModal()">
                    بستن
                </button>
            </div>
        </div>
    </div>
    
    <!-- مودال برای علامت‌گذاری کلاهبردار -->
    <div id="scammerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3>علامت‌گذاری صراف به عنوان کلاهبردار</h3>
            <p id="scammerVendorName" style="font-weight: bold; color: #dc3545; margin: 10px 0 20px 0;"></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('mark_scammer_action', '_wpnonce'); ?>
                <input type="hidden" name="mark_scammer_submit" value="1">
                <input type="hidden" name="vendor_id" id="scammerVendorId">
                
                <div style="margin-bottom: 20px;">
                    <label for="scammer_reason">دلیل علامت‌گذاری *</label>
                    <textarea name="scammer_reason" id="scammer_reason" rows="4" required 
                              placeholder="دلیل دقیق علامت‌گذاری این صراف به عنوان کلاهبردار را بنویسید..." 
                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="button button-primary button-danger" 
                            style="background: #dc3545; border-color: #dc3545;">
                        تأیید و علامت‌گذاری
                    </button>
                    <button type="button" class="button button-secondary" onclick="closeScammerModal()">
                        انصراف
                    </button>
                </div>
            </form>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // باز کردن مودال علامت‌گذاری کلاهبردار
        document.querySelectorAll('.mark-scammer-btn').forEach(button => {
            button.addEventListener('click', function() {
                const vendorId = this.dataset.vendorId;
                const vendorName = this.dataset.vendorName;
                
                document.getElementById('scammerVendorId').value = vendorId;
                document.getElementById('scammerVendorName').textContent = 
                    'صراف: ' + vendorName + ' (ID: ' + vendorId + ')';
                
                document.getElementById('scammerModal').style.display = 'flex';
            });
        });
        
        // مشاهده جزئیات گزارش
        document.querySelectorAll('.view-report').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.dataset.id;
                const vendorName = this.dataset.vendorName;
                const reporter = this.dataset.reporter;
                const phone = this.dataset.phone;
                const type = this.dataset.type;
                const text = this.dataset.text;
                const date = this.dataset.date;
                
                const content = `
                    <div style="margin-bottom: 15px;">
                        <strong>صراف:</strong> ${vendorName}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>گزارش‌دهنده:</strong> ${reporter}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>تلفن گزارش‌دهنده:</strong> ${phone}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>نوع تخلف:</strong> ${type}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>تاریخ گزارش:</strong> ${date}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>متن گزارش:</strong>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 5px;">
                            ${text.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                `;
                
                document.getElementById('reportDetailContent').innerHTML = content;
                document.getElementById('reportDetailModal').style.display = 'flex';
            });
        });
    });
    
    function closeScammerModal() {
        document.getElementById('scammerModal').style.display = 'none';
    }
    
    function closeReportDetailModal() {
        document.getElementById('reportDetailModal').style.display = 'none';
    }
    
    // بستن مودال‌ها با کلیک بیرون
    document.getElementById('scammerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeScammerModal();
        }
    });
    
    document.getElementById('reportDetailModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReportDetailModal();
        }
    });
    </script>
    
    <?php
}

// صفحه تاریخچه تاییدها
function wer_render_approval_history_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // تاریخچه تغییرات وضعیت
    $history = $wpdb->get_results("
        SELECT e.*, u.user_email 
        FROM {$wpdb->prefix}exchanges e
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
        WHERE e.status != 'pending'
        ORDER BY e.updated_at DESC
        LIMIT 50
    ");
    ?>
    
    <div class="wrap">
        <h1>📊 تاریخچه تایید/رد صراف‌ها</h1>
        
        <?php if (empty($history)): ?>
            <div class="notice notice-info">
                <p>هنوز هیچ عملیات تایید/ردی انجام نشده است.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>نام صراف</th>
                        <th>ایمیل</th>
                        <th>وضعیت جدید</th>
                        <th>تاریخ تغییر</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $vendor): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($vendor->exchange_name_fa); ?></strong>
                                <?php if ($vendor->exchange_name_en): ?>
                                    <br><small><?php echo esc_html($vendor->exchange_name_en); ?></small>
                                <?php endif; ?>
                                <?php if ($vendor->scammer_status === 'scammer'): ?>
                                    <br><small style="color: #dc3545;">🚫 کلاهبردار</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($vendor->user_email); ?></td>
                            <td>
                                <?php 
                                $status_text = [
                                    'verified' => '✅ تایید شده',
                                    'rejected' => '❌ رد شده'
                                ];
                                $status_class = [
                                    'verified' => 'approved',
                                    'rejected' => 'rejected'
                                ];
                                ?>
                                <span class="vendor-status-badge <?php echo $status_class[$vendor->status] ?? ''; ?>">
                                    <?php echo $status_text[$vendor->status] ?? $vendor->status; ?>
                                </span>
                                <?php if ($vendor->rejection_reason && $vendor->status == 'rejected'): ?>
                                    <br><small style="color: #666;"><?php echo esc_html($vendor->rejection_reason); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date_i18n('Y/m/d H:i', strtotime($vendor->updated_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wer-vendors-pending&vendor_id=' . $vendor->id); ?>" 
                                   class="button button-small">
                                    مشاهده
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php
}

// ارسال ایمیل وضعیت به صراف
function wer_send_vendor_status_notification($vendor_id, $status, $reason = '') {
    global $wpdb;
    
    $vendor = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, u.user_email, u.display_name 
         FROM {$wpdb->prefix}exchanges e
         LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
         WHERE e.id = %d",
        $vendor_id
    ));
    
    if (!$vendor) return false;
    
    $subject = ($status === 'verified') 
        ? '✅ حساب صرافی شما تایید شد'
        : '❌ درخواست حساب صرافی شما رد شد';
    
    $message = ($status === 'verified')
        ? '<div style="font-family: Tahoma; padding: 20px; background: #f8f9fa;">
            <h2>تبریک! حساب صرافی شما تایید شد</h2>
            <p>سلام ' . esc_html($vendor->exchange_name_fa) . '،</p>
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
            <p>سلام ' . esc_html($vendor->exchange_name_fa) . '،</p>
            <p>متأسفانه درخواست حساب صرافی شما تأیید نشد.</p>' .
            ($reason ? '<p><strong>دلیل رد درخواست:</strong><br>' . esc_html($reason) . '</p>' : '') .
            '<p>لطفاً برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.</p>
        </div>';
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    return wp_mail($vendor->user_email, $subject, $message, $headers);
}

// تابع برای علامت‌گذاری صراف به عنوان کلاهبردار
function wer_mark_vendor_as_scammer($vendor_id, $reason) {
    global $wpdb;
    
    $result = $wpdb->update(
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
    
    if ($result !== false) {
        // به‌روزرسانی تمام گزارشات
        $wpdb->update(
            $wpdb->prefix . 'exchange_vendor_reports',
            [
                'status' => 'reviewed',
                'admin_notes' => 'صراف به عنوان کلاهبردار علامت‌گذاری شد. ' . $reason,
                'updated_at' => current_time('mysql')
            ],
            ['vendor_id' => $vendor_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        return true;
    }
    
    return false;
}

// تابع برای حذف علامت کلاهبردار
function wer_remove_scammer_mark($vendor_id) {
    global $wpdb;
    
    $result = $wpdb->update(
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
    
    return $result !== false;
}


/**
 * حذف کامل اطلاعات یک صراف از تمام جداول مرتبط
 * @param int $exchange_id شناسه صراف
 * @param int $user_id شناسه کاربر صراف
 * @return array نتیجه عملیات
 */
function wer_delete_exchange_completely($exchange_id, $user_id = null) {
    global $wpdb;
    
    $results = [
        'success' => false,
        'message' => '',
        'deleted_counts' => [],
        'errors' => []
    ];
    
    try {
        // 1. ابتدا user_id را پیدا کن (اگر داده نشده)
        if (!$user_id) {
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM wp_exchanges WHERE id = %d",
                $exchange_id
            ));
            
            if (!$user_id) {
                $results['message'] = 'صراف با این شناسه یافت نشد';
                return $results;
            }
        }
        
        // 2. پاک کردن از wp_exchange_vendor_docs
        $results['deleted_counts']['vendor_docs'] = $wpdb->delete(
            'wp_exchange_vendor_docs',
            ['exchange_id' => $exchange_id],
            ['%d']
        );
        
        // 3. پاک کردن از wp_exchange_vendor_ratings
        $results['deleted_counts']['vendor_ratings'] = $wpdb->delete(
            'wp_exchange_vendor_ratings',
            ['vendor_id' => $exchange_id],
            ['%d']
        );
        
        // 4. پاک کردن از wp_exchange_vendor_reports
        $results['deleted_counts']['vendor_reports'] = $wpdb->delete(
            'wp_exchange_vendor_reports',
            ['vendor_id' => $exchange_id],
            ['%d']
        );
        
        // 5. پیدا کردن temp_docs مرتبط (بر اساس فایل‌های مشابه)
        // فرض: فایل‌های temp که بعداً در vendor_docs استفاده شده‌اند
        $vendor_docs_urls = $wpdb->get_col($wpdb->prepare(
            "SELECT file_url FROM wp_exchange_vendor_docs WHERE exchange_id = %d",
            $exchange_id
        ));
        
        if (!empty($vendor_docs_urls)) {
            $placeholders = array_fill(0, count($vendor_docs_urls), '%s');
            $format_string = implode(',', $placeholders);
            
            $results['deleted_counts']['temp_docs'] = $wpdb->query($wpdb->prepare(
                "DELETE FROM wp_exchange_temp_docs WHERE file_url IN ($format_string)",
                $vendor_docs_urls
            ));
        } else {
            $results['deleted_counts']['temp_docs'] = 0;
        }
        
        // 6. پاک کردن از wp_exchanges (جدول اصلی)
        $results['deleted_counts']['exchanges'] = $wpdb->delete(
            'wp_exchanges',
            ['id' => $exchange_id],
            ['%d']
        );
        
        // 7. حذف فایل‌های فیزیکی از سرور (اختیاری)
        $deleted_files_count = 0;
        $all_file_urls = array_merge(
            $vendor_docs_urls,
            $wpdb->get_col($wpdb->prepare(
                "SELECT file_url FROM wp_exchange_temp_docs WHERE session_id LIKE %s",
                '%' . $wpdb->esc_like($user_id) . '%'
            ))
        );
        
        foreach ($all_file_urls as $file_url) {
            if (!empty($file_url)) {
                $file_path = str_replace(site_url(), ABSPATH, $file_url);
                if (file_exists($file_path) && is_writable($file_path)) {
                    if (@unlink($file_path)) {
                        $deleted_files_count++;
                    }
                }
            }
        }
        
        $results['deleted_counts']['physical_files'] = $deleted_files_count;
        
        // 8. بررسی موفقیت آمیز بودن عملیات
        if ($results['deleted_counts']['exchanges'] > 0) {
            $results['success'] = true;
            $results['message'] = sprintf(
                'صراف با شناسه %d به همراه %d فایل وابسته حذف شد',
                $exchange_id,
                array_sum($results['deleted_counts'])
            );
        } else {
            $results['message'] = 'حذف صراف با مشکل مواجه شد';
        }
        
    } catch (Exception $e) {
        $results['errors'][] = 'خطا در حذف: ' . $e->getMessage();
        $results['message'] = 'خطای سیستمی رخ داد';
    }
    
    return $results;
}

/**
 * بررسی اطلاعات یک صراف قبل از حذف
 * @param int $exchange_id شناسه صراف
 * @return array اطلاعات صراف
 */
function wer_get_exchange_summary($exchange_id) {
    global $wpdb;
    
    $summary = [
        'exchange_info' => null,
        'counts' => [],
        'files_info' => []
    ];
    
    // اطلاعات صراف
    $summary['exchange_info'] = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, exchange_name_fa, status, created_at 
         FROM wp_exchanges WHERE id = %d",
        $exchange_id
    ));
    
    if (!$summary['exchange_info']) {
        return $summary;
    }
    
    $user_id = $summary['exchange_info']->user_id;
    
    // تعداد رکوردهای وابسته
    $summary['counts']['vendor_docs'] = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_exchange_vendor_docs WHERE exchange_id = %d",
        $exchange_id
    ));
    
    $summary['counts']['vendor_ratings'] = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_exchange_vendor_ratings WHERE vendor_id = %d",
        $exchange_id
    ));
    
    $summary['counts']['vendor_reports'] = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_exchange_vendor_reports WHERE vendor_id = %d",
        $exchange_id
    ));
    
    // اطلاعات فایل‌ها
    $summary['files_info']['vendor_docs'] = $wpdb->get_results($wpdb->prepare(
        "SELECT file_name, file_size FROM wp_exchange_vendor_docs WHERE exchange_id = %d",
        $exchange_id
    ));
    
    // جمع حجم فایل‌ها
    $total_size = 0;
    foreach ($summary['files_info']['vendor_docs'] as $file) {
        $total_size += (int)$file->file_size;
    }
    $summary['files_info']['total_size_mb'] = round($total_size / 1024 / 1024, 2);
    
    return $summary;
}

/**
 * فرم نمایش و حذف صراف (برای استفاده در پنل ادمین)
 */
 
function wer_render_delete_vendor_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.');
    }wer_display_exchange_deletion_form();
}
function wer_display_exchange_deletion_form() {
    ?>
    <div class="wrap">
        <h1>حذف صراف از سیستم</h1>
        
        <?php
        if (isset($_GET['exchange_id']) && is_numeric($_GET['exchange_id'])) {
            $exchange_id = intval($_GET['exchange_id']);
            
            // بررسی قبل از حذف
            $summary = wer_get_exchange_summary($exchange_id);
            
            if (!$summary['exchange_info']) {
                echo '<div class="notice notice-error"><p>صراف با این شناسه یافت نشد</p></div>';
                return;
            }
            
            $exchange = $summary['exchange_info'];
            ?>
            
            <div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border-left: 4px solid #dc3545;">
                <h2 style="color: #dc3545; margin-top: 0;">⚠️ هشدار: عملیات حذف</h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th colspan="2">اطلاعات صراف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td width="30%"><strong>شناسه صراف:</strong></td>
                            <td><?php echo esc_html($exchange->id); ?></td>
                        </tr>
                        <tr>
                            <td><strong>نام صراف:</strong></td>
                            <td><?php echo esc_html($exchange->exchange_name_fa); ?></td>
                        </tr>
                        <tr>
                            <td><strong>شناسه کاربر:</strong></td>
                            <td><?php echo esc_html($exchange->user_id); ?></td>
                        </tr>
                        <tr>
                            <td><strong>تاریخ ثبت:</strong></td>
                            <td><?php echo esc_html($exchange->created_at); ?></td>
                        </tr>
                        <tr>
                            <td><strong>وضعیت:</strong></td>
                            <td><?php echo esc_html($exchange->status); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3 style="margin-top: 30px;">داده‌های وابسته که حذف خواهند شد:</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>نوع داده</th>
                            <th>تعداد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>مدارک آپلود شده</td>
                            <td><?php echo $summary['counts']['vendor_docs']; ?> فایل</td>
                        </tr>
                        <tr>
                            <td>نظرات و امتیازات</td>
                            <td><?php echo $summary['counts']['vendor_ratings']; ?> نظر</td>
                        </tr>
                        <tr>
                            <td>گزارش‌های کاربران</td>
                            <td><?php echo $summary['counts']['vendor_reports']; ?> گزارش</td>
                        </tr>
                        <tr>
                            <td>حجم فایل‌ها</td>
                            <td><?php echo $summary['files_info']['total_size_mb']; ?> مگابایت</td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!empty($summary['files_info']['vendor_docs'])): ?>
                <details style="margin-top: 20px;">
                    <summary><strong>لیست فایل‌ها:</strong></summary>
                    <ul style="margin: 10px 0; padding-right: 20px;">
                        <?php foreach ($summary['files_info']['vendor_docs'] as $file): ?>
                        <li><?php echo esc_html($file->file_name); ?> (<?php echo round($file->file_size / 1024, 2); ?> KB)</li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
                
                <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                    <h3 style="color: #856404; margin-top: 0;">⚠️ توجه</h3>
                    <p>این عملیات <strong>غیر قابل بازگشت</strong> است. تمام اطلاعات صراف از جمله:</p>
                    <ul>
                        <li>اطلاعات ثبت شده در جدول صرافان</li>
                        <li>تمامی مدارک آپلود شده</li>
                        <li>نظرات و امتیازات کاربران</li>
                        <li>گزارش‌های ثبت شده</li>
                        <li>فایل‌های فیزیکی روی سرور</li>
                    </ul>
                    <p>برای همیشه حذف خواهند شد.</p>
                </div>
                
                <form method="post" style="margin-top: 30px;">
                    <?php wp_nonce_field('delete_exchange_' . $exchange_id, 'exchange_nonce'); ?>
                    <input type="hidden" name="exchange_id" value="<?php echo $exchange_id; ?>">
                    <input type="hidden" name="action" value="delete_exchange">
                    
                    <p>
                        <label>
                            <input type="checkbox" name="confirm_delete" required>
                            <strong>تأیید می‌کنم که می‌خواهم این صراف را حذف کنم</strong>
                        </label>
                    </p>
                    
                    <p>
                        <button type="submit" class="button button-primary" 
                                style="background: #dc3545; border-color: #dc3545;"
                                onclick="return confirm('آیا مطمئن هستید؟ این عمل غیر قابل بازگشت است.')">
                            🗑️ حذف کامل صراف
                        </button>
                        <a href="<?php echo remove_query_arg('exchange_id'); ?>" class="button">
                            انصراف
                        </a>
                    </p>
                </form>
            </div>
            <?php
            
            // پردازش درخواست حذف
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
                isset($_POST['action']) && 
                $_POST['action'] === 'delete_exchange' &&
                isset($_POST['exchange_id']) &&
                wp_verify_nonce($_POST['exchange_nonce'], 'delete_exchange_' . $_POST['exchange_id'])) {
                
                $deletion_result = wer_delete_exchange_completely($exchange_id, $exchange->user_id);
                
                echo '<div class="notice notice-' . ($deletion_result['success'] ? 'success' : 'error') . '">';
                echo '<p><strong>' . ($deletion_result['success'] ? '✅ موفق' : '❌ خطا') . ':</strong> ';
                echo esc_html($deletion_result['message']) . '</p>';
                
                if (!empty($deletion_result['deleted_counts'])) {
                    echo '<ul>';
                    foreach ($deletion_result['deleted_counts'] as $type => $count) {
                        if ($count > 0) {
                            echo '<li>' . esc_html($type) . ': ' . $count . ' رکورد حذف شد</li>';
                        }
                    }
                    echo '</ul>';
                }
                
                echo '</div>';
            }
            
        } else {
            // فرم جستجوی صراف
            ?>
            <div class="card" style="padding: 20px; max-width: 600px;">
                <h2>جستجوی صراف برای حذف</h2>
                
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                    
                    <p>
                        <label for="exchange_id"><strong>شناسه صراف:</strong></label><br>
                        <input type="number" id="exchange_id" name="exchange_id" 
                               style="width: 200px; margin-top: 5px;" 
                               required min="1">
                    </p>
                    
                    <p style="margin-top: 20px;">
                        <button type="submit" class="button button-primary">
                            🔍 جستجوی صراف
                        </button>
                    </p>
                </form>
                
                <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <h3>راهنمایی:</h3>
                    <p>برای پیدا کردن شناسه صراف:</p>
                    <ol>
                        <li>به صفحه مدیریت صرافان بروید</li>
                        <li>روی صراف مورد نظر کلیک کنید</li>
                        <li>در آدرس URL، شناسه را پیدا کنید (مثلاً: exchange_id=123)</li>
                        <li>یا از جدول wp_exchanges در phpMyAdmin استفاده کنید</li>
                    </ol>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
    
    <style>
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }
        details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        summary {
            cursor: pointer;
            font-weight: bold;
        }
    </style>
    <?php
}


