<?php
/**
 * 该类负责创建插件的设置页面
 */
class WPAD_Settings_Page {
    /**
     * 构造函数，添加管理菜单和相关操作
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_wpad_manual_sync', [$this, 'handle_manual_sync']);
        add_action('admin_post_wpad_save_ad_config', [$this, 'handle_save_ad_config']);
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
     * 处理保存 AD 服务器配置信息的请求
     */
    public function handle_save_ad_config() {
        check_admin_referer('wpad_save_ad_config_nonce');

        // 解析IP和端口
        $ad_server = sanitize_text_field($_POST['ad_ip_address']);
        $parts = explode(':', $ad_server);
        $ip_address = $parts[0];
        $port = isset($parts[1]) ? $parts[1] : 389;

        $admin_username = sanitize_text_field($_POST['ad_admin_username']);
        $admin_domain = sanitize_text_field($_POST['ad_admin_domain']);
        $admin_password = sanitize_text_field($_POST['ad_admin_password']);
        $search_org_dn = sanitize_text_field($_POST['ad_search_org_dn']);

        // 组合服务账户DN为"域\用户名"格式
        $service_account_dn = "{$admin_domain}\\{$admin_username}";

        // 保存到正确的选项名称
        update_option('wpad_ldap_host', $ip_address);
        update_option('wpad_ldap_port', $port);
        update_option('wpad_service_account_dn', $service_account_dn);
        update_option('wpad_service_account_password', $admin_password);
        update_option('wpad_ad_search_org_dn', $search_org_dn);

        wp_redirect(admin_url('admin.php?page=wp-ad-connector-admin&config_saved=1'));
        exit;
    }

    /**
     * 渲染管理页面 HTML
     */
    public function admin_page_html() {
        $sync_success = isset($_GET['sync_success']) && $_GET['sync_success'] == 1;
        $config_saved = isset($_GET['config_saved']) && $_GET['config_saved'] == 1;

        $ip_address = get_option('wpad_ldap_host', ''); // 修改为新的选项名
        $admin_username = ''; // 这里不再直接从选项获取，因为格式变了
        $admin_domain = '';   // 同上
        $admin_password = get_option('wpad_service_account_password', ''); // 修改为新的选项名
        $search_org_dn = get_option('wpad_ad_search_org_dn', '');

        // 尝试从 service_account_dn 解析出用户名和域名
        $service_account_dn = get_option('wpad_service_account_dn', '');
        if ($service_account_dn) {
            $parts = explode('\\', $service_account_dn);
            if (count($parts) == 2) {
                $admin_domain = $parts[0];
                $admin_username = $parts[1];
            }
        }

        ?>
        <div class="wrap">
            <h1>WP·AD互联管理</h1>

            <?php if ($sync_success): ?>
                <div class="updated notice is-dismissible">
                    <p>手动同步成功！</p>
                </div>
            <?php endif; ?>

            <?php if ($config_saved): ?>
                <div class="updated notice is-dismissible">
                    <p>AD 服务器配置信息保存成功！</p>
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

            <h2>AD 服务器配置信息</h2>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                <input type="hidden" name="action" value="wpad_save_ad_config">
                <?php wp_nonce_field('wpad_save_ad_config_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ad_ip_address">IP 地址（域名）</label></th>
                        <td><input type="text" id="ad_ip_address" name="ad_ip_address" value="<?php echo esc_attr($ip_address); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ad_admin_username">管理用户名称</label></th>
                        <td><input type="text" id="ad_admin_username" name="ad_admin_username" value="<?php echo esc_attr($admin_username); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ad_admin_domain">授权域</label></th>
                        <td><input type="text" id="ad_admin_domain" name="ad_admin_domain" value="<?php echo esc_attr($admin_domain); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ad_admin_password">管理用户密码</label></th>
                        <td><input type="password" id="ad_admin_password" name="ad_admin_password" value="<?php echo esc_attr($admin_password); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ad_search_org_dn">查询的组织 DN</label></th>
                        <td><input type="text" id="ad_search_org_dn" name="ad_search_org_dn" value="<?php echo esc_attr($search_org_dn); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="保存配置">
                    <!-- 在表单中添加验证按钮 -->
                    <input type="button" class="button" id="ad-config-verify" value="验证 AD 配置">
                </p>

                <!-- 添加 JavaScript 代码来处理验证按钮的点击事件 -->
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $("#ad-config-verify").click(function() {
                            // 获取所有需要的字段
                            var ad_ip_address = $("#ad_ip_address").val();
                            var ad_admin_username = $("#ad_admin_username").val();
                            var ad_admin_domain = $("#ad_admin_domain").val();
                            var ad_admin_password = $("#ad_admin_password").val();
                            var ad_search_org_dn = $("#ad_search_org_dn").val();

                            $.ajax({
                                url: ajaxurl,
                                type: "POST",
                                data: {
                                    action: "ad_verify_config",
                                    ad_ip_address: ad_ip_address,
                                    ad_admin_username: ad_admin_username,
                                    ad_admin_domain: ad_admin_domain,
                                    ad_admin_password: ad_admin_password,
                                    ad_search_org_dn: ad_search_org_dn
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // 验证成功，显示绿色提示
                                        var successMessage = "验证成功<br>验证域名: " + ad_admin_domain + "<br>DN: " + ad_search_org_dn;
                                        showMessage(successMessage, 'green');
                                    } else {
                                        // 验证失败，显示红色提示
                                        var errorMessage = "验证失败: " + response.data.message;
                                        showMessage(errorMessage, 'red');
                                    }
                                },
                                error: function() {
                                    // 验证请求失败，显示红色提示
                                    showMessage("验证请求失败，请稍后重试。", 'red');
                                }
                            });
                        });

                        // 显示提示信息的函数
                        function showMessage(message, color) {
                            var messageDiv = $('<div></div>')
                               .html(message)
                               .css({
                                    'color': color,
                                    'padding': '10px',
                                    'margin': '10px 0',
                                    'border': '1px solid ' + color
                                });
                            $('#ad-config-verify').after(messageDiv);
                        }
                    });
                </script>
            </form>
        </div>
        <?php
    }
}

// 实例化设置页面类
//new WPAD_Settings_Page();