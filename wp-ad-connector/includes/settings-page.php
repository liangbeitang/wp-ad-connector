<?php
/**
 * 该类负责创建和管理插件的设置页面
 */
class WPAD_Settings_Page {
    /**
     * 构造函数，添加管理菜单和初始化设置
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            'WP·AD互联插件设置',
            'WP·AD互联设置',
            'manage_options',
            'wp-ad-connector-settings',
            [$this, 'settings_page_html']
        );
    }

    /**
     * 初始化设置
     */
    public function settings_init() {
        // 注册设置组
        register_setting('wp_ad_connector_settings', 'wpad_ldap_host');
        register_setting('wp_ad_connector_settings', 'wpad_ldap_port');
        register_setting('wp_ad_connector_settings', 'wpad_service_account_dn');
        register_setting('wp_ad_connector_settings', 'wpad_service_account_password');
        register_setting('wp_ad_connector_settings', 'wpad_base_dn');
        register_setting('wp_ad_connector_settings', 'wpad_default_role');
        register_setting('wp_ad_connector_settings', 'wpassword_policy');
        register_setting('wp_ad_connector_settings', 'wpad_use_ldaps');
        register_setting('wp_ad_connector_settings', 'wpad_mail_provider');

        // 添加设置部分
        add_settings_section(
            'wp_ad_connector_ldap_section',
            'LDAP 连接设置',
            '',
            'wp_ad_connector_settings'
        );

        add_settings_section(
            'wp_ad_connector_user_section',
            '用户同步设置',
            '',
            'wp_ad_connector_settings'
        );

        add_settings_section(
            'wp_ad_connector_password_section',
            '密码策略设置',
            '',
            'wp_ad_connector_settings'
        );

        add_settings_section(
            'wp_ad_connector_mail_section',
            '邮件服务设置',
            '',
            'wp_ad_connector_settings'
        );

        // 添加 LDAP 连接设置字段
        add_settings_field(
            'wpad_ldap_host',
            'LDAP 服务器地址',
            [$this, 'wpad_ldap_host_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_ldap_section'
        );

        add_settings_field(
            'wpad_ldap_port',
            'LDAP 服务器端口',
            [$this, 'wpad_ldap_port_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_ldap_section'
        );

        add_settings_field(
            'wpad_service_account_dn',
            '服务账户 DN',
            [$this, 'wpad_service_account_dn_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_ldap_section'
        );

        add_settings_field(
            'wpad_service_account_password',
            '服务账户密码',
            [$this, 'wpad_service_account_password_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_ldap_section'
        );

        add_settings_field(
            'wpad_base_dn',
            '基础 DN',
            [$this, 'wpad_base_dn_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_ldap_section'
        );

        add_settings_field(
            'wpad_use_ldaps',
            '使用 LDAPS',
            [$this, 'wpad_use_ldaps_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_ldap_section'
        );

        // 添加用户同步设置字段
        add_settings_field(
            'wpad_default_role',
            '默认用户角色',
            [$this, 'wpad_default_role_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_user_section'
        );

        // 添加密码策略设置字段
        add_settings_field(
            'wpassword_policy',
            '密码策略',
            [$this, 'wpassword_policy_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_password_section'
        );

        // 添加邮件服务设置字段
        add_settings_field(
            'wpad_mail_provider',
            '邮件服务商',
            [$this, 'wpad_mail_provider_render'],
            'wp_ad_connector_settings',
            'wp_ad_connector_mail_section'
        );
    }

    /**
     * 渲染 LDAP 服务器地址输入框
     */
    public function wpad_ldap_host_render() {
        $setting = get_option('wpad_ldap_host');
        echo '<input type="text" name="wpad_ldap_host" value="' . esc_attr($setting) . '">';
    }

    /**
     * 渲染 LDAP 服务器端口输入框
     */
    public function wpad_ldap_port_render() {
        $setting = get_option('wpad_ldap_port', 389);
        echo '<input type="number" name="wpad_ldap_port" value="' . esc_attr($setting) . '">';
    }

    /**
     * 渲染服务账户 DN 输入框
     */
    public function wpad_service_account_dn_render() {
        $setting = get_option('wpad_service_account_dn');
        echo '<input type="text" name="wpad_service_account_dn" value="' . esc_attr($setting) . '">';
    }

    /**
     * 渲染服务账户密码输入框
     */
    public function wpad_service_account_password_render() {
        $setting = get_option('wpad_service_account_password');
        echo '<input type="password" name="wpad_service_account_password" value="' . esc_attr($setting) . '">';
    }

    /**
     * 渲染基础 DN 输入框
     */
    public function wpad_base_dn_render() {
        $setting = get_option('wpad_base_dn');
        echo '<input type="text" name="wpad_base_dn" value="' . esc_attr($setting) . '">';
    }

    /**
     * 渲染是否使用 LDAPS 复选框
     */
    public function wpad_use_ldaps_render() {
        $setting = get_option('wpad_use_ldaps', false);
        $checked = $setting ? 'checked' : '';
        echo '<input type="checkbox" name="wpad_use_ldaps" ' . $checked . '>';
    }

    /**
     * 渲染默认用户角色选择框
     */
    public function wpad_default_role_render() {
        global $wp_roles;
        $roles = $wp_roles->get_names();
        $selected = get_option('wpad_default_role', 'subscriber');
        echo '<select name="wpad_default_role">';
        foreach ($roles as $role => $name) {
            $selected_option = selected($role, $selected, false);
            echo '<option value="' . esc_attr($role) . '" ' . $selected_option . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    /**
     * 渲染密码策略选择框
     */
    public function wpassword_policy_render() {
        $policies = [
            'low' => '低',
            'medium' => '中',
            'high' => '高'
        ];
        $selected = get_option('wpassword_policy', 'medium');
        echo '<select name="wpassword_policy">';
        foreach ($policies as $policy => $name) {
            $selected_option = selected($policy, $selected, false);
            echo '<option value="' . esc_attr($policy) . '" ' . $selected_option . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    /**
     * 渲染邮件服务商选择框
     */
    public function wpad_mail_provider_render() {
        $providers = [
            'default' => '默认（wp_mail）',
            'smtp' => 'SMTP'
        ];
        $selected = get_option('wpad_mail_provider', 'default');
        echo '<select name="wpad_mail_provider">';
        foreach ($providers as $provider => $name) {
            $selected_option = selected($provider, $selected, false);
            echo '<option value="' . esc_attr($provider) . '" ' . $selected_option . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    /**
     * 渲染设置页面 HTML
     */
    public function settings_page_html() {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('wp_ad_connector_settings');
            do_settings_sections('wp_ad_connector_settings');
            submit_button();
            ?>
        </form>
        <?php
    }
}