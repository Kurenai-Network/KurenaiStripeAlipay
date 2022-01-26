<?php
use Stripe\Stripe;
use Stripe\Source;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once(__DIR__ . '/kurenai_stripe-alipay_whmcs/vendor/autoload.php');

function KurenaiStripeAlipay_config() {
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Kurenai Stripe Alipay',
        ),
        'StripeSkLive' => array(
            'FriendlyName' => 'SK_LIVE',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从Stripe获取到的秘密密钥（SK_LIVE）',
        ),
        'StripeWebhookKey' => array(
            'FriendlyName' => 'Stripe Webhook密钥',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从Stripe获取到的Webhook密钥签名',
        ),
        'StripeCurrency' => array(
            'FriendlyName' => '发起交易货币',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '默认获取WHMCS的货币，与您设置的发起交易货币进行汇率转换，再使用转换后的价格和货币向Stripe请求',
        )
    );
}

function KurenaiStripeAlipay_link($params){
    $exchange = exchange($params['currency'], strtoupper($params['StripeCurrency']));
    if (!$exchange) {
        return '<div class="alert alert-danger text-center" role="alert">支付网关错误，请联系客服进行处理</div>';
    }
    $stripeSKlive = $params['StripeSkLive'];
    $stripeWebhookKey = $params['StripeWebhookKey'];
    Stripe::setApiKey($stripeSKlive);
    try {
        $source = Source::create([
            'amount' => floor($params['amount'] * $exchange * 100.00),
            'currency' => $params['StripeCurrency'],
            'type' => 'alipay',
            'statement_descriptor' => "invoiceID: ".$params['invoiceid'],
            'metadata' => [
                'invoice_id' => $params['invoiceid'],
                'original_amount' => $params['amount']
            ],
            'redirect' => [
                'return_url' => $params['systemurl'] . 'modules/gateways/kurenai_stripe-alipay_whmcs/alipay_return.php'
            ]
        ]);

    } catch (Exception $e){
        return '<div class="alert alert-danger text-center" role="alert">支付网关错误，请联系客服进行处理</div>';
    }
    if ($source->redirect->status == 'pending') {
        $url = explode("?",$source['redirect']['url'])[0];
        $secret = explode("=",explode("?",$source['redirect']['url'])[1])[1];

        return '<form action="'.$url.'" method="get"><input type="hidden" name="client_secret" value="'.$secret.'"><input type="submit" class="btn btn-primary" value="'.$params['langpaynow'].'" /></form>';
    }
    return '<div class="alert alert-danger text-center" role="alert">发生错误，请创建工单联系客服处理</div>';
}

function exchange($from, $to)
{
    try {
        $result = file_get_contents('https://api.exchangerate.host/latest?symbols=' . $to . '&base=' . $from);
        $result = json_decode($result, true);
        return $result['rates'][$to];
    } catch (Exception $e){
        echo "Exchange error: ".$e;
        return "Exchange error: ".$e;
    }

}