<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\QueriesRepository;
use Illuminate\Http\Request;
use App\GetJobCardData;

class QueriesController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new QueriesRepository();
    }

    public function getDashBoardData(Request $request)
    {
        $vsm_code = $request->vsm_code;
        $supervisor_code = $request->supervisor_code;
        $current_date_time = $request->current_date_time;
        $shift = $request->shift;
        $dashboard = null;
        $index = strpos($request->operation,"-");
        $operation = substr($request->operation,0,$index);
        $direction = substr($request->operation,$index+1);

        if($request->dashboard){
            $dashboard = $request->dashboard;
            
        }



        return $this->repo->getDashBoardData($vsm_code, $supervisor_code, $current_date_time,$shift,$dashboard,$operation,$direction);
    }

    public function getDashboardTemplate(){
        return $this->repo->getDashboardTemplate();
    }

    public function getTeamPerShift(Request $request){
        $vsm_code = $request->vsm_code;
        $supevisor_code = $request->supevisor_code;
        $current_date_time = $request->current_date_time;
        $daily_shift = $request->daily_shift;
        $dashboard = null;    
        return $this->repo->_getTeamsPerShift($vsm_code, $supevisor_code, $daily_shift,$dashboard);
    }

    public function getOperation(){
        return $this->repo->getOperation();
    }


    //////////////////////////////////////////////////////////////////

    public function getJobCardData()
    {
//        $users = \App\GetJobCardData::select("*")
//            ->get()
//            ->toArray();
        $users = GetJobCardData::all();
        return $users;
    }
}
