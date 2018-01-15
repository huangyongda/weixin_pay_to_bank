<?php
/**
 * Created by PhpStorm.
 * User: huangyd
 * Date: 18-1-13
 * Time: 下午2:43
 */
class WeixinPayBank{

    private $WEIXIN_MCHID;
    private $WEIXIN_KEY;
    private $WEIXIN_SSLCERT_PATH;
    private $WEIXIN_SSLKEY_PATH;
    private $WEIXIN_rootca_PATH;

    public function __construct()
    {
        $config=array(
            "WEIXIN_APPID" => "wx44d6b4*******",
            //受理商ID，身份标识
            "WEIXIN_MCHID" => "12732*******",
            //商户支付密钥Key。审核通过后，在微信发送的邮件中查看
            "WEIXIN_KEY" => "54ce05cbfa108ecc08*******",

            //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
            "WEIXIN_APPSECRET" => "952774e8daa6439b*******",

            //=======【证书路径设置】=====================================
            //证书路径,注意应该填写绝对路径
            "WEIXIN_SSLCERT_PATH" => "/home/www/*******/apiclient_cert.pem",
            "WEIXIN_SSLKEY_PATH" => "/home/www/*******/apiclient_key.pem",
            "WEIXIN_rootca_PATH" => "/home/www/*******/rootca.pem",

        );
        $this->WEIXIN_MCHID=$config["WEIXIN_MCHID"];
        $this->WEIXIN_KEY=$config["WEIXIN_KEY"];
        $this->WEIXIN_SSLCERT_PATH=$config["WEIXIN_SSLCERT_PATH"];
        $this->WEIXIN_SSLKEY_PATH=$config["WEIXIN_SSLKEY_PATH"];
        $this->WEIXIN_rootca_PATH=$config["WEIXIN_rootca_PATH"];
    }

    /**
     * 	作用：格式化参数，签名过程需要使用
     */
    private function formatBizQueryParaMap2($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar="";
        if (strlen($buff) > 0)
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /**
     * 	作用：生成签名
     */
    private function getSign2($Obj)
    {
        foreach ($Obj as $k => $v)
        {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap2($Parameters, false);
        //echo "【string】 =".$String."</br>";
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".$this->WEIXIN_KEY;
        //echo "【string】 =".$String."</br>";
        //签名步骤三：MD5加密
//        dump($String);
        $result_ = strtoupper(md5($String));
        return $result_;
    }

    /**
     * 	作用：array转xml
     */
    private function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val))
            {
                $xml.="<".$key.">".$val."</".$key.">";

            }
            else
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
        }
        $xml.="</xml>";
        return $xml;
    }


    private function curl_post_ssl($url, $vars, $second=30,$aHeader=array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);

        curl_setopt($ch,CURLOPT_SSLCERT,$this->WEIXIN_SSLCERT_PATH);
        curl_setopt($ch,CURLOPT_SSLKEY,$this->WEIXIN_SSLKEY_PATH);
        curl_setopt($ch,CURLOPT_CAINFO,$this->WEIXIN_rootca_PATH);

        //以下两种方式需选择一种

        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        //curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/cert.pem');
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        //curl_setopt($ch,CURLOPT_SSLKEY,getcwd().'/private.pem');

        //第二种方式，两个文件合成一个.pem文件
//      curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');

        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }



    //获取rsa加密公钥   返回PKCS#1  公钥
    public function getRsaApi()
    {
        $path=$this->getRasApiPath();
        $pub_key=file_get_contents($path);
        return $pub_key;

    }

    public function getRasApiPath()
    {
        $path="pub_key_pkcs1.pem";
        if(file_exists($path) && file_get_contents($path)){
            return $path;
        }
        $params = array(
            'mch_id'    => $this->WEIXIN_MCHID,
            'nonce_str' => strtoupper(md5(time())),
            'sign_type' => 'MD5'
        );
        $signature = $this->getSign2($params); //生成sign
        $params["sign"]=$signature;
        $xml = $this->arrayToXml($params); //创建xml，
        $response = $this->curl_post_ssl("https://fraud.mch.weixin.qq.com/risk/getpublickey",$xml);  //提交获取rsa的请求
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($xml->return_code == 'SUCCESS')
        {
            // w表示以写入的方式打开文件，如果文件不存在，系统会自动建立
            $file_pointer = fopen($path,"w+");
            fwrite($file_pointer,$xml->pub_key);
            fclose($file_pointer);
            return $path;
        }
        return false;
    }

    public function getRsaApiPkcs8(){
        $path="pub_key_pkcs8.pem";
        if(file_exists($path) && file_get_contents($path)){
            //如果文件存在且内容有则不用再执行
        }
        else{
            system("openssl rsa -RSAPublicKey_in -in ".$this->getRasApiPath()." -pubout >".$path);
        }
        $pub_key=file_get_contents($path);
        return $pub_key;
    }
    //rsa加密响应字段
    public function rsaEncrypt($str)
    {
//        echo $this->getrsaapi();exit;
//        $pu_key = openssl_pkey_get_public("/home/huangyd/public_html/gitfour/pub.pem");  //读取公钥内容
//        dump($pu_key);exit;
        $pu_key = openssl_pkey_get_public($this->getRsaApiPkcs8());  //读取公钥内容

        $encryptedBlock = '';
        $encrypted = '';
// 用标准的RSA加密库对敏感信息进行加密，选择RSA_PKCS1_OAEP_PADDING填充模式
        //   （eg：Java的填充方式要选 " RSA/ECB/OAEPWITHSHA-1ANDMGF1PADDING"）
// 得到进行rsa加密并转base64之后的密文
        $info=openssl_public_encrypt($str,$encryptedBlock,$pu_key,OPENSSL_PKCS1_OAEP_PADDING);


        $str_base64  = base64_encode($encrypted.$encryptedBlock);


        return $str_base64;
    }


    /**
     * 获取银行卡代码
     * @param string $name
     * @return mixed
     */
    private function getBankCode($name="")
    {
        $bank_list=array(
            "工商银行"=>"1002",
            "农业银行"=>"1005",
            "中国银行"=>"1026",
            "建设银行"=>"1003",
            "招商银行"=>"1001",
            "邮储银行"=>"1066",
            "邮政储蓄"=>"1066",
            "交通银行"=>"1020",
            "浦发银行"=>"1004",
            "民生银行"=>"1006",
            "兴业银行"=>"1009",
            "平安银行"=>"1010",
            "中信银行"=>"1021",
            "华夏银行"=>"1025",
            "广发银行"=>"1027",
            "光大银行"=>"1022",
            "北京银行"=>"1032",
            "宁波银行"=>"1056",
        );
        if(!$bank_list[$name]){
            //E("不支持该银行");
        }
        return $bank_list[$name];
    }

    public function pay_bank()
    {

        $params = array(
            'mch_id'    => $this->WEIXIN_MCHID,
            'partner_trade_no' => date("YmdHis"),//商户企业付款单号
            'nonce_str' => strtoupper(md5(time())),
            'enc_bank_no' => $this->rsaEncrypt("123456"),//收款方银行卡号
            'enc_true_name' => $this->rsaEncrypt("张三"),//收款方用户名
            'bank_code' => $this->getBankCode("建设银行"),//银行卡所在开户行编号,详见银行编号列表 收款方开户行
            'amount' => intval(1*100),//付款金额：RMB分（支付总额，不含手续费）  注：大于0的整数
            'desc' => "[中国联保提现]",//  企业付款到银行卡付款说明,即订单备注（UTF8编码，允许100个字符以内）
        );
        $signature = $this->getSign2($params); //生成sign
        $params["sign"]=$signature;
        $xml = $this->arrayToXml($params); //创建xml，
        $url="https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank";
        $response = $this->curl_post_ssl($url,$xml);  //提交获取rsa的请求
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
//        print_r($response);
        if($xml->return_code == 'SUCCESS' && $xml->result_code == 'SUCCESS' &&$xml->err_code == 'SUCCESS')
        {
            return true;
        }
        if($xml->return_code == 'SUCCESS' && $xml->result_code == 'FAIL' &&$xml->err_code == 'PARAM_ERROR')
        {
//            E("提交到微信失败:".$err_code_des);
        }
        return false;

    }

    /**
     * 查询订单是否成功或者失败
     * @param string $trade_no
     * @return bool
     */
    public function query($trade_no="")
    {
        $params = array(
            'mch_id'    => $this->WEIXIN_MCHID,
            'partner_trade_no' => $trade_no,//商户企业付款单号
            'nonce_str' => strtoupper(md5(time())),
        );
        $signature = $this->getSign2($params); //生成sign
        $params["sign"]=$signature;
        $xml = $this->arrayToXml($params); //创建xml，
        $url="https://api.mch.weixin.qq.com/mmpaysptrans/query_bank";
        $response = $this->curl_post_ssl($url,$xml);  //提交获取rsa的请求
        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        //status
        /**
         * 代付订单状态：
        PROCESSING（处理中，如有明确失败，则返回额外失败原因；否则没有错误原因）
        SUCCESS（付款成功）
        FAILED（付款失败）
        BANK_FAIL（银行退票，订单状态由付款成功流转至退票,退票时付款金额和手续费会自动退还）
         */
        if($xml->result_code == 'SUCCESS' && $xml->status == 'PROCESSING'){
            //E("处理中");
        }
        if($xml->result_code == 'SUCCESS' && $xml->status == 'BANK_FAIL'){
//            E("银行退票，订单状态由付款成功流转至退票,退票时付款金额和手续费会自动退还");
        }
        if($xml->return_code == 'SUCCESS' && $xml->result_code == 'FAIL' && $xml->status == 'FAILED'){//表示打款失败

            return false;
        }
        if($xml->return_code == 'SUCCESS' && $xml->result_code == 'FAIL' && $xml->err_code == 'ORDERNOTEXIST'){//表示订单不存在

            return false;
        }
        if(    $xml->return_code == 'SUCCESS'
            && $xml->result_code == 'SUCCESS'
            && $xml->err_code == 'SUCCESS'
            && $xml->status == 'SUCCESS'){//表示打款成功
            return true;
        }
    }
}

$pay=new WeixinPayBank();
$pay->pay_bank();//支付