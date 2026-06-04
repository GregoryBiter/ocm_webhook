<?php

class ControllerEventOcmWebhook extends Controller
{
    public function before(&$route, &$data = null)
    {
        try {
            if (!class_exists('OcmWebhook\\Dispatcher')) {
                require_once DIR_SYSTEM . 'library/ocm_webhook/dispatcher.php';
            }

            $dispatcher = new \OcmWebhook\Dispatcher($this->registry);
            $dispatcher->dispatchBefore($route, $data);
        } catch (\Throwable $e) {
            if (!class_exists('Log')) {
                require_once DIR_SYSTEM . 'library/log.php';
            }

            $log = new Log('ocm_webhook.log');
            $log->write('Admin event dispatcher error: ' . $e->getMessage());
        }
    }

    public function after(&$route, &$data = null, &$output = null)
    {
        try {
            if (!class_exists('OcmWebhook\\Dispatcher')) {
                require_once DIR_SYSTEM . 'library/ocm_webhook/dispatcher.php';
            }

            $dispatcher = new \OcmWebhook\Dispatcher($this->registry);
            $dispatcher->dispatchAfter($route, $data, $output);
        } catch (\Throwable $e) {
            if (!class_exists('Log')) {
                require_once DIR_SYSTEM . 'library/log.php';
            }

            $log = new Log('ocm_webhook.log');
            $log->write('Admin event dispatcher error: ' . $e->getMessage());
        }
    }
}
