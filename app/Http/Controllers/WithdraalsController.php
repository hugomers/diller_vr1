<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\Doctrine\CarbonImmutableType;

class WithdraalsController extends Controller
{

    private $conn ;
    public function __construct(){
        $access = env("ACCESS_FILE");
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }


    }    

    public function replywithdrawals(request $request){
        $date = is_null($request->date) ? date('Y-m-d', time()) : $request->date; 
        try{
            $whithdrawals = "SELECT * FROM F_RET WHERE FECRET = #".$date."#";
            $exec = $this->conn->prepare($whithdrawals);
            $exec -> execute();
            $wth=$exec->fetchall(\PDO::FETCH_ASSOC);
          }catch (\PDOException $e){ die($e->getMessage());}
            if($wth){
              foreach($wth as $wt){
                $provider = $wt['PRORET'] != 0 ? $wt['PRORET'] : 800  ;
                $whith [] = [
                  "code"=>$wt['CODRET'],
                  "_store"=>intval(12),
                  "_cash"=>$wt['CAJRET'],
                  "description"=>$wt['CONRET'],
                  "import"=>$wt['IMPRET'],
                  "created_at"=>$wt['FECRET'],
                  "_provider"=>INTVAL($provider)
                ];
              }
              $insert = DB::table('withdrawals')->insert($whith);
             }
    }

    public function index(){
        $inmutable = CarbonImmutable::now()->startOfWeek()->format('d-m-Y H:i:s');
        
            return $inmutable;
    }

    }