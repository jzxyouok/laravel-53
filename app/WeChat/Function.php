<?php
/**
 * Created by PhpStorm.
 * User: ubuntu
 * Date: 16-9-1
 * Time: 下午2:57
 */
namespace App\WeChat;

use EasyWeChat\Foundation\Application;
use DB;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Text;
//use App\Http\Requests;


class Usage
{
    /**
     * 从wx_user_info获取用户资料
     * @param $openid
     * @return mixed|static
     */
    public function get_openid_info($openid)
    {
        $row = DB::table('wx_user_info')
            ->where('wx_openid', $openid)
            ->first();
        return $row;
    }

    /**
     * 获取eventkey相关信息
     * @param $eventkey
     * @return mixed|static
     */
    public function get_eventkey_info($eventkey)
    {
        $row = DB::table('wx_qrscene_info')
            ->where('qrscene_id', $eventkey)
            ->first();
        return $row;
    }


    /**
     * 获取uid
     * @param $fromUsername
     * @return string
     */
    public function get_uid($openid)
    {
        $uid = $this->get_eventkey_info($this->get_openid_info($openid)->eventkey)->uid;
        $endtime = $this->get_openid_info($openid)->endtime;
        if (!$uid) {
            $differs_days = (strtotime("now") - strtotime($endtime)) / 86400;
            if ($differs_days <= 10 && $differs_days > 1) {
                $uid = "627A7778313233";
            }
        }
        return $uid;
    }


    /**
     * 查询eventkey对应的tag
     * @param $eventkey
     * @return null
     */
    public function query_tag_id($eventkey)
    {
        $row = DB::table('wx_user_tag')->where('eventkey', $eventkey)->first();

        if ($row) {
            return $row->tag_id;
        } else {
            return null;
        }
    }

    /**
     * 查询门店对应信息
     * @param $shop_id
     * @return mixed|string|static
     */
    public function get_shop_info($shop_id)
    {
        $row = DB::table('wx_shop_info')
            ->where('shop_id', $shop_id)
            ->first();
        if($row)
        {
            return $row;
        }
        else
        {
            return '';
        }

    }

    /*
     * 加密解密
     *
     *
     */

    public function authcode($string, $operation = 'DECODE', $expiry = 0)
    {
        if ($operation == 'DECODE') {
            $string = str_replace('[a]', '+', $string);
            $string = str_replace('[b]', '&', $string);
            $string = str_replace('[c]', '/', $string);
        }

        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;
        $key = "hdtravel";
        // 密匙
        $key = md5($key ? $key : 'hengdianworld');

        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密匙c用于变化生成的密文
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) :
            substr(md5(microtime()), -$ckey_length)) : '';
        // 参与运算的密匙
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，
//解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) :
            sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        // 产生密匙簿
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'DECODE') {
            // 验证数据有效性，请看未加密明文的格式
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) &&
                substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)
            ) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            $ustr = $keyc . str_replace('=', '', base64_encode($result));
            $ustr = str_replace('+', '[a]', $ustr);
            $ustr = str_replace('&', '[b]', $ustr);
            $ustr = str_replace('/', '[c]', $ustr);
            return $ustr;

        }
    }


    public function v($openid, $project_id)
    {
        $row = DB::table('tour_project_wait_detail')
            ->where('wx_openid', $openid)
            ->where('project_id', $project_id)
            ->whereDate('addtime', '=', date('Y-m-d'))
            ->first();

        if (!$row) {
            $content = "您今天没有预约。";
        } elseif ($row->used == "1") {
            $content = "不能重复游玩。";
        } else {
            /*查询是否符合核销条件（当天，一小时前）*/
            /*     $row1 = $db->query("select * from tour_project_wait_detail WHERE wx_openid=:wx_openid AND project_id=:project_id AND  used=:used AND date(addtime)=:tempdate  AND UNIX_TIMESTAMP(addtime)<=:endtime",
                     array("wx_openid" => $fromUsername, "project_id" => $project_id, "used" => "0", "tempdate" => date('Y-m-d'), "endtime" => strtotime(date("Y-m-d H:i", time() - 3300))));
          */
            $row1 = DB::table('tour_project_wait_detail')
                ->where('wx_openid', $openid)
                ->where('project_id', $project_id)
                ->whereDate('addtime', '=', date('Y-m-d'))
                ->where('addtime', '<=', date("Y-m-d H:i", time() - 3300))
                ->first();

            if (!$row1) {
                $content = "您好，现在未到您的预约时间";
            } else {
                /*                $row2 = $db->query("update tour_project_wait_detail set used=:used,usetime=:usetime WHERE wx_openid=:wx_openid AND project_id=:project_id AND date(addtime)=:tempdate  and  UNIX_TIMESTAMP(addtime)<=:endtime",
                                    array("used" => "1","usetime"=>date('Y-m-d H-i-s'), "wx_openid" => $fromUsername, "project_id" => $project_id, "tempdate" => date('Y-m-d'), "endtime" => strtotime(date("Y-m-d H:i", time() - 3300))));
                       */
                $row2 = DB::table('tour_project_wait_detail')
                    ->where('wx_openid', $openid)
                    ->where('project_id', $project_id)
                    ->whereDate('addtime', '=', date('Y-m-d'))
                    ->update(['used' => '1', 'usetime' => date('Y-m-d H-i-s')]);


                if ($row2 > 0) {
                    $content = "您好，您现在可以入场。";
                } else {
                    $content = "核销有误，请联系工作人员。";
                }
            }
        }
        return $content;


    }
}