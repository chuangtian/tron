<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Events extends Command
{
    //const CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';//正式服USDT
    const CONTRACT = 'TK6eQTi2s68UgqSxizz7T7M6QyPHbrqhcd';//测试服USDT
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每分钟获取一下数据';

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
        //获取数据库中最高块
        $NUpdatetime = DB::table('updatetime')->orderBy('id', 'desc')->first();
         if(!$NUpdatetime){
            $min_block_timestamp=time()-90;
        }else{
            $min_block_timestamp=$NUpdatetime->updatetime+1;
        }
        $max_block_timestamp=$min_block_timestamp+29;
        $allTrc20Transaction=array();
        //$Trc20TransactionUrl="https://api.trongrid.io/v1/contracts/".self::CONTRACT."/events?event_name=Transfer&min_block_timestamp=".$min_block_timestamp."000&max_block_timestamp=".$max_block_timestamp."000&limit=200";//正式服
        $Trc20TransactionUrl="https://nile.trongrid.io/v1/contracts/".self::CONTRACT."/events?event_name=Transfer&min_block_timestamp=".$min_block_timestamp."000&max_block_timestamp=".$max_block_timestamp."000&limit=200";//nile测试服
        $Trc20Transaction=$this->GetTrc20Transaction($Trc20TransactionUrl);
        if(count($Trc20Transaction["data"])>0){
            foreach ($Trc20Transaction["data"] as $key => $value) {
                $sqlTrc20Transaction['block_number']=$value['block_number'];
                $sqlTrc20Transaction['block_timestamp']=$value['block_timestamp'];
                $sqlTrc20Transaction['contract_address']=$value['contract_address'];
                $sqlTrc20Transaction['from']='41'.substr($value['result']['from'],2);
                $sqlTrc20Transaction['to']='41'.substr($value['result']['to'],2);
                $sqlTrc20Transaction['value']=$value['result'][2];
                $sqlTrc20Transaction['transaction_id']=$value['transaction_id'];
                $sqlTrc20Transaction['datetime']=date("Y-m-d H:i:s",time());
                $allTrc20Transaction[]=$sqlTrc20Transaction;
            }
        }
        $max_block_timestamp=$max_block_timestamp+1;
        $dataTimestamp['updatetime']=$max_block_timestamp+29;
        $dataTimestamp['datetime']=date("Y-m-d H:i:s",time());
        //$Trc20TransactionUrl="https://api.trongrid.io/v1/contracts/".self::CONTRACT."/events?event_name=Transfer&min_block_timestamp=".$max_block_timestamp."000&max_block_timestamp=".$dataTimestamp['updatetime']."000&limit=200";//正式服
        $Trc20TransactionUrl="https://nile.trongrid.io/v1/contracts/".self::CONTRACT."/events?event_name=Transfer&min_block_timestamp=".$max_block_timestamp."000&max_block_timestamp=".$dataTimestamp['updatetime']."000&limit=200";//nile测试服
        $Trc20Transaction=$this->GetTrc20Transaction($Trc20TransactionUrl);
        if(count($Trc20Transaction["data"])>0){
            foreach ($Trc20Transaction["data"] as $key => $value) {
                $sqlTrc20Transaction['block_number']=$value['block_number'];
                $sqlTrc20Transaction['block_timestamp']=$value['block_timestamp'];
                $sqlTrc20Transaction['contract_address']=$value['contract_address'];
                $sqlTrc20Transaction['from']='41'.substr($value['result']['from'],2);
                $sqlTrc20Transaction['to']='41'.substr($value['result']['to'],2);
                $sqlTrc20Transaction['value']=$value['result'][2];
                $sqlTrc20Transaction['transaction_id']=$value['transaction_id'];
                $sqlTrc20Transaction['datetime']=date("Y-m-d H:i:s",time());
                $allTrc20Transaction[]=$sqlTrc20Transaction;
            }
        }
        
        DB::table('trc20_transactions')->insert($allTrc20Transaction);
        DB::table('updatetime')->insert($dataTimestamp);
        //获取发推送
        $results = DB::select("SELECT

            contract_address,
            transaction_id,
            hexAddress,
            `to`,
            platformName,
            address
        FROM
            tron.trc20_transactions
        INNER JOIN tron.accounts ON `to` = tron.accounts.hexAddress
        WHERE
            (
                block_timestamp >= :min_block_timestamp
                AND :max_block_timestamp >= block_timestamp
            )
        AND (
            `to` IN (
                SELECT
                    hexAddress
                FROM
                    tron.accounts
            )
        )
        AND (
            contract_address IN (
                :contract_address
            )
        )", ['min_block_timestamp' => $min_block_timestamp.'000','max_block_timestamp'=>$dataTimestamp["updatetime"].'000','contract_address'=>self::CONTRACT]);
       foreach ($results as $key => $value) {
          $tUrl="http://127.0.0.1:80/api/receiveERC?transaction_id=".$value->transaction_id."&to=".$value->to."&contract_address=".$value->contract_address;
            file_get_contents($tUrl);
       }

        return 1;
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
}
