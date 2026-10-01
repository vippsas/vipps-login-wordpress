<?php
/* Login with Vipps settings administration. Copyright (c) 2026 WP-Hosting AS.
 * Distributed under the MIT license; see ../../LICENSE.
 */
if (!defined('ABSPATH')) exit;

class VippsLoginAdminSettings {
    const OPTION = 'vipps_login_settings';
    const ACTION = 'vipps_login_save_settings';
    const COPY_ACTION = 'vipps_login_copy_payment_keys';
    const NONCE = 'vipps_login_settings';
    const PAYMENT_KEYS_IMPORTED = 'payment_keys_imported';
    private static $instance;
    private $legacy_result;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function register() {
        // Internal updates (migrations, activation, page repair) must not be treated
        // as submissions of editable fields. options.php already checks its nonce.
        $args = array();
        global $pagenow;
        if ($pagenow === 'options.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['option_page'] ?? '') === self::OPTION
            && ($_POST['action'] ?? '') === 'update') {
            $args['sanitize_callback'] = array($this, 'validate_legacy');
        }
        register_setting(self::OPTION, self::OPTION, $args);
        add_action('wp_ajax_' . self::ACTION, array($this, 'ajax_save'));
        add_action('wp_ajax_' . self::COPY_ACTION, array($this, 'ajax_copy_payment_keys'));
    }

    // Field definitions are read-only. WooCommerce fields exist only when its
    // login integration is loaded. No gateway classes are used here.
    public function sections() {
        $sections = array(
            ContinueWithVipps::instance()->init_form_login_options(),
            VippsLogin::instance()->init_form_login_options2(),
        );
        if (class_exists('VippsWooLogin')) {
            $woo = VippsWooLogin::instance()->init_form_login_woo_options();
            if ($woo) $sections[] = $woo;
        }
        return $sections;
    }

    private function fields() {
        $fields = array();
        foreach ($this->sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                if ($field['type'] !== 'description') $fields[$key] = $field;
            }
        }
        return $fields;
    }

    // For server-rendered bootstrap, not a public read endpoint. Credentials are
    // available only to authorized administrators and never logged by this class.
    public function bootstrap() {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Insufficient privileges', 'login-with-vipps'));
        }
        // Retry after activation if WooCommerce was not initialized yet.
        $this->maybe_import_payment_keys();
        $stored = get_option(self::OPTION, array());
        $values = array();
        foreach ($this->fields() as $key => $field) {
            $value = $stored[$key] ?? ($field['default'] ?? '');
            if ($field['type'] === 'checkbox') $value = in_array($value, array(true, 1, '1'), true) ? 1 : 0;
            if ($field['type'] === 'multicheck' && !is_array($value)) $value = array();
            $values[$key] = $value;
        }
        return array(
            'values' => $values,
            'sections' => $this->sections(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'action' => self::ACTION,
            'copy_action' => self::COPY_ACTION,
            'nonce' => wp_create_nonce(self::NONCE),
            'payment_keys_available' => $this->payment_keys_available(),
            'translations' => $this->translations(),
        );
    }

    /** Import payment credentials once, without overwriting Login credentials. */
    public function maybe_import_payment_keys() {
        $stored = get_option(self::OPTION, array());
        $stored = is_array($stored) ? $stored : array();
        if (!empty($stored[self::PAYMENT_KEYS_IMPORTED])) return false;
        if (!empty($stored['clientid']) || !empty($stored['clientsecret'])) {
            $stored[self::PAYMENT_KEYS_IMPORTED] = 1;
            update_option(self::OPTION, $stored);
            return false;
        }
        if (!empty($stored['use_vipps_login'])) return false;
        $keys = $this->payment_keys();
        if (!$keys) return false;
        $stored['clientid'] = $keys['clientid'];
        $stored['clientsecret'] = $keys['clientsecret'];
        $stored[self::PAYMENT_KEYS_IMPORTED] = 1;
        update_option(self::OPTION, $stored);
        return true;
    }

    /** Read primary credentials from whichever payment gateway class is present. */
    private function payment_keys() {
        foreach (array('WC_Payment_Gateway_Vipps', 'WC_Gateway_Vipps') as $class) {
            if (!class_exists($class) || !method_exists($class, 'instance')) continue;
            $gateway = call_user_func(array($class, 'instance'));
            if (!$gateway || !method_exists($gateway, 'get_option')) continue;
            $clientid = (string) $gateway->get_option('clientId');
            $clientsecret = (string) $gateway->get_option('secret');
            if ($clientid !== '' && $clientsecret !== '') return array('clientid' => $clientid, 'clientsecret' => $clientsecret);
        }
        return false;
    }

    private function payment_keys_available() { return (bool) $this->payment_keys(); }

    /** Render the React root and provide all data before loading the bundle. */
    public function render_react_settings_page() {
        if (!is_admin() || !current_user_can('manage_options')) {
            wp_die(__('Insufficient privileges', 'login-with-vipps'));
        }
        echo '<div class="wrap vipps-login-admin-settings-page"><div class="wp-header-end"></div><div id="vipps-login-react-ui"></div></div>';
    }

    /** Enqueue and localize the bundle before WordPress prints the admin head. */
    public function enqueue_react_assets($suffix) {
        global $pagenow;
        $canonical_page = $pagenow === 'admin.php' && (($_GET['page'] ?? '') === 'vipps_login_options');
        $legacy_page = $pagenow === 'options-general.php' && (($_GET['page'] ?? '') === 'vipps_login_settings');
        if (!$canonical_page && !$legacy_page) return;
        $bootstrap = $this->bootstrap();
        if (is_wp_error($bootstrap)) return;
        $script = dirname(__DIR__) . '/settings/dist/plugin.js';
        $style = dirname(__DIR__) . '/settings/dist/plugin.css';
        wp_enqueue_script('vipps-login-react-ui', plugins_url('dist/plugin.js', __FILE__), array('wp-element'), file_exists($script) ? filemtime($script) : null, true);
        if (file_exists($style)) wp_enqueue_style('vipps-login-react-ui', plugins_url('dist/plugin.css', __FILE__), array(), filemtime($style));
        wp_localize_script('vipps-login-react-ui', 'VippsLoginReactSettings', $bootstrap);
    }

    /** Keep UI copy in the login text domain and independent from payment translations. */
    private function translations() {
        return array(
            'company_name' => VippsLogin::CompanyName(),
            'page_title' => sprintf(__('Login with %1$s', 'login-with-vipps'), VippsLogin::CompanyName()),
            'general' => __('General', 'login-with-vipps'),
            'api_keys' => __('API keys', 'login-with-vipps'),
            'advanced' => __('Advanced', 'login-with-vipps'),
            'woocommerce' => __('WooCommerce', 'login-with-vipps'),
            'settings_saved' => __('Settings saved', 'login-with-vipps'),
            'save_changes' => __('Save changes', 'login-with-vipps'),
            'save_failed' => __('Could not save settings. Please try again.', 'login-with-vipps'),
            'copy_keys' => __('Copy payment gateway keys', 'login-with-vipps'),
            'copy_keys_failed' => __('Could not copy the payment plugin credentials.', 'login-with-vipps'),
            'unsaved_changes' => __('You have unsaved changes.', 'login-with-vipps'),
            'show' => __('Show', 'login-with-vipps'),
            'hide' => __('Hide', 'login-with-vipps'),
        );
    }

    // Input is already unslashed. Missing keys mean unchanged; empty strings
    // deliberately clear credentials/text, and an empty roles map clears roles.
    // This method has no write side effects, including when validation fails.
    public function validate($input) {
        if (!is_array($input)) {
            return new WP_Error('values', __('Settings must be an object.', 'login-with-vipps'));
        }
        $fields = $this->fields();
        $current = get_option(self::OPTION, array());
        $current = is_array($current) ? $current : array();
        $valid = $current;
        $errors = new WP_Error();
        foreach ($input as $key => $value) {
            if (!isset($fields[$key])) {
                $errors->add('values', __('An unknown or unavailable setting was submitted.', 'login-with-vipps'));
                continue;
            }
            $field = $fields[$key];
            $ok = true;
            switch ($field['type']) {
                case 'checkbox':
                    $ok = in_array($value, array(0, 1, '0', '1', false, true), true);
                    if ($ok) $value = (int) (bool) $value;
                    break;
                case 'password':
                    // Opaque credentials: do not trim, sanitize or unescape twice.
                    $ok = is_string($value);
                    break;
                case 'text':
                    $ok = is_string($value);
                    if ($ok) $value = wp_kses_post($value);
                    break;
                case 'multicheck':
                    $ok = is_array($value);
                    if ($ok) {
                        $roles = array();
                        foreach ($value as $role => $selected) {
                            // Preserve a previously selected removed role until
                            // the administrator explicitly clears it.
                            $known = isset($field['options'][$role]) || isset($current[$key][$role]);
                            if (!$known || !in_array($selected, array(0, 1, '0', '1', false, true), true)) {
                                $ok = false;
                                break;
                            }
                            if ($selected) $roles[$role] = 1;
                        }
                        $value = $roles;
                    }
                    break;
                case 'select':
                    if ($key === 'continuepageid') {
                        $ok = is_int($value) || (is_string($value) && ($value === '' || ctype_digit($value)));
                        if ($ok) {
                            $value = (int) $value;
                            $ok = $value >= 0;
                            if ($value > 0) {
                                $page = get_post($value);
                                $ok = $page && $page->post_type === 'page' && $page->post_status !== 'trash';
                            }
                        }
                    } else {
                        $ok = is_string($value) && array_key_exists($value, $field['options']);
                    }
                    break;
                default:
                    $ok = false;
            }
            if (!$ok) {
                $errors->add($key, sprintf(__('Invalid value for %s.', 'login-with-vipps'), $field['title']));
            } else {
                $valid[$key] = $value;
            }
        }
        return $errors->get_error_codes() ? $errors : $valid;
    }

    private function prepare($input) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Insufficient privileges', 'login-with-vipps'));
        }
        $valid = $this->validate($input);
        if (is_wp_error($valid)) return $valid;
        if (array_key_exists('continuepageid', $input) && !$valid['continuepageid']) {
            $page = VippsLogin::instance()->create_continue_with_vipps_page($valid['login_method'] ?? null);
            if (is_wp_error($page)) return $page;
            $valid['continuepageid'] = $page->ID;
        }
        return $valid;
    }

    public function validate_legacy($input) {
        // WordPress may sanitize twice when an option is first added. Reuse the
        // result so a page is created once and merged internal keys aren't rejected.
        if ($this->legacy_result !== null) return $this->legacy_result;
        if (is_array($input) && isset($input['required_roles']) && $input['required_roles'] === '') {
            $input['required_roles'] = array();
        }
        $valid = $this->prepare($input);
        if (is_wp_error($valid)) {
            foreach ($valid->get_error_codes() as $code) {
                foreach ($valid->get_error_messages($code) as $message) {
                    add_settings_error(self::OPTION, $code, $message);
                }
            }
            $valid = get_option(self::OPTION, array());
        }
        $this->legacy_result = $valid;
        return $valid;
    }

    public function ajax_save() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('errors' => array('forbidden' => array(__('Insufficient privileges', 'login-with-vipps')))), 403);
            return;
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('errors' => array('nonce' => array(__('Your session has expired. Reload the page and try again.', 'login-with-vipps')))), 403);
            return;
        }
        // JSON inside a form parameter preserves empty maps and numeric/boolean
        // types while WordPress adds slashes to the outer request exactly once.
        $json = isset($_POST['values']) && is_string($_POST['values']) ? wp_unslash($_POST['values']) : '';
        $object = json_decode($json);
        if (!is_object($object)) {
            wp_send_json_error(array('errors' => array('values' => array(__('Settings must be a JSON object.', 'login-with-vipps')))), 400);
            return;
        }
        $input = json_decode($json, true);
        $valid = $this->prepare($input);
        if (is_wp_error($valid)) {
            $errors = array();
            foreach ($valid->get_error_codes() as $code) $errors[$code] = $valid->get_error_messages($code);
            wp_send_json_error(array('errors' => $errors), 400);
            return;
        }
        $updated = update_option(self::OPTION, $valid);
        if (!$updated && get_option(self::OPTION, array()) !== $valid) {
            wp_send_json_error(array('errors' => array('save' => array(__('Could not save settings. Please try again.', 'login-with-vipps')))), 500);
            return;
        }
        ContinueWithVipps::instance()->settings = get_option(self::OPTION, array());
        wp_send_json_success($this->bootstrap());
    }

    /** Explicit manual copy action for an administrator viewing the Keys tab. */
    public function ajax_copy_payment_keys() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('errors' => array('forbidden' => array(__('Insufficient privileges', 'login-with-vipps')))), 403);
            return;
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('errors' => array('nonce' => array(__('Your session has expired. Reload the page and try again.', 'login-with-vipps')))), 403);
            return;
        }
        $stored = get_option(self::OPTION, array());
        $stored = is_array($stored) ? $stored : array();
        // The React form may contain unsaved clears. Trust only an explicit
        // request showing both current fields empty; older callers still use
        // the saved values as the fallback check.
        $request_values = null;
        if (isset($_POST['values']) && is_string($_POST['values'])) {
            $request_values = json_decode(wp_unslash($_POST['values']), true);
        }
        $keys_are_empty = is_array($request_values)
            ? empty($request_values['clientid']) && empty($request_values['clientsecret'])
            : empty($stored['clientid']) && empty($stored['clientsecret']);
        if (!$keys_are_empty) {
            wp_send_json_error(array('errors' => array('keys' => array(__('Both Login credential fields must be empty before copying.', 'login-with-vipps')))), 400);
            return;
        }
        $keys = $this->payment_keys();
        if (!$keys) {
            wp_send_json_error(array('errors' => array('keys' => array(__('Payment plugin credentials are unavailable.', 'login-with-vipps')))), 400);
            return;
        }
        $stored['clientid'] = $keys['clientid'];
        $stored['clientsecret'] = $keys['clientsecret'];
        $stored[self::PAYMENT_KEYS_IMPORTED] = 1;
        update_option(self::OPTION, $stored);
        ContinueWithVipps::instance()->settings = $stored;
        wp_send_json_success($this->bootstrap());
    }
}
