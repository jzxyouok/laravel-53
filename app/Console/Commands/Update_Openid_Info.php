<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class Update_Openid_Info extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update_openid_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $app = app('wechat');
        $token = $app->access_token->getToken();

        $row = DB::table('wx_user_info')
            ->where('esc', '0')
            ->where('city', '')
            ->where('endtime', '>=', date("Y-m-d", strtotime("-1 day")))
            ->orderBy('id', 'desc')
            ->get();
        foreach ($row as $result) {
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $token . "&openid=" . $result->wx_openid;
            $json = $this->http_request_json($url);//这个地方不能用file_get_contents
            $data = json_decode($json, true);
//            $nickname = $data['nickname'];
//            $sex = $data['sex'];
            $city = $data['city'];
            $province = $data['province'];
//            $country = $data['country'];
//            $subscribe_time = $data['subscribe_time'];
            $unionid = $data['unionid'];

            DB::table('wx_user_info')
                ->where('id', $result->id)
                ->update(['city' => $city, 'province' => $province]);
            DB::table('wx_user_unionid')
                ->insert(['wx_openid' => $result->wx_openid, 'wx_unionid' => $unionid]);
            \Log::info($result->wx_openid);

        }
    }

    //因为url是https 所有请求不能用file_get_contents,用curl请求json 数据
    private function http_request_json($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
}
