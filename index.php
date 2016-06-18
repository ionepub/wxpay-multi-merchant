<?php
/*
* 微信支付demo
*/

$act = isset($_GET['act']) && $_GET['act'] != "" ? trim($_GET['act']) : "";

if(!$act){
	exit('failed');
}

$act_list = array('pay', 'wxpayCallback');

if(!in_array($act, $act_list)){
	exit('failed');
}

$siteurl =  'http://'.$_SERVER['HTTP_HOST'];

if($act == 'pay'){
	//微信扫码支付
	// 生成微信支付二维码
	
	$shopid = 4;
	@include_once('./conf/wxpay_shop_'. $shopid .'.config.php');
	if(!isset($wxpay_config['appid']) || !isset($wxpay_config['mchid']) || !isset($wxpay_config['key'])){
		//支付配置失败
		exit('failed');
	}
	
	//示例数据
	$ordergoods = array();
	$ordergoods[] = array(
		'goodsname' => '测试商品',
		'goodsid' => 10001,
		'goodsnum' => 1,
 	);
	$order_goods_count = 1;
	$order_goods_detail = $ordergoods[0]['goodsname']."等".$order_goods_count."件商品";
	
	$orderinfo = array(
		'orderno' => '2016061700001',
		'allcost' => 0.01,
	);
	
	$postdata = array(
		'body' => $ordergoods[0]['goodsname']."等".$order_goods_count."件商品", //商品描述
		'detail' => $order_goods_detail, //商品详情
		'attach' => 'shop_'.$shopid, //附加数据，原样返回
		'out_trade_no' => $orderinfo['orderno'], //订单编号
		'total_fee' => $orderinfo['allcost']*100,
		'product_id' => $ordergoods[0]['goodsid'], //商品id
		'shopid' => $shopid, //店铺id
	);
	
	$qrcode_url = $this->curl_get_contents($siteurl.'/wxpay-multi-merchant/native.php', $postdata);
	if(!$qrcode_url){
		exit('failed');
	}
}

if($act == 'wxpayCallback'){
	//微信支付完成
	if(!empty($_POST) && !empty($_POST['out_trade_no'])){
		$out_trade_no = trim($_POST['out_trade_no']);
		$total_fee = floatval($_POST['total_fee']);
		$shopid = trim($_POST['shopid']);
		$transaction_id = trim($_POST['transaction_id']);
		
		//订单支付完成操作
		
		echo 'success';
	}
	exit;
}
?>
<?php
if($act == 'pay' && $qrcode_url!=""){
?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" /> 
    <title>微信扫码支付</title>
</head>
<body>
	<p>支付金额：￥<?=$orderinfo['allcost']?></p>
	<div id="qrcode"></div>
	<script type="text/javascript" src="./js/qrcode.js"></script>
	<script type="text/javascript">
	new QRCode(document.getElementById("qrcode"), "<?=$qrcode_url?>");
	</script>
</body>
</html>
<?php
}
?>