# 微信扫码支付多商户版

    多商户版指的是一个多商户系统中，不使用统一的平台的微信支付帐号，而是每个商户自己有一个微信支付帐号，收帐退款等都在商户自己的微信帐号，不经过平台。

使用平台统一帐号的请移步 [https://github.com/ionepub/wxpay-sample](https://github.com/ionepub/wxpay-sample)

跟单商户扫码支付的主要区别在于：

    不固定设置微信支付的参数，如appid/mchid等

我的做法是使用文件保存（或数据库）商户的支付参数，将商户shopid以attach的形式发送给微信，支付完成之后微信将shopid原样返回，此时再通过配置文件获取支付参数，完成支付

多商户版的扫码支付依然使用的是预支付方式：

    1. 调用统一下单接口，获取到微信支付的url（如：weixin://wxpay/bizpayurl?pr=XNY7eDw），使用第三方工具生成二维码显示在页面上
    2. 用户扫描二维码，进行支付
    3. 支付完成之后，微信服务器会通知支付成功
    4. 在支付成功通知中通过查单确认是否真正支付成功

## 使用说明

1. 在conf文件夹中添加自定义的账户配置文件 wxpay_shop_XX.config.php，其中XX为商城系统中的商户ID
2. 在配置文件中添加一个数组，存储微信支付配置信息
```php
$wxpay_config = array(
	'appid' => 'wx426b3015555a46be',                    //微信支付appid
	'mchid' => '1225312702',                            //微信支付商户id
	'key' => 'e10adc3949ba59abbe56e057f20f883e',        //微信支付key
);
```
3. 多个商户则添加多个配置文件即可
4. 在index.php文件中实例了发起支付，获得二维码，及异步回调通知的处理
5. 流程：index.php发送post数据到native.php中，由native.php生成二维码链接，返回给index.php，index.php接收到二维码链接之后，用二维码生成工具生成二维码供前台显示；用户扫描二维码后，微信发送异步通知到notify.php。notify.php查询订单无误之后发送post数据到index.php，index.php接收到数据之后进行商城订单支付处理


## 修改说明
针对微信支付源文件进行了以下改动：

1. 修改了lib/Wxpai.config.php文件，使用session和载入配置文件方式替换类中常量
```php
/**
* 	配置账号信息
*/
@session_start();
class WxPayConfig
{
	// const APPID = 'wx426b3015555a46be';
	// const MCHID = '1225312702';
	// const KEY = 'e10adc3949ba59abbe56e057f20f883e';
	// const APPSECRET = '01c6d59a3f9024db6336662ac95c8e74';
	
	// 获取shopid,发起支付请求时会用到
	static function GetShop_id(){
		return isset($_SESSION['wx_shopid']) ? $_SESSION['wx_shopid'] : 0;
	}
	// 设置shopid,发起支付请求时会用到
	static function SetShop_id($shopid){
		$_SESSION['wx_shopid'] = intval($shopid);
	}
	// 从配置文件获取appid
	static function GetAppid(){
		include dirname(__FILE__).'/../conf/wxpay_shop_'.self::GetShop_id().'.config.php';
		return isset($wxpay_config['appid']) ? $wxpay_config['appid'] : '';
	}
	// 从配置文件获取商户id
	static function GetMch_id(){
		include dirname(__FILE__).'/../conf/wxpay_shop_'.self::GetShop_id().'.config.php';
		return isset($wxpay_config['mchid']) ? $wxpay_config['mchid'] : '';
	}
	// 从配置文件获取支付key
	static function GetKey(){
		include dirname(__FILE__).'/../conf/wxpay_shop_'.self::GetShop_id().'.config.php';
		return isset($wxpay_config['key']) ? $wxpay_config['key'] : '';
	}
}
```

2. 修改调用了Wxpay.config.php文件的文件，设置为新的获取方式（批量替换）
```php
#lib/WxPay.Api.php
        // eg:
		$inputObj->SetAppid(WxPayConfig::GetAppid());//公众账号ID
		$inputObj->SetMch_id(WxPayConfig::GetMch_id());//商户号
```

3. 修改lib/WxPay.Data.php文件，在第210行处添加获取店铺id的代码
```php
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public static function Init($xml)
	{	
		$obj = new self();
		$obj->FromXml($xml);
		
		#####添加以下代码
		//获取到店铺id
		if(isset($obj->values['attach']) && !empty($obj->values['attach'])){
			$attach_temp = explode("_", $obj->values['attach']);
			$attach_temp = isset($attach_temp[1]) ? $attach_temp[1] : '';
			$shopid = intval($attach_temp);
			$_SESSION['wx_shopid'] = $shopid;
			WxPayConfig::SetShop_id($shopid);
		}
		#####添加完毕
		
		//fix bug 2015-06-29
		if($obj->values['return_code'] != 'SUCCESS'){
			 return $obj->GetValues();
		}
		$obj->CheckSign();
        return $obj->GetValues();
	}

```

4. 修改native.php，将需要设置的参数以post形式获取
5. 修改notify.php，添加回调处理代码
```php
    //重写回调处理函数
	public function NotifyProcess($data, &$msg)
	{
		Log::DEBUG("call back:" . json_encode($data));
		$notfiyOutput = array();
		
		if(!array_key_exists("transaction_id", $data)){
			$msg = "输入参数不正确";
			return false;
		}
		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"])){
			$msg = "订单查询失败";
			return false;
		}
		//操作订单
		//Log::DEBUG("porcess order start");
		$postdata = array();
		$out_trade_no = $data['out_trade_no'];
		$mch_id = $data['mch_id'];
		$postdata['out_trade_no'] = str_replace($mch_id, "", $out_trade_no); //订单号
		$postdata['total_fee'] = $data['total_fee'];
		// attach: shop_128
		$data['attach'] = explode("_", $data['attach']);
		$data['attach'] = isset($data['attach'][1]) ? $data['attach'][1] : '';
		$postdata['shopid'] = intval($data['attach']);
		$postdata['transaction_id'] = $data['transaction_id'];
		
		//Log::DEBUG("postdata:".json_encode($postdata));
		$siteurl =  'http://'.$_SERVER['HTTP_HOST'];
		$response = $this->curl_get_contents($siteurl.'/index.php?act=wxpayCallback', $postdata);
		return true;
	}

	/**
	 * curl获取信息
	 */
	private function curl_get_contents($url, $postdata) 
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		$r = curl_exec($ch);
		curl_close($ch);
		return $r;
	}
```


