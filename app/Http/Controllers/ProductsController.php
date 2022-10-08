<?php
namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\returnSelf;

class ProductsController extends Controller
{

  private $conn = null;

public function __construct(){
  $access = env("ACCESS_FILE");
  if(file_exists($access)){
  try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
      }catch(\PDOException $e){ die($e->getMessage()); }
  }else{ die("$access no es un origen de datos valido."); }
}

public function products(request $request){
  $date = is_null($request->date) ? date('Y-m-d', time()) : $request->date; //SE OBTIENE LA FECHA EN LA QUE SE VA A TRABAJAR
  $saveprices = null;
  $goals = [
    "fsol"=>[],
    "vizapp"=>[
        "ins"=>[],
        "upd"=>[]
      ]   ]; //SE almacenan los valores en cada uno de ellos
  $fails = []; //SE ALMACENARAN LOS ERRORES O EN CASO DE NO HABER NADA PARA MODIFICAR O PARA INSERTAR
  $products = [];//SE CREA EL CONTENEDOR DE EL ARREGLO DE PRODUCTOS

  try{
    $sql = "SELECT * FROM F_ART  WHERE FUMART >= #".$date."#";
    $exec = $this->conn->prepare($sql);
    $exec -> execute();
    $filasFsol=$exec->fetchall(\PDO::FETCH_ASSOC);
    $fprices = "SELECT F_LTA.* FROM F_LTA INNER JOIN F_ART ON F_ART.CODART = F_LTA.ARTLTA WHERE FUMART >= #".$date."# ";
    $exec = $this->conn->prepare($fprices);
    $exec -> execute();
    $fsprice=$exec->fetchall(\PDO::FETCH_ASSOC);
   }catch (\PDOException $e){ die($e->getMessage());}
   if($filasFsol){
   $colsTabProds = array_keys($filasFsol[0]);
   $reply = $this->fsolupdate($filasFsol,$fsprice);
   foreach($filasFsol as $row){
      foreach($colsTabProds as $col){ $row[$col] = utf8_encode($row[$col]); }
      $old = DB::table('products')->where('code', $row['CODART'])->first(); // COMPRUEBO SI HAY ARTICULOS DE ESAS FECHAS QUE EXISTAN EN MYSQL
      $ptosm = null;//product to save on mysql
      $caty = DB::table('product_categories as PC')// SE BUSCA LA CATEGORIA DE EL PRODUCTO EN MYSQL
       ->join('product_categories as PF', 'PF.id', '=','PC.root')
       ->where('PC.alias', $row['CP1ART'])
       ->where('PF.alias', $row['FAMART'])
       ->value('PC.id'); 
      if($caty){//debe de existir la categoria
                $status = $row['NPUART'] == 0 ? 1 : 3; //SE CAMBIA EL STATUS EN MYSQL PARA INSERTARLO
                $unit_assort = DB::table('units_measures')->where('name',$row['CP3ART'])->value('id');
                $ptosm = [
                   "id"=>null,
                   "short_code"=>INTVAL($row['CCOART']),
                   "code" =>mb_convert_encoding((string)$row['CODART'], "UTF-8", "Windows-1252"),
                   "barcode" =>$row['EANART'],
                   "description"=>mb_convert_encoding((string)$row['DESART'], "UTF-8", "Windows-1252"),
                   "label"=>mb_convert_encoding((string)$row['DEEART'], "UTF-8", "Windows-1252"),
                   "reference" =>mb_convert_encoding((string)$row['REFART'], "UTF-8", "Windows-1252"),
                   "pieces" =>INTVAL($row['UPPART']),
                   "cost"=>$row['PCOART'],
                   "created_at" =>$row['FALART'],
                   "updated_at" =>$row['FUMART'],
                   "default_amount" =>1,
                   "_kit" =>NULL,
                   "picture" =>NULL,
                   "_provider" =>INTVAL($row['PHAART']),
                   "_category" =>INTVAL($caty),
                   "_maker" =>INTVAL($row['FTEART']),
                   "_unit_mesure" =>INTVAL($row['UMEART']),
                   "_state" =>INTVAL($status),
                   "_product_additional" =>NULL,
                   "_assortment_unit"=>$unit_assort
                ];//SE CONSTRUYE EL ARREGLO DE LOS PRODUCTOS A COMO SE DEBEN DE INSERTAR EN MYSQL
                if($old){
                   // codigo para actualizar en mysql
                    $ptosm["id"]=$old->id;
                    $ptosm["updated_at"]=now();
                    $ptosm["created_at"]=$old->created_at;
                    $updt = DB::table('products')->where('id', $old->id)->update($ptosm) ;
                    $goals["vizapp"]["upd"][]= ["art" =>$ptosm];             
                    $ids [] = $ptosm["id"];
                    $saveprices = $this->pricessave($ids);
                }else{// insertar en mysql    
                  try{//SE MUESTRA LOS ARTICULO QUE SE INSERTARAN                     
                      $new []=[ "art" => $ptosm];
                      $ptosm["created_at"]=now();
                      $ptosm["updated_at"]=now();
                      $insproduct = DB::table('products')->insert($ptosm);
                      $goals["vizapp"]["ins"][] = ["art" => $new];
                      }catch (\Exception $e) {$fails[]= $e ->getMessage();}
                      }// o almacenar lo que voy a insertar                                                                                                                                                                                     
       }else{$fails[] = "{$row['CODART']}: La categoria {$row['CP1ART']} de la familia {$row['FAMART']}, no se encuentra en VizApp";}//EN CASO DE NO TENER LA CATEGORIA CORRECTA MANDARA UNA ALERTA
       $products[] = $ptosm;
       $goals["fsol"][] = $ptosm;
   }
   $newprices = $this->pricesnew($date);
   return response()->json([
        "row" => $goals,
        "fails" =>$fails,
        "historicsprices" =>$saveprices,
        "pricesnuevos" =>$newprices,
        "replicacion" =>$reply
        ]);
   }else{ return response()->json(["message"=>"NO HAY NADA QUE ACTUALIZAR A PARTIR DEL DIA ".$date.""]);}
}

public function pricessave($ids){
  foreach( $ids as $id){
    // se obtiene el producto con precios
    $product_prices = DB::select(DB::raw("SELECT
              PP._product as _product,
              P.code as code,
              P.description as descriptionrow,
              P.cost AS costo,
              MAX(IF(PP._rate = 1, PP.price, 0)) AS MENUDEO,
              MAX(IF(PP._rate = 2, PP.price, 0)) AS MAYOREO,
              MAX(IF(PP._rate = 3, PP.price, 0)) AS DOCENA,
              MAX(IF(PP._rate = 4, PP.price, 0)) AS CAJA,
              MAX(IF(PP._rate = 5, PP.price, 0)) AS ESPECIAL,
              MAX(IF(PP._rate = 6, PP.price, 0)) AS CENTRO,
              MAX(IF(PP._rate = 7, PP.price, 0)) AS AAA
              FROM product_prices PP
              INNER JOIN products P ON PP._product = P.id
              WHERE _product = $id
              GROUP BY PP._product, P.code")
    );
      // creamos la fila para insertar en historicos
    foreach($product_prices as $col){
        $priceshis=null;
        $priceshis = [
          "created_at" =>now(),
          "_product"=>$col->_product,
          "code" =>$col->code,
          "prices_log" => json_encode([
            "code"=>$col->code,
            "description"=>$col->descriptionrow,
            "MENUDEO" => $col->MENUDEO,
            "MAYOREO" => $col->MAYOREO,
            "DOCENA" =>$col->DOCENA,
            "CAJA" =>$col->CAJA,
            "ESPECIAL" =>$col->ESPECIAL,
            "CENTRO" =>$col->CENTRO,
            "AAA" =>$col->AAA,
            "COSTO"=>$col->costo
          ])
        ];                               
      }
  }
  if($product_prices){
    DB::connection('historics')->table('price_historic_products')->insert($priceshis);//SE GUARDA EN EL HISTORICO DE LOS PRECIOS EN LA OTRA BASE DE DATOS
    return "Se Guardo Correctamente el Log de Los Precios";
    }else{return "NO SE GUARDO NADA";}
}


public function pricesnew($date){
  $goals = [];
  $fails =  [];
  try{
      $fprices = "SELECT F_LTA.ARTLTA AS ARTICULO, F_LTA.TARLTA AS TARIFA, F_LTA.PRELTA AS PRECIO  FROM F_LTA INNER JOIN F_ART ON F_ART.CODART = F_LTA.ARTLTA WHERE FUMART >= #".$date."# ";
      $exec = $this->conn->prepare($fprices);
      $exec -> execute();
      $fsprice=$exec->fetchall(\PDO::FETCH_ASSOC);
      }catch (\PDOException $e){ die($e->getMessage());}
  foreach($fsprice as $prices){
        $_product = DB::table('products')->where('code',$prices['ARTICULO'])->value('id');
        $price []  = [
                      "_product" => $_product,
                      "_type" => 1,
                      "_rate" => INTVAL($prices['TARIFA']),
                      "price" => DOUBLEVAL($prices['PRECIO'])
                      ];
         }

     try{//SE MUESTRA LOS ARTICULO QUE SE INSERTARAN   
    DB::table('product_prices')->where('_product', $_product)->delete();
    DB::table('product_prices')->insert($price);
  }catch (\Exception $e) {$fails[]= "no se insertaron debido a un problema con la insercion de productos";}
  $goals[] = "Se Insertaron Correctamente los Precios";
  if($fails){
 return response()->json([ "fail"=>$fails]); }else{return response()->json(["row" => $goals]);}
  // $res = [
  //       "row" => $goals,
  //       "fail" => $fails
  //       ];
  // return $res;
}

public function fsolupdate($filasFsol,$fsprice){
  $complete =[];
  $failed = [];
  $stores = DB::table('stores')->where('_state', 1)->where('_type', 2)->get();

  $colsTabProds = array_keys($filasFsol[0]);
  foreach($filasFsol as $product){foreach($colsTabProds as $col){ $product[$col] = utf8_encode($product[$col]);}
    $productos []= $product;}
    foreach($fsprice as $price)
      {$prices [] = $price;}

    foreach($stores as $store){
      $domain = $store->local_domain.":".$store->local_port;
    
      // $url ="$domain/diller/public/api/products/access";
      $url = "192.168.12.205:1619/diller/public/api/products/access";
      $ch = curl_init($url);
      $data = json_encode(["products" => $productos,"prices" => $prices]);
      curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
      $exec = curl_exec($ch);

      curl_close($ch);
    //   if($exec){$complete[]=$store->alias;}else{$failed[]=$store->alias;}
    // }
    // $res = [
    //   "completado" => $complete,
    //   "fail" => $failed
    // ];

    // return $res;
    return $ch;
}}

public function replace(Request $request){

  $updates = $request->all();
  
    foreach($updates as $update){

      $original = "'".$update['original']."'";
      $upd = "'".$update['edit']."'";
      
      try{
        $upda = "UPDATE F_LFA SET ARTLFA = $upd WHERE ARTLFA = $original";
        $exec = $this->conn->prepare($upda);
        $exec -> execute();
        $updsto = "UPDATE F_LFR SET ARTLFR = $upd WHERE ARTLFR = $original";
        $exec = $this->conn->prepare($updsto);
        $exec -> execute();
        $updlta = "UPDATE F_LEN SET ARTLEN = $upd WHERE ARTLEN = $original";
        $exec = $this->conn->prepare($updlta);
        $exec -> execute();
        $updltr = "UPDATE F_LTR SET ARTLTR = $upd WHERE ARTLTR = $original";
        $exec = $this->conn->prepare($updltr);
        $exec -> execute();
        $updcin = "UPDATE F_LFB SET ARTLFB = $upd WHERE ARTLFB = $original";
        $exec = $this->conn->prepare($updcin);
        $exec -> execute();
        $upddev = "UPDATE F_LFD SET ARTLFD = $upd WHERE ARTLFD = $original";
        $exec = $this->conn->prepare($upddev);
        $exec -> execute();

      }catch (\PDOException $e){ die($e->getMessage());}
      
    }


  return response()->json("ya esta");
}

public function minmax(){
  $actual =DB::connection('actual')->table('product_stock as PS')
                                ->join('products as P','P.id','=','PS._product')
                                ->join('workpoints as W','W.id','=','PS._workpoint')->where('PS.min','>',0)
                                ->select('P.code AS codigo','PS.min AS minimo','PS.max AS maximo','W.dominio AS dominio')
                                ->get();

  foreach($actual as $row){
    $minmax [] = $row;
    $update = DB::table('product_stock as PS')
              ->join('products as P','P.id','=','PS._product')
              ->join('warehouses as W','W.id','=','PS._warehouse')
              ->join('stores as S','S.id','=','W._store')
              ->where('P.code',$row->codigo)
              ->where('S.local_domain',$row->dominio)
              ->where('W._type',1)
              ->where('PS._min','!=', 0)
              ->update(['_min'=>$row->minimo,
                        '_max'=>$row->maximo,
                        ]);
  }

  

return $update;

}


}