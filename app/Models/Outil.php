<?php

namespace App\Models;


use Illuminate\Support\Facades\DB; 
use Illuminate\Database\Eloquent\Model;

use Illuminate\Http\Request;

use App\Exports\DatasExport;
use Barryvdh\DomPDF\Facade as PDF;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use MPDF;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Mail;
use App\Mail\Maileur;

class Outil extends Model
{

    public static $queries = array(
        "users"                       =>  " id,name,email,role{id,nom}",
        "clients"                     =>  " id,nom_complet,telephone,adresse",
        "transactions"                =>  " id,debit,credit,solde,client{id,nom_complet,telephone,adresse}",
    );

    public static function redirectgraphql($itemName, $critere,$liste_attributs)
    {
        $path='{'.$itemName.'('.$critere.'){'.$liste_attributs.'}}';
        return redirect('graphql?query='.urlencode($path));
    }
    public static function loyerht($montant_loyer_ttc,$tva,$tom,$tlv,$cc)
    {
       // Calculer la somme des taux de taxes en pourcentage
        $tva_rate = $tva ? $tva->value / 100 : 0;
        $tom_rate = $tom ? $tom->value / 100 : 0;
        $tlv_rate = $tlv ? $tlv->value / 100 : 0;
        $cc_rate = $cc ? $cc / 100 : 0;

        // Calculer le total des taux de taxes
        $total_taxes_rate = $tva_rate + $tom_rate + $tlv_rate + $cc_rate;

        // Calculer le montant HT
        $loyer_ht = $montant_loyer_ttc / (1 + $total_taxes_rate);

        return $loyer_ht;
    }

    public static function loyerttc($montant_loyer_ht,$tva,$tom,$tlv,$cc)
    {
       $tva =  $tva != false ? $tva = $tva->value/100 : 0;
       $tom =  $tom != false ? $tom = $tom->value/100 : 0;
       $tlv =  $tlv != false ? $tlv = $tlv->value/100 : 0;
       $cc =   $cc != false ?  $cc = $cc/100 : 0;
        $somme_tva = 1 + ($tva+$tom+$tlv+$cc);
        $loyer_ttc = $montant_loyer_ht * $somme_tva;
        return $loyer_ttc;
    }

    public static function getResponseError(\Exception $e)
    {
        return response()->json(array(
            'errors'          => [config('env.APP_ERROR_API') ? $e->getMessage() : config('env.MSG_ERROR')],
            'errors_debug'    => [$e->getMessage()],
            'errors_line'    => [$e->getLine()],
        ));
    }
    public static function getOneItemWithGraphQl($queryName, $id_critere, $justone = true)
    {
        $guzzleClient = new \GuzzleHttp\Client([
            'defaults' => [
                'exceptions' => true
            ]
        ]);

        $critere = (is_numeric($id_critere)) ? "id:{$id_critere}" : $id_critere;
        $queryAttr = Outil::$queries[$queryName];
        $apiUrl = self::getAPI(); // Utilisation de l'URL dynamique
        $response = $guzzleClient->get("{$apiUrl}/graphql?query={{$queryName}({$critere}){{$queryAttr}}}");
        $data = json_decode($response->getBody(), true);
        return ($justone) ? $data['data'][$queryName][0] : $data;
    }
    public static function getItemWithGraphQl($queryName, $start,$end, $justone = true)
    {
        $guzzleClient = new \GuzzleHttp\Client([
            'defaults' => [
                'exceptions' => true
            ]
        ]);
        $critere = "created_at_start:\"{$start}\",created_at_end:\"{$end}\"";
        $queryAttr = Outil::$queries[$queryName];
        $apiUrl = self::getAPI(); // Utilisation de l'URL dynamique
        $response = $guzzleClient->get("{$apiUrl}/graphql?query={{$queryName}({$critere}){{$queryAttr}}}");
        $data = json_decode($response->getBody(), true);
        $start = date("d/m/y",strtotime($start));
        $end = date("d/m/y",strtotime($end));
        $data['detail_journals'] = $data['data']['detail_journals'];
        $data['start'] = $start;
        $data['end'] = $end;
        // dd($data);
        return $data;
    }

    public static function getItemSituationProprioWithGraphQl($queryName, $start = null, $end = null, $proprioId, $justone = true)
    {
        $guzzleClient = new \GuzzleHttp\Client([
            'defaults' => [
                'exceptions' => true
            ]
        ]);
    
        // Créer le critère avec ou sans dates
        $critere = "proprio_id_entree:{$proprioId}";
        if ($start && $end) {
            $critere .= ",created_at_start:\"{$start}\",created_at_end:\"{$end}\"";
        }
        
        $queryAttr = Outil::$queries[$queryName];
        $apiUrl = self::getAPI(); // Utilisation de l'URL dynamique
    
        $response = $guzzleClient->get("{$apiUrl}/graphql?query={{$queryName}({$critere}){{$queryAttr}}}");
        $data = json_decode($response->getBody(), true);
        
        return $data;
    }
    
    public static function getAPI()
    {
        return config('env.APP_URL');
    }
    public static function formatdate()
    {
        return "Y-m-d H:i:s";
    }
    public static function premereLettreMajuscule($val)
    {
        return ucfirst($val);
    }
    //Formater le prix
    public static function formatPrixToMonetaire($nbre, $arrondir = false, $avecDevise = false)
    {
        //Ajouté pour arrondir le montant
        if ($arrondir == true) {
            // $nbre = Outil::enleveEspaces($nbre);
            $nbre = round($nbre);
        }
        $rslt = "";
        $position = strpos($nbre, '.');
        if ($position === false) {
            //---C'est un entier---//
            //Cas 1 000 000 000 Ã  9 999 000
            if (strlen($nbre) >= 9) {
                $c = substr($nbre, -3, 3);
                $b = substr($nbre, -6, 3);
                $d = substr($nbre, -9, 3);
                $a = substr($nbre, 0, strlen($nbre) - 9);
                $rslt = $a . ' ' . $d . ' ' . $b . ' ' . $c;
            } //Cas 100 000 000 Ã  9 999 000
            elseif (strlen($nbre) >= 7 && strlen($nbre) < 9) {
                $c = substr($nbre, -3, 3);
                $b = substr($nbre, -6, 3);
                $a = substr($nbre, 0, strlen($nbre) - 6);
                $rslt = $a . ' ' . $b . ' ' . $c;
            } //Cas 100 000 Ã  999 000
            elseif (strlen($nbre) >= 6 && strlen($nbre) < 7) {
                $a = substr($nbre, 0, 3);
                $b = substr($nbre, 3);
                $rslt = $a . ' ' . $b;
                //Cas 0 Ã  99 000
            } elseif (strlen($nbre) < 6) {
                if (strlen($nbre) > 3) {
                    $a = substr($nbre, 0, strlen($nbre) - 3);
                    $b = substr($nbre, -3, 3);
                    $rslt = $a . ' ' . $b;
                } else {
                    $rslt = $nbre;
                }
            }
        } else {
            //---C'est un décimal---//
            $partieEntiere = substr($nbre, 0, $position);
            $partieDecimale = substr($nbre, $position, strlen($nbre));
            //Cas 1 000 000 000 Ã  9 999 000
            if (strlen($partieEntiere) >= 9) {
                $c = substr($partieEntiere, -3, 3);
                $b = substr($partieEntiere, -6, 3);
                $d = substr($partieEntiere, -9, 3);
                $a = substr($partieEntiere, 0, strlen($partieEntiere) - 9);
                $rslt = $a . ' ' . $d . ' ' . $b . ' ' . $c;
            } //Cas 100 000 000 Ã  9 999 000
            elseif (strlen($partieEntiere) >= 7 && strlen($partieEntiere) < 9) {
                $c = substr($partieEntiere, -3, 3);
                $b = substr($partieEntiere, -6, 3);
                $a = substr($partieEntiere, 0, strlen($partieEntiere) - 6);
                $rslt = $a . ' ' . $b . ' ' . $c;
            } //Cas 100 000 Ã  999 000
            elseif (strlen($partieEntiere) >= 6 && strlen($partieEntiere) < 7) {
                $a = substr($partieEntiere, 0, 3);
                $b = substr($partieEntiere, 3);
                $rslt = $a . ' ' . $b;
                //Cas 0 Ã  99 000
            } elseif (strlen($partieEntiere) < 6) {
                if (strlen($partieEntiere) > 3) {
                    $a = substr($partieEntiere, 0, strlen($partieEntiere) - 3);
                    $b = substr($partieEntiere, -3, 3);
                    $rslt = $a . ' ' . $b;
                } else {
                    $rslt = $partieEntiere;
                }
            }
            if ($partieDecimale == '.0' || $partieDecimale == '.00' || $partieDecimale == '.000') {
                $partieDecimale = '';
            }
            $rslt = $rslt . '' . $partieDecimale;
        }
        if ($avecDevise == true) {
            $formatDevise = Outil::donneFormatDevise();
            $rslt = $rslt . '' . $formatDevise;
        }
        return $rslt;
    }
    public static function donneFormatDevise()
    {
        $retour = ' F CFA';
        return $retour;
    }

    public static function getOperateurLikeDB()
    {
        return config('database.default')=="mysql" ? "like" : "ilike";
    }
}
/*select * from reservations where programme_id in (select id from programmes where id=1112 and ((quotepart_pourcentage is not null && quotepart_pourcentage!=0) or (quotepart_valeur is not null && quotepart_valeur!=0)));*/
