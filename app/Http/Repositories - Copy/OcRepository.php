<?php

namespace App\Http\Repositories;

use App\CutPlan;
use App\Fpo;
use App\FpoCutPlan;
use App\Oc;
// use App\HashStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OcWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\OcCreateValidator;
use App\Http\Validators\OcUpdateValidator;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class OcRepository
{
  const DISCARD_COLUMNS = [];

  const FIELD_MAPPING = [
    'oc_no' => 'wfx_oc_no',
    'buyer_code' => ['rule' => 'foreign', 'model' => 'Buyer', 'db_field' => 'buyer_id'],
    'style_code' => ['rule' => 'foreign', 'model' => 'Style', 'db_field' => 'style_id']
  ];

  public function show(Oc $oc)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new OcWithParentsResource($oc),
      ],
      200
    );
  }

  private static function getValidationMessages()
  {
    return [
      'wfx_oc_no.required' => 'OC No is required',
      'wfx_oc_no.unique' => 'OC No has already been taken',
    ];
  }

  public static function createRec(array $rec)
  {
    $rec['qty_json'] = json_encode($rec['qty_json']);
    $validator = Validator::make(
      $rec,
      OcCreateValidator::getCreateRules(),
      self::getValidationMessages()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['qty_json'] = json_decode($rec['qty_json']);
    try {
      $model = Oc::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Oc::findOrFail($model_id);
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    if ($model->isReadOnly()) {
      throw new \App\Exceptions\GeneralException("Oc " . $model->wfx_oc_no . " is " . Oc::GetClientState($model->state));
    }
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_encode($rec['qty_json']);
    }
    Utilities::hydrate($model, $rec);
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_encode($rec['qty_json']);
    }
    $validator = Validator::make(
      $rec,
      OcUpdateValidator::getUpdateRules($model_id),
      self::getValidationMessages()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_decode($rec['qty_json']);
    }
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
    foreach ($recs as $id) {
      $model = Oc::findOrFail($id);
      if ($model->isReadOnly()) {
        throw new Exception("Oc " . $model->wfx_oc_no . " is " . Oc::GetClientState($model->state));
      }
      Oc::destroy([$id]);
    }
  }

  public static function actionOpen(Oc $oc)
  {
    try {
      $oc->update(['state' => 'OPEN']);
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      throw $e;
    }
  }

  public static function actionReadonly(Oc $oc)
  {
    try {
      $oc->update(['state' => 'READONLY']);
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      throw $e;
    }
  }

  public function lovOcs()
  {
    $column = 'wfx_oc_no';
    $item = [];
    $ocs = Oc::select(['id', $column])->orderBy($column)->get();
    foreach ($ocs as $key => $value) {
      $item[] = [$value['id'] => $value[$column]];
    }
    $ret = [$column => $item];

    return response()->json($ret, 200);
  }

  public function lovSocs(Oc $oc)
  {
    $column = 'wfx_soc_no';
    $oc->load(['socs' => function ($query) use ($column) {
      $query->select('id', $column, 'oc_id');
    }]);

    foreach ($oc->socs as $key => $value) {
      $item[] = [$value['id'] => $value[$column]];
    }
    $ret = [$column => $item];

    return response()->json($ret, 200);
  }

  public function getFpoList(Oc $oc)
  {
    $fpo_list = [];
    foreach ($oc->oc_colors as $oc_color) {
      foreach ($oc_color->socs as $soc) {
        foreach ($soc->fpos as $fpo) {
          $fpo_list[] = [
            'fpo_id' => $fpo->id,
            'fpo_no' => $fpo->wfx_fpo_no,
            'soc_id' => $soc->id,
            'soc_no' => $soc->wfx_soc_no,
            'utilized' => $fpo->utilized
          ];
        }
      }
    }
    return $fpo_list;
  }

  public function getLayPlanningHeader(Oc $oc)
  {
    $ret = [
      'no_of_laysheets' => $oc->lay_sheets()->count(),
      'buyer_code' => $oc->buyer->buyer_code,
      'buyer_department' => isset($oc->buyer->buyer_code) ? $oc->buyer->buyer_code : 'dummy department',
      'style_code' => $oc->style->style_code,
      'pack_color' => $oc->pack_color
    ];
    return $ret;
  }

  public function createLaySheet(Oc $oc, Request $request)
  {
    $oc->load('lay_sheets');
    $connected_lay_sheets = $oc->lay_sheets->count();
    $sheet_no = $connected_lay_sheets > 0 ? $connected_lay_sheets + 1 : 1;
    $max_plies = (int) $request->all()['max_plies'];


    try {
      DB::beginTransaction();

      $laySheet = LaySheetRepository::createRec(['sheet_no' => $sheet_no, 'oc_id' => $oc->id]);

      $markers = $request->all()['lay_marker_details'];
      $max_plies = $request->all()['max_plies'];
      $cut_marker_matrix = [];
      $run_no = 0;

      foreach ($markers as $index => $marker) {
        $total_plies = $marker['total_plies'];
        $number_of_cuts = ceil($total_plies / $max_plies);
        for ($cut_no = 1; $cut_no < ($number_of_cuts + 1); $cut_no++) {
          $cut_size = (($total_plies - $cut_no * $max_plies) >= 0) ? $max_plies : ($total_plies - ($cut_no - 1) * $max_plies);
          foreach ($marker['qty_json'] as $size => $marker_qty) {
            $cut_marker_matrix[($cut_no + $run_no)][$size] = $cut_size * $marker_qty;
          }
          $cut_marker_matrix[($cut_no + $run_no)]['max_plies'] = $cut_size;
          $cut_marker_matrix[($cut_no + $run_no)]['marker_name'] = $marker['marker_name'];
        }
        $run_no = $cut_no - 1;
        foreach ($marker['qty_json'] as $size => $marker_qty) {
          $ratio_json[$marker['marker_name']][$size] = $marker_qty;
        }
        $ratio_json[$marker['marker_name']]['total_plies'] = $marker['total_plies'];
      }

      foreach ($cut_marker_matrix as $cut_no => $detail) {
        $value_json = $detail;
        unset($value_json['max_plies']);
        unset($value_json['marker_name']);
        unset($ratio_json[$detail['marker_name']]['total_plies']);
        $cut_plan_id = CutPlanRepository::createRec([
          'cut_no' => $cut_no,
          'ratio_json' => $ratio_json[$detail['marker_name']],
          'value_json' => $value_json,
          'marker_name' => $detail['marker_name'],
          'max_plies' => $detail['max_plies'],
          'lay_sheet_id' => $laySheet->id
        ])->id;
        $cut_markers[$cut_no] = $cut_plan_id;
      }

      foreach ($request->all()['fpos'] as $fpo_id) {
        $fpo = Fpo::findOrFail($fpo_id);
        foreach ($fpo->qty_json as $size => $qty) {
          $fpo_qty_arr[$size][$fpo_id] = $qty;
        }
      }
      $combined_array = [];


      foreach ($request->all()['lay_marker_details'][0]['qty_json'] as $size => $temp) {

        $relaxed_fpo_arr = [];
        $start_index = 0;
        foreach ($fpo_qty_arr[$size] as $fpo_id => $fpo_qty) {
          $fpo_qty = (is_null($fpo_qty) ? 0 : $fpo_qty);
          $relaxed_fpo_arr = array_merge($relaxed_fpo_arr, array_fill($start_index, $fpo_qty, $fpo_id));
          $start_index = $fpo_qty;
        }

        $relaxed_cut_marker_matrix = [];
        $start_index = 0;
        foreach ($cut_marker_matrix as $cut_no => $details) {
          $relaxed_cut_marker_matrix = array_merge($relaxed_cut_marker_matrix, array_fill($start_index, $details[$size], $cut_no));
          $start_index = $details[$size];
        }

        $fpo_index = 0;
        foreach ($relaxed_cut_marker_matrix as $index => $value) {
          if (isset($relaxed_fpo_arr[$index])) {
            $fpo_index = $index;
          }
          if (isset($relaxed_fpo_arr[$fpo_index]) && isset($relaxed_cut_marker_matrix[$index])) {
            $combined_array[$size][] = $relaxed_fpo_arr[$fpo_index] . '!' . $relaxed_cut_marker_matrix[$index];
          }
        }

        $combined_array[$size] = isset($combined_array[$size]) ? array_count_values($combined_array[$size]) : 0;

        $processed_array = [];
        foreach ($combined_array as $size => $contents) {
          if ($contents != 0) {
            foreach ($contents as $fpo_cut => $qty) {
              $processed_array[$fpo_cut][$size] = $qty;
            }
          }
        }
      }

      $running_index = 1;
      $prev_fpo_id = "";
      foreach ($processed_array as $key => $value) {
        $fpo_id = explode('!', $key)[0];
        $cut_no = explode('!', $key)[1];
        $cut_plan_id = $cut_markers[$cut_no];

        if ($fpo_id == $prev_fpo_id) {
          $running_index++;
        }
        FpoCutPlanRepository::createRec([
          'line_no' => $running_index,
          'qty_json' => ($value),
          'cut_plan_id' => $cut_plan_id,
          'fppo_id' => null,
          'fpo_id' => $fpo_id
        ]);
        $fpo = Fpo::findOrFail($fpo_id);
        $fpo->utilized = true;
        $fpo->save();
        $prev_fpo_id = $fpo_id;
      }
      /*
    $oc->load('lay_sheets');
    $connected_lay_sheets = $oc->lay_sheets->count();
    $sheet_no = $connected_lay_sheets > 0 ? $connected_lay_sheets + 1 : 1;
    $max_plies = (int) $request->all()['max_plies'];


    try {
      DB::beginTransaction();

      $laySheet = LaySheetRepository::createRec(['sheet_no' => $sheet_no, 'oc_id' => $oc->id]);
      $marker_cut_values = [];
      $no_of_cuts = 0;
      $length_of_sizes = [];
      $cut_markers = [];
      $cut_max_plies_arr = [];

      foreach ($request->all()['lay_marker_details'] as $index => $marker) {
        $total_plies = $marker['total_plies'];
        $ration_json_arr[$marker['marker_name']] = $marker['qty_json'];
        if ($max_plies <= $total_plies) {
          $no_of_cuts = ceil($total_plies / $max_plies);
          for ($i = 1; $i <= $no_of_cuts; $i++) {
            $cut_max_plies = (($total_plies - $i * $max_plies) >= 0) ? $max_plies : ($total_plies - ($i - 1) * $max_plies);
            $cut_max_plies_arr[$i] = $cut_max_plies;
            foreach ($marker['qty_json'] as $size => $value) {
              $cut_size_qty = $cut_max_plies * (is_null($value) ? 0 : $value);
              $marker_cut_values[$i][$size] = $cut_size_qty;
              $cut_markers[$i] = $marker['marker_name'];
              $length_of_sizes[$size] = (isset($length_of_sizes[$size]) ? $length_of_sizes[$size] : 0) + $cut_size_qty;
            }
          }
        } else {
          foreach ($marker['qty_json'] as $size => $value) {
            $cut_size_qty = $total_plies * (is_null($value) ? 0 : $value);
            $marker_cut_values[($no_of_cuts + 1)][$size] = $cut_size_qty;
            $cut_max_plies_arr[($no_of_cuts + 1)] = $total_plies;
            $cut_markers[($no_of_cuts + 1)] = $marker['marker_name'];
            $length_of_sizes[$size] = (isset($length_of_sizes[$size]) ? $length_of_sizes[$size] : 0) + $cut_size_qty;
          }
        }
      }

      foreach ($marker_cut_values as $cut_no => $cut_qty_json) {
        $cut_plan_id = CutPlanRepository::createRec([
          'cut_no' => $cut_no,
          'ratio_json' => $ration_json_arr[$cut_markers[$cut_no]],
          'value_json' => $cut_qty_json,
          'marker_name' => $cut_markers[$cut_no],
          'max_plies' => $cut_max_plies_arr[$cut_no],
          'lay_sheet_id' => $laySheet->id
        ])->id;
        $cut_markers[$cut_no] = $cut_plan_id;
      }

      // @@@@@@@@@@@@@@@@@@@@@@@@@@
      foreach ($marker['qty_json'] as $size => $value) {
        Log::info('--------0000--------');
        $cut_array = [];
        $fpo_array = [];
        // $total_length_of_cut_size = $length_of_sizes[$size];
        $start_index = 0;
        foreach ($marker_cut_values as $cut => $qty_json) {
          $cut_array = array_merge($cut_array, array_fill($start_index, $qty_json[$size], $cut));
          $start_index = $qty_json[$size];
        }
        $start_index = 0;
        foreach ($request->all()['fpos'] as $fpo_id) {
          $qty_json = Fpo::findOrFail($fpo_id)->qty_json;
          $fpo_array = array_merge($fpo_array, array_fill($start_index, $qty_json[$size], $fpo_id));
          $start_index = $qty_json[$size];
        }
        foreach ($cut_array as $index => $value) {
          if (isset($fpo_array[$index])) {
            Log::info('---------1--------');
            $fpo_index = $index;
          }
          Log::info('--------2--------');
          Log::info('-------------fpo_index: ' . $fpo_index);
          Log::info('-------------index: ' . $index);
          $combined_array[$size][] = $fpo_array[$fpo_index] . '!' . $cut_array[$index];
        }
      }
      
      // @@@@@@@@@@@@@@@@@@@@@@@@@@
      foreach ($marker['qty_json'] as $size => $value) {
        $combined_array[$size] = array_count_values($combined_array[$size]);
      }

      $processed_array = [];
      foreach ($combined_array as $size => $contents) {
        foreach ($contents as $fpo_cut => $qty) {
          $processed_array[$fpo_cut][$size] = $qty;
        }
      }

      $running_index = 1;
      $prev_fpo_id = "";

      foreach ($processed_array as $key => $value) {
        $fpo_id = explode('!', $key)[0];
        $cut_no = explode('!', $key)[1];
        $cut_plan_id = $cut_markers[$cut_no];

        if ($fpo_id == $prev_fpo_id) {
          $running_index++;
        }

        FpoCutPlanRepository::createRec([
          'line_no' => $running_index,
          'qty_json' => ($value),
          'cut_plan_id' => $cut_plan_id,
          'fppo_id' => null,
          'fpo_id' => $fpo_id
        ]);

        $fpo = Fpo::findOrFail($fpo_id);
        $fpo->utilized = true;
        $fpo->save();
        $prev_fpo_id = $fpo_id;
      }

      // return [
      //   'marker_cut_values' => $marker_cut_values,
      //   'length_of_sizes' => $length_of_sizes,
      //   'combined_array' => $combined_array,
      //   'processed_array' => $processed_array,
      //   'cut_markers' => $cut_markers
      // ];


      //   $cut_no++;
      // }
      */

      DB::commit();

      // return ['tempx' => $tempx, 'processed_array' => $processed_array, 'combined_array' => $combined_array, 'relaxed_cut_marker_matrix' => $relaxed_cut_marker_matrix, 'relaxed_fpo_arr' => $relaxed_fpo_arr, 'fpo_qty_arr' => $fpo_qty_arr, 'cut_marker_matrix' => $cut_marker_matrix, 'ratio_json' => $ratio_json];
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      return response()->json(
        [
          'status' => 'error',
          'message' => $e->getMessage(),
          'trace' => ($e->getTraceAsString())
        ],
        500
      );
    }
  }

  public static function importExcel($fileNameWithPath)
  {
    $oc_no = '';
    $started_at = microtime();
    try {

      $ocs = [];
      $lines = Utilities::readFile($fileNameWithPath);
      Utilities::discardColumns($lines, self::DISCARD_COLUMNS);
      $lines = Utilities::removeEmptyLines($lines);
      $header = Utilities::extractHeader($lines);
      $originalHeader = $header;
      // replace columns with foreign key columns
      $header = Utilities::prepareHeader($header, self::FIELD_MAPPING);

      $old_oc_no = null;
      $qty_json = [];
      $iteration = 1;
      foreach ($lines as $key => $line) {
        // DB::beginTransaction();
        try {
          Utilities::prepareForeignKeyValues($originalHeader, self::FIELD_MAPPING, $line);
          $oc_no = $line[0];
          $buyer_id = $line[1];
          $style_id = $line[2];
          if (is_null($old_oc_no)) {
            $old_oc_no = $oc_no;
          }
          for ($n = 4; $n < sizeof($line); $n++) {
            if (isset($qty_json[$header[$n]])) {
              if (!is_null($line[$n])) {
                $qty_json[$header[$n]] = $line[$n];
              }
            } else {
              $qty_json[$header[$n]] = $line[$n];
            }
          }
          if (($old_oc_no != $oc_no) || ($iteration == (sizeof($lines) - 1))) {
            $oc_rec = [
              'wfx_oc_no' => $old_oc_no,
              'buyer_id' => $buyer_id,
              'style_id' => $style_id,
              'qty_json' => ($qty_json)
            ];
            $oc = OcRepository::createRec($oc_rec);

            $info = '';
            $ret[] = ["status" => "success", "data" => $old_oc_no, "info" => $info];
            // DB::commit();
            unset($qty_json);
          }
        } catch (Exception $e) {
          $ret[] = ["status" => "error", "data" => $oc_no, "info" => $e->getMessage()];
        }
        $iteration++;
        $old_oc_no = $oc_no;
      }

      // add OcColors
      $run = 0;
      $old_garment_color = '';
      $old_oc_no = '';
      reset($lines);
      foreach ($lines as $key => $line) {
        try {
          $run++;
          if (($old_garment_color != $line[3]) || ($old_oc_no != $line[0])) {
            $oc_id = Oc::where('wfx_oc_no', $line[0])->firstOrFail()->id;
            for ($n = 4; $n < sizeof($line); $n++) {
              if (isset($qty_json[$header[$n]])) {
                if (!is_null($line[$n])) {
                  $qty_json[$header[$n]] = $line[$n];
                }
              } else {
                $qty_json[$header[$n]] = $line[$n];
              }
            }
            $oc_color_rec = [
              'oc_id' => $oc_id,
              'garment_color' => $line[3],
              'qty_json' => array_keys($qty_json)
            ];
            OcColorRepository::createRec($oc_color_rec);
            $info = '';
            // $ret['OcColor'][] = ["status" => "success", "data" => ['oc_no' => $line[0], 'garment_color' => $line[3]], "info" => $info];
          }
          $old_garment_color = $line[3];
          $old_oc_no = $line[0];

          // DB::commit();
        } catch (Exception $e) {
          // $ret['OcColor'][] = ["status" => "error", "data" => ['oc_no' => $line[0], 'garment_color' => $line[3]], "info" => $e->getMessage()];
        }
      }

      return response()->json($ret, 200);
    } catch (Exception $e) {
      throw $e;
    }
  }
}
