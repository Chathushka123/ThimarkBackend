<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\InvoiceRepository;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{

    private $repo;

    public function __construct()
    {
        $this->repo = new InvoiceRepository();
    }

    public function createAndUpdateInvoice(Request $request)
    {


        return $this->repo->createAndUpdateInvoice($request);
    }

    public function getSearchByInvoice(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $mobile = $request->input('contact');
        return $this->repo->getSearchByInvoice($id, $name, $mobile);
    }

    public function getInvoicePrint(Request $request)
    {
        $id = $request->input('id');
        return $this->repo->getInvoicePrint($id);
    }

    public function getStatus(Request $request)
    {
        return $this->repo->getStatus();
    }

    public function getInvoice(Request $request)
    {
        return $this->repo->getInvoice();
    }

    // public function generateMarkerPlan(Request $request){
    //     return $this->repo->generateMarkerPlan($request);
    // }

    public function setupInvoiceID(Request $request)
    {
        return $this->repo->setupInvoiceID($request);
    }

    public function getTransactionSummary(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        return $this->repo->getTransactionSummary($dateFrom, $dateTo);
    }

    public function getPaymentMethods($type)
    {
        return $this->repo->getPaymentMethodsByType($type);
    }

    /////////////// Day Book ///////////////
    public function getPaymentMethod(Request $request)
    {
        return $this->repo->getPaymentMethod();
    }

    public function createDayBookTransaction(Request $request)
    {
        return $this->repo->createDayBookTransaction($request);
    }

    public function createPaymentType(Request $request)
    {
        return $this->repo->createPaymentType($request);
    }
}
