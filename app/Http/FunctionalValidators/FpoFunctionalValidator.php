<?php

namespace App\Http\FunctionalValidators;

use App\Fpo;
use App\Http\Repositories\SocRepository;
use App\Soc;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FpoFunctionalValidator
{
  private $rec;
  private $model;

  public function __construct(array $rec, $model = null)
  {
    $this->rec = $rec;
    $this->model = $model;
  }

  public function validateCreate()
  {
    if (isset($this->rec['priority']) && ($this->rec['priority'] > 10)) {
      throw new \App\Exceptions\GeneralException("Invalid value for Priority.");
    }

    //Validate for 0 quantities
     
     
    //validate FPO quantities
    $soc = Soc::find($this->rec['soc_id']);
    $soc_bal_qtys = SocRepository::getBalanceQuantities($soc);

    $qty_json = json_decode($this->rec['qty_json']); 

    
    foreach ($soc_bal_qtys['balance_qty'] as $size => $qty) {
      foreach ($qty_json as $key => $val) {
        if (strval($key)  == strval($size) && strlen($key) == strlen($size)) {
          if ($qty < $val) {
            throw new \App\Exceptions\GeneralException('Quantity Entered Exceeds the Available Quantity'.$qty.' '.$size.' '.$val.' '.$key);
          }
        }
      }
    }

  }

  public function validateUpdate()
  {
    if (isset($this->rec['priority']) && ($this->rec['priority'] > 10)) {
      throw new \App\Exceptions\GeneralException("Invalid value for Priority.");
    }

    //validate FPO quantities
    $soc = Soc::find($this->rec['soc_id']);
    $soc_bal_qtys = SocRepository::getBalanceQuantities($soc);
     
    
    $qty_json = json_decode($this->rec['qty_json']);

    $old_qty_json = ($this->model->qty_json);

    // check if quantity has changed
    foreach ($old_qty_json as $oldkey => $oldvalue) {
      foreach ($qty_json as $newkey => $newval) {
        if (($oldkey  == $newkey) ) {
          
          //$required_qty = $newval -  $oldvalue;
          // if ($required_qty > 0) {
          //   if ($soc_bal_qtys['balance_qty'][$newkey] < $required_qty) {
          //     throw new \App\Exceptions\GeneralException('Quantity Entered Exceeds the Available Quantity');
          //   }
          // }
        }
      }
    }
  }

}
