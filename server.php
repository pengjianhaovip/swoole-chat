<?php


/***
 *
 *
 * 消息签名加密规则
 *
 * 企业账号：szwtdl
 * 企业私钥：xsdldk4o
 * 平台私钥：x^3dkw
 * 企业账号:md5(szwtdl)
 * 企业加入时间戳： 1929492838
 *
 * $sign = 企业账号 + 企业私钥 + 平台私钥 + 企业账号 + 企业加入时间戳 (b)
 *
 *
 *
 */


require_once 'vendor/autoload.php';

class WebsocketTest
{
    public $server;
    public $redis;
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';


    public function __construct()
    {
	$this->redis = new redis();
	$this->redis->connect('127.0.0.1',6379);
	$this->server = new swoole_websocket_server("0.0.0.0", 9501);
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            echo "链接成功 ID:{$request->fd}\n";
            $this->server->push($request->fd, $this->myResult(self::TYPE_SUCCESS,array('user_id'=>$request->fd)));
        });
        $this->server->on('message', function (swoole_websocket_server $server, $frame) {
            $data = json_decode($frame->data,true);
            if (empty($data['type'])){
                $this->server->push($frame->fd,$this->myResult(self::TYPE_ERROR,$data,200,'数据类型错误'));
	    	$this->server->close($frame->fd);
	    }
            switch ($data['type']){
                case 'login':
                        $this->login($frame->fd,$data);
                    break;
                case 'logout':
                        $this->logout($frame->fd,$data);
                    break;
                case 'all':
                        $this->all($frame->fd,$data);
                    break;
                case 'say':
                        $this->say($frame->fd,$data);
                    break;
                case 'say_photo':
                        $this->say_photo($frame->fd,$data);
                    break;
                case 'say_audio':
                        $this->say_audio($frame->fd,$data);
                    break;
                case 'group':
                        $this->group($frame->fd,$data);
                    break;
                case 'group_join':
                        $this->group_join($frame->fd,$data);
                    break;
                case 'group_add':
                        $this->group_add($frame->fd,$data);
                    break;
                case 'list':
                        $this->users($frame->fd,$data);
                    break;
            }
//            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        });
        $this->server->on('close', function ($ser, $fd) {
            echo "client {$fd} closed\n";
        });
        $this->server->on('request', function ($request, $response) {
            foreach ($this->server->connections as $fd) {
                $this->server->push($fd, $request->get['message']);
            }
        });
        $this->server->start();

    }

    /**
     * 发送广播消息
     * @param $fd
     * @param $data
     */
    public function all($fd,$data){
        if (empty($data['content'])){
            $this->error($fd,$data,40001);
        }
        foreach ($this->server->connections as $user){
            if ($fd == $user){
                continue;
            }
            $this->server->push($user,json_encode($data));
        }
        return ;
    }

    /**
     * 获取全部用户列表
     * @param $fd
     * @param $data
     */
    public function users($fd,$data){
        $page = 0;
        $pagesize = 10;
        if (!empty($data['page'])){
            $page = intval($data['page']);
        }
        $users = $this->redis->lrange("users", $page ,$pagesize);
        $this->server->push($fd,json_encode($users));
    }

    /**
     * 群组消息
     * @param $fd
     * @param $data
     */
    public function group($fd, $data)
    {
        if (empty($data['group_id'])) {
            $this->error($fd,$data,40005);
        }
        $group = array(2, 4, 5);
        foreach ($this->server->connections as $user) {
            if (!in_array($user, $group)) {
                continue;
            }
            $this->server->push($user, $this->myResult(self::TYPE_SUCCESS, $data));
        }
    }

    /**
     * 加入群组
     * @param $fd
     * @param $data
     */
    public function group_join($fd,$data){


    }

    /**
     * 创建群组
     * @param $fd
     * @param $data
     */
    public function group_add($fd,$data){


    }

    /**
     * 删除群组
     * @param $fd
     * @param $data
     */
    public function group_del($fd,$data){

    }

    /**
     * 修改群组
     * @param $fd
     * @param $data
     */
    public function group_edit($fd,$data){

    }

    /**
     * 单聊消息
     * @param $fd
     * @param $data
     */
    public function say($fd,$data){
        if (empty($data['form_user_id']) && empty($data['content'])){
            $this->error($fd,$data,40002);
        }
        $this->server->push($data['to_user_id'],$this->myResult(self::TYPE_SUCCESS,$data));
    }

    /**发图片消息
     * @param $fd
     * @param $data
     */
    public function say_photo($fd,$data){

    }

    /**
     * 发生音频消息
     * @param $fd
     * @param $data
     */
    public function say_audio($fd,$data){

    }

    /**
     * 用户登录
     * @param $fd
     * @param $data
     */
    public function login($fd,$data){
        foreach ($this->server->connections as $user){
            if ($user == $fd) {
                continue;
            }
	        $this->server->push($user,$this->myResult(self::TYPE_SUCCESS,$data,200,'userID'.$fd.'登录成功'));
        }
        return;
    }

    /**
     * 退出登录账号
     * @param $fd
     * @param $data
     */
    public function logout($fd,$data){
        $logoutId = $fd;
        $this->server->close($fd);
        foreach ($this->server->connections as $user){
            $this->server->push($user,$this->myResult(self::TYPE_SUCCESS,$data,200,'用户UserId:'.$logoutId.'退出登录'));
        }
    }

    /**
     * 返回固定格式消息
     * @param $type
     * @param $data
     * @param int $code
     * @param string $msg
     * @return string
     */
    function myResult($type,$data,$code=200,$msg='请求成功'){
        $result = [
            'code'=>$code,
            'msg'=>$msg,
            'type'=>$type,
            'data'=>$data
        ];
        return json_encode($result);
    }

    /**
     * 全部错误数据格式
     * @param $fd
     * @param $data
     * @param $code
     */
    public function error($fd,$data,$code){
        $this->server->push($fd,$this->myResult(self::TYPE_ERROR,$data,500,'错误编号:'.$code));
        return;
    }


}

new WebsocketTest();




