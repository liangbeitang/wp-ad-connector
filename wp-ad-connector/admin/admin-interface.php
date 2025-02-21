<?php
/**
 * 该类负责创建插件的管理界面
 */
class WPAD_Admin_Interface {
    /**
     * 构造函数，添加管理菜单和相关操作
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_wpad_manual_sync', [$this, 'handle_manual_sync']);
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_menu_page(
            'WP·AD互联管理',
            'WP·AD互联',
            'manage_options',
            'wp-ad-connector-admin',
            [$this, 'admin_page_html'],
            'dashicons-admin-network',
            25
        );
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

    /**
     * 渲染管理页面 HTML
     */
    public function admin_page_html() {
        $sync_success = isset($_GET['sync_success']) && $_GET['sync_success'] == 1;
        ?>
        <div class="wrap">
            <h1>WP·AD互联管理</h1>

            <?php if ($sync_success): ?>
                <div class="updated notice is-dismissible">
                    <p>手动同步成功！</p>
                </div>
            <?php endif; ?>

            <h2>统计信息</h2>
            <p>这里可以显示一些统计信息，例如：</p>
            <ul>
                <li>已同步用户数量：<span id="synced-users-count">待统计</span></li>
                <li>上次同步时间：<span id="last-sync-time">待统计</span></li>
            </ul>

            <h2>手动同步</h2>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                <input type="hidden" name="action" value="wpad_manual_sync">
                <?php wp_nonce_field('wpad_manual_sync_nonce'); ?>
                <input type="submit" class="button button-primary" value="手动同步用户">
            </form>
        </div>
        <?php
    }
}

// 实例化管理界面类
new WPAD_Admin_Interface();