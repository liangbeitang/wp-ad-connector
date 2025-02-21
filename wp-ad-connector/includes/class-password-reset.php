<?php
/**
 * 该类负责处理用户修改 AD 密码的操作
 */
class WPAD_Password_Reset {
    private $ad_operator;
    private $mail_service;

    /**
     * 构造函数，初始化 AD 操作类和邮件服务类实例，并设置相关操作钩子
     */
    public function __construct() {
        $this->ad_operator = new WPAD_AD_Operator();
        $this->mail_service = new WPAD_Mail_Service();
        $this->handle_password_change();
    }

    /**
     * 处理密码修改相关操作，包括表单渲染和 AJAX 请求处理
     */
    public function handle_password_change() {
        // 当访问指定页面时，渲染密码修改表单
        add_action('template_redirect', function() {
            if (is_page('ad-password-change')) {
                $this->render_password_form();
            }
        });

        // 处理 AJAX 请求，用于修改密码
        add_action('wp_ajax_ad_change_password', [$this, 'process_password_change']);
        add_action('wp_ajax_nopriv_ad_change_password', [$this, 'process_password_change']);
    }

    /**
     * 渲染密码修改表单
     */
    public function render_password_form() {
        $template_path = dirname(__FILE__) . '/../public/templates/password-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        exit;
    }

    /**
     * 处理密码修改请求
     */
    public function process_password_change() {
        // 验证验证码
        if (!$this->verify_captcha()) {
            wp_send_json_error('验证码验证失败');
        }

        // 验证密码强度
        if (!$this->validate_password_strength($_POST['new_password'])) {
            wp_send_json_error('密码强度不符合要求');
        }

        $current_user = wp_get_current_user();
        if ($current_user->ID) {
            $user_dn = $this->get_user_dn($current_user->user_login);
            if ($user_dn) {
                // 执行 AD 密码修改
                if ($this->ad_operator->change_user_password(
                    $user_dn,
                    $_POST['current_password'],
                    $_POST['new_password']
                )) {
                    do_action('wpad_password_changed', $current_user->ID);
                    wp_send_json_success('密码修改成功');
                } else {
                    wp_send_json_error('AD 密码修改失败');
                }
            } else {
                wp_send_json_error('无法获取用户的 DN');
            }
        } else {
            wp_send_json_error('用户未登录');
        }
    }

    /**
     * 验证验证码
     *
     * @return bool 如果验证码验证通过，返回 true；否则返回 false
     */
    private function verify_captcha() {
        // 获取用户输入的验证码
        $user_captcha = isset($_POST['captcha']) ? sanitize_text_field($_POST['captcha']) : '';
        // 这里可以实现具体的验证码验证逻辑，例如从会话中获取正确的验证码进行比较
        // 以下是一个简单的示例，假设正确的验证码存储在会话中
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $correct_captcha = isset($_SESSION['captcha']) ? $_SESSION['captcha'] : '';
        return $user_captcha === $correct_captcha;
    }

    /**
     * 验证密码强度
     *
     * @param string $password 用户输入的新密码
     * @return bool 如果密码强度符合要求，返回 true；否则返回 false
     */
    private function validate_password_strength($password) {
        $policy = get_option('wpassword_policy', 'medium');
        $min_length = 12;
        $has_special_char = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

        // 根据密码策略进行验证
        if (strlen($password) < $min_length) {
            return false;
        }
        if (!$has_special_char) {
            return false;
        }
        // 可以添加更多的密码强度验证逻辑，例如禁止使用最近 3 次密码等
        return true;
    }

    /**
     * 根据用户名获取用户的 DN
     *
     * @param string $username 用户名
     * @return string|bool 如果获取成功，返回用户的 DN；否则返回 false
     */
    private function get_user_dn($username) {
        $base_dn = get_option('wpad_base_dn', 'DC=example,DC=com');
        $filter = "(sAMAccountName={$username})";
        $attributes = ['distinguishedName'];

        $search_result = $this->ad_operator->search_users($base_dn, $filter, $attributes);
        if ($search_result && $search_result['count'] > 0) {
            return $search_result[0]['distinguishedname'][0];
        }
        return false;
    }
}