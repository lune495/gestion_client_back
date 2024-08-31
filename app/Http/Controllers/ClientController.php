<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\{Client,Outil};
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    //
    private $queryName = "clients";

     public function save(Request $request)
    {
        try {
            return DB::transaction(function () use ($request)
            {
                DB::beginTransaction();
                $errors =null;
                $item = new Client();
                if (!empty($request->id))
                {
                    $item = Taxe::find($request->id);
                }
                if (empty($request->nom_complet))
                {
                    $errors = "Renseignez le nom du client";
                }
                // if (empty($request->telephone))
                // {
                //     $errors = "Renseignez le telephone";
                // }
                $item->nom_complet = $request->nom_complet;
                $item->telephone = $request->telephone;
                $item->adresse = $request->adresse;
                if (!isset($errors)) 
                {
                    $item->save();
                    $id = $item->id;
                }
                if (isset($errors))
                {
                    throw new \Exception($errors);
                }
                DB::commit();
                return  Outil::redirectgraphql($this->queryName, "id:{$id}", Outil::$queries[$this->queryName]);
          });
        } catch (exception $e) {            
             DB::rollback();
             return $e->getMessage();
        }
    }
}
