<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\StatusTrait;
use Illuminate\Support\Facades\Auth;

class Soc extends Model
{
    use StatusTrait;
    public static function boot()
    {
       parent::boot();
       static::creating(function($model)
       {
           $user = Auth::user();
           $model->created_by_id = $user->id;
           $model->updated_by_id = $user->id;
       });
       static::updating(function($model)
       {
           $user = Auth::user();
           $model->updated_by_id = $user->id;
       });
   }

    protected $fillable = [
        'wfx_soc_no',
        'buyer_id',
        'style_id',
        'pack_color',
        'garment_color',
        'customer_style_ref',
        'kit_pack_id',
        'qty_json',
        'qty_json_order',
        'status',
        'max_sequence',
        'tolerance',
        'tolerance_json',
        'pack_tolerance_json'
    ];

    #region FSM
    protected static $fsm = [
        '_START_' => [
            'Open'
        ],
        'Open' => [
            'close' => 'Closed'
        ],
        'Closed' => [
            'reopen' => 'Open',
            'close' => 'Closed'
        ]
    ];
    #endregion

    protected $casts = [
        'qty_json' => 'array',
        'qty_json_order' => 'array',
        'tolerance_json'=>'array',
        'pack_tolerance_json'=>'array'
    ];

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function style()
    {
        return $this->belongsTo(Style::class);
    }

    public function fpos()
    {
        return $this->hasMany(Fpo::class);
    }

    public function packing_list_socs()
    {
        return $this->hasMany(PackingListSoc::class);
    }

    public function soc_tolerance_logs()
    {
        return $this->hasMany(SocToleranceLog::class);
    }
}
