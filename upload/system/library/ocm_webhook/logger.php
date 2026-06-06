<?php

namespace OcmWebhook;

class Logger
{
    private const FILE = 'ocm_webhook.log';
    private const MAX_STRING_LENGTH = 500;
    private const MAX_ITEMS = 25;

    private $log;
    private $config;

    public function __construct($registry = null)
    {
        if (!class_exists('Log')) {
            require_once DIR_SYSTEM . 'library/log.php';
        }

        $this->log = new \Log(self::FILE);
        $this->config = $registry && method_exists($registry, 'get') ? $registry->get('config') : null;
    }

    public function info($message, array $context = array())
    {
        if (!$this->isNormalLoggingEnabled()) {
            return;
        }

        $this->write('INFO', $message, $context);
    }

    public function warning($message, array $context = array())
    {
        if (!$this->isNormalLoggingEnabled()) {
            return;
        }

        $this->write('WARNING', $message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->write('ERROR', $message, $context);
    }

    public static function infoStatic($message, array $context = array(), $registry = null)
    {
        $logger = new self($registry);
        $logger->info($message, $context);
    }

    public static function warningStatic($message, array $context = array(), $registry = null)
    {
        $logger = new self($registry);
        $logger->warning($message, $context);
    }

    public static function errorStatic($message, array $context = array(), $registry = null)
    {
        $logger = new self($registry);
        $logger->error($message, $context);
    }

    private function write($level, $message, array $context = array())
    {
        $line = '[OCM Webhook][' . $level . '] ' . (string)$message;

        if ($context) {
            $encoded = json_encode($this->normalize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($encoded !== false) {
                $line .= ' | ' . $encoded;
            }
        }

        $this->log->write($line);
    }

    private function normalize($value, $depth = 0, $key = '')
    {
        if ($depth > 4) {
            return '[max_depth]';
        }

        if ($this->shouldMask($key)) {
            return '[masked]';
        }

        if (is_array($value)) {
            $normalized = array();
            $count = 0;

            foreach ($value as $itemKey => $itemValue) {
                if ($count++ >= self::MAX_ITEMS) {
                    $normalized['__truncated'] = true;
                    break;
                }

                $normalized[$itemKey] = $this->normalize($itemValue, $depth + 1, (string)$itemKey);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format(DATE_ATOM);
            }

            if (method_exists($value, '__toString')) {
                return $this->truncate((string)$value);
            }

            return array('__class' => get_class($value));
        }

        if (is_string($value)) {
            return $this->truncate($value);
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    private function shouldMask($key)
    {
        $key = strtolower($key);

        return strpos($key, 'token') !== false
            || strpos($key, 'password') !== false
            || strpos($key, 'secret') !== false
            || strpos($key, 'authorization') !== false
            || strpos($key, 'signature') !== false;
    }

    private function truncate($value)
    {
        if (strlen($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return substr($value, 0, self::MAX_STRING_LENGTH) . '...[truncated]';
    }

    private function isNormalLoggingEnabled()
    {
        if (!$this->config) {
            return true;
        }

        return (bool)$this->config->get('module_ocm_webhook_log_status');
    }
}
