<?php

$_['heading_title'] = 'OCM Webhook';

$_['text_extension'] = 'Extensions';
$_['text_home'] = 'Home';
$_['text_success'] = 'Webhook settings saved successfully!';
$_['text_edit'] = 'OCM Webhook Settings';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';

$_['entry_status'] = 'Module Status';
$_['entry_rules'] = 'Webhook Rules';
$_['entry_event'] = 'OpenCart Event';
$_['entry_url'] = 'Webhook URL';
$_['entry_sort_order'] = 'Execution Order';
$_['entry_auth'] = 'Authorization';
$_['entry_auth_type'] = 'Auth Method';
$_['entry_auth_token'] = 'Auth Token / Secret';
$_['entry_auth_username'] = 'Basic Auth Username';
$_['entry_auth_password'] = 'Basic Auth Password';

$_['help_rules'] = 'Add as many rules as you need. Each rule is registered as a separate OpenCart event. For the freshest data, use /after events and a high execution order, for example 1000.';
$_['help_event'] = 'OpenCart trigger, for example `catalog/model/checkout/order/addOrder/after`. `admin/`, `controller/`, `model/`, `view/`, `language/` and `config/` events are also supported.';
$_['help_url'] = 'Absolute URL for POST webhook requests. If the request fails, the error goes to `ocm_webhook.log` and OpenCart keeps running.';
$_['help_sort_order'] = 'Higher values run later. If you need the latest updated data, use 1000 or more.';
$_['help_auth'] = 'Optional. Leave this disabled if the target webhook does not require authorization.';
$_['help_auth_token'] = 'Used by Query Token, Bearer Token, Header Token, and HMAC Signature modes. You can generate a shared secret automatically.';
$_['help_auth_basic'] = 'Used only for Basic Auth. The username and password are sent in the Authorization header.';
$_['button_generate_token'] = 'Generate Token';
$_['text_auth_none'] = 'No auth';
$_['text_auth_query'] = 'Query token';
$_['text_auth_bearer'] = 'Bearer token';
$_['text_auth_header'] = 'Custom header token';
$_['text_auth_basic'] = 'Basic auth';
$_['text_auth_hmac'] = 'HMAC SHA256';

$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';
$_['button_add_rule'] = 'Add Rule';
$_['button_remove'] = 'Remove';

$_['error_permission'] = 'Warning: You do not have permission to modify this module!';
