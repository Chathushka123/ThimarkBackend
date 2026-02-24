<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Style;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Resources\StyleResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\StyleWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\StyleCreateValidator;
use App\Http\Validators\StyleUpdateValidator;
use App\Soc;
use Illuminate\Support\Facades\Log;

class StyleRepository
{
  const DISCARD_COLUMNS = [2, 4, 5, 6];

  const FIELD_MAPPING = [
    'style_code' => 'style_code',
    'description' => 'description',
    'route_code' => ['rule' => 'foreign', 'model' => 'Routing', 'db_field' => 'routing_id']
  ];

  public function show(Style $style)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new StyleWithParentsResource($style),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {       
    $rec['size_fit'] = json_encode(Utilities::createStyleString($rec['size_fit_json']));
    $rec['size_fit_json'] = json_encode($rec['size_fit_json']);

    $validator = Validator::make(
      $rec,
      StyleCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['size_fit'] = json_decode($rec['size_fit']);
    $rec['size_fit_json'] = json_decode($rec['size_fit_json']);
    $rec['style_code'] = Str::upper($rec['style_code']);
    try {
      $model = Style::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  private static function _validateStyleUpdate($style_id, $size_fit_json){
    // if already used 
    Log::info($style_id);
    $soc_style = Soc::where('style_id', $style_id)->get();

    foreach($soc_style as $rec){
      $soc = Soc::findOrFail($rec->id);
      if($soc->count > 0){	
        if($soc->packing_list_socs->count() > 0){
        throw new Exception("Packing List already progressed, Modifications Not Allowed.");
        }
        else if( $soc->fpos->combine_order->count() > 0){
          
        throw new Exception("Combined Order alredy , Modifications Not Allowed.".$soc->fpos->id."");
        }
      }
      }


  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Style::findOrFail($model_id);
    Utilities::validateCode($model->style_code, $rec['style_code'], "Style Code");

    // if(isset($rec['updated_at'])){
    //   if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
    //     $entity = (new \ReflectionClass($model))->getShortName();
    //     throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    //   }
    // }
      
    $rec['size_fit'] = json_encode(Utilities::createStyleString($rec['size_fit_json']));
    $rec['size_fit_json'] = json_encode($rec['size_fit_json']);   

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      StyleUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }

    self::_validateStyleUpdate($model_id, $rec['size_fit']);
      
    $rec['size_fit'] = json_decode($rec['size_fit']);
    $rec['size_fit_json'] = json_decode($rec['size_fit_json']);
    try {
      $model->update($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function createMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $rec) {
      $parent_key = array_search("!PARENT_KEY!", $rec);
      if ($parent_key) {
        $rec[$parent_key] = $master_id;
      }
      $ret[] = self::createRec($rec);
    }

    return $ret;
  }

  public static function updateMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $index => $body) {
      // below loop only executes once. foreach is used to extract [key, value] pair
      foreach ($body as $child_id => $rec) {
        $parent_key = array_search("!PARENT_KEY!", $rec);
        if ($parent_key) {
          $rec[$parent_key] = $master_id;
        }
        $ret[] = self::updateRec($child_id, $rec);
      }
    }

    return $ret;
  }

  public static function deleteRecs(array $recs)
  {
    Style::destroy($recs);
  }

  public function getStylesByFppoAndCutNo($fppo_id, $cut_no = null)
  {
    // $data = [
    //   "A" => ["id" => 12, "name" => "test1"],
    //   "B" => ["id" => 13, "name" => "test2"]
    // ];
    // data_fill($data, '*.cut_id', 1);

    // return $data;

    $cut_plans = CutPlan::select('id')
      ->distinct()
      ->where('fppo_id', $fppo_id)
      ->where('cut_no', 'LIKE', (is_null($cut_no) ? '%' : $cut_no))
      ->get()
      ->toArray();
    $cut_update_ids = CutUpdate::select('id')->distinct()->whereIn('cut_plan_id', [$cut_plans])->get()->toArray();
    $styles = Style::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
      $q->whereIn('id', $cut_update_ids);
    })->get();

    return StyleResource::collection($styles);
  }

  public static function importExcel($fileNameWithPath)
  {
    $lines = Utilities::readFile($fileNameWithPath);
    Utilities::discardColumns($lines, self::DISCARD_COLUMNS);
    $lines = Utilities::removeEmptyLines($lines);
    $header = Utilities::extractHeader($lines);
    $originalHeader = $header;
    $header = Utilities::prepareHeader($header, self::FIELD_MAPPING);

    $prev_style_code = $lines[0][0];
    $qty_json = [];
    foreach ($lines as $key => $line) {
      try {
        if ($prev_style_code == $line[0]) {
          $qty_json[] = $line[2];
        } else {
          if ($style = Style::where('style_code', $prev_style_code)->first()) {
            $style->style_code = $prev_style_code;
            $style->description = $lines[$key - 1][1];
            $style->size_fit = ($qty_json);
            $style->save();
            $info = 'Existing Style was updated';
          } else {
            $style = new Style();
            $style->style_code = $prev_style_code;
            $style->description = $lines[$key - 1][1];
            $style->size_fit = ($qty_json);
            $style->save();
            $info = '';

            // $rec = [
            //   'style_code' => $prev_style_code,
            //   'description' => $lines[$key - 1][1],
            //   'size_fit' => $qty_json
            // ];
            // StyleRepository::createRec($rec);
          }
          $qty_json = [];
          $ret[] = ["status" => "success", "data" => $prev_style_code, "info" => $info];
        }
        $prev_style_code = $line[0];
      } catch (Exception $e) {
        $ret[] = ["status" => "error", "data" => $prev_style_code, "info" => $e->getMessage()];
      }
    }

    return response()->json($ret, 200);
  }
}
