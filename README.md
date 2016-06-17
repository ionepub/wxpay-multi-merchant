# 微信扫码支付多商户版

    多商户版指的是一个多商户系统中，不使用统一的平台的微信支付帐号，而是每个商户自己有一个微信支付帐号，收帐退款等都在商户自己的微信帐号，不经过平台。

使用平台统一帐号的请移步 [https://github.com/ionepub/wxpay-sample](https://github.com/ionepub/wxpay-sample)

跟单商户扫码支付的主要区别在于：

    不固定设置微信支付的参数，如appid/mchid等

我的做法是使用文件保存（或数据库）商户的支付参数，将商户shopid以attach的形式发送给微信，支付完成之后微信将shopid原样返回，此时再通过配置文件获取支付参数，完成支付

//todo
