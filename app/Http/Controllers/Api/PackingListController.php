<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PackingList;
use App\Http\Resources\PackingListResource;
use App\Http\Repositories\PackingListRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class PackingListController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new PackingListRepository();
    }

    public function createAndUpdatePackingList(Request $request)
    {
        return $this->repo->createAndUpdatePackingList($request);
    }

    public function getFullPackingList(Request $request)
    {
        $packing_list_id = $request->packing_list_id;
        return $this->repo->getFullPackingList($packing_list_id);
    }

    public function generatePackingListDetails(Request $request)
    {
        $packing_list_id = $request->packing_list_id;
        return $this->repo->generatePackingListDetails($packing_list_id);
    }

    public function getCalculatedNoOfCartons(Request $request)
    {
        $balance_json = $request->balance_json;
        $carton_json = $request->carton_json;
        $packing_list_id = $request->packing_list_id;
        $carton_id = $request->carton_id;

        return $this->repo->getCalculatedNoOfCartons($balance_json, $carton_json, $packing_list_id,$carton_id);
    }

    public function getPackingListBalanceQuantity(Request $request)
    {
        $packing_list_id = $request->packing_list_id;
        $socNo = $request->socNo;

        return $this->repo->getPackingListBalanceQuantity($packing_list_id,$socNo);
    }

    public function getPackingListLayOutReport(Request $request)
    {
        $packing_list_id = $request->packing_list_id;
        $revision_no = $request->revision_no;
        return $this->repo->getPackingListLayOutReport($packing_list_id, $revision_no);
        //return view('excel', ['name' => 'James']);


    }

    public function updateBoxScanning(Request $request){

        return $this->repo->updateBoxScanning($request);
    }

    public function updateBoxScanningOld(Request $request){

        return $this->repo->updateBoxScanningOld($request);
    }

    public function getFgScanningList(Request $request){

        return $this->repo->getFgScanningList($request);
    }

    public function getFgScanningListOld(Request $request){

        return $this->repo->getFgScanningListOld($request);
    }

    public function getAllBoxIds(Request $request){

        return $this->repo->getAllBoxIds($request);
    }

    public function deleteFgScanningList(Request $request){
        return $this->repo->deleteFgScanningList($request);
    }

    public function deleteFgScanningListOld(Request $request){
        return $this->repo->deleteFgScanningListOld($request);
    }

    public function updateCurrentVPO(Request $request){
        return $this->repo->updateCurrentVPO($request);
    }
    public function reopenPackingList(Request $request){
        return $this->repo->reopenPackingList($request);
    }

    public function revisePackingList(Request $request){
        return $this->repo->revisePackingList($request);
    }
    public function finalizeRevisePackingList(Request $request){
        return $this->repo->finalizeRevisePackingList($request);
    }
    public function get_scan_box(Request $request){
        return $this->repo->get_scan_box($request);
    }

    public function getPackingListBySoc(Request $request){
        return $this->repo->getPackingListBySoc($request);
    }
    public function removeBox(Request $request){

        return $this->repo->removeBox($request);
    }

    public function getBoxDetailsFG(Request $request){
        $boxId = $request->input('box_id');
        return $this->repo->getBoxDetailsFG($boxId);
    }

    public function getBoxDetailsBS(Request $request){
        $boxId = $request->input('box_id');
        return $this->repo->getBoxDetailsBS($boxId);
    }

    public function fgScanFinalSave(Request $request){
        return $this->repo->fgScanFinalSave($request);
    }

    public function updateCartonLocation(Request $request){
        return $this->repo->updateCartonLocation($request);
    }

    public function getDailyTransfer(Request $request){
        return $this->repo->getDailyTransfer($request);
    }
}
