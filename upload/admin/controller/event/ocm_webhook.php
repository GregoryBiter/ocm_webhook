<?php

class ControllerEventOcmWebhook extends Controller
{
    public function before(&$route, &$data = null)
    {
        try {
            if (!class_exists('OcmWebhook\\Logger')) {
                require_once DIR_SYSTEM . 'library/ocm_webhook/logger.php';
            }

            if (!class_exists('OcmWebhook\\Dispatcher')) {
                require_once DIR_SYSTEM . 'library/ocm_webhook/logger.php';
                require_once DIR_SYSTEM . 'library/ocm_webhook/dispatcher.php';
            }

            $dispatcher = new \OcmWebhook\Dispatcher($this->registry);
            $dispatcher->dispatchBefore($route, $data);
        } catch (\Throwable $e) {
            \OcmWebhook\Logger::errorStatic('Admin event dispatcher error', array(
                'route' => $route,
                'stage' => 'before',
                'message' => $e->getMessage()
            ), $this->registry);
        }
    }

    public function after(&$route, &$data = null, &$output = null)
    {
        try {
            if (!class_exists('OcmWebhook\\Logger')) {
                require_once DIR_SYSTEM . 'library/ocm_webhook/logger.php';
            }

            if (!class_exists('OcmWebhook\\Dispatcher')) {
                require_once DIR_SYSTEM . 'library/ocm_webhook/logger.php';
                require_once DIR_SYSTEM . 'library/ocm_webhook/dispatcher.php';
            }

            $dispatcher = new \OcmWebhook\Dispatcher($this->registry);
            $dispatcher->dispatchAfter($route, $data, $output);
        } catch (\Throwable $e) {
            \OcmWebhook\Logger::errorStatic('Admin event dispatcher error', array(
                'route' => $route,
                'stage' => 'after',
                'message' => $e->getMessage()
            ), $this->registry);
        }
    }
}
