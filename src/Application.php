<?php
/**
 * Created by PhpStorm.
 * User: JoseChan
 * Date: 2017/6/20 0020
 * Time: 下午 4:59
 */

namespace JoseChan\Wechat\MiniProgram;

/**
 * Class Application
 * @package JoseChan\UserLogin\Libraries\Wechat\MiniProgram
 */
class Application
{
    /** @var string $app_id 应用ID */
    private $app_id;
    /** @var string $app_secret 应用密钥 */
    private $app_secret;
    /**
     * @var \Redis;
     */
    private $redis;

    public function __construct($app_id, $app_secret)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
    }

    /**
     * 绑定redis对象
     * @param \Redis $redis
     * @return $this
     */
    public function bindRedis($redis)
    {
        $this->redis = $redis;
        return $this;
    }

    /**
     * 登录
     * @param $code
     * @return mixed
     */
    public function login($code)
    {
        $result = $this->getSessionKey($code);

//        $result['3rd_session'] = $this->get3RdSession();

        return $result;
    }

    /**
     * 验签
     * @param $rawData
     * @param $session_key
     * @param $sign
     * @return bool
     */
    public function verifySign($rawData, $session_key, $sign)
    {
        $unsign_str = $rawData . $session_key;
        $my_sign = sha1($unsign_str);
        return $my_sign === $sign;
    }

    /**
     * 解密
     * @param $data
     * @param $session_key
     * @param $iv
     * @return int
     */
    public function encryptedDataDecode($data, $session_key, $iv)
    {
        $pc = new WXBizDataCrypt($this->app_id, $session_key);
        $errCode = $pc->decryptData($data, $iv, $data);
        if ($errCode == 0) {
            return $data;
        } else {
            return $errCode;
        }
    }

    /**
     * 获取授权码
     * @return bool|string
     */
    public function getAccessToken()
    {
        $key = "wechat:mini_program:access_token:" . $this->app_id;
        if (!$access_token = $this->redis->get($key)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->app_id}&secret={$this->app_secret}";
            $data = $this->curl($url);
            $data = json_decode($data, true);
            $access_token = $data['access_token'];
            $expire = isset($data['expires_in']) ? $data['expires_in'] : 3600;
            $this->redis->setex($key, $expire, $access_token);
        }

        return $access_token;
    }

    /**
     * 获取小程序码
     * @param string $scene 场景值
     * @param string $page
     * @param int $width
     * @param bool $auto_color
     * @param null $line_color
     * @param bool $is_hyaline
     * @return array|mixed
     */
    public function getWxaCodeUnLimit($scene, $page = "pages/index/index", $width = 430, $auto_color = false, $line_color = null, $is_hyaline = false)
    {
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $this->getAccessToken();

        if ($line_color === null) {
            $line_color = ["r" => 0, "g" => 0, "b" => 0];
        }

        $post_data = [
            "scene" => $scene,
            "page" => $page,
            "width" => $width,
            "auto_color" => $auto_color,
            "line_color" => $line_color,
            "is_hyaline" => $is_hyaline
        ];

        $result = $this->curl($url, $post_data, "POST", "JSON");
        $data = json_decode($result, true);
        if ($data) {
            return $data['errcode'];
        } else {
            return $result;
        }
    }

    /**
     * 发送模版消息
     * @param $touser
     * @param $template_id
     * @param $from_id
     * @param array $data
     * @param string $page
     * @param string $emphasis_keyword
     * @return bool
     */
    public function sendTemplateMsg($touser, $template_id, $from_id, $data = [], $page = "", $emphasis_keyword = "")
    {
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $this->getAccessToken();
        $post_data = [
            "touser" => $touser,
            "template_id" => $template_id,
            "page" => $page,
            "form_id" => $from_id,
            "data" => $data,
            "emphasis_keyword" => $emphasis_keyword
        ];
        $result = $this->curl($url, $post_data, "POST", "JSON");
        $data = json_decode($result, true);
        if ($data['errcode'] == 0) {
            return true;
        } else {
            return $data['errcode'];
        }

    }

    /**
     * 发送客服消息
     * @param $data
     * @return bool
     */
    public function sendCustomerMsg($data)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $this->getAccessToken();
        $result = $this->curl($url, $data, "POST", "JSON");
        $data = json_decode($result, true);
        if ($data['errcode'] == 0) {
            return true;
        } else {
            return $data['errcode'];
        }
    }

    /**
     * 上传图片
     * @param \CURLFile $file
     * @return mixed
     */
    public function uploadImage(\CURLFile $file)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=" . $this->getAccessToken() . "&type=image";

        $params['media'] = $file;
        $result = $this->curl($url, $params, "POST");
        $data = json_decode($result, true);

        if (isset($data['errcode']) && $data['errcode'] != 0) {
            return $data['errcode'];
        }

        return $data;
    }

    /**
     * 获取sessionkey
     * @param $code
     * @return mixed
     */
    private function getSessionKey($code)
    {
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->app_id}&secret={$this->app_secret}&js_code={$code}&grant_type=authorization_code";
        $result = $this->curl($url);

        return json_decode($result, true);
    }

    /**
     * 获取第三方session
     * @return int
     * @throws \Exception
     */
    private function get3RdSession()
    {
        $rand = $this->getURandom();
        return $rand;
    }

    /**
     * 通过/dev/urandom获取随机数
     * @param int $min
     * @param int $max
     * @return int
     * @throws \Exception
     */
    private function getURandom($min = 0, $max = 0x7FFFFFFF)
    {
        $diff = $max - $min;
        if ($diff > PHP_INT_MAX) {
            throw new \Exception('Bad Range');
        }

        $fh = fopen('/dev/urandom', 'r');
        stream_set_read_buffer($fh, PHP_INT_SIZE);
        $bytes = fread($fh, PHP_INT_SIZE);
        if ($bytes === false || strlen($bytes) != PHP_INT_SIZE) {
            return 0;
        }
        fclose($fh);

        if (PHP_INT_SIZE == 8) { // 64-bit versions
            list($higher, $lower) = array_values(unpack('N2', $bytes));
            $value = $higher << 32 | $lower;
        } else { // 32-bit versions
            list($value) = array_values(unpack('Nint', $bytes));

        }

        $val = $value & PHP_INT_MAX;
        $fp = (float)$val / PHP_INT_MAX;

        return (int)(round($fp * $diff) + $min);
    }

    /**
     * @param $url
     * @param array $data
     * @param string $method
     * @param string $dataType
     * @param int $second
     * @return array|mixed
     */
    private function curl($url, $data = [], $method = "GET", $dataType = 'FORM', $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);//严格校验
        //设置header
//        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        if ($dataType == "JSON") {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data))
            );
        }
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //post提交方式
        if ($method == "POST") {
//            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
        }
    }
}