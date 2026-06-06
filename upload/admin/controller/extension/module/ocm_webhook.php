<?php

class ControllerExtensionModuleOcmWebhook extends Controller
{
    private const CODE = 'module_ocm_webhook';
    private const ROUTE = 'extension/module/ocm_webhook';
    private const EVENT_PREFIX = 'ocm_webhook_';
    private const DEFAULT_SORT_ORDER = 1000;
    private const LOG_STATUS_KEY = 'module_ocm_webhook_log_status';
    private const AUTH_NONE = 'none';
    private const AUTH_QUERY = 'query';
    private const AUTH_BEARER = 'bearer';
    private const AUTH_HEADER = 'header';
    private const AUTH_BASIC = 'basic';
    private const AUTH_HMAC = 'hmac';
    private const AUTH_TOKEN_LENGTH = 32;
    private const EVENT_ACTION_BEFORE = 'event/ocm_webhook/before';
    private const EVENT_ACTION_AFTER = 'event/ocm_webhook/after';

    private $error = array();

    public function index()
    {
        $this->loadLogger();
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('setting/event');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $settings = $this->normalizeSettings($this->request->post);
            $this->writeLog('Saving module settings', array(
                'status' => isset($settings[self::CODE . '_status']) ? (int)$settings[self::CODE . '_status'] : 0,
                'log_status' => isset($settings[self::LOG_STATUS_KEY]) ? (int)$settings[self::LOG_STATUS_KEY] : 0,
                'rules_count' => isset($settings[self::CODE . '_rules']) ? count($settings[self::CODE . '_rules']) : 0
            ));

            $this->model_setting_setting->editSetting(self::CODE, $settings);
            $this->syncEvents($settings);
            $this->writeLog('Module settings saved', array(
                'rules_count' => isset($settings[self::CODE . '_rules']) ? count($settings[self::CODE . '_rules']) : 0
            ));

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true));
        }

        $data = array();
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_add_rule'] = $this->language->get('button_add_rule');
        $data['button_remove'] = $this->language->get('button_remove');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_log_status'] = $this->language->get('entry_log_status');
        $data['entry_rules'] = $this->language->get('entry_rules');
        $data['entry_event'] = $this->language->get('entry_event');
        $data['entry_url'] = $this->language->get('entry_url');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entry_auth'] = $this->language->get('entry_auth');
        $data['entry_auth_type'] = $this->language->get('entry_auth_type');
        $data['entry_auth_token'] = $this->language->get('entry_auth_token');
        $data['entry_auth_username'] = $this->language->get('entry_auth_username');
        $data['entry_auth_password'] = $this->language->get('entry_auth_password');
        $data['help_rules'] = $this->language->get('help_rules');
        $data['help_event'] = $this->language->get('help_event');
        $data['help_url'] = $this->language->get('help_url');
        $data['help_sort_order'] = $this->language->get('help_sort_order');
        $data['help_auth'] = $this->language->get('help_auth');
        $data['help_auth_token'] = $this->language->get('help_auth_token');
        $data['help_auth_basic'] = $this->language->get('help_auth_basic');
        $data['button_generate_token'] = $this->language->get('button_generate_token');
        $data['text_auth_none'] = $this->language->get('text_auth_none');
        $data['text_auth_query'] = $this->language->get('text_auth_query');
        $data['text_auth_bearer'] = $this->language->get('text_auth_bearer');
        $data['text_auth_header'] = $this->language->get('text_auth_header');
        $data['text_auth_basic'] = $this->language->get('text_auth_basic');
        $data['text_auth_hmac'] = $this->language->get('text_auth_hmac');

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true)
            )
        );

        $data['action'] = $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['module_code'] = self::CODE;

        $settings = $this->model_setting_setting->getSetting(self::CODE);
        $data[self::CODE . '_status'] = $this->getSettingValue($settings, self::CODE . '_status', 0);
        $data[self::LOG_STATUS_KEY] = $this->getSettingValue($settings, self::LOG_STATUS_KEY, 1);
        $data[self::CODE . '_rules'] = $this->prepareRulesForForm($settings);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::ROUTE, $data));
    }

    public function install()
    {
        $this->loadLogger();
        $this->load->model('setting/setting');
        $this->load->model('setting/event');

        $settings = array(
            self::CODE . '_status' => 1,
            self::LOG_STATUS_KEY => 1,
            self::CODE . '_rules' => array()
        );

        $this->model_setting_setting->editSetting(self::CODE, $settings);
        $this->syncEvents($settings);
        $this->writeLog('Module installed', array(
            'status' => 1
        ));
    }

    public function uninstall()
    {
        $this->loadLogger();
        $this->load->model('setting/setting');
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE '" . $this->db->escape(self::EVENT_PREFIX) . "%'");
        $this->model_setting_setting->deleteSetting(self::CODE);
        $this->writeLog('Module uninstalled');
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->error['warning'] = $this->language->get('error_permission');
            $this->loadLogger();
            $this->writeLog('Permission denied while saving module settings', array(
                'user_id' => isset($this->session->data['user_id']) ? $this->session->data['user_id'] : null
            ), 'error');
        }

        return !$this->error;
    }

    private function normalizeSettings(array $post)
    {
        $post[self::CODE . '_status'] = isset($post[self::CODE . '_status']) ? (int)$post[self::CODE . '_status'] : 0;
        $post[self::LOG_STATUS_KEY] = isset($post[self::LOG_STATUS_KEY]) ? (int)$post[self::LOG_STATUS_KEY] : 0;
        $post[self::CODE . '_rules'] = $this->normalizeRules(isset($post[self::CODE . '_rules']) ? $post[self::CODE . '_rules'] : array());

        return $post;
    }

    private function normalizeRules($rules)
    {
        if (!is_array($rules)) {
            $rules = json_decode((string)$rules, true);
        }

        if (!is_array($rules)) {
            $rules = array();
        }

        $normalized = array();

        foreach ($rules as $rule) {
            $code = isset($rule['code']) ? trim((string)$rule['code']) : '';
            $event = isset($rule['event']) ? trim((string)$rule['event']) : '';
            $url = isset($rule['url']) ? trim((string)$rule['url']) : '';
            $sort_order = isset($rule['sort_order']) ? (int)$rule['sort_order'] : self::DEFAULT_SORT_ORDER;
            $status = isset($rule['status']) ? (int)$rule['status'] : 1;
            $auth = $this->normalizeAuthRule($rule);

            if ($event === '' && $url === '') {
                continue;
            }

            if ($code === '') {
                $code = $this->generateCode($event, $url, count($normalized));
            }

            $normalized[] = array(
                'code' => $code,
                'event' => $event,
                'url' => $url,
                'sort_order' => $sort_order,
                'status' => $status,
                'auth_type' => $auth['auth_type'],
                'auth_token' => $auth['auth_token'],
                'auth_username' => $auth['auth_username'],
                'auth_password' => $auth['auth_password']
            );
        }

        if (!$normalized) {
            $normalized[] = array(
                'code' => $this->generateCode('default', '', 0),
                'event' => '',
                'url' => '',
                'sort_order' => self::DEFAULT_SORT_ORDER,
                'status' => 1,
                'auth_type' => self::AUTH_NONE,
                'auth_token' => '',
                'auth_username' => '',
                'auth_password' => ''
            );
        }

        return array_values($normalized);
    }

    private function prepareRulesForForm(array $settings)
    {
        $rules = isset($settings[self::CODE . '_rules']) ? $settings[self::CODE . '_rules'] : array();

        if (!is_array($rules)) {
            $rules = json_decode((string)$rules, true);
        }

        if (!is_array($rules) || !$rules) {
            return array(
                array(
                    'code' => $this->generateCode('default', '', 0),
            'event' => '',
            'url' => '',
            'sort_order' => self::DEFAULT_SORT_ORDER,
            'status' => 1,
            'auth_type' => self::AUTH_NONE,
            'auth_token' => '',
            'auth_username' => '',
            'auth_password' => ''
                )
            );
        }

        foreach ($rules as &$rule) {
            $rule['code'] = isset($rule['code']) ? $rule['code'] : $this->generateCode(isset($rule['event']) ? $rule['event'] : '', isset($rule['url']) ? $rule['url'] : '', 0);
            $rule['event'] = isset($rule['event']) ? $rule['event'] : '';
            $rule['url'] = isset($rule['url']) ? $rule['url'] : '';
            $rule['sort_order'] = isset($rule['sort_order']) ? (int)$rule['sort_order'] : self::DEFAULT_SORT_ORDER;
            $rule['status'] = isset($rule['status']) ? (int)$rule['status'] : 1;
            $rule['auth_type'] = isset($rule['auth_type']) && in_array($rule['auth_type'], $this->getAuthTypes(), true) ? $rule['auth_type'] : self::AUTH_NONE;
            $rule['auth_token'] = isset($rule['auth_token']) ? (string)$rule['auth_token'] : '';
            $rule['auth_username'] = isset($rule['auth_username']) ? (string)$rule['auth_username'] : '';
            $rule['auth_password'] = isset($rule['auth_password']) ? (string)$rule['auth_password'] : '';
        }
        unset($rule);

        return array_values($rules);
    }

    private function normalizeAuthRule(array $rule)
    {
        $auth_type = isset($rule['auth_type']) ? trim((string)$rule['auth_type']) : self::AUTH_NONE;
        if (!in_array($auth_type, $this->getAuthTypes(), true)) {
            $auth_type = self::AUTH_NONE;
        }

        $auth_token = isset($rule['auth_token']) ? trim((string)$rule['auth_token']) : '';
        $auth_username = isset($rule['auth_username']) ? trim((string)$rule['auth_username']) : '';
        $auth_password = isset($rule['auth_password']) ? (string)$rule['auth_password'] : '';

        if ($auth_type === self::AUTH_NONE) {
            $auth_token = '';
            $auth_username = '';
            $auth_password = '';
        } elseif ($auth_type === self::AUTH_BASIC) {
            $auth_token = '';
        } else {
            $auth_username = '';
            $auth_password = '';
        }

        if ($auth_type !== self::AUTH_NONE && $auth_type !== self::AUTH_BASIC && $auth_token === '') {
            $auth_token = $this->generateToken();
        }

        return array(
            'auth_type' => $auth_type,
            'auth_token' => $auth_token,
            'auth_username' => $auth_username,
            'auth_password' => $auth_password
        );
    }

    private function getAuthTypes()
    {
        return array(
            self::AUTH_NONE,
            self::AUTH_QUERY,
            self::AUTH_BEARER,
            self::AUTH_HEADER,
            self::AUTH_BASIC,
            self::AUTH_HMAC
        );
    }

    private function syncEvents(array $settings)
    {
        $this->loadLogger();
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE '" . $this->db->escape(self::EVENT_PREFIX) . "%'");
        $this->writeLog('Existing webhook events cleared');

        if (empty($settings[self::CODE . '_status'])) {
            $this->writeLog('Event sync skipped because module is disabled');
            return;
        }

        $rules = isset($settings[self::CODE . '_rules']) ? $settings[self::CODE . '_rules'] : array();

        foreach ($rules as $rule) {
            $event = isset($rule['event']) ? trim((string)$rule['event']) : '';
            $url = isset($rule['url']) ? trim((string)$rule['url']) : '';
            $code = isset($rule['code']) ? trim((string)$rule['code']) : '';
            $sort_order = isset($rule['sort_order']) ? (int)$rule['sort_order'] : self::DEFAULT_SORT_ORDER;
            $status = isset($rule['status']) ? (int)$rule['status'] : 1;

            if ($event === '' || $url === '') {
                $this->writeLog('Rule skipped during sync because event or URL is empty', array(
                    'code' => $code,
                    'event' => $event,
                    'url' => $url
                ), 'warning');
                continue;
            }

            if ($code === '') {
                $code = $this->generateCode($event, $url, $sort_order);
            }

            $action = $this->getActionRouteByEvent($event);

            $this->model_setting_event->addEvent($code, $event, $action, $status, $sort_order);
            $this->writeLog('Webhook event registered', array(
                'code' => $code,
                'event' => $event,
                'action' => $action,
                'status' => $status,
                'sort_order' => $sort_order
            ));
        }
    }

    private function getActionRouteByEvent($event)
    {
        $parsed = $this->parseEventStage($event);

        if ($parsed === 'after') {
            return self::EVENT_ACTION_AFTER;
        }

        return self::EVENT_ACTION_BEFORE;
    }

    private function parseEventStage($event)
    {
        $event = trim((string)$event);
        if ($event === '') {
            return null;
        }

        $parts = explode('/', $event);
        $stage = end($parts);

        if (in_array($stage, array('before', 'after'), true)) {
            return $stage;
        }

        return null;
    }

    private function generateCode($event, $url, $salt)
    {
        return self::EVENT_PREFIX . substr(sha1($event . '|' . $url . '|' . microtime(true) . '|' . $salt), 0, 24);
    }

    private function generateToken($length = self::AUTH_TOKEN_LENGTH)
    {
        $length = max(16, (int)$length);

        if (function_exists('random_bytes')) {
            return rtrim(strtr(base64_encode(random_bytes((int)ceil($length * 3 / 4))), '+/', '-_'), '=');
        }

        return substr(sha1(uniqid((string)mt_rand(), true) . microtime(true) . session_id()), 0, $length);
    }

    private function getSettingValue(array $settings, $key, $default = null)
    {
        if (!isset($settings[$key])) {
            return $default;
        }

        $value = $settings[$key];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    private function loadLogger()
    {
        if (!class_exists('OcmWebhook\\Logger')) {
            require_once DIR_SYSTEM . 'library/ocm_webhook/logger.php';
        }
    }

    private function writeLog($message, array $context = array(), $level = 'info')
    {
        $this->loadLogger();

        $logger = new \OcmWebhook\Logger($this->registry);

        if ($level === 'warning') {
            $logger->warning($message, $context);
            return;
        }

        if ($level === 'error') {
            $logger->error($message, $context);
            return;
        }

        $logger->info($message, $context);
    }
}
