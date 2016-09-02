<?php
/**
 * Created by PhpStorm.
 * User: ubuntu
 * Date: 16-9-1
 * Time: 下午2:58
 */

namespace App\WeChat;

use Carbon\Carbon;
use ClassesWithParents\D;
use EasyWeChat\Message\Voice;
use Illuminate\Database\Eloquent\Model;
use EasyWeChat\Foundation\Application;
use DB;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Text;
use App\WeChat\Usage;
//use App\Http\Requests;
use Crypt;

class Response
{

    public $app;
    public $usage;
    public $openid;

    public function __construct($message)
    {
        $this->app = app('wechat');

        $this->usage = new Usage();
        /*       $userService = $this->app->user;
               $this->openid = $userService->get($message->FromUserName)->openid;*/
    }

    /*    protected $usage;
        public function __construct(usage $usage)
        {
            $this->usage=$usage;
        }*/
    public function news($message, $keyword)
    {

//        $app = app('wechat');

        $userService = $this->app->user;
        $openid = $userService->get($message->FromUserName)->openid;
        switch ($keyword) {
            case "a":
                $content = new Text();
                if ($this->usage->get_openid_info($openid)->eventkey) {
                    $content->content = $this->usage->get_openid_info($openid)->eventkey;
                } else {
                    $content->content = '无eventkey';
                }
//                $content->content = $app->access_token->getToken();
                break;
            case "预约":
                $content = new Text();
                $content->content = $this->query_wite_info($openid);
                break;
            case 's':
                $content = new News();
                $content->title = "laravel-wechat";
                $content->description = "测试";
                $content->url = "http://blog.unclewang.me/zone/subscribe/ldjl/asdass/";
                $content->image = "http://www.hengdianworld.com/images/JQ/scenic_dy.png";
                $this->app->staff->message([$content])->to($openid)->send();
                break;
            case 'd':
                $content = new Text();
                $info = $this->usage->get_openid_info('o2e-YuBgnbLLgJGMQykhSg_V3VRI');
                $content->content = $info->eventkey;
                break;
            case 'hx':
                $content = new Text();
                $tour = new Tour();
                $content->content = $tour->verification_subscribe($openid, '1');
                break;
            case '天气':
                $content = new Text();
                $content->content = $this->get_weather_info();
                break;
            default:
                $content = $this->request_keyword($openid, $keyword);
                break;
        }
        return $content;
    }

    /**
     * 菜单回复
     * @param $openid
     * @param $menuID
     * @return array|Text
     */
    public function click_request($openid, $menuid)
    {
        $eventkey = $this->usage->get_openid_info($openid)->eventkey;
        $this->request_news($openid, $eventkey, '2', '', $menuid);
        $this->add_menu_click_hit($openid, $menuid); //增加点击数统计
//        return $content;
    }

    /**
     * 关键字回复
     * @param $openid
     * @param $keyword
     * @return array|Text
     */
    private function request_keyword($openid, $keyword)
    {
        $eventkey = $this->usage->get_openid_info($openid)->eventkey;
        if (!$eventkey) {
            $eventkey = 'all';
        }
//        $content = $this->request_news($openid, $eventkey, '3', $keyword, '');

        $flag = false;    //先设置flag，如果news，txt，voice都没有的话，检查flag值，还是false时，输出默认关注显示
        //检查该关键字回复中是否有图文消息
        if ($this->check_keyword_message($eventkey, "news", $keyword)) {
            $flag = true;
            $this->request_news($openid, $eventkey, '3', $keyword, '');
//            $this->app->staff->message($content_news)->by('1001@u_hengdian')->to($openid)->send();
        }
        if ($this->check_keyword_message($eventkey, "voice", $keyword)) {
            $flag = true;
            $this->request_voice($openid, '2', $eventkey, $keyword);
        }
        if ($this->check_keyword_message($eventkey, "txt", $keyword)) {
            $flag = true;
            $this->request_txt($openid, '2', $eventkey, $keyword);             //直接在查询文本回复时使用客服接口
        }

        if (!$flag)     //如果该二维码没有对应的关注推送信息
        {
            $content = new Text();
            $content->content = "嘟......您的留言已经进入自动留声机，小横横回来后会努力回复你的~\n您也可以拨打400-9999141立刻接通小横横。";
            $this->app->staff->message($content)->by('1001@u_hengdian')->to($openid)->send();
        }


//        return $content;
    }

    /**
     * 关注回复
     * @param $fromUsername
     * @param $eventkey
     */
    public function request_focus($openid, $eventkey)
    {
        if (!$eventkey or $eventkey == "") {
            $eventkey = "all";
        }
        $flag = false;    //先设置flag，如果news，txt，voice都没有的话，检查flag值，还是false时，输出默认关注显示
        //检查该二维码下关注回复中是否有图文消息
        if ($this->check_eventkey_message($eventkey, "news", "1")) {
            $flag = true;
            $this->request_news($openid, $eventkey, '1', '', '');
//            $this->app->staff->message($content_news)->by('1001@u_hengdian')->to($openid)->send();
        }
        if ($this->check_eventkey_message($eventkey, "voice", "1")) {
            $flag = true;
            $this->request_voice($openid, '1', $eventkey, '');
        }
        if ($this->check_eventkey_message($eventkey, "txt", "1")) {
            $flag = true;
            $this->request_txt($openid, '1', $eventkey, '');             //直接在查询文本回复时使用客服接口
        }

        if (!$flag)     //如果该二维码没有对应的关注推送信息
        {
            $this->request_news($openid, 'all', '1', '', '');
//            $this->app->staff->message($content_news)->to($openid)->send();
        }
//        return $content;
    }

    /**
     * 检查关注是否有对应二维码的消息回复（图文、语音、文字、图片）
     * @param $eventkey
     * @param $type ：   news:图文    txt:文字      voice:语音
     * @param $focus :   1:关注    0：不关注
     * @return boolkey
     */
    private function check_eventkey_message($eventkey, $type, $focus)
    {
//        $db = new DB();
        $flag = false;
        switch ($type) {
            case "news":
                $row_news = DB::table('wx_article')
                    ->where('msgtype', 'news')
                    ->where('focus', $focus)
                    ->where('audit', '1')
                    ->where('del', '0')
                    ->where('online', '1')
                    ->where('eventkey', $eventkey)
                    ->whereDate('startdate', '<=', date('Y-m-d'))
                    ->whereDate('enddate', '>=', date('Y-m-d'))
                    ->first();

                if ($row_news) {
                    $flag = true;
                }
                break;
            case "txt":
                $row_txt = DB::table('wx_txt_request')
                    ->where('eventkey', $eventkey)
                    ->where('online', '1')
                    ->where('focus', $focus)
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->first();

                if ($row_txt) {
                    $flag = true;
                }
                break;
            case "voice":
                $row_voice = DB::table('wx_voice_request')
                    ->where('eventkey', $eventkey)
                    ->where('online', '1')
                    ->where('focus', $focus)
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->first();

                if ($row_voice) {
                    $flag = true;
                }
                break;
            case "images":
                $row_images = DB::table('wx_images_request')
                    ->where('eventkey', $eventkey)
                    ->where('online', '1')
                    ->where('focus', $focus)
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->first();
                if ($row_images) {
                    $flag = true;
                }
                break;
            default:
                break;

        }
        return $flag;
    }


    /**
     * 检查关键字是否有对应的消息回复（图文、语音、文字、图片）
     * @param $eventkey
     * @param $type ：   news:图文    txt:文字      voice:语音
     * @param $focus :   1:关注    0：不关注
     * @return boolkey
     */
    private function check_keyword_message($eventkey, $type, $keyword)
    {
//        $db = new DB();
        $keyword = $this->check_keywowrd($keyword);
        $flag = false;
        switch ($type) {
            case "news":
                $row_news = DB::table('wx_article')
                    ->where('msgtype', 'news')
                    ->where('audit', '1')
                    ->where('del', '0')
                    ->where('online', '1')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->whereDate('startdate', '<=', date('Y-m-d'))
                    ->whereDate('enddate', '>=', date('Y-m-d'))
                    ->first();

                if ($row_news) {
                    $flag = true;
                }
                break;
            case "txt":
                $row_txt = DB::table('wx_txt_request')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('online', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->first();

                if ($row_txt) {
                    $flag = true;
                }
                break;
            case "voice":
                $row_voice = DB::table('wx_voice_request')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('online', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->first();

                if ($row_voice) {
                    $flag = true;
                }
                break;
            case "images":
                $row_images = DB::table('wx_images_request')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('online', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->first();
                if ($row_images) {
                    $flag = true;
                }
                break;
            default:
                break;

        }
        return $flag;
    }


    /**
     * 推送图文
     * @param $openid
     * @param $eventkey
     * @param $type 1：关注    2：菜单    3：关键字
     * @param $keyword      关键字
     * @param $menuid       菜单ID
     */
    public function request_news($openid, $eventkey, $type, $keyword, $menuid)
    {
//        $wxnumber = Crypt::encrypt($openid);      //由于龙帝惊临预约要解密，采用另外的函数
        $wxnumber = $this->usage->authcode($openid, 'ENCODE', 0);
        $uid = $this->usage->get_uid($openid);
        if (!$eventkey) {
            $eventkey = 'all';
        }
        switch ($type) {
            case 1:
                $row = DB::table('wx_article')
                    ->where('msgtype', 'news')
                    ->where('focus', '1')
                    ->where('audit', '1')
                    ->where('del', '0')
                    ->where('online', '1')
                    ->where('eventkey', $eventkey)
                    ->whereDate('startdate', '<=', date('Y-m-d'))
                    ->whereDate('enddate', '>=', date('Y-m-d'))
                    ->orderBy('priority', 'asc')
                    ->orderBy('id', 'desc')
                    ->skip(0)->take(8)->get();
                break;
            case 2:
                $row = DB::table('wx_article')
                    ->where('msgtype', 'news')
                    ->where('classid', $menuid)
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('audit', '1')
                    ->where('del', '0')
                    ->where('online', '1')
                    ->where('startdate', '<=', date('Y-m-d'))
                    ->where('enddate', '>=', date('Y-m-d'))
                    ->orderBy('eventkey', 'asc')
                    ->orderBy('priority', 'asc')
                    ->orderBy('id', 'desc')
                    ->skip(0)->take(8)->get();
                break;
            case 3:
                $keyword = $this->check_keywowrd($keyword);
                $row = DB::table('wx_article')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('audit', '1')
                    ->where('del', '0')
                    ->where('online', '1')
                    ->where('startdate', '<=', date('Y-m-d'))
                    ->where('enddate', '>=', date('Y-m-d'))
                    ->orderBy('priority', 'asc')
                    ->orderBy('id', 'desc')
                    ->skip(0)->take(8)->get();
                break;
        }
        if ($row) {
            $content = array();
            foreach ($row as $result) {
                $url = $result->url;
                $id = $result->id;
                /*如果只直接跳转链接页面时，判断是否已经带参数*/
                if ($url != '') {
                    /*链接跳转的数据统计*/
                    $linkjump = "http://weix2.hengdianworld.com/inc/linkjump.php?id=" . $id;
                    if (strstr($url, '?') != '') {
                        $url = $url . "&comefrom=1&wxnumber={$wxnumber}&uid={$uid}&wpay=1";
                    } else {
                        $url = $url . "?comefrom=1&wxnumber={$wxnumber}&uid={$uid}&wpay=1";
                    }
                    $url = $linkjump . "&link=" . $url;
                } else {
                    $url = "http://weix2.hengdianworld.com/article/articledetail.php?id=" . $id . "&wxnumber=" . $wxnumber;
                }
                $new = new News();
                $new->title = $result->title;
                $new->description = $result->description;
                $new->url = $url;
                $new->image = "http://weix2.hengdianworld.com/" . $result->picurl;
                $content[] = $new;
            }
            $this->app->staff->message($content)->by('1001@u_hengdian')->to($openid)->send();
        }

        /*        else {
                    $content = new Text();
                    $content->content = "嘟......您的留言已经进入自动留声机，小横横回来后会努力回复你的~\n您也可以拨打400-9999141立刻接通小横横。";
                }*/

    }

    /**
     * @param $openid
     * @param $type 1:关注    2：关键字
     * @param $eventkey
     * @param $keyword
     */

    private function request_txt($openid, $type, $eventkey, $keyword)
    {
//        $app = app('wechat');
        switch ($type) {
            case 1:
                $row = DB::table('wx_txt_request')
                    ->where('eventkey', $eventkey)
                    ->where('focus', '1')
                    ->where('online', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->orderBy('id', 'desc')
                    ->get();
                break;
            case 2:
                $keyword = $this->check_keywowrd($keyword);
                $row = DB::table('wx_txt_request')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('online', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->orderBy('id', 'desc')
                    ->get();
                break;
        }
        foreach ($row as $result) {
            $content = new Text();
            $content->content = $result->content;
            $this->app->staff->message($content)->by('1001@u_hengdian')->to($openid)->send();
        }
    }

    /*
    * 回复Voice
    *$focus:1（关注）；2（关键字）
    */
    public function request_voice($openid, $type, $eventkey, $keyword)
    {
        switch ($type) {
            case '1':
                $row = DB::table('wx_voice_request')
                    ->where('eventkey', $eventkey)
                    ->where('online', '1')
                    ->where('focus', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->orderBy('id', 'desc')
                    ->get();
                break;
            case "2":
                $keyword = $this->check_keywowrd($keyword);
                $row = DB::table('wx_voice_request')
                    ->where('keyword', 'like', '%' . $keyword . '%')
                    ->where(function ($query) use ($eventkey) {
                        $query->where('eventkey', $eventkey)
                            ->orWhere('eventkey', 'all');
                    })
                    ->where('online', '1')
                    ->whereDate('start_date', '<=', date('Y-m-d'))
                    ->whereDate('end_date', '>=', date('Y-m-d'))
                    ->orderBy('id', 'desc')
                    ->get();
                break;
        }
        foreach ($row as $result) {
            $voice = new Voice();
            $voice->media_id = $result->media_id;
            $this->app->staff->message($voice)->by('1001@u_hengdian')->to($openid)->send();
        }
    }


    /**
     * 增加菜单点击数
     * @param $openid
     * @param $menuID
     */

    private function add_menu_click_hit($openid, $menuID)
    {
        DB::table('wx_click_hits')
            ->insert(['wx_openid' => $openid, 'click' => $menuID]);
    }

    /**
     * 获取天气资讯
     * @return string
     */
    private function get_weather_info()
    {
        $json = file_get_contents("http://api.map.baidu.com/telematics/v3/weather?location=%E4%B8%9C%E9%98%B3&output=json&ak=2c87d6d0443ab161753291258ac8ab7a");
        $data = json_decode($json, true);
        $contentStr = "【横店天气预报】：\n\n";
        $contentStr = $contentStr . $data['results'][0]['weather_data'][0]['date'] . "\n";
        $contentStr = $contentStr . "天气情况：" . $data['results'][0]['weather_data'][0]['weather'] . "\n";
        $contentStr = $contentStr . "气温：" . $data['results'][0]['weather_data'][0]['temperature'] . "\n\n";
        $contentStr = $contentStr . "明天：" . $data['results'][0]['weather_data'][1]['date'] . "\n";
        $contentStr = $contentStr . "天气情况：" . $data['results'][0]['weather_data'][1]['weather'] . "\n";
        $contentStr = $contentStr . "气温：" . $data['results'][0]['weather_data'][1]['temperature'] . "\n\n";
        $contentStr = $contentStr . "后天：" . $data['results'][0]['weather_data'][2]['date'] . "\n";
        $contentStr = $contentStr . "天气情况：" . $data['results'][0]['weather_data'][2]['weather'] . "\n";
        $contentStr = $contentStr . "气温：" . $data['results'][0]['weather_data'][2]['temperature'] . "\n";
        return $contentStr;
    }

    public function scopePublished($query)
    {
        $query->where('audit', '1')
            ->where('del', '0')
            ->where('online', '1')
            ->where('startdate', '<=', date('Y-m-d'))
            ->where('enddate', '>=', date('Y-m-d'));
    }


    public function insert_subscribe($openid, $eventkey, $type)
    {
        $tag_id = $this->usage->query_tag_id($eventkey);

        $row = DB::table('wx_user_info')
            ->where('wx_openid', $openid)
            ->first();
        if (!$row) {
            DB::table('wx_user_info')
                ->insert(['wx_openid' => $openid, 'eventkey' => $eventkey, 'tag_id' => $tag_id, 'subscribe' => '1', 'adddate' => Carbon::now(), 'scandate' => Carbon::now()]);
        } else {
            DB::table('wx_user_info')
                ->where('wx_openid', $openid)
                ->update(['eventkey' => $eventkey, 'tag_id' => $tag_id, 'subscribe' => 1, 'esc' => '0', 'scandate' => Carbon::now(), 'endtime' => Carbon::now()]);
        }

        if ($type == "subscribe")//新关注
        {
            DB::table('wx_user_add')
                ->insert(['wx_openid' => $openid, 'eventkey' => $eventkey]);             //插入数据统计的表
        }
    }


    /**
     * 客人取消关注时，删除user_info中的信息，在user_esc中增加
     * @param $fromUsername
     */
    public function insert_unsubscribe($openid)
    {
        DB::table('wx_user_info')->where('wx_openid', $openid)->update(['esc' => '1', 'subscribe' => '0', 'esctime' => Carbon::now()]);   //设置取消关键字为1，以及取消时间
        DB::table('wx_user_esc')->insert(['wx_openid' => $openid]);                           //增加到wx_user_esc表中
    }


    /**
     * 查询该用户在unionid表中是否存在
     * @param $fromUsername
     * @return string | boolean
     */
    function check_unionid($openid)
    {
        $row = DB::table('wx_user_unionid')
            ->where('wx_openid', $openid)->first();
        $flag = ($row) ? true : false;
        return $flag;
    }

    /**
     * 表中加入客人的unionid
     * @param $openid
     * @param $unionid
     */
    public function insert_user_unionid($openid, $unionid)
    {
        if (!$this->check_unionid($openid)) {//检查union表中是否存在
            DB::table('wx_user_unionid')
                ->insert(['wx_openid' => $openid, "wx_unionid" => $unionid]);
        }
    }


    /**
     * 客人关注时打上tag
     * @param $openid
     * @param $eventkey
     */
    public function make_user_tag($openid, $eventkey)
    {
        /*先删除原有tag*/

        $tag = $this->app->user_tag;
        $userTags = $tag->userTags($openid);

        if ($userTags->tagid_list) {
            foreach ($userTags as $userTag) {
                foreach ($userTag as $value) {
                    $tag->batchUntagUsers([$openid], $value);                      //删除原有标签
                }
            }
        }

        if ($this->usage->query_tag_id($eventkey)) {                          //获取eventkey对应的tag
            $tag->batchTagUsers([$openid], $this->usage->query_tag_id($eventkey));          //增加标签
        }

    }

    /*
 * 检查关键字中是否包含可回复字符
 * @param    string       $text        客人输入关键字
 * @return   string       $result      到数据库查（WX_Request_Keyword）询输出关键字
*/
    private function check_keywowrd($text)
    {
        $flag = "不包含";
        $row = DB::table('wx_request_keyword')
            ->orderBy('id', 'asc')->get();

        foreach ($row as $result) {
            if (@strstr($text, $result->keyword) != '') {
                $flag = $result->keyword;
                break;
            }
        }
        return $flag;
    }

    /**
     * 连接wifi 时获取资料
     * ToUserName    开发者微信号
     * FromUserName    连网的用户帐号（一个OpenID）
     * CreateTime    消息创建时间 （整型）
     * MsgType    消息类型，event
     * Event    事件类型，WifiConnected (Wi-Fi连网成功)
     * ConnectTime    连网时间（整型）
     * ExpireTime    系统保留字段，固定值
     * VendorId    系统保留字段，固定值
     * ShopId    门店ID，即shop_id
     * DeviceNo    连网的设备无线mac地址，对应bssid
     *
     */

    public function return_WifiConnected($postObj)
    {
        $openid = $postObj->FromUserName;
        $shop_id = $postObj->ShopId;
        $bssid = $postObj->DeviceNo;
        $connecttime = $postObj->ConnectTime;

        $connecttime = date('Y-m-d H-i-s', $connecttime);

        /*插入wifi信息*/
        DB::table('wx_wificonnect_info')
            ->insert(['wx_openid' => $openid, 'shop_id' => $shop_id, 'bssid' => $bssid, 'connecttime' => $connecttime]);

        $row = DB::table('wx_shop_info')
            ->where('shop_id', $postObj->ShopId)
            ->first();

        $eventkey = $row->eventkey;
        $this->insert_subscribe($openid, $eventkey, 'scan');            //更新openid信息
        $this->make_user_tag($openid, $eventkey);                        //标签管理


    }

    /**
     * 检查是不是3分钟之内扫二维码连的wifi，如果是，根据shop_id获取eventkey
     * @param $openid
     * @return string
     */
    public function check_openid_wificonnected($openid)
    {

        $row = DB::table('wx_wificonnect_info')
            ->where('wx_openid', $openid)
            ->where('connecttime', '>', date('Y-m-d H-i-s', time() - 180))->first();
        if ($row) {
            $eventkey = $this->usage->get_shop_info($row->shop_id)->eventkey;
        } else {
            $eventkey = '';
        }
        return $eventkey;
    }

    /*
   * 查询景区节目预约情况
   *
   *
   */
    public function query_wite_info($openid)
    {
        $tour = new Tour();
        $result = DB::table('tour_project_wait_detail')
            ->where('wx_openid', $openid)
            ->whereDate('addtime', '=', date('Y-m-d'))
            ->first();
        if (!$result) {
            $content = "您好，您今天没有预约。";
        } else {
            $project_id = $result->project_id;
            $project_name = $tour->get_project_name($project_id);
            $zone_name = $tour->get_zone_name($project_id, "2");
            $datetime = date('Y-m-d');
            $starttime = date("H:i", strtotime($result->addtime) + 3600);
//                $endtime = date("H:i", strtotime($result->addtime) + 7200);
            if ($result->used == 0) {
                $used = "未使用";
            } else {
                $used = "已使用";
            }
            $str = "您预约了" . $datetime . $zone_name . "景区" . $project_name . "项目;\n预约时间：" . $starttime . "---16：30。\n状态：" . $used;

            $content = $str;
        }
        return $content;
    }
}