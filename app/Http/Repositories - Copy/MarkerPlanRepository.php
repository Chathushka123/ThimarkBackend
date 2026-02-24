<?php

namespace App\Http\Repositories;
use Illuminate\Http\Request;
use App\Exceptions\ConcurrencyCheckFailedException;
use PDF;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;
use App\Exceptions\GeneralException;
use Illuminate\Support\Facades\Log;
use App\Style;


class MarkerPlanRepository
{

   // private $qr;

    public function __construct()
    {
        //$this->qr = new QrCodeController();
    }

    public function getSearchByStyleFabric($style_code,$description,$fabric){
        $results = Style::select(
            'styles.style_code',
            'styles.description',
            'style_fabrics.fabric'
 
          )
            ->join('style_fabrics', 'style_fabrics.style_id', '=', 'styles.id')
            ->where('styles.style_code', 'LIKE', (is_null($style_code) ? '%' :  '%' . $style_code . '%'))->where('styles.style_code', 'LIKE', (is_null($style_code) ? '%' :  '%' . $style_code . '%'))
            ->where('styles.description', 'LIKE', (is_null($description) ? '%' :  '%' . $description . '%'))
            ->where('style_fabrics.fabric', 'LIKE', (is_null($fabric) ? '%' :  '%' . $fabric . '%'))
            ->get();

            return $results;
    }

    public function generateMarkerPlan($request){
        $ratio_array = [];
        $found =true;

        $style_code = $request->style_code;
        $max_plies = $request->max_plies;
        $max_length = $request->max_length;
        $consumption = $request->consumption;
        $fabric = $request->fabric;
        $sizeQty = $request->sizeQty;
        $type = $request->type;

        $values = array_column($sizeQty, 'qty');
        $sum = array_sum($values);
       // print_r($sum."/");


        
        $index =0;
        while($found){

            usort($sizeQty, function($a, $b) {
                return $a['qty'] <=> $b['qty'];
            });

            if($type == 1){
                $return_array = self::getOptimizeRatioByMaxQty($sizeQty,$max_length,$max_plies,$consumption);
            }else{
                $return_array = self::getOptimizeRatioByMaxLength($sizeQty,$max_length,$max_plies,$consumption);
            }
            
            
            $ratio = $return_array['ratio'];            
            $plies = $return_array['plies'];

            $ratio['total']=array_sum($ratio);
            $ratio['plies']=$plies;
            
            $ratio_array[$index] = $ratio;
            $index++;

            DB::table('marker_plan')->insert(['style_code'=>$style_code,'plies'=>$plies,'ratio_json'=>json_encode($ratio),'fabric'=>$fabric,'marker_pcs'=>array_sum($ratio)]);

            foreach($sizeQty as $key => $val){
                $size = $val['size'];
                if(isset($ratio[$size])){
                    $sizeQty[$key]['qty'] -= intval($ratio[$size])*intval($plies);
                    if($sizeQty[$key]['qty'] < 0){
                        $sizeQty[$key]['qty'] =0;
                    }
                } 
            }

            $values = array_column($sizeQty, 'qty');
            $sum = array_sum($values);
           // print_r($sum."/");
            if($sum == 0){
                $found = false;
            }
            
        }

        // foreach($ratio_array as $k => $v){
            
        // }



        return $ratio_array;


    }

    public static function getOptimizeRatio($qtyArray,$max_length,$max_plies,$consumption){
        $optimum_ratio = [];
        $max_qty =0;
        
        $plies=0;
        foreach($qtyArray as $key => $value){
            $ratio =[];
            $qty = intval($value['qty']);
            $exsits_max_plies = $qty;

            if($max_plies < $qty){
                $exsits_max_plies = intval($qty/$max_plies)*$max_plies;
            }
            if($qty > 0){
               
                for($i=$key; $i < sizeof($qtyArray); $i++){
                  if(intval(intval($qtyArray[$i]['qty'])/$qty) > 0){
                    $ratio[$qtyArray[$i]['size']] = intval(intval($qtyArray[$i]['qty'])/$qty);
                  }
                }
               
                if((intval(array_sum($ratio)*$exsits_max_plies) > $max_qty) && ((array_sum($ratio)*floatval($consumption)) <=$max_length)){
                    $max_qty = intval(array_sum($ratio)*$exsits_max_plies);
                    $optimum_ratio = $ratio;
                    $plies = $exsits_max_plies;
                  
                }
            }   
        }
        $data['ratio'] =$optimum_ratio;
        $data['plies'] = $plies;
        return $data;
    }

    public static function getOptimizeRatioByMaxQty($qtyArray,$max_length,$max_plies,$consumption){
        $optimum_ratio = [];
        $max_qty =0;
        
        $plies=0;
        $minval=1;
        foreach($qtyArray as $key => $value){
            $ratio =[];
            $qty = intval($value['qty']);

            if($qty > 0){
            
                for($index=$minval; $index <= $qty; $index++){
                
                    for($i=$key; $i < sizeof($qtyArray); $i++){
                        if(intval(intval($qtyArray[$i]['qty'])/$index) > 0){
                            $ratio[$qtyArray[$i]['size']] = intval(intval($qtyArray[$i]['qty'])/$index);
                        }
                    }
                
                    if((intval(array_sum($ratio)*$index) > $max_qty) && ((array_sum($ratio)*floatval($consumption)) <=$max_length)){
                        $max_qty = intval(array_sum($ratio)*$index);
                        $optimum_ratio = $ratio;
                        if(intval($index/$max_plies) == 0){
                            $plies=$index;
                        }else{
                            $plies = intval($index/$max_plies)*$max_plies;
                        }
                        
                    
                    }
                }
                $minval = intval($qty)+1;
            }   
        }
        
        $data['ratio'] =$optimum_ratio;
        $data['plies'] = $plies;
        return $data;
    }

    public static function getOptimizeRatioByMaxLength($qtyArray,$max_length,$max_plies,$consumption){
        
        $max_qty =0;
        $data=[];
        $data['ratio'] = [];
        $data['plies'] = [];
        
        $plies=0;
        
                for($index=1; $index <= $qtyArray[sizeof($qtyArray)-1]['qty']; $index++){
                    $ratio =[];
                    for($i=0; $i < sizeof($qtyArray); $i++){
                        if(intval(intval($qtyArray[$i]['qty'])/$index) > 0){
                            $ratio[$qtyArray[$i]['size']] = intval(intval($qtyArray[$i]['qty'])/$index);
                        }

                    }
                    
                    if($index > $max_plies){
                        $plies = $max_plies;
                    }else{
                        $plies =$index;
                    }
                
                    if((intval(array_sum($ratio)*$plies) > $max_qty) && ((array_sum($ratio)*floatval($consumption)) <=$max_length)){
                        
                        $max_qty = intval(array_sum($ratio)*$plies);
                        $data['ratio'] = $ratio;
                        $data['plies'] = $plies;
                    
                    }

                }
        
        
        return $data;
    }

    
}