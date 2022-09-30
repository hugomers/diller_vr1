<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClientsContoller extends Controller
{
 


    public function index(Request $request){
        $date = is_null($request->date) ? "no recibo nada" : $request->date;

        return response()->json($date);
    
    }
}
