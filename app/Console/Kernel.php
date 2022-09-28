<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */

  private $conn = null;




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
    
  })->dailyAt('23:00');

  $schedule->call(function (){
    $date = CarbonImmutable::now()->startOfWeek();
    $datefsol = $date->format('d-m-Y');
    $datesql = $date->format('Y-m-d');
    $fecha = strval($datesql);

    try{
      $whithdrawals = "SELECT * FROM F_RET WHERE FECRET >= #".$datefsol."#";
      $exec = $this->conn->prepare($whithdrawals);
      $exec -> execute();
      $wth=$exec->fetchall(\PDO::FETCH_ASSOC);
    }catch (\PDOException $e){ die($e->getMessage());}

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('SET SQL_SAFE_UPDATES = 0');
        DB::table('withdrawals')->where('created_at','>=',$fecha)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        DB::statement('SET SQL_SAFE_UPDATES = 1');
        $idmax = DB::table('withdrawals')->max('id');
        DB::statement('ALTER TABLE withdrawals AUTO_INCREMENT = '.$idmax.'');
        // if($wth){
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
      // }   
       
    
  })->weeklyOn(0,'23:30');






}}


