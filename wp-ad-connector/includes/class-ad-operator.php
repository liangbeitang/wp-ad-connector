<?php
/**
 * 该类负责处理与 Active Directory 的连接和基本操作
 */
class WPAD_AD_Operator {
    private $ldap_connection;
    private $ldap_host;
    private $ldap_port;
    private $ldap_dn;
    private $ldap_password;
    private $use_ldaps;

    /**
     * 构造函数，初始化 AD 连接所需的参数
     */
    public function __construct() {
        // 从 WordPress 选项中获取 AD 服务器的主机名，默认为 'dc.example.com'
        $this->ldap_host = get_option('wpad_ldap_host', 'dc.example.com');
        // 从 WordPress 选项中获取 AD 服务器的端口号，默认为 389（LDAP）
        $this->ldap_port = get_option('wpad_ldap_port', 389);
        // 从 WordPress 选项中获取服务账户的 DN
        $this->ldap_dn = get_option('wpad_service_account_dn');
        // 从 WordPress 选项中获取服务账户的密码
        $this->ldap_password = get_option('wpad_service_account_password');
        // 从 WordPress 选项中获取是否使用 LDAPS 协议，默认为 false
        $this->use_ldaps = get_option('wpad_use_ldaps', false);
    }

    /**
     * 建立与 AD 的 LDAP 连接
     *
     * @return bool|resource 如果连接成功，返回 LDAP 连接资源；否则返回 false
     */
    public function connect() {
        if ($this->use_ldaps) {
            $this->ldap_connection = ldap_connect("ldaps://{$this->ldap_host}:{$this->ldap_port}");
        } else {
            $this->ldap_connection = ldap_connect($this->ldap_host, $this->ldap_port);
        }

        if ($this->ldap_connection) {
            ldap_set_option($this->ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($this->ldap_connection, LDAP_OPT_REFERRALS, 0);

            // 绑定服务账户
            $bind = ldap_bind($this->ldap_connection, $this->ldap_dn, $this->ldap_password);
            if ($bind) {
                return $this->ldap_connection;
            } else {
                error_log("LDAP 绑定失败: " . ldap_error($this->ldap_connection));
                return false;
            }
        } else {
            error_log("无法连接到 LDAP 服务器: {$this->ldap_host}:{$this->ldap_port}");
            return false;
        }
    }

    /**
     * 从 AD 中搜索用户信息
     *
     * @param string $base_dn 搜索的基础 DN
     * @param string $filter 搜索过滤器
     * @param array $attributes 需要返回的属性列表
     * @return array|bool 如果搜索成功，返回用户信息数组；否则返回 false
     */
    public function search_users($base_dn, $filter, $attributes = []) {
        if (!$this->ldap_connection) {
            if (!$this->connect()) {
                return false;
            }
        }

        $search_result = ldap_search($this->ldap_connection, $base_dn, $filter, $attributes);
        if ($search_result) {
            $entries = ldap_get_entries($this->ldap_connection, $search_result);
            if ($entries['count'] > 0) {
                return $entries;
            }
        }
        return false;
    }

    /**
     * 修改 AD 中用户的密码
     *
     * @param string $user_dn 用户的 DN
     * @param string $old_password 用户的旧密码
     * @param string $new_password 用户的新密码
     * @return bool 如果密码修改成功，返回 true；否则返回 false
     */
    public function change_user_password($user_dn, $old_password, $new_password) {
        if (!$this->ldap_connection) {
            if (!$this->connect()) {
                return false;
            }
        }

        $new_password_utf16 = iconv('UTF-8', 'UTF-16LE', '"' . $new_password . '"');
        $old_password_utf16 = iconv('UTF-8', 'UTF-16LE', '"' . $old_password . '"');

        $modifications = [
            [
                'attrib' => 'unicodePwd',
                'modtype' => LDAP_MODIFY_BATCH_REMOVE,
                'values' => [$old_password_utf16]
            ],
            [
                'attrib' => 'unicodePwd',
                'modtype' => LDAP_MODIFY_BATCH_ADD,
                'values' => [$new_password_utf16]
            ]
        ];

        $result = ldap_modify_batch($this->ldap_connection, $user_dn, $modifications);
        if ($result) {
            return true;
        } else {
            error_log("修改 AD 用户密码失败: " . ldap_error($this->ldap_connection));
            return false;
        }
    }

    /**
     * 关闭 LDAP 连接
     */
    public function close() {
        if ($this->ldap_connection) {
            ldap_close($this->ldap_connection);
        }
    }
}