<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\DB;

class IndexController extends Controller
{
	const ADDRESS_HEX = '41ab6500e21bc89ce32dc6e54fcc0cc9f8b27ca54b';
    const ADDRESS_BASE58 = 'TRbTYhq2UGjfJiSXUmrFgyKCgrxwQKPAjh';
    const FULL_NODE_API = 'http://127.0.0.1:8090';
    const SOLIDITY_NODE_API = 'http://127.0.0.1:8091';
    //const CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';//正式服USDT
    const CONTRACT = 'TK6eQTi2s68UgqSxizz7T7M6QyPHbrqhcd';//测试服USDT


    public function index()
    {

        
        
       
        $send=$this->send(config('app.activationAddress'),"41262f9bc8a1c04d2425e5a2fa02700c75e6a90575",1.1204,config('app.activationAddressPrivateKey'));
        dd($send);
        //$this->send(config('app.activationAddress'),$generateaddress["address"],1,config('app.activationAddressPrivateKey'));
        $a=$this->sendToken('TDT7iFHuXqdX8pR9ZNgC6yLz9myQafwguD',config('app.companyAddress'),'TK6eQTi2s68UgqSxizz7T7M6QyPHbrqhcd',1,'9b85e10b64202b42aecebe4e94abd3198e9e0a0968fd9b1d5e9418e132f8575d');
        dd($a);

		//发送token
		$sendTokenHash=$this->sendToken('TRbTYhq2UGjfJiSXUmrFgyKCgrxwQKPAjh','TDs5Hh2YxmW7BXnQ7vmr1UKTLfbPrdGbyz','TK6eQTi2s68UgqSxizz7T7M6QyPHbrqhcd',2000000,"2f338a58edec06499cd3e3beac57a123289a8bad27480c58cf4a4af17ad49a29");
		//发送TRX
		//$send=$this->send("TRbTYhq2UGjfJiSXUmrFgyKCgrxwQKPAjh","TDs5Hh2YxmW7BXnQ7vmr1UKTLfbPrdGbyz",2);
		
		dd($sendTokenHash);
    }

    //接收推送写入数据库
    public function receiveERC(Request $request){
        //$date2= date("Y-m-d H:i:s", strtotime("-5 minute"));
        $trcHash=$request->input('transaction_id',false);
        //$info=DB::table('token_confirm')->where('update_time','>',$date2)->where('hash',$trcHash)->first();
        $info=DB::table('token_confirm')->where('hash',$trcHash)->first();
        if($info){
            return 4;
        }
    
        $trc20_data=DB::table('trc20_transactions')->where('transaction_id',$trcHash)->first();
        $data['hash']=$trcHash;
        $data['confirm']=1;
        $data['update_time']=date('Y-m-d H:i:s');
        $data['block']=$trc20_data->block_number;
        $data['type']=2;
        $data['from']=$trc20_data->from;
        $data['to']=$trc20_data->to;
        $data['amount']=$trc20_data->value;
        $data['token']=$trc20_data->contract_address;
       
        $this->getApi($trcHash);
        
        // if($erc20_data->erc20_to===config('app.erc20Address')){
        //     return 2;
        // }
        
        try {
           $info=DB::table('token_confirm')->insert($data);
        } catch (\Exception $exception) {
            Log::info($exception);
        }
        return 1;
    }

    //更新确认块转到指定账号
    public function updateBlock(){
        $getNewblockUrl=self::FULL_NODE_API."/wallet/getnowblock";
        $NewBblock = json_decode(file_get_contents($getNewblockUrl),true);
        $blockNumber=$NewBblock["block_header"]["raw_data"]["number"];
        $info=DB::table('token_confirm')->where('confirm','<',20)->get();

        foreach ($info as $value){
            $confirm=$blockNumber-$value->block+1;
            $info=DB::table('token_confirm')->where('id',$value->id)->update(array('confirm'=>$confirm));
            if($confirm>=20){
            	$this->getApi($value->hash);
                try {
                        $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
                } catch (\Exception $exception) {
                    Log::info($exception);
                }
                $tron->setAddress($value->to);
                $balance=$tron->getBalance();
                if($balance<1500000){
                    if($value->fee==0){
                        $send=$this->send(config('app.activationAddress'),$value->to,4,config('app.activationAddressPrivateKey'));
                        $info=DB::table('token_confirm')->where('id',$value->id)->update(array('fee'=>1));
                    }
                }
            }
        }

        $erc20_data=DB::table('token_confirm')->where('confirm','>=',20)->where('status',0)->orderBy('id', 'asc')->get();

        foreach ($erc20_data as $value){
            $keyinfo=DB::table('accounts')->where('hexAddress',$value->to)->where('platformName','test')->first();
            if($keyinfo){
                try {
                    try {
                        $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
                    } catch (\Exception $exception) {
                        Log::info($exception);
                    }
                    $tron->setAddress($value->to);
                    $balance=$tron->getBalance();
                    if($balance>=1500000){
                       $a=$this->sendToken($value->to,config('app.companyAddress'),$value->token,$value->amount,$keyinfo->privateKey);
                       if($a["result"]){
                            $info=DB::table('token_confirm')->where('id',$value->id)->update(array('status'=>1));
                        }
                        
                    }
                } catch (\Exception $exception) {
                	Log::info($exception);
                }
            }

        }
        return 1;
    }





    public function getApi($hash){
        //$hash='0xaf67ac4758c2c8fbff8269a6556f2e93d5e7288db8084ae1a289e80b655b7cc1';
        try {
            $info=DB::table('trc20_transactions')->where('transaction_id',$hash)->first();
            if(!$info){
                DB::table('token_boss_get')->insert(array('hash'=>$hash,'update_time'=>date('Y-m-d H:i:s'),'to'=>'','data'=>'hash找不到'));
                return 0;
            }
            $getNewBblock=self::FULL_NODE_API."/wallet/getnowblock";
            $NewBblock = json_decode(file_get_contents($getNewBblock),true);
            $info->block_confirmations=$NewBblock["block_header"]["raw_data"]["number"]-$info->block_number+1;
            $tokeninfo=DB::table('trc20_contracts1')->where('trc20_address',$info->contract_address)->first();
            if(!$tokeninfo){
                DB::table('token_boss_get')->insert(array('hash'=>$hash,'update_time'=>date('Y-m-d H:i:s'),'to'=>'','data'=>'token小数点没有设置bcdiv'));
                return 0;
            }
            $c='1';
            for ($i=0;$i<$tokeninfo->decimals;$i++){
                $c.='0';
            }
            $info->value=bcdiv($info->value,$c,$tokeninfo->decimals);
            $key=md5($info->to.$info->contract_address.$info->transaction_id.$info->block_confirmations.$info->block_timestamp.$info->value.'Ual@wvsHsXFDQ8Vu'.'NcO%FJJf%8iALbof');
            $url2 = 'https://client.rcmfx.com/erc_api?hash='.$info->transaction_id.'&to='.$info->to.'&api_key='.$key.'&time_stamp='.$info->block_timestamp.'&block_confirmations='.$info->block_confirmations.'&token='.$info->contract_address.'&value='.$info->value;
            //$url2 = 'https://testclient.rcmfx.com/erc_api?hash='.$info->erc20_tx_hash.'&to='.$info->erc20_to.'&api_key='.$key.'&time_stamp='.$info->time_stamp.'&block_confirmations='.$info->block_confirmations.'&token='.$info->erc20_token.'&value='.$info->erc20_value;
            //dd($url2);
            $task_message2 = file_get_contents($url2);
            //dd($url2,$task_message2);
            //$task_message2 = json_decode(file_get_contents($url2),true);
            DB::table('token_boss_get')->insert(array('hash'=>$info->block_confirmations,'update_time'=>date('Y-m-d H:i:s'),'to'=>$info->to,'data'=>$task_message2));
            return 1;
        } catch (\Exception $exception) {
        	 Log::info($exception);
            DB::table('token_boss_get')->insert(array('hash'=>$hash,'update_time'=>date('Y-m-d H:i:s'),'to'=>'','data'=>'发生错误'));
            return 1;

        }

    }

    public function GetTrc20Transaction($url)
    {
    	try {
	    	$TransactionData = json_decode(file_get_contents($url),true);
		} catch (\Exception $exception) {
			$TransactionData = $this->GetTrc20Transaction($url);
		}

    	return $TransactionData;
    }

    //创建地址
    public function generateaddress()
    {
    	try {
	    	$url="http://127.0.0.1:8090/wallet/generateaddress";
	    	$re=$this->postJson($url,'');
	    	$generateaddress=json_decode($re,true);
	    	$generateaddress['datetime']=date("Y-m-d H:i:s",time());
	    	$generateaddress['platformName']='test';
			DB::table('accounts')->insert($generateaddress);
			$data["code"]=200;
			$data["address"]=$generateaddress["address"];
			return $data;
		} catch (\Exception $exception) {
			$data["code"]=403;
			$data["address"]="创建地址失败";
			Log::info($exception);
			return $data;
		}
	
    }

    public function send($from,$to,$amount,$setPrivateKey)
    {
    	try {
		    $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
		} catch (\Exception $exception) {
		    Log::info($exception);
		}
		$tron->setAddress($from);
		$tron->setPrivateKey($setPrivateKey);
		try {
    		$transfer = $tron->send($to, $amount);
    		return $transfer;
		} catch (\Exception $exception) {
		   Log::info($exception);
		}

    }

    public function sendToken($from,$to,$token,$amount,$privateKey)
    {
    	try {
            $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
        } catch (\Exception $exception) {
            Log::info($exception);
        }
		//创建交易
    	$url="http://192.168.100.31:8090/wallet/triggersmartcontract";//创建交易url
    	$tron->setAddress($token);
    	$tokendata["contract_address"]=$tron->getAddress()['hex'];
    	$tron->setAddress($to);
    	//金额转16进制
    	$slAmount=dechex($amount);
		$amount=$slAmount;
    	for ($i=0; $i < 64-strlen($slAmount); $i++) { 
    		$amount='0'.$amount;
    	}
    	$tokendata["function_selector"]="transfer(address,uint256)";
    	$tokendata["parameter"]='0000000000000000000000'.$tron->getAddress()['hex'].$amount;
    	$tokendata["fee_limit"]="100000000";
    	$tokendata["call_value"]=0;
    	 $tron->setAddress($from);
    	$tokendata["owner_address"]=$tron->getAddress()['hex'];
    	$re=$this->postJson($url,json_encode($tokendata));
    	
    	//签名交易
    	$arrayRe=json_decode($re,true)["transaction"];
    	$qianMingUrl="http://192.168.100.31:8090/wallet/gettransactionsign";//签名交易url
    	$qianMingData["transaction"]=json_encode($arrayRe);
    	$qianMingData["privateKey"]=$privateKey;
    	$qianMingRe=$this->postJson($qianMingUrl,json_encode($qianMingData));

    	//发送签名交易
		$sendQianMingUrl="http://192.168.100.31:8090/wallet/broadcasttransaction";//签名交易
		$sendQianMingRe=$this->postJson($sendQianMingUrl,$qianMingRe);
		return json_decode($sendQianMingRe,true);
    }

    public function postJson($url, $data_string) {

        $ch = curl_init();

        curl_setopt ( $ch, CURLOPT_URL, $url );

        curl_setopt ( $ch, CURLOPT_POST, 1 );

        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // https请求 不验证证书和hosts

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(

            'Content-Type: application/json',

            'Content-Length: ' . strlen($data_string)

        ));

        $post_result = curl_exec($ch);        

        if (curl_errno($ch)) {

            //print curl_error($ch);

        }

        curl_close($ch);

        return $post_result;

    }

}
