<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */

  private $conn = null;
  private $con = null;

//   protected function scheduleTimezone()
// {
//     return 'America/Mexico_City';
// }


protected function schedule(Schedule $schedule){


  $access = env("ACCESS_FILE");
  if(file_exists($access)){
  try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
      }catch(\PDOException $e){ die($e->getMessage()); }
  }else{ die("$access no es un origen de datos valido."); }


  $schedule->call(function (){
    $date = carbon::now()->format('d-m-Y');
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
        DB::table('withdrawals')->insert($whith);
       }
    
  })->dailyAt('19:38');

  $schedule->call(function (){
    $date = carbon::now()->format('d-m-Y');
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
        DB::table('withdrawals')->insert($whith);
       }
    
  })->weeklyOn(0,'23:30');






}

}
