<?php

namespace App\Http\Repositories;

use App\Exceptions\GeneralException;
use App\ForeignKeyMapper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataFileImporterRepository
{
  public function import(Request $request)
  {
    $file = $request->file('payload');
    if (!$file) {
      throw new \App\Exceptions\GeneralException("No file attached!");
    }
    $model = $request->input('model');

    switch (Str::upper($model)) {
      case 'BUYER':
        $inputFileName = storage_path('app') . '/' . str_replace('\\', '/', $file->store('imports'));
        $result = BuyerRepository::importExcel($inputFileName);
        break;
      case 'OC':
        $inputFileName = storage_path('app') . '/' . str_replace('\\', '/', $file->store('imports'));
        $result = OcRepository::importExcel($inputFileName);
        break;
      case 'STYLE':
        $inputFileName = storage_path('app') . '/' . str_replace('\\', '/', $file->store('imports'));
        $result = StyleRepository::importExcel($inputFileName);
        break;
      default:
        $result = response()->json("Not yet implemented");
        break;
    }

    return $result;

    /*
    // $structured_data = [];
    // $data = [];
    // try {
    //   $filenames = array();
    //   if ($request->hasFile('payloads')) {
    //     foreach ($request->file('payloads') as $file) {
    //       if ($file->extension() == 'pdf') {
    //       }
    //       $filenames[] = [
    //         "filename" => $file->getClientOriginalName(),
    //         "mime_type" => $file->extension(),
    //         "path" => $file->store('imports')
    //       ];
    //     }

    //     foreach ($filenames as $file) {
    //       $model = explode('.', $file['filename'])[0];
    //       $fullpath = storage_path('app') . '\\' . $file['path'];
    //       $ret = $this->csvToArray($model, $fullpath);
    //       // $data = $ret['data'];
    //       // $header = $ret['header'];

    //       // $structured_data[] = [$model => ["CRE" => $data]];
    //     }

    //     return $ret;

    //     // return $data;
    //     return response()->json(["status" => "success"], 200);
    //   } else {
    //     return response()->json(["status" => "warning", "message" => "No data files to import."], 400);
    //   }
    // } catch (Exception $e) {
    //   return response()->json([
    //     "status" => "error",
    //     "message" => $e->getMessage(),
    //     "trace" => $e->getTraceAsString()
    //   ], 400);
    // }
    */
  }

  /*
  private function csvToArray($model, $filename = '', $delimiter = ';')
  {
    if (!file_exists($filename) || !is_readable($filename))
      return false;

    $original_header = [];
    $header = [];
    $data = [];
    $ret = [];
    $hasForeignRelations = (ForeignKeyMapper::select('key_mapping')->where('model', $model)->count() > 0);
    if (($handle = fopen($filename, 'r')) !== false) {
      while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
        if (!$header) {
          $original_header = $row;
          if ($hasForeignRelations) {
            $ret = $this->substituteFields($model, $original_header);
            $header = $ret['header'];
          } else {
            $header = $original_header;
          }
        } else {
          if ($hasForeignRelations) {
            foreach ($ret['model_info'] as $i => $val) {
              $foreignModel = 'App\\' . $val;
              $foreignId = $foreignModel::select('id')->where($original_header[$i], $row[$i])->first()->toArray()['id'];
              $row[$i] = $foreignId;
            }
          }
          $data[] = array_combine($header, $row);
        }
      }
      fclose($handle);
    }

    return [$model => ['CRE' => [$data]]];
  }

  private function substituteFields($model, $header)
  {
    $model_info = [];
    $fieldMappers = ForeignKeyMapper::select('key_mapping')->where('model', $model)->first();
    if ($fieldMappers) {
      $fieldMappers = $fieldMappers->toArray();
      foreach ($fieldMappers['key_mapping'] as $field => $target) {
        $index = array_search($field, $header);
        if ($index) {
          $header[$index] = $target['field'];
          $model_info[$index] = $target['model'];
        };
      }
    }
    return ['header' => $header, 'model_info' => $model_info];
  }
  */
}
