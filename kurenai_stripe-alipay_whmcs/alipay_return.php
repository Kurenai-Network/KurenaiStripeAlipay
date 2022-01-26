<?php
use Stripe\Stripe;
use Stripe\Source;
use Stripe\Charge;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once(__DIR__ . '/vendor/autoload.php');
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
if (!isset($_GET['source'])) {
    die("错误的请求");
}

$gatewayParams = getGatewayVariables("KurenaiStripeAlipay");
$stripeSklive = $gatewayParams['StripeSkLive'];
$stripeWebhookKey = $gatewayParams['StripeWebhookKey'];
Stripe::setApiKey($stripeSklive);


$source = Source::retrieve($_GET['source']);
if ($source['status'] == 'chargeable' && $source['type'] == 'alipay') {
    try {
        $params = getGatewayVariables('KurenaiStripeAlipay');
        $count = $source['metadata']['original_amount'];
        if ($count > 0) {
            header('Refresh: 3; url=/viewinvoice.php?' . http_build_query([
                    'id' => $source['metadata']['invoice_id'],
                    'pay' => true
                ]));

            exit();
        }
    } catch (Exception $e) {
        echo "Error return".$e;
    }
}
header('Location: /viewinvoice.php?' . http_build_query([
        'id' => $source['metadata']['invoice_id']
    ]));
exit();