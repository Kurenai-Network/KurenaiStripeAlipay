<?php
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Charge;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once(__DIR__ . '/vendor/autoload.php');

$gatewayParams = getGatewayVariables("KurenaiStripeAlipay");
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
if (!isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
    die("错误请求");
}
$stripeSklive = $gatewayParams['StripeSkLive'];
$stripeWebhookKey = $gatewayParams['StripeWebhookKey'];
Stripe::setApiKey($stripeSklive);
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = Webhook::constructEvent(
        $payload, $sig_header, $stripeWebhookKey
    );
} catch(\UnexpectedValueException $e) {
    logTransaction($params['paymentmethod'], $e, 'PayGateway-KurenaiStripeAlipay: Invalid payload');
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    logTransaction($params['paymentmethod'], $e, 'PayGateway-KurenaiStripeAlipay: Invalid signature');
    http_response_code(400);
    exit();
}
try {
    switch ($event->type) {
        case 'charge.succeeded':
            $charge = $event->data->object;
            //获取Stripe订单号(唯一，切记保持一致)
            $trade_no = $charge['source']['id'];
            //获取添加到支付信息中的whmcs订单号
            $invoice_id = $charge['source']['metadata']['invoice_id'];
            $paymentFee = 0;
            if ($charge['paid'] == "true"){
                $amount = $charge['metadata']['original_amount'];
                if ($charge->status === 'succeeded'){
                    checkCbInvoiceID($invoice_id, $params['name']);
                    logTransaction($params['paymentmethod'], $source, 'success(charge)-callback');
                    checkCbTransID($trade_no);
                    echo "Pass the checkCbTransID check\n";
                    addInvoicePayment(
                        $invoice_id,
                        $trade_no,
                        $amount,
                        $paymentFee,
                        $params['paymentmethod']
                    );
                    echo "Success to addInvoicePayment\n";
                }
            }
            break;

        case 'source.chargeable':
            $source = $event->data->object;
            //生成一个Charge给stripe，然后会丢要给charge object给你验证，就是charge.succeeded
            $clientEmail = $params['clientdetails']['email'];
            Charge::create([
                'amount' => $source['amount'],
                'currency' => $source['currency'],
                'source' => $source['id'],
                'description' => "Invoice#" . $source['metadata']['invoice_id']. $clientEmail
            ]);
            break;
    }
} catch (Exception $e) {
    logTransaction($params['paymentmethod'], $e, 'error-callback');
    http_response_code(400);
    echo $e;
}
