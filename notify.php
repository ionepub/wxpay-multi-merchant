<?php
ini_set('date.timezone','Asia/Shanghai');
// error_reporting(E_ERROR);

require_once "./lib/WxPay.Api.php";
require_once './lib/WxPay.Notify.php';
require_once 'log.php';

//初始化日志
$logHandler= new CLogFileHandler("./logs/".date('Y-m-d').'.log');
$log = Log::Init($logHandler, 15);

class PayNotifyCallBack extends WxPayNotify
{
	//查询订单
	public function Queryorder($transaction_id)
	{
		$input = new WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
		$result = WxPayApi::orderQuery($input);
		Log::DEBUG("query:" . json_encode($result));
		if(array_key_exists("return_code", $result)
			&& array_key_exists("result_code", $result)
			&& $result["return_code"] == "SUCCESS"
			&& $result["result_code"] == "SUCCESS")
		{
			return true;
		}
		return false;
	}
	
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
}

Log::DEBUG("begin notify");
$notify = new PayNotifyCallBack();
$notify->Handle(false);
