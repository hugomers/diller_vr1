<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS_FILE");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    }

}
