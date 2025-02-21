<?php
/*
Plugin Name: WP·AD互联插件
Plugin URI: https://www.liangbeitang.com/open-source-coding/wp-plugin/wp-ad-connector/
Description: 该插件用于实现 WordPress 与 Active Directory 的集成，具备用户同步、密码管理等功能。
Version: 1.0
Author: 梁北棠 <contact@liangbeitang.com>
Author URI: https://www.liangbeitang.com
License: GPL2
*/

// 插件激活时的钩子，当插件被激活时会执行该函数
register_activation_hook(__FILE__, function() {
    // 添加 AD 相关的配置选项
    // AD 服务器的主机名，默认设置为 'dc.example.com'
    add_option('wpad_ldap_host', 'dc.example.com');
    // WordPress 中用户的默认角色，默认设置为 'subscriber'
    add_option('wpad_default_role', 'subscriber');
    // 密码策略级别，默认设置为 'medium'
    add_option('wpassword_policy', 'medium');
});

// 插件加载时的钩子，当 WordPress 加载插件时会执行该函数
add_action('plugins_loaded', function() {
    // 加载多语言支持，确保插件能在不同语言环境下正常显示
    load_plugin_textdomain('wp-ad-connector', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // 包含核心类文件
    require_once dirname(__FILE__) . '/includes/class-ad-operator.php';
    require_once dirname(__FILE__) . '/includes/class-user-sync.php';
    require_once dirname(__FILE__) . '/includes/class-password-reset.php';
    require_once dirname(__FILE__) . '/includes/class-mail-service.php';
    require_once dirname(__FILE__) . '/includes/settings-page.php';
    require_once dirname(__FILE__) . '/admin/admin-interface.php';

    // 初始化核心模块
    // 实例化设置页面类，用于在 WordPress 后台显示插件的设置界面
    new WPAD_Settings_Page();
    // 实例化用户同步类，负责从 AD 同步用户到 WordPress
    new WPAD_User_Sync();
    // 实例化密码重置类，处理用户在 WordPress 中修改 AD 密码的操作
    new WPAD_Password_Reset();
    // 实例化邮件服务类，用于发送邮件验证码等邮件通知
    new WPAD_Mail_Service();

    // 加载密码修改端点，包含用户资料扩展及相关表单处理逻辑
    require_once dirname(__FILE__) . '/public/user-profile.php';

    // 处理验证 AD 配置的 AJAX 请求
    add_action('wp_ajax_ad_verify_config', function() {
        $ad_admin_domain = sanitize_text_field($_POST['ad_admin_domain']);
        $ad_admin_password = sanitize_text_field($_POST['ad_admin_password']);
        $ad_search_org_dn = sanitize_text_field($_POST['ad_search_org_dn']);

        // 实例化 AD 操作类
        $ad_operator = new WPAD_AD_Operator();

        // 调用验证方法
        $result = $ad_operator->verify_ad_config($ad_admin_domain, $ad_admin_password, $ad_search_org_dn);

        if ($result) {
            wp_send_json_success('AD 配置验证成功');
        } else {
            wp_send_json_error('AD 配置验证失败，请检查输入信息。');
        }
    });

    add_action('wp_ajax_nopriv_ad_verify_config', function() {
        wp_send_json_error('你没有权限进行此操作。');
    });
});

// 添加 WP-CLI 命令，方便通过命令行进行用户同步操作
if (defined('WP_CLI') && WP_CLI) {
    class WPAD_Connector_CLI_Command extends WP_CLI_Command {
        /**
         * 执行用户同步操作。
         *
         * ## OPTIONS
         *
         * [--full]
         * : 执行全量同步。
         *
         * ## EXAMPLES
         *
         *     wp ad-connector sync-users
         *     wp ad-connector sync-users --full
         */
        public function sync_users($args, $assoc_args) {
            require_once dirname(__FILE__) . '/includes/class-user-sync.php';
            $user_sync = new WPAD_User_Sync();
            if (isset($assoc_args['full'])) {
                // 这里可以实现全量同步的逻辑
                WP_CLI::log('执行全量用户同步...');
            } else {
                // 执行增量同步
                $user_sync->sync_users();
                WP_CLI::log('执行增量用户同步...');
            }
        }
    }
    WP_CLI::add_command('ad-connector', 'WPAD_Connector_CLI_Command');
}