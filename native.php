<?php
ini_set('date.timezone','Asia/Shanghai');
// error_reporting(E_ERROR);
@session_start();

if(empty($_POST) || empty($_POST['total_fee'])){
	exit;
}

$_SESSION['wx_shopid'] = $_POST['shopid'];

require_once "./lib/WxPay.Api.php";
require_once "./WxPay.NativePay.php";

$siteurl =  'http://'.$_SERVER['HTTP_HOST'];

$input = new WxPayUnifiedOrder();
$input->SetBody($_POST['body']);
$input->SetDetail($_POST['detail']);
$input->SetAttach($_POST['attach']);
$input->SetOut_trade_no(WxPayConfig::GetMch_id().$_POST['out_trade_no']);
$input->SetTotal_fee($_POST['total_fee']);
$input->SetTime_start(date("YmdHis"));
$input->SetTime_expire(date("YmdHis", time() + 600)); //订单失效时间, 最短失效时间间隔必须大于5分钟
$input->SetNotify_url($siteurl."/wxpay-multi-merchant/notify.php");
$input->SetTrade_type("NATIVE");
$input->SetProduct_id($_POST['product_id']);
$notify = new NativePay();
$result = $notify->GetPayUrl($input);
$url = isset($result["code_url"]) ? $result["code_url"] : '';
echo $url;
exit;
?>