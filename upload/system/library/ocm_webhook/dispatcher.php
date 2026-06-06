<?php

namespace OcmWebhook;

class Dispatcher
{
    private $registry;
    private $logger;
    private $settings;
    private static $queue = array();
    private static $shutdownRegistered = false;
    private static $runtimeRegistry = null;

    public function __construct($registry)
    {
        $this->registry = $registry;
        self::$runtimeRegistry = $registry;
        $this->logger = new Logger($registry);
    }

    public function dispatchBefore($route, $data)
    {
        $this->dispatch('before', $route, $data, null);
    }

    public function dispatchAfter($route, $data, $output)
    {
        $this->dispatch('after', $route, $data, $output);
    }

    private function getRules()
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $config = $this->registry->get('config');
        $rules = $config ? $config->get('module_ocm_webhook_rules') : array();

        if (!is_array($rules)) {
            $rules = json_decode((string)$rules, true);
        }

        $this->settings = array();

        if (!$rules) {
            return $this->settings;
        }

        foreach ($rules as $rule) {
            $event = isset($rule['event']) ? trim((string)$rule['event']) : '';
            $url = isset($rule['url']) ? trim((string)$rule['url']) : '';
            $enabled = isset($rule['status']) ? (int)$rule['status'] : 1;
            $sort_order = isset($rule['sort_order']) ? (int)$rule['sort_order'] : 1000;

            if ($event === '' || $url === '') {
                continue;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->writeLog('Skipped invalid webhook URL', array(
                    'event' => $event,
                    'url' => $url
                ));
                continue;
            }

            $this->settings[] = array(
                'code' => isset($rule['code']) ? (string)$rule['code'] : '',
                'event' => $event,
                'url' => $url,
                'status' => $enabled,
                'sort_order' => $sort_order,
                'auth_type' => isset($rule['auth_type']) ? (string)$rule['auth_type'] : 'none',
                'auth_token' => isset($rule['auth_token']) ? (string)$rule['auth_token'] : '',
                'auth_username' => isset($rule['auth_username']) ? (string)$rule['auth_username'] : '',
                'auth_password' => isset($rule['auth_password']) ? (string)$rule['auth_password'] : ''
            );
        }

        return $this->settings;
    }

    private function isRuleEnabled(array $rule)
    {
        $config = $this->registry->get('config');

        if (!$config || !$config->get('module_ocm_webhook_status')) {
            return false;
        }

        return !empty($rule['status']);
    }

    private function dispatch($stage, $route, $data, $output)
    {
        try {
            $rules = $this->getRules();
            if (!$rules) {
                return;
            }

            $matched = false;
            foreach ($rules as $rule) {
                if (!$this->isRuleEnabled($rule)) {
                    continue;
                }

                if (!$this->matchesRule($rule['event'], $stage, $route)) {
                    continue;
                }

                $matched = true;
                $payload = $this->buildPayload($rule['event'], $stage, $route, $data, $output, $rule);
                $queueItem = array(
                    'url' => $rule['url'],
                    'payload' => $payload,
                    'auth' => $this->buildAuthConfig($rule)
                );
                self::$queue[] = $queueItem;

                $this->writeLog('Queued webhook event', array(
                    'event' => $rule['event'],
                    'route' => $route,
                    'stage' => $stage,
                    'url' => $rule['url'],
                    'queue_size' => count(self::$queue)
                ));
            }

            if ($matched) {
                $this->registerShutdown();
            }
        } catch (\Throwable $e) {
            $this->writeLog('Dispatcher error', array(
                'message' => $e->getMessage(),
                'route' => $route,
                'stage' => $stage
            ), 'error');
        }
    }

    private function matchesRule($event, $stage, $route)
    {
        $parsed = $this->parseEvent($event);

        if (!$parsed) {
            return false;
        }

        $family = $parsed['family'];
        $ruleRoute = $parsed['route'];
        $ruleStage = $parsed['stage'];

        if ($stage !== $ruleStage) {
            return false;
        }

        return $this->routeMatches($family, $ruleRoute, $route);
    }

    private function routeMatches($family, $pattern, $route)
    {
        if ($pattern === '') {
            return false;
        }

        if (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false) {
            return (bool)preg_match('/^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, '/')) . '$/', $route);
        }

        if ($family === 'view') {
            return strpos($route, $pattern) !== false;
        }

        return $route === $pattern;
    }

    private function parseEvent($event)
    {
        $event = trim((string)$event);
        if ($event === '') {
            return null;
        }

        $prefix = '';
        if (strpos($event, 'catalog/') === 0) {
            $prefix = 'catalog/';
        } elseif (strpos($event, 'admin/') === 0) {
            $prefix = 'admin/';
        }

        if ($prefix === '') {
            return null;
        }

        $body = substr($event, strlen($prefix));
        $parts = explode('/', $body);
        if (count($parts) < 2) {
            return null;
        }

        $family = array_shift($parts);
        $stage = null;
        if ($parts && in_array(end($parts), array('before', 'after'), true)) {
            $stage = array_pop($parts);
        }

        return array(
            'family' => $family,
            'route' => implode('/', $parts),
            'stage' => $stage
        );
    }

    private function buildPayload($event, $stage, $route, $data, $output, array $rule)
    {
        $config = $this->registry->get('config');

        return array(
            'event' => $event,
            'stage' => $stage,
            'route' => $route,
            'sort_order' => (int)$rule['sort_order'],
            'timestamp' => date('c'),
            'store' => array(
                'id' => $config ? (int)$config->get('config_store_id') : 0,
                'name' => $config ? (string)$config->get('config_name') : '',
                'url' => $config ? (string)$config->get('config_url') : ''
            ),
            'request' => array(
                'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
                'uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
            ),
            'data' => $this->normalizeValue($data),
            'output' => $this->normalizeValue($output)
        );
    }

    private function normalizeValue($value, $depth = 0)
    {
        if ($depth > 5) {
            return '[max_depth]';
        }

        if (is_array($value)) {
            $normalized = array();
            $count = 0;

            foreach ($value as $key => $item) {
                if ($count++ >= 50) {
                    $normalized['__truncated'] = true;
                    break;
                }

                $normalized[$key] = $this->normalizeValue($item, $depth + 1);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format(DATE_ATOM);
            }

            if (method_exists($value, '__toString')) {
                return (string)$value;
            }

            return array('__class' => get_class($value));
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    private function registerShutdown()
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function(array(__CLASS__, 'flushQueue'));
    }

    public static function flushQueue()
    {
        if (!self::$queue) {
            return;
        }

        self::writeStaticLog('Flushing webhook queue', array(
            'items' => count(self::$queue)
        ));

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        ignore_user_abort(true);
        @set_time_limit(0);

        while (self::$queue) {
            $item = array_shift(self::$queue);

            try {
                self::sendWebhook(
                    isset($item['url']) ? $item['url'] : '',
                    isset($item['payload']) ? $item['payload'] : array(),
                    isset($item['auth']) ? $item['auth'] : array()
                );
            } catch (\Throwable $e) {
                self::writeStaticLog('Webhook send error', array(
                    'message' => $e->getMessage(),
                    'url' => isset($item['url']) ? $item['url'] : ''
                ), 'error');
            }
        }
    }

    private static function sendWebhook($url, array $payload, array $auth = array())
    {
        $url = self::applyAuthToUrl($url, $auth);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            self::writeStaticLog('Failed to encode webhook payload', array(
                'url' => $url,
                'event' => isset($payload['event']) ? $payload['event'] : ''
            ), 'error');
            return false;
        }

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json'
        );
        $method = isset($auth['type']) ? (string)$auth['type'] : 'none';

        if ($method === 'bearer' && !empty($auth['token'])) {
            $headers[] = 'Authorization: Bearer ' . $auth['token'];
        } elseif ($method === 'header' && !empty($auth['token'])) {
            $headers[] = 'X-Webhook-Token: ' . $auth['token'];
        } elseif ($method === 'hmac' && !empty($auth['token'])) {
            $timestamp = (string)time();
            $signature = hash_hmac('sha256', $timestamp . '.' . $body, $auth['token']);
            $headers[] = 'X-Webhook-Timestamp: ' . $timestamp;
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        } elseif ($method === 'basic' && (!empty($auth['username']) || !empty($auth['password']))) {
            $headers[] = 'Authorization: Basic ' . base64_encode($auth['username'] . ':' . $auth['password']);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ));

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $errno || $http_code >= 400) {
                self::writeStaticLog('Webhook request failed', array(
                    'url' => $url,
                    'event' => isset($payload['event']) ? $payload['event'] : '',
                    'http_code' => (int)$http_code,
                    'error' => $error ?: 'no curl error'
                ), 'error');
                return false;
            }

            self::writeStaticLog('Webhook request completed', array(
                'url' => $url,
                'event' => isset($payload['event']) ? $payload['event'] : '',
                'http_code' => (int)$http_code
            ));
            return true;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 2,
                'ignore_errors' => true
            )
        ));

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            self::writeStaticLog('Webhook request failed using stream fallback', array(
                'url' => $url,
                'event' => isset($payload['event']) ? $payload['event'] : ''
            ), 'error');
            return false;
        }

        self::writeStaticLog('Webhook request completed using stream fallback', array(
            'url' => $url,
            'event' => isset($payload['event']) ? $payload['event'] : ''
        ));
        return true;
    }

    private function buildAuthConfig(array $rule)
    {
        $type = isset($rule['auth_type']) ? trim((string)$rule['auth_type']) : 'none';
        $token = isset($rule['auth_token']) ? trim((string)$rule['auth_token']) : '';
        $username = isset($rule['auth_username']) ? (string)$rule['auth_username'] : '';
        $password = isset($rule['auth_password']) ? (string)$rule['auth_password'] : '';

        if (!in_array($type, array('none', 'query', 'bearer', 'header', 'basic', 'hmac'), true)) {
            $type = 'none';
        }

        return array(
            'type' => $type,
            'token' => $token,
            'username' => $username,
            'password' => $password
        );
    }

    private static function applyAuthToUrl($url, array $auth)
    {
        if (!isset($auth['type']) || $auth['type'] !== 'query' || empty($auth['token'])) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['token'] = $auth['token'];
        $parts['query'] = http_build_query($query);

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user_info = '';

        if (!empty($parts['user'])) {
            $user_info = $parts['user'];
            if (isset($parts['pass']) && $parts['pass'] !== '') {
                $user_info .= ':' . $parts['pass'];
            }
            $user_info .= '@';
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        $query_string = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $user_info . $host . $port . $path . $query_string . $fragment;
    }

    private function writeLog($message, array $context = array(), $level = 'info')
    {
        if ($level === 'warning') {
            $this->logger->warning($message, $context);
            return;
        }

        if ($level === 'error') {
            $this->logger->error($message, $context);
            return;
        }

        $this->logger->info($message, $context);
    }

    private static function writeStaticLog($message, array $context = array(), $level = 'info')
    {
        if ($level === 'warning') {
            Logger::warningStatic($message, $context, self::$runtimeRegistry);
            return;
        }

        if ($level === 'error') {
            Logger::errorStatic($message, $context, self::$runtimeRegistry);
            return;
        }

        Logger::infoStatic($message, $context, self::$runtimeRegistry);
    }
}
