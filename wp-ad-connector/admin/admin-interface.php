<?php
/**
 * 该类负责创建插件的管理界面
 */
class WPAD_Admin_Interface {
    /**
     * 构造函数，添加管理菜单和相关操作
     */
    public function __construct() {
        add_action('admin_post_wpad_manual_sync', [$this, 'handle_manual_sync']);
    }

    /**
     * 处理手动同步请求
     */
    public function handle_manual_sync() {
        check_admin_referer('wpad_manual_sync_nonce');

        require_once __DIR__ . '/../includes/class-user-sync.php';
        $user_sync = new WPAD_User_Sync();
        $user_sync->sync_users();

        wp_redirect(admin_url('admin.php?page=wp-ad-connector-admin&sync_success=1'));
        exit;
    }
}

// 实例化管理界面类
new WPAD_Admin_Interface();