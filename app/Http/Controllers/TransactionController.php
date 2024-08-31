<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\{Transaction,Outil};
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    //
    private $queryName = "transactions";

     public function save(Request $request)
    {
        try {
            return DB::transaction(function () use ($request)
            {
                DB::beginTransaction();
                $errors =null;
                $item = new Transaction();
                // $user = Auth::user();
                if (!empty($request->id))
                {
                    $item = Transaction::find($request->id);
                }
                if (empty($request->client_id))
                {
                    $errors = "Renseignez le client pour l'opération";
                }
                // if (empty($request->telephone))
                // {
                //     $errors = "Renseignez le telephone";
                // }
                $item->debit = $request->debit;
                $item->credit = $request->credit;
                $item->client_id = $request->client_id;
                // $item->user_id = $user->id;
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
