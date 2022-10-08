<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsContoller extends Controller
{
 
    public function __construct(){
        $access = env("ACCESS_FILE");
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
      }


    public function index(Request $request){
        $goals = [
            "ins"=>[],
            "upd"=>[]
                ]; 
        $fails = [];
         $clientes = [];
        $date = $request->date;
        try{
        $clien = "SELECT * FROM F_CLI WHERE FUMCLI >= #".$date."#";
        $exec = $this->conn->prepare($clien);
        $exec -> execute();
        $clients = $exec->fetchall(\PDO::FETCH_ASSOC);
    }catch (\PDOException $e){ die($e->getMessage());}
    if($clients){
        $colsTabProds = array_keys($clients[0]);
        $reply = $this->replycli($clients);
        foreach($clients as $client){
            foreach($colsTabProds as $col){ $client[$col] = utf8_encode($client[$col]); }
            $inscli = null;
            $state = $client['NVCCLI'] = 0 ? 1 : 2;
            $payment = DB::table('payment_methods')->where('alias',$client['FPACLI'])->value('id');
            $type = DB::table('client_types')->where('alias',$client['TCLCLI'])->value('id');
            $oldcli = DB::table('clients')->where('id',$client['CODCLI'])->first();
            $inscli  = [
                "id" => $client['CODCLI'],
                "name"=> $client['NOFCLI'],
                "address"=> $client['DOMCLI']." ".$client['POBCLI']." C.P.".$client['CPOCLI']." DEL.".$client['PROCLI'],
                "celphone"=>intval($client['TELCLI']),
                "phone"=>null,
                "RFC"=>null,
                "created_at"=>$client['FALCLI'],
                "updated_at"=>$client['FUMCLI'],
                "barcode"=>null,
                "_payment"=>$payment,
                "_rate"=>intval($client['TARCLI']),
                "_type"=>$type,
                "_state"=>$state,
                        ];
            if($oldcli){
                $inscli['updated_at']=now();
                DB::table('clients')->where('id',$oldcli->id)->update($inscli);
                $goals['upd'][]=$inscli;
            }else{
                try{
                $new[]=[ "cli" =>$inscli];
                DB::table('clients')->insert($inscli);
                $goals['ins'][]=$inscli;
            }catch (\Exception $e) {$fails[]= $e ->getMessage();}
            }
        $clientes []= $inscli;                
        }

        $resp = [
            "rows"=>$goals,
            "fails"=>$fails,
            "reply"=>$reply
        ];

      return response()->json($resp);
        }else{ return response()->json("NO HAY NADA QUE MODIFICAR EL DIA $date");}
    }
    public function replycli($clients){
        $complete =[];
        $failed = [];
        // $stores = DB::table('stores')->where('_state', 1)->where('_type', 2)->get();
      
        $colsTabProds = array_keys($clients[0]);
        foreach($clients as $client){foreach($colsTabProds as $col){ $client[$col] = utf8_encode($client[$col]);}
          $clientes []= $client;}

      
        //   foreach($stores as $store){
        //     $domain = $store->local_domain.":".$store->local_port;
          
        //     $url ="$domain/diller/public/api/clients/c";
            $url ="192.168.12.205:1619/diller/public/api/clients/c";
            $ch = curl_init($url);
            $data = json_encode(["clients" => $clientes]);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            $exec = curl_exec($ch);
      
            curl_close($ch);

            return response()->json($exec);;

    }
}
