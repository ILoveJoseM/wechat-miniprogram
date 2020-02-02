## 微信小程序组件

#### 安装 install

````shell
composer require jose-chan/wechat-miniprogram -vvv
````

#### 使用 usage

- 创建实例

````php
<?php
include "vendor/autoload.php";

use JoseChan\Wechat\MiniProgram\Application;

$app_id = "<Your app_id>";
$app_secret = "<Your app_secret>";

$mini_program = new Application($app_id, $app_secret);

````

- 获取用户授权信息

````php
<?php

use JoseChan\Wechat\MiniProgram\Application;

//微信授权code
$code = "<auth code>";

/** @var Application $mini_program */

/**
 * @var array $result
 * [
 *     "openid" => "<user openid>"
 *     "session_key" => "<wechat session_key>"
 * ] 
 */
$result = $mini_program->login($code);

````

- 获取微信授权码AccessToken

> 注意：需要用到access_token的所有接口都需要使用bindRedis方法注入redis对象

````php
<?php

use JoseChan\Wechat\MiniProgram\Application;

/** @var \Redis $redis */

/** @var Application $mini_program */

/** @var string $access_token */
$access_token = $mini_program->bindRedis($redis)->getAccessToken();

````

- 对微信返回的数据验签

````php
<?php

use JoseChan\Wechat\MiniProgram\Application;

/** @var string $raw_data 微信返回的rawData */
/** @var string $session_key 用户的session_key $ */
/** @var string $sign 签名 $ */

/** @var Application $mini_program */

/** @var bool $result */
$result = $mini_program->verifySign($raw_data, $session_key, $sign);

````

- 获取小程序码

````php
<?php

use JoseChan\Wechat\MiniProgram\Application;

/** @var string $scene 场景值 */

/** @var Application $mini_program */

/** @var string $result 图片的二进制流 */
$result = $mini_program->getWxaCodeUnLimit($scene);

````
