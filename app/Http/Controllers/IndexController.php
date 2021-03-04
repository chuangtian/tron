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
        try {
            $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
        } catch (\Exception $exception) {
            Log::info($exception);
        }
        $tron->setAddress("TCYiVkoq5PLnmPcY3xDdbYVfiTZVu4Ct6F");
        $balance=$tron->getTokenBalance("TK6eQTi2s68UgqSxizz7T7M6QyPHbrqhcd");
        dd($balance);
        $getNewblockUrl=self::FULL_NODE_API."/wallet/getnowblock";
        //$getNewblockUrl="https://api.nileex.io/wallet/getnowblock";
        $NewBblock = json_decode(file_get_contents($getNewblockUrl),true);
        $blockNumber=$NewBblock["block_header"]["raw_data"]["number"];
        dd($blockNumber);
        
       
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
        //$getNewblockUrl=self::FULL_NODE_API."/wallet/getnowblock";
        $getNewblockUrl="https://api.nileex.io/wallet/getnowblock";
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
    	$url="http://127.0.0.1:8090/wallet/triggersmartcontract";//创建交易url
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
    	$qianMingUrl="http://127.0.0.1:8090/wallet/gettransactionsign";//签名交易url
    	$qianMingData["transaction"]=json_encode($arrayRe);
    	$qianMingData["privateKey"]=$privateKey;
    	$qianMingRe=$this->postJson($qianMingUrl,json_encode($qianMingData));

    	//发送签名交易
		$sendQianMingUrl="http://127.0.0.1:8090/wallet/broadcasttransaction";//签名交易
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

    //删除数据
    public function deletedata(){
        $deldate=date("Y-m-d H:i:s",strtotime("-1 day"));
        DB::table('trc20_transactions')->where("datetime","<=",$deldate)->delete();
        DB::table('updatetime')->where("datetime","<=",$deldate)->delete();
        return 1;
    }

    public function wsend(Request $request){
        $validator = Validator::make($request->all(), [
            'to' => 'required',
            'amount' => 'required',
            'key' => 'required',
            'contract' => 'required',
            'decimals' => 'required',
            'wid' => 'required',
        ]);
//        $ip=$_SERVER["REMOTE_ADDR"];
//        if($ip!='103.84.86.162' and $ip!='103.84.86.163'){
//            $data['code']=402;
//            $data['message']='拒绝访问';
//            return $data;
//        }

        $errors = json_decode(json_encode($validator->errors()), true);
        //判断参数不为空
        if ($validator->fails()) {
            $data['code']=402;
            $data['message']=$errors;
            return $data;
        }
        //dd(implode(',',$request->all()));
//        $a=implode(',',$request->all());
//        $info=DB::table('accounts')->insert(array('address'=>$a,'platformName'=>'data'));
        $from_data['from']=config('app.wsendAddress');
        $from_data['password']=config('app.wsendAddressPrivateKey');
        $from_data['to']=$request->to;
        $from_data['amount']=$request->amount;
        $from_data['key']=$request->key;
        $from_data['contract']=$request->contract;
        $from_data['decimals']=$request->decimals;
        $from_data['addtime']=date('Y-m-d H:i:s');
        $from_data['wid']=$request->wid;

        if($request->wid!=0){
            $info=DB::table('token_w_from_data')->where('wid',$request->wid)->orderBy('id', 'desc')->first();
            //dd($info,$request->wid);
            if($info and $info->hash!='' ){
                $data['code']=201;
                $data['message']=$info->hash;
                return $data;
            }
            //避免重复发送
            if($info){
                $amountc=bcmul($from_data['amount'],'1000000');
                $sta=$this->examination($from_data['to'],$amountc,$from_data['from'],1);
                if($sta==1){
                    $data['code']=407;
                    $data['message']='稍后重试';
                    return $data;
                }elseif($sta==2){
                    //dd($sta);
                }else{
                    $data['code']=201;
                    $data['message']=$sta;
                    DB::table('token_w_from_data')->where('id',$info->id)->update(array('hash'=>$sta));
                    return $data;

                }
            }

        }else{
            $data['code']=402;
            $data['message']='wid不能为0';
            return $data;
        }
        $id=DB::table('token_w_from_data')->insertGetId($from_data);

        //判断key
        $key=$request->input('key');
        $hash = md5($from_data['wid'].'l4xbuh%DjehrGgqW'.'Ual@wvsHsXFDQ8Vu'.'NcO%FJJf%8iALbof'.$request->amount.$request->to);
        if($key!=$hash){
            $data['code']=402;
            $data['message']='Key error';
            return $data;
        }

        try {
            $data['from']=$request->input('from');
            $data['password']='l4xbuh%DjehrGgqW';

            $data['to']=$request->input('to');
            $data['amount']=$request->input('amount');
            try {
                $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
            } catch (\Exception $exception) {
                Log::info($exception);
            }
            $tron->setAddress($value->to);
            $balance=$tron->getBalance();

            if($balance<4000000){
                $data['code']=402;
                $data['message']='TRX不足';
                return $data;
            }

            $payer = $data['from']; // Sender's Ethereum account
            $payee = $data['to']; // Recipient's Ethereum account
            $amount=$data['amount'];
            $decimals=$request->input('decimals');
            $ling='1';
            for ($i=0;$i<$decimals;$i++){
                $ling.='0';
            }

            //计算转出金额
            $amount= bcmul($amount, $ling);
            //验证余额是否充足
            $dalance_data=$this->getBalance($payer,$contract);
            //dd($dalance_data,$amount);
            if($dalance_data['code']==200){
                if($amount>$dalance_data['balance']){
                    $del=DB::table('token_balance')->where('address',$payer)->delete();
                    //验证余额是否充足 二次验证
                    $dalance_data2=$this->getBalance($payer,$contract);
                    if($dalance_data2['code']==200){
                        if($amount>$dalance_data2['balance']){
                            $data['code']=402;
                            $data['message']='Token 余额不足';
                            return $data;
                        }else{
                            DB::beginTransaction(); //开启事务
                            $modelTokenBalance=new TokenBalance();
                            $up_balance=bcsub($dalance_data2['balance'],$amount,0);
                            $upinfo=$modelTokenBalance->updateBalance2($data['from'],$up_balance);
                            if($upinfo){
                                DB::commit();  //提交
                            }else{
                                DB::rollback();  //回滚
                            }

                        }
                    }

                }else{
                    DB::beginTransaction(); //开启事务
                    $modelTokenBalance=new TokenBalance();
                    $up_balance=bcsub($dalance_data['balance'],$amount,0);
                    $upinfo=$modelTokenBalance->updateBalance2($data['from'],$up_balance);
                    if($upinfo){
                        DB::commit();  //提交
                    }else{
                        DB::rollback();  //回滚
                    }

                }
            }
            $token = $erc20->token($contract);
            $data["data"] = $token->encodedTransferData($payee,$amount);
            $gasPrice2= bcdiv(bcmul($gasPrice3,'2',18), "1000000000000000000",18);
            if($gasPrice2<0.00000004){
                $gasPrice2= '0.00000004';
            }
            $transaction = $geth->personal()->transaction($payer, $contract)->gas(80000,$gasPrice2)->amount("0")->data($data["data"]); // Our encoded ERC20 token transfer data from previous step
            //$transaction->nonce=$from_data['nonce'];
            $nonce=$this->nonce($request->from);
            $transaction->nonce=$nonce;
            //dd($transaction,$data);
            $res = $transaction->send($data['password']); // Replace "secret" with actual passphrase of SENDER's ethereum
            if($res){
                $new_nonce=$nonce+1;
                //Session::put($payer,$new_nonce);
                Redis::set($payer,$new_nonce);
                $r_data['code']=200;
                $r_data['message']=$res;
                DB::table('token_w_from_data')->where('id',$id)->update(array('hash'=>$res,'nonce'=>$nonce));
                return $r_data;
            }
            return 0;
        } catch (\Exception $exception) {
            dd($exception);
            //恢复金额
            $amount=$request->input('amount');
            $decimals=$request->input('decimals');
            $ling='1';
            for ($i=0;$i<$decimals;$i++){
                $ling.='0';
            }
            //计算恢复金额
            $amount= bcmul($amount, $ling);
            $modelTokenBalance=new TokenBalance();
            $dalance_data=$this->getBalance($request->input('from'),$request->input('contract'));
            $up_balance=bcadd($dalance_data['balance'],$amount,0);
            $upinfo=$modelTokenBalance->updateBalance2($request->input('from'),$up_balance);
            //dd($exception);
            $data['code']=405;
            $data['message']='error';
            return $data;
        }

    }

}
