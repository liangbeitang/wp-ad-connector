<?php
/**
 * 该类负责处理邮件服务相关操作，如发送验证码邮件
 */
class WPAD_Mail_Service {
    private $mail_provider;

    /**
     * 构造函数，初始化邮件服务商
     */
    public function __construct() {
        // 从 WordPress 选项中获取邮件服务商配置，默认为 'default'（使用 wp_mail）
        $this->mail_provider = get_option('wpad_mail_provider', 'default');
    }

    /**
     * 发送验证码邮件
     *
     * @param string $email 收件人邮箱地址
     * @param string $code 验证码
     * @return bool 如果邮件发送成功，返回 true；否则返回 false
     */
    public function send_verification_code($email, $code) {
        $subject = 'AD 密码修改验证码';
        $message = "您的 AD 密码修改验证码是：{$code}，验证码有效期为 5 分钟，请尽快使用。";

        switch ($this->mail_provider) {
            case 'default':
                return $this->send_mail_using_wp_mail($email, $subject, $message);
            case 'smtp':
                // 这里可以实现使用 SMTP 发送邮件的逻辑
                return $this->send_mail_using_smtp($email, $subject, $message);
            // 可以添加更多邮件服务商的处理逻辑
            default:
                return $this->send_mail_using_wp_mail($email, $subject, $message);
        }
    }

    /**
     * 使用 WordPress 内置的 wp_mail 函数发送邮件
     *
     * @param string $email 收件人邮箱地址
     * @param string $subject 邮件主题
     * @param string $message 邮件内容
     * @return bool 如果邮件发送成功，返回 true；否则返回 false
     */
    private function send_mail_using_wp_mail($email, $subject, $message) {
        return wp_mail($email, $subject, $message);
    }

    /**
     * 使用 SMTP 发送邮件（示例逻辑，需要根据实际配置完善）
     *
     * @param string $email 收件人邮箱地址
     * @param string $subject 邮件主题
     * @param string $message 邮件内容
     * @return bool 如果邮件发送成功，返回 true；否则返回 false
     */
    private function send_mail_using_smtp($email, $subject, $message) {
        // 这里需要根据具体的 SMTP 配置（如服务器地址、端口、用户名、密码等）来实现邮件发送逻辑
        // 可以使用 PHPMailer 等库来实现 SMTP 邮件发送
        // 以下是一个简单的示例，仅作示意
        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($email, $subject, $message, $headers);
    }
}