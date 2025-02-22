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
    // 腾讯云验证码appid，默认空
    add_option('wpad_captcha_appid', '');

    // 刷新重写规则，确保新规则生效
    // flush_rewrite_rules(); // 暂时注释掉，避免激活时刷新重写规则导致问题
});

// 插件加载时的钩子，当 WordPress 加载插件时会执行该函数
add_action('init', function() {
    add_rewrite_rule('^ad-login$', 'index.php?ad_login=true', 'top');
    // flush_rewrite_rules(); // 暂时注释掉，避免每次加载时刷新重写规则
});

add_filter('query_vars', function($query_vars) {
    $query_vars[] = 'ad_login';
    return $query_vars;
});

// 添加新的登录页面处理
add_action('login_form', function() {
    echo '<a href="' . site_url('/ad-login') . '" class="button button-primary">AD域登录</a>';
});

add_action('template_include', function($template) {
    if (get_query_var('ad_login') === 'true') {
        return plugin_dir_path(__FILE__) . 'public/ad-login.php';
    }
    return $template;
});

add_action('wp_ajax_ad_verify_config', function() {
    $ad_ip_address = sanitize_text_field($_POST['ad_ip_address']);
    $ad_admin_username = sanitize_text_field($_POST['ad_admin_username']);
    $ad_admin_password = sanitize_text_field($_POST['ad_admin_password']);
    $ad_admin_domain = sanitize_text_field($_POST['ad_admin_domain']); // 添加获取域名的代码

    // 实例化 AD 操作类
    $ad_operator = new WPAD_AD_Operator();

    // 调用验证方法
    $result = $ad_operator->verify_admin_login($ad_ip_address, $ad_admin_username, $ad_admin_password, $ad_admin_domain);

    if ($result) {
        wp_send_json_success('AD 配置验证成功');
    } else {
        wp_send_json_error('AD 配置验证失败，请检查输入信息。');
    }
});

add_action('wp_ajax_nopriv_ad_verify_config', function() {
    wp_send_json_error('你没有权限进行此操作。');
});

add_action('wp_ajax_nopriv_ad_login', 'handle_ad_login');
add_action('wp_ajax_ad_login', 'handle_ad_login');

function handle_ad_login() {
    // 检查请求方法是否为 POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log('无效的请求方法');
        wp_send_json_error(['message' => '无效的请求方法']);
        return;
    }

    // 检查必要的 POST 参数是否存在
    $required_fields = ['employee_id', 'password', 'captcha_randstr', 'captcha_ticket'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            error_log("缺少必要参数: {$field}");
            wp_send_json_error(['message' => "缺少必要参数: {$field}"]);
            return;
        }
    }

    $employee_id = sanitize_text_field($_POST['employee_id']);
    $password = sanitize_text_field($_POST['password']);
    $captcha_randstr = sanitize_text_field($_POST['captcha_randstr']);
    $captcha_ticket = sanitize_text_field($_POST['captcha_ticket']);

    // 验证验证码
    $captcha_appid = get_option('wpad_captcha_appid');
    $captcha_secret_key = get_option('wpad_captcha_secret_key'); // 确保保存了 Secret Key

    if (empty($captcha_appid) || empty($captcha_secret_key)) {
        wp_send_json_error('验证码配置不完整，请检查腾讯云验证码 appid 和 secret key。');
        return;
    }

    // 调用腾讯云验证码验证 API
    $url = "https://api.tencentcloudapi.com/";
    $params = [
        'SecretId' => $captcha_secret_key,
        'SecretKey' => $captcha_secret_key, // 根据实际情况调整
        'Action' => 'VerifyCaptcha',
        'Version' => '2019-07-22',
        'Region' => 'ap-guangzhou', // 根据实际情况调整
        'Ticket' => $captcha_ticket,
        'Randstr' => $captcha_randstr,
        // 其他必要参数
    ];

    // 使用 cURL 或其他 HTTP 客户端发送请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("验证码验证请求失败: {$error}");
        wp_send_json_error('验证码验证请求失败，请稍后重试。');
        return;
    }
    curl_close($ch);

    $result = json_decode($response, true);

    if (!is_array($result) || !isset($result['Response']['Result'])) {
        error_log("验证码验证响应格式错误: " . json_encode($result));
        wp_send_json_error('验证码验证失败，请重试。');
        return;
    }

    if ($result['Response']['Result'] === 0) {
        // 验证码验证成功，继续处理登录逻辑
        // 这里添加AD域登录的验证逻辑，例如使用LDAP验证
        // 假设我们有一个 WPAD_AD_Operator 类，其中有一个 verify_ad_login 方法用于验证
        require_once dirname(__FILE__) . '/includes/class-ad-operator.php';
        $ad_operator = new WPAD_AD_Operator();
        $is_valid = $ad_operator->verify_ad_login($employee_id, $password);

        if ($is_valid) {
            $user = wp_signon([
                'user_login'    => $employee_id,
                'user_password' => $password,
                'remember'      => false
            ]);

            if (is_wp_error($user)) {
                wp_send_json_error('用户名或密码错误。');
            } else {
                wp_send_json_success(['redirect_url' => get_home_url()]);
            }
        } else {
            wp_send_json_error('AD域登录验证失败，请检查输入信息。');
        }
    } else {
        // 验证码验证失败
        wp_send_json_error('验证码验证失败，请重试。');
    }
}

// 确保设置页面类在插件加载时被实例化
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
});