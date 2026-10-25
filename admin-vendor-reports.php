<?php
/**
 * مدیریت گزارشات تخلف - پنل ادمین
 */

if (!defined('ABSPATH')) exit;

// اضافه کردن منوی مدیریت گزارشات
add_action('admin_menu', 'wer_add_vendor_reports_admin_menu');

function wer_add_vendor_reports_admin_menu() {
    if (!current_user_can('administrator')) return;
    
    add_submenu_page(
        'vendor-dashboard',
        'گزارشات تخلف',
        'گزارشات تخلف',
        'administrator',
        'vendor-reports',
        'wer_render_vendor_reports_page'
    );
}

function wer_render_vendor_reports_page() {
    if (!current_user_can('administrator')) {
        wp_die('دسترسی ندارید.');
    }
    
    global $wpdb;
    
    // بررسی اقدامات
    $action = $_GET['action'] ?? '';
    $report_id = intval($_GET['report_id'] ?? 0);
    $vendor_id = intval($_GET['vendor_id'] ?? 0);
    
    if ($action === 'mark_scammer' && $vendor_id > 0) {
        // علامت‌گذاری صراف به عنوان کلاهبردار
        $reason = sanitize_textarea_field($_POST['scammer_reason'] ?? 'کلاهبردار تأیید شده');
        
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
        
        echo '<div class="notice notice-success"><p>صراف به عنوان کلاهبردار علامت‌گذاری شد.</p></div>';
    }
    
    if ($action === 'update_report' && $report_id > 0) {
        $status = sanitize_text_field($_POST['status'] ?? 'pending');
        $notes = sanitize_textarea_field($_POST['admin_notes'] ?? '');
        
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
        
        echo '<div class="notice notice-success"><p>گزارش به‌روزرسانی شد.</p></div>';
    }
    
    // دریافت گزارشات
    $reports = $wpdb->get_results("
        SELECT r.*, 
               e.exchange_name_fa, 
               e.phone as vendor_phone,
               e.scammer_status,
               e.status as vendor_status,
               e.rating,
               e.total_ratings
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
        <h1>📋 مدیریت گزارشات تخلف صراف‌ها</h1>
        
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
                                <a href="<?php echo admin_url('admin.php?page=vendor-profile&vendor_id=' . $report->vendor_id); ?>" target="_blank">
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
                                    <button class="button button-small view-report" data-id="<?php echo $report->id; ?>">
                                        مشاهده جزئیات
                                    </button>
                                    <?php if ($report->status === 'pending'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=vendor-reports&action=update_report&report_id=' . $report->id . '&status=reviewed'); ?>" 
                                           class="button button-small button-primary">
                                            بررسی شده
                                        </a>
                                        <button class="button button-small button-danger mark-scammer" 
                                                data-vendor-id="<?php echo $report->vendor_id; ?>"
                                                data-vendor-name="<?php echo esc_attr($report->exchange_name_fa); ?>">
                                            علامت‌گذاری کلاهبردار
                                        </button>
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
                            <a href="<?php echo admin_url('admin.php?page=vendor-profile&vendor_id=' . $vendor->id); ?>" target="_blank">
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
                            <button class="button button-small button-danger mark-scammer" 
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
    
    <!-- مودال برای علامت‌گذاری کلاهبردار -->
    <div id="scammerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3>علامت‌گذاری صراف به عنوان کلاهبردار</h3>
            <p id="scammerVendorName" style="font-weight: bold; color: #dc3545; margin: 10px 0 20px 0;"></p>
            
            <form method="post" action="<?php echo admin_url('admin.php?page=vendor-reports&action=mark_scammer'); ?>">
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
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // باز کردن مودال علامت‌گذاری کلاهبردار
        document.querySelectorAll('.mark-scammer').forEach(button => {
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
                // در اینجا می‌توانید یک مودال برای نمایش جزئیات کامل گزارش ایجاد کنید
                alert('نمایش جزئیات گزارش ID: ' + reportId + '\n\nاین بخش قابل توسعه است.');
            });
        });
    });
    
    function closeScammerModal() {
        document.getElementById('scammerModal').style.display = 'none';
    }
    
    // بستن مودال با کلیک بیرون
    document.getElementById('scammerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeScammerModal();
        }
    });
    </script>
    <?php
}