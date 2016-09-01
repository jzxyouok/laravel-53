<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use EasyWeChat\Message\Text;
use App\WeChat\Response;
use App\WeChat\tour;
use Log;
//use App\Http\Requests;

class WechatController extends Controller
{
    //
    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function serve()
    {
        Log::info('request arrived.'); # 注意：Log 为 Laravel 组件，所以它记的日志去 Laravel 日志看，而不是 EasyWeChat 日志

        $wechat = app('wechat');
        $userService = $wechat->user;
        $wechat->server->setMessageHandler(function($message) use ($userService){
            $openid = $userService->get($message->FromUserName)->openid;
            return "欢迎关注 overtrue！".$openid;
        });

        Log::info('return response.');

        return $wechat->server->serve();
    }
}
