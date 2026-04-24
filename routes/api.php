<?php

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {

    Route::post('login', 'Api\AuthController@login')->name('login');
    Route::post('register', 'Api\AuthController@register')->name('register');
    Route::get('fpos/{fpo}/generateLayout', 'Api\FpoController@generateLayout')->name('fpos.generateLayout');

    // Route::get('search', 'Api\SearchController@search')->name('search.index');
    Route::group(['middleware' => 'auth:api'], function () {
        Route::post('novelSearch', 'Api\SearchController@novelSearch')->name('novelSearch');
        Route::post('getFunctionalPermission', 'Api\SearchController@getFunctionalPermission')->name('getFunctionalPermission');

        ///////////////////////////  For Excel Report  /////////////////////
        Route::get('export', 'ImportExportController@export')->name('export');
        Route::get('importExportView', 'ImportExportController@importExportView');
        Route::post('import', 'ImportExportController@import')->name('import');

        // Auth
        Route::get('user', 'Api\AuthController@user')->name('user.get');
        Route::post('logout', 'Api\AuthController@logout')->name('logout');
        Route::get('user/stickers/{id}', 'Api\UserController@printStickers')->name('user.printStickers');


        // Company
        Route::get('companies/{company}', 'Api\CompanyController@show')->name('companies.show');
        Route::get('companies', 'Api\CompanyController@index')->name('companies.index');
        Route::post('companies', 'Api\CompanyController@store')->name('companies.store');


        Route::put('companies', 'Api\CompanyController@update')->name('companies.update');
        Route::delete('companies', 'Api\CompanyController@destroy')->name('companies.destroy');

        // Style
        Route::get('styles/{style}', 'Api\StyleController@show')->name('styles.show');
        Route::get('styles', 'Api\StyleController@index')->name('styles.index');
        Route::post('styles', 'Api\StyleController@store')->name('styles.store');
        Route::put('styles', 'Api\StyleController@update')->name('styles.update');
        Route::delete('styles', 'Api\StyleController@destroy')->name('styles.destroy');

        // Oc
        Route::post('ocs/{oc}/actions/readonly', 'Api\OcController@actionReadonly')->name('ocs.actionReadonly');
        Route::post('ocs/{oc}/actions/open', 'Api\OcController@actionOpen')->name('ocs.actionOpen');
        Route::post('ocs/{oc}/createLaySheet', 'Api\OcController@createLaySheet')->name('ocs.createLaySheet');
        Route::get('ocs/{oc}/layPlanningHeader', 'Api\OcController@getLayPlanningHeader')->name('ocs.getLayPlanningHeader');
        Route::get('ocs/{oc}/getFpoList', 'Api\OcController@getFpoList')->name('ocs.getFpoList');
        Route::get('ocs/lovOcs', 'Api\OcController@lovOcs')->name('ocs.lov');
        Route::get('ocs/{oc}/lovSocs', 'Api\OcController@lovSocs')->name('ocs.socs.lov');
        Route::get('ocs/{param}', 'Api\OcController@show')->name('ocs.show');
        Route::get('ocs', 'Api\OcController@index')->name('ocs.index');
        Route::post('ocs', 'Api\OcController@store')->name('ocs.store');
        Route::put('ocs', 'Api\OcController@update')->name('ocs.update');
        Route::delete('ocs', 'Api\OcController@destroy')->name('ocs.destroy');

        // OcColor
        Route::get('ocColors/{ocColor}/getBalanceQuantities', 'Api\OcColorController@getBalanceQuantities')->name('ocColors.getBalanceQuantities');
        Route::get('ocColors/{ocColor}', 'Api\OcColorController@show')->name('ocColors.show');
        Route::get('ocColors', 'Api\OcColorController@index')->name('ocColors.index');
        Route::post('ocColors', 'Api\OcColorController@store')->name('ocColors.store');
        Route::put('ocColors', 'Api\OcColorController@update')->name('ocColors.update');
        Route::delete('ocColors', 'Api\OcColorController@destroy')->name('ocColors.destroy');

        // Buyer
        Route::get('buyers/{buyer}', 'Api\BuyerController@show')->name('buyers.show');
        Route::get('buyers', 'Api\BuyerController@index')->name('buyers.index');
        Route::post('buyers', 'Api\BuyerController@store')->name('buyers.store');
        Route::put('buyers', 'Api\BuyerController@update')->name('buyers.update');
        Route::delete('buyers', 'Api\BuyerController@destroy')->name('buyers.destroy');

        // Soc
        Route::get('socs/{soc}/getBalanceQuantities', 'Api\SocController@getBalanceQuantities')->name('socs.getBalanceQuantities');
        Route::get('socs/{soc}/prepareFpoCreate', 'Api\SocController@prepareFpoCreate')->name('socs.prepareFpoCreate');
        Route::get('socs/{soc}', 'Api\SocController@show')->name('socs.show');
        Route::get('socs', 'Api\SocController@index')->name('socs.index');
        Route::post('socs', 'Api\SocController@store')->name('socs.store');
        Route::put('socs', 'Api\SocController@update')->name('socs.update');
        Route::delete('socs', 'Api\SocController@destroy')->name('socs.destroy');
        Route::post('socs/getSearchByCustomerStyleRef', 'Api\SocController@getSearchByCustomerStyleRef')->name('socs.getSearchByCustomerStyleRef');
        Route::post('socs/getSearchByCustomerStyleRefCode', 'Api\SocController@getSearchByCustomerStyleRefCode')->name('socs.getSearchByCustomerStyleRefCode');
        Route::post('socs/getPendingConnectedFpos', 'Api\SocController@getPendingConnectedFpos')->name('socs.getPendingConnectedFpos');

        // CombineOrdersocs/getSearchByCustomerStyleRef
        Route::post('combineOrders/combineFpos', 'Api\CombineOrderController@combineFpos')->name('combineOrders.combineFpos');
        Route::post('combineOrders/getConnectedFpos', 'Api\CombineOrderController@getConnectedFpos')->name('combineOrders.getConnectedFpos');
        Route::post('combineOrders/getCombineOrdersByStyleRef', 'Api\CombineOrderController@getCombineOrdersByStyleRef')->name('combineOrders.getCombineOrdersByStyleRef');
        Route::post('combineOrders/getFabricInfo', 'Api\CombineOrderController@getFabricInfo')->name('combineOrders.getFabricInfo');
        Route::post('combineOrders/generateCutPlan', 'Api\CombineOrderController@generateCutPlan')->name('combineOrders.generateCutPlan');
        Route::post('combineOrders/getSearchByStyleRefCode', 'Api\CombineOrderController@getSearchByStyleRefCode')->name('combineOrders.getSearchByStyleRefCode');
        Route::post('combineOrders/getSearchResultByStyleRefCode', 'Api\CombineOrderController@getSearchResultByStyleRefCode')->name('combineOrders.getSearchResultByStyleRefCode');
        Route::post('combineOrders/getFpoFabricAndOperation', 'Api\CombineOrderController@getFpoFabricAndOperation')->name('combineOrders.getFpoFabricAndOperation');
        Route::post('combineOrders/getCombineOrderTotalQty', 'Api\CombineOrderController@getCombineOrderTotalQty')->name('combineOrders.getCombineOrderTotalQty');

        // Fpo
        Route::post('fpos/getColorQuantities', 'Api\FpoController@getColorQuantities')->name('fpos.getColorQuantities');
        Route::get('fpos/list', 'Api\FpoController@list')->name('fpos.list');
        Route::get('fpos/getBySocId/{soc}', 'Api\FpoController@getBySocId')->name('fpos.getBySocId');
        Route::get('fpos/getBySocNo/{socNo}', 'Api\FpoController@getBySocNo')->name('fpos.getBySocNo');
        Route::get('fpos/{fpoNo}/getByNo', 'Api\FpoController@getByNo')->name('fpos.getByNo');
        Route::get('fpos/{fpo}/getById', 'Api\FpoController@getById')->name('fpos.getById');
        Route::get('fpos/getCreateMeta', 'Api\FpoController@getCreateMeta')->name('fpos.getCreateMeta');
        Route::post('fpos/{fpo}/createPackingList', 'Api\FpoController@createPackingList')->name('fpos.createPackingList');

        Route::get('fpos/{fpo}', 'Api\FpoController@show')->name('fpos.show');
        Route::get('fpos', 'Api\FpoController@index')->name('fpos.index');
        Route::post('fpos', 'Api\FpoController@store')->name('fpos.store');
        Route::put('fpos', 'Api\FpoController@update')->name('fpos.update');
        Route::delete('fpos', 'Api\FpoController@destroy')->name('fpos.destroy');

        // RoutingOperation
        Route::get('routingOperations/{routingOperation}', 'Api\RoutingOperationController@show')->name('routingOperations.show');
        Route::get('routingOperations', 'Api\RoutingOperationController@index')->name('routingOperations.index');
        Route::post('routingOperations', 'Api\RoutingOperationController@store')->name('routingOperations.store');
        Route::put('routingOperations', 'Api\RoutingOperationController@update')->name('routingOperations.update');
        Route::delete('routingOperations', 'Api\RoutingOperationController@destroy')->name('routingOperations.destroy');
        Route::post('routingOperations/addOperation', 'Api\RoutingOperationController@addOperation')->name('routingOperations.addOperation');
        Route::post('routingOperations/removeOperation', 'Api\RoutingOperationController@removeOperation')->name('routingOperations.removeOperation');
        Route::post('routingOperations/getOperationStructure', 'Api\RoutingOperationController@getOperationStructure')->name('routingOperations.getOperationStructure');
        Route::post('routingOperations/get_operation', 'Api\RoutingOperationController@get_operation')->name('routingOperations.get_operation');

        // FpoOperation
        Route::get('fpoOperations/{fpoOperation}', 'Api\FpoOperationController@show')->name('fpoOperations.show');
        Route::get('fpoOperations', 'Api\FpoOperationController@index')->name('fpoOperations.index');
        Route::post('fpoOperations', 'Api\FpoOperationController@store')->name('fpoOperations.store');
        Route::put('fpoOperations', 'Api\FpoOperationController@update')->name('fpoOperations.update');
        Route::delete('fpoOperations', 'Api\FpoOperationController@destroy')->name('fpoOperations.destroy');
        Route::post('fpoOperations/getFpoOperationsStructure', 'Api\FpoOperationController@getFpoOperationsStructure')->name('fpoOperations.getFpoOperationsStructure');

        // Routings
        Route::get('routings/{routing}', 'Api\RoutingController@show')->name('routings.show');
        Route::get('routings', 'Api\RoutingController@index')->name('routings.index');
        Route::post('routings', 'Api\RoutingController@store')->name('routings.store');
        Route::put('routings', 'Api\RoutingController@update')->name('routings.update');
        Route::delete('routings', 'Api\RoutingController@destroy')->name('routings.destroy');
        Route::post('routings/getFullStructure', 'Api\RoutingController@getFullStructure')->name('routings.getFullStructure');

        // Search
        Route::get('searchByUuid/{uuid}', 'Api\SearchController@searchByUuid')->name('search.uuid');
        Route::post('searchByParameters', 'Api\SearchController@searchByParameters')->name('search.queryString');
        Route::post('searchByParametersJson', 'Api\SearchController@searchByParametersJson')->name('search.queryStringJson');

        // HashStore
        Route::get('hashStores/getByUuid/{uuid}', 'Api\HashStoreController@getByUuid')->name('hashStores.getByUuid');
        Route::post('hashStores', 'Api\HashStoreController@store')->name('hashStores.store');

        // MasterDetail
        Route::post('masterDetails', 'Api\MasterDetailController')->name('masterDetails');
        Route::post('testDetails', 'Api\TestController')->name('testDetails');
        Route::post('getTestData', 'Api\TestController@getTestData')->name('getTestData');
        // Route::post('masterDetails', 'Api\MasterDetailController@store')->name('masterDetails.store');
        // Route::put('masterDetails', 'Api\MasterDetailController@update')->name('masterDetails.update');

        // LaySheet
        Route::get('laySheets/{laySheet}/getDistinctFpos', 'Api\LaySheetController@getDistinctFpos')->name('laySheets.getDistinctFpos');
        Route::get('laySheets/{param}', 'Api\LaySheetController@show')->name('laySheets.show');
        Route::get('laySheets', 'Api\LaySheetController@index')->name('laySheets.index');
        Route::post('laySheets', 'Api\LaySheetController@store')->name('laySheets.store');
        Route::put('laySheets', 'Api\LaySheetController@update')->name('laySheets.update');
        Route::delete('laySheets', 'Api\LaySheetController@destroy')->name('laySheets.destroy');

        // CutPlan
        Route::get('cutPlans/{param}', 'Api\CutPlanController@show')->name('cutPlans.show');
        Route::get('cutPlans', 'Api\CutPlanController@index')->name('cutPlans.index');
        Route::post('cutPlans', 'Api\CutPlanController@store')->name('cutPlans.store');
        Route::put('cutPlans', 'Api\CutPlanController@update')->name('cutPlans.update');
        Route::delete('cutPlans', 'Api\CutPlanController@destroy')->name('cutPlans.destroy');
        Route::post('cutPlans/getCutPlan', 'Api\CutPlanController@getCutPlan')->name('cutPlans.getCutPlan');
        Route::post('cutPlans/deleteCutPlansByCombineOrder', 'Api\CutPlanController@deleteCutPlansByCombineOrder')->name('cutPlans.deleteCutPlansByCombineOrder');
        Route::post('cutPlans/getCutLaySheet', 'Api\CutPlanController@getCutLaySheet')->name('cutPlans.getCutLaySheet');
        Route::post('cutPlans/saveSpecialRemarks', 'Api\CutPlanController@saveSpecialRemarks')->name('cutPlans.saveSpecialRemarks');
        Route::post('cutPlans/getSpecialRemarks', 'Api\CutPlanController@getSpecialRemarks')->name('cutPlans.getSpecialRemarks');
        Route::post('cutPlans/getBundleTagReport', 'Api\CutPlanController@getBundleTagReport')->name('cutPlans.getBundleTagReport');
        Route::post('cutPlans/saveFpoTolerance', 'Api\CutPlanController@saveFpoTolerance')->name('cutPlans.saveFpoTolerance');
        Route::post('cutPlans/getBundleReport', 'Api\CutPlanController@getBundleReport')->name('cutPlans.getBundleReport');
        Route::post('cutPlans/getBundleReportInRationPlanning', 'Api\CutPlanController@getBundleReportInRationPlanning')->name('cutPlans.getBundleReportInRationPlanning');
        Route::post('cutPlans/createAndUpdateShadeDetails', 'Api\CutPlanController@createAndUpdateShadeDetails')->name('cutPlans.createAndUpdateShadeDetails');
        Route::post('cutPlans/getCutPlanByFpo', 'Api\CutPlanController@getCutPlanByFpo')->name('cutPlans.getCutPlanByFpo');

        Route::post('cutPlans/updateBundleLocation', 'Api\CutPlanController@updateBundleLocation')->name('cutPlans.updateBundleLocation');
        Route::post('cutPlans/getDailyTransfer', 'Api\CutPlanController@getDailyTransfer')->name('cutPlans.getDailyTransfer');
        Route::post('cutPlans/getBundleDetailsReport', 'Api\CutPlanController@getBundleDetailsReport')->name('cutPlans.getBundleDetailsReport');
        Route::post('cutPlans/saveBundleNumbering', 'Api\CutPlanController@saveBundleNumbering')->name('cutPlans.saveBundleNumbering');
        Route::post('cutPlans/getCutNoSizeJson', 'Api\CutPlanController@getCutNoSizeJson')->name('cutPlans.getCutNoSizeJson');
        Route::post('cutPlans/getBundleReportByMultipleFppo', 'Api\CutPlanController@getBundleReportByMultipleFppo')->name('cutPlans.getBundleReportByMultipleFppo');


        // FpoCutPlan
        Route::post('fpoCutPlans/getPendingFpoCutPlansByCombineOrder', 'Api\FpoCutPlanController@getPendingFpoCutPlansByCombineOrder')->name('fpoCutPlans.getPendingFpoCutPlansByCombineOrder');
        Route::post('fpoCutPlans/createFppo', 'Api\FpoCutPlanController@createFppo')->name('fpoCutPlans.createFppo');
        Route::post('fpoCutPlans/manualCutUpdate', 'Api\FpoCutPlanController@manualCutUpdate')->name('fpoCutPlans.manualCutUpdate');
        Route::post('fpoCutPlans/printConsumptionReport', 'Api\FpoCutPlanController@printConsumptionReport')->name('fpoCutPlans.printConsumptionReport');
        Route::post('fpoCutPlans/cutUpdateByShade', 'Api\FpoCutPlanController@cutUpdateByShade')->name('fpoCutPlans.cutUpdateByShade');
        Route::post('fpoCutPlans/deleteCut', 'Api\FpoCutPlanController@deleteCut')->name('fpoCutPlans.deleteCut');


        //FPPO
        Route::post('fppos/getSumOfCutUpdates', 'Api\FppoController@getSumOfCutUpdates')->name('fppos.getSumOfCutUpdates');
        Route::post('fppos/createBundle', 'Api\FppoController@createBundle')->name('fppos.createBundle');
        Route::post('fppos/updateFppo', 'Api\FppoController@updateFppo')->name('fppos.updateFppo');
        Route::post('fppos/createBundleTickets', 'Api\FppoController@createBundleTickets')->name('fppos.createBundleTickets');
        Route::post('fppos/createManualBundle', 'Api\FppoController@createManualBundle')->name('fppos.createManualBundle');
        Route::post('fppos/createBundleByPlyHeight', 'Api\FppoController@createBundleByPlyHeight')->name('fppos.createBundleByPlyHeight');
        // CutUpdate
        Route::post('cutUpdates/getCutUpdates', 'Api\CutUpdateController@getCutUpdates')->name('cutUpdates.getCutUpdates');

        // Bundle
        Route::post('bundles/bundlesByFppoAndCutNo', 'Api\BundleController@getBundlesByFppoAndCutNo')->name('bundles.getBundlesByFppoAndCutNo');
        Route::post('bundles/getUnutilizedBundlesByFppoAndCutNo', 'Api\BundleController@getUnutilizedBundlesByFppoAndCutNo')->name('bundles.getUnutilizedBundlesByFppoAndCutNo');
        //Route::post('bundles/deleteBundles', 'Api\BundleController@deleteBundles')->name('bundles.deleteBundles');
        Route::post('bundles/getUnutilizedBundlesByFppoNo', 'Api\BundleController@getUnutilizedBundlesByFppoNo')->name('bundles.getUnutilizedBundlesByFppoNo');
        Route::post('bundles/deleteBundlesByFppo', 'Api\BundleController@deleteBundlesByFppo')->name('bundles.deleteBundlesByFppo');
        Route::post('bundles/getUnutilizedBundlesByFpoNo', 'Api\BundleController@getUnutilizedBundlesByFpoNo')->name('bundles.getUnutilizedBundlesByFpoNo');
        Route::post('bundles/getBundlesByFppoAndCutId', 'Api\BundleController@getBundlesByFppoAndCutId')->name('bundles.getBundlesByFppoAndCutId');
        Route::post('bundles/saveRemarksByFppo', 'Api\BundleController@saveRemarksByFppo')->name('bundles.saveRemarksByFppo');
        Route::post('bundles/getRemarksByFppo', 'Api\BundleController@getRemarkByFppo')->name('bundles.getRemarkByFppo');
        Route::post('bundles/deleteBundlesByFppoOneByOne', 'Api\BundleController@deleteBundlesByFppoOneByOne')->name('bundles.deleteBundlesByFppoOneByOne');
        Route::post('bundles/getUnutilizedBundlesByCutNo', 'Api\BundleController@getUnutilizedBundlesByCutNo')->name('bundles.getUnutilizedBundlesByCutNo');
        Route::post('bundles/getUnutilizedBundlesByCmbOrder', 'Api\BundleController@getUnutilizedBundlesByCmbOrder')->name('bundles.getUnutilizedBundlesByCmbOrder');

        ////////////////////////////////////////////////////////////////
        Route::post('bundles/getDoubleScaningBundle', 'Api\BundleController@getDoubleScaningBundle')->name('bundles.getDoubleScaningBundle');

        // BALP/BALG ---> Minu Integration
        Route::post('bundles/updateMaxSeqCombineOrder', 'Api\BundleController@updateMaxSeqCombineOrder')->name('bundles.updateMaxSeqCombineOrder');
        Route::post('bundles/createWorkOrdersForExistJobCard', 'Api\BundleController@createWorkOrdersForExistJobCard')->name('bundles.createWorkOrdersForExistJobCard');
        Route::post('bundles/updateCutUpdatedFpoCutPlan', 'Api\BundleController@updateCutUpdatedFpoCutPlan')->name('bundles.updateCutUpdatedFpoCutPlan');

        // BundleBin
        Route::post('bundleBins/getByFppo', 'Api\BundleBinController@getByFppo')->name('bundleBins.getByFppo');
        Route::post('bundleBins/createBundle', 'Api\BundleBinController@createBundle')->name('bundleBins.createBundle');

        // BundleTicket
        //Route::post('bundleTickets/scan', 'Api\BundleTicketController@scan')->name('bundleTickets.scan');
        Route::post('bundleTickets/scanByScanningSlotOld', 'Api\BundleTicketController@scanByScanningSlotOld')->name('bundleTickets.scanByScanningSlotOld');
        Route::post('bundleTickets/scanByScanningSlot', 'Api\BundleTicketController@scanByScanningSlot')->name('bundleTickets.scanByScanningSlot');
        Route::post('bundleTickets/scanByScanningSlotOPD', 'Api\BundleTicketController@scanByScanningSlotOPD')->name('bundleTickets.scanByScanningSlotOPD');
        Route::post('bundleTickets/newPackingInScanningOPD', 'Api\BundleTicketController@newPackingInScanningOPD')->name('bundleTickets.newPackingInScanningOPD');
        Route::post('bundleTickets/unscan', 'Api\BundleTicketController@unscan')->name('bundleTickets.unscan');
        Route::post('bundleTickets/getBundleTicketByFppo', 'Api\BundleTicketController@getBundleTicketByFppo')->name('bundleTickets.getBundleTicketByFppo');
        Route::post('bundleTickets/recordQc', 'Api\BundleTicketController@recordQc')->name('bundleTickets.recordQc');
        Route::post('bundleTickets/fetchQc', 'Api\BundleTicketController@fetchQc')->name('bundleTickets.fetchQc');
        Route::post('bundleTickets/getPendingQcRecoverbles', 'Api\BundleTicketController@getPendingQcRecoverbles')->name('bundleTickets.getPendingQcRecoverbles');
        Route::post('bundleTickets/getScanResults', 'Api\BundleTicketController@getScanResults')->name('bundleTickets.getScanResults');
        Route::post('bundleTickets/updateScannedQuantity', 'Api\BundleTicketController@updateScannedQuantity')->name('bundleTickets.updateScannedQuantity');
        Route::post('bundleTickets/getBundleByOperation', 'Api\BundleTicketController@getBundleByOperation')->name('bundleTickets.getBundleByOperation');
        Route::post('bundleTickets/manualScanByOperation', 'Api\BundleTicketController@manualScanByOperation')->name('bundleTickets.manualScanByOperation');
        Route::post('bundleTickets/selectTeamAutomatically', 'Api\BundleTicketController@selectTeamAutomatically')->name('bundleTickets.selectTeamAutomatically');
        Route::post('bundleTickets/getGridDataBundleScanning', 'Api\BundleTicketController@getGridDataBundleScanning')->name('bundleTickets.getGridDataBundleScanning');
        Route::post('bundleTickets/newPackingInScanning', 'Api\BundleTicketController@newPackingInScanning')->name('bundleTickets.newPackingInScanning');
        Route::post('bundleTickets/unscanNew', 'Api\BundleTicketController@unscanNew')->name('bundleTickets.unscanNew');
        Route::post('bundleTickets/getDataBarcode', 'Api\BundleTicketController@getDataBarcode')->name('bundleTickets.getDataBarcode');
        Route::post('bundleTickets/deleteQCRejects', 'Api\BundleTicketController@deleteQCRejects')->name('bundleTickets.deleteQCRejects');
        Route::post('bundleTickets/getSearchBundleTicket', 'Api\BundleTicketController@getSearchBundleTicket')->name('bundleTickets.getSearchBundleTicket');
        Route::post('bundleTickets/getSearchByTicketId', 'Api\BundleTicketController@getSearchByTicketId')->name('bundleTickets.getSearchByTicketId');
        Route::post('bundleTickets/getEditData', 'Api\BundleTicketController@getEditData')->name('bundleTickets.getEditData');
        Route::post('bundleTickets/getTeams', 'Api\BundleTicketController@getTeams')->name('bundleTickets.getTeams');
        Route::post('bundleTickets/saveNewTeam', 'Api\BundleTicketController@saveNewTeam')->name('bundleTickets.saveNewTeam');
        Route::post('bundleTickets/getEditDataBox', 'Api\BundleTicketController@getEditDataBox')->name('bundleTickets.getEditDataBox');
        Route::post('bundleTickets/saveNewTeamBox', 'Api\BundleTicketController@saveNewTeamBox')->name('bundleTickets.saveNewTeamBox');
        Route::post('bundleTickets/deleteFromEditBundle', 'Api\BundleTicketController@deleteFromEditBundle')->name('bundleTickets.deleteFromEditBundle');
        Route::post('bundleTickets/getOperationDataBarcode', 'Api\BundleTicketController@getOperationDataBarcode')->name('bundleTickets.getOperationDataBarcode');
        Route::post('bundleTickets/getUserWiseScanningData', 'Api\BundleTicketController@getUserWiseScanningData')->name('bundleTickets.getUserWiseScanningData');
        Route::post('bundleTickets/getShiftDetailsForScanning', 'Api\BundleTicketController@getShiftDetailsForScanning')->name('bundleTickets.getShiftDetailsForScanning');
        Route::post('bundleTickets/getTeamDetails', 'Api\BundleTicketController@getTeamDetails')->name('bundleTickets.getTeamDetails');
        Route::post('BundleTickets/ScanPKByCartonOPD', 'Api\BundleTicketController@ScanPKByCartonOPD')->name('BundleTickets.ScanPKByCartonOPD');
        Route::post('bundleTickets/getBoxFullDetails', 'Api\BundleTicketController@getBoxFullDetails')->name('bundleTickets.getBoxFullDetails');
        Route::post('bundleTickets/getBoxDetailsCartonOPD', 'Api\BundleTicketController@getBoxDetailsCartonOPD')->name('bundleTickets.getBoxDetailsCartonOPD');

        //bundle ticket secondary
        Route::post('bundleTicketsSecondary/createNewRecord', 'Api\BundleTicketSecondaryController@createBundleTicketSecondary')->name('bundleTicketSecondary.createBundleTicketSecondary');
        Route::post('bundleTicketsSecondary/createNewRecordChecked', 'Api\BundleTicketSecondaryController@createBundleTicketSecondaryChecked')->name('bundleTicketSecondary.createBundleTicketSecondaryChecked');
        Route::post('bundleTicketsSecondary/createNewRecordCheckedOPD', 'Api\BundleTicketSecondaryController@createBundleTicketSecondaryCheckedOPD')->name('bundleTicketSecondary.createBundleTicketSecondaryCheckedOPD');
        Route::post('bundleTicketsSecondary/createNewRecordCheckedOld', 'Api\BundleTicketSecondaryController@createBundleTicketSecondaryCheckedOld')->name('bundleTicketSecondary.createBundleTicketSecondaryCheckedOld');

        // Employee
        Route::get('employees/getEmployeeTypes', 'Api\EmployeeController@getEmployeeTypes')->name('employees.getEmployeeTypes');

        // JobCard
        Route::post('jobCards/{jobCard}/actions/{action}', 'Api\JobCardController@handleFsmAction')->name('jobCards.handleFsmAction');
        Route::post('jobCards/getSupermarketJobCards', 'Api\JobCardController@getSupermarketJobCards')->name('jobCards.getSupermarketJobCards');
        Route::post('jobCards/getProductionJobCards', 'Api\JobCardController@getProductionJobCards')->name('jobCards.getProductionJobCards');
        Route::post('jobCards/changeIssuedToFinalize', 'Api\JobCardController@changeIssuedToFinalize')->name('jobCards.changeIssuedToFinalize');
        Route::post('jobCards/moveJobCard', 'Api\JobCardController@moveJobCard')->name('jobCards.moveJobCard');
        Route::post('jobCards/createAndUpdateJobCard', 'Api\JobCardController@createAndUpdateJobCard')->name('jobCards.createAndUpdateJobCard');
        Route::post('jobCards/getFullJobCard', 'Api\JobCardController@getFullJobCard')->name('jobCards.getFullJobCard');
        Route::post('jobCards/changeJobCardStatus', 'Api\JobCardController@changeJobCardStatus')->name('jobCards.changeJobCardStatus');
        Route::post('jobCards/printTrimsReport', 'Api\JobCardController@printTrimsReport')->name('jobCards.printTrimsReport');
        Route::post('jobCards/getJobcards', 'Api\JobCardController@getJobcards')->name('jobCards.getJobcards');
        Route::post('jobCards/printBundleStickerReport', 'Api\JobCardController@printBundleStickerReport')->name('jobCards.printBundleStickerReport');
        Route::post('jobCards/getCurrentShiftDate', 'Api\JobCardController@getCurrentSlotDate')->name('jobCards.getCurrentSlotDate');
        Route::post('jobCards/changeTeam', 'Api\JobCardController@changeTeam')->name('jobCards.changeTeam');
        Route::post('jobCards/createAndUpdateJobCardByCut', 'Api\JobCardController@createAndUpdateJobCardByCut')->name('jobCards.createAndUpdateJobCardByCut');
        Route::post('jobCards/copyJobCard', 'Api\JobCardController@copyJobCard')->name('jobCards.copyJobCard');
        Route::post('jobCards/getBundleReportInJobCardByCut', 'Api\JobCardController@getBundleReportInJobCardByCut')->name('jobCards.getBundleReportInJobCardByCut');

        Route::post('jobCards/getFullWorkOrder', 'Api\JobCardController@getFullWorkOrder')->name('jobCards.getFullWorkOrder');
        Route::post('jobCards/FinalizeWorkOrder', 'Api\JobCardController@FinalizeWorkOrder')->name('jobCards.FinalizeWorkOrder');
        Route::post('jobCards/reOpenWorkOrder', 'Api\JobCardController@reOpenWorkOrder')->name('jobCards.reOpenWorkOrder');
        Route::post('jobCards/deleteWorkOrder', 'Api\JobCardController@deleteWorkOrder')->name('jobCards.deleteWorkOrder');
        Route::post('jobCards/getWorkOrderReport', 'Api\JobCardController@getWorkOrderReport')->name('jobCards.getWorkOrderReport');
        Route::post('jobCards/issueWorkOrder', 'Api\JobCardController@issueWorkOrder')->name('jobCards.issueWorkOrder');
        Route::post('jobCards/holdWorkOrder', 'Api\JobCardController@holdWorkOrder')->name('jobCards.holdWorkOrder');
        Route::post('jobCards/moveWorkOrder', 'Api\JobCardController@moveWorkOrder')->name('jobCards.moveWorkOrder');
        Route::post('jobCards/UpdateTrimStatus', 'Api\JobCardController@UpdateTrimStatus')->name('jobCards.UpdateTrimStatus');

        // TrimStore
        Route::get('trimStores/getStatuses', 'Api\TrimStoreController@getStatuses')->name('trimStores.getStatuses');

        // DailyShift
        Route::post('dailyShifts/createShiftTeamsSlotsPerDay', 'Api\DailyShiftController@createShiftTeamsSlotsPerDay')->name('dailyShifts.createShiftTeamsSlotsPerDay');
        Route::post('dailyShifts/getDailyShiftWithChildren', 'Api\DailyShiftController@getDailyShiftWithChildren')->name('dailyShifts.getDailyShiftWithChildren');
        Route::post('dailyShifts/modifyShiftTeamsSlotsPerDay', 'Api\DailyShiftController@modifyShiftTeamsSlotsPerDay')->name('dailyShifts.modifyShiftTeamsSlotsPerDay');
        Route::post('dailyShifts/getShiftPerDay', 'Api\DailyShiftController@getShiftPerDay')->name('dailyShifts.getShiftPerDay');
        Route::post('dailyShifts/generateSlots', 'Api\DailyShiftController@generateSlots')->name('dailyShifts.generateSlots');
        // Route::post('dailyShifts/getDailyShifts', 'Api\DailyShiftController@getDailyShifts')->name('dailyShifts.getDailyShifts');


        // DailyShiftTeam
        Route::post('dailyShiftTeams/getEmployeeAllocation', 'Api\DailyShiftTeamController@getEmployeeAllocation')->name('dailyShiftTeams.getEmployeeAllocation');
        Route::post('dailyShiftTeams/getByDay', 'Api\DailyShiftTeamController@getByDay')->name('dailyShiftTeams.getByDay');
        Route::post('dailyShiftTeams/getTargeInformation', 'Api\DailyShiftTeamController@getTargeInformation')->name('dailyShiftTeams.getTargeInformation');
        Route::post('dailyShiftTeams/getTeamsPerDay', 'Api\DailyShiftTeamController@getTeamsPerDay')->name('dailyShiftTeams.getTeamsPerDay');
        Route::post('dailyShiftTeams/getTeamsPerDayByCodeAndDesc', 'Api\DailyShiftTeamController@getTeamsPerDayByCodeAndDesc')->name('dailyShiftTeams.getTeamsPerDayByCodeAndDesc');
        Route::post('dailyShiftTeams/getShiftSlotsByDailyShiftTeam', 'Api\DailyShiftTeamController@getShiftSlotsByDailyShiftTeam')->name('dailyShiftTeams.getShiftSlotsByDailyShiftTeam');
        Route::post('dailyShiftTeams/getTeamsPerShift', 'Api\DailyShiftTeamController@getTeamsPerShift')->name('dailyShiftTeams.getTeamsPerShift');
        Route::post('dailyShiftTeams/getEmployeeAllocationByProgress', 'Api\DailyShiftTeamController@getEmployeeAllocationByProgress')->name('dailyShiftTeams.getEmployeeAllocationByProgress');
        Route::post('dailyShiftTeams/getTargetInformationSetup', 'Api\DailyShiftTeamController@getTargetInformationSetup')->name('dailyShiftTeams.getTargetInformationSetup');
        Route::post('dailyShiftTeams/createTargetInformationSetup', 'Api\DailyShiftTeamController@createTargetInformationSetup')->name('dailyShiftTeams.createTargetInformationSetup');
        Route::post('dailyShiftTeams/updateTargetInformationSetup', 'Api\DailyShiftTeamController@updateTargetInformationSetup')->name('dailyShiftTeams.updateTargetInformationSetup');


        //DailyTeamEmployee
        Route::post('dailyTeamEmployees/allocateEmployees', 'Api\DailyTeamEmployeeController@allocateEmployees')->name('dailyTeamEmployees.allocateEmployees');
        Route::post('dailyTeamEmployees/reallocateEmployees', 'Api\DailyTeamEmployeeController@reallocateEmployees')->name('dailyTeamEmployees.reallocateEmployees');

        //Team
        Route::post('teams/getVsmList', 'Api\TeamController@getVsmList')->name('teams.getVsmList');
        Route::post('teams/getSupervisorList', 'Api\TeamController@getSupervisorList')->name('teams.getSupervisorList');
        Route::post('teams/ReCalculateTargetData', 'Api\TeamController@ReCalculateTargetData')->name('teams.ReCalculateTargetData');
        Route::post('teams/getTeamReport', 'Api\TeamController@getTeamReport')->name('teams.getTeamReport');

        // QcExclude
        Route::get('qcExcludes/{param}', 'Api\QcExcludeController@show')->name('qcExcludes.show');
        Route::get('qcExcludes', 'Api\QcExcludeController@index')->name('qcExcludes.index');
        Route::post('qcExcludes', 'Api\QcExcludeController@store')->name('qcExcludes.store');
        Route::put('qcExcludes', 'Api\QcExcludeController@update')->name('qcExcludes.update');
        Route::delete('qcExcludes', 'Api\QcExcludeController@destroy')->name('qcExcludes.destroy');

        // RecoverableScan
        Route::get('recoverableScan/{param}', 'Api\RecoverableScanController@show')->name('recoverableScan.show');
        Route::get('recoverableScan', 'Api\RecoverableScanController@index')->name('recoverableScan.index');
        Route::post('recoverableScan', 'Api\RecoverableScanController@store')->name('recoverableScan.store');
        Route::put('recoverableScan', 'Api\RecoverableScanController@update')->name('recoverableScan.update');
        Route::delete('recoverableScan', 'Api\RecoverableScanController@destroy')->name('recoverableScan.destroy');

        // DailyScanningSlot
        Route::post('dailyScanningSlots/getBySeqNo', 'Api\DailyScanningSlotController@getBySeqNo')->name('dailyScanningSlots.getBySeqNo');
        Route::post('dailyScanningSlots/getSlotsWithProgress', 'Api\DailyScanningSlotController@getSlotsWithProgress')->name('dailyScanningSlots.getSlotsWithProgress');

        //DailyScanningSlotEmployee
        Route::post('dailyScanningSlotEmployees/getSearchByAllocation', 'Api\DailyScanningSlotEmployeeController@getSearchByAllocation')->name('dailyScanningSlotEmployees.getSearchByAllocation');
        Route::post('dailyScanningSlotEmployees/getSearchResultsByAllocation', 'Api\DailyScanningSlotEmployeeController@getSearchResultsByAllocation')->name('dailyScanningSlotEmployees.getSearchResultsByAllocation');
        Route::post('dailyScanningSlotEmployees/assignEmployee', 'Api\DailyScanningSlotEmployeeController@assignEmployee')->name('dailyScanningSlotEmployees.assignEmployee');

        // PackingList
        Route::post('packingLists/createAndUpdatePackingList', 'Api\PackingListController@createAndUpdatePackingList')->name('packingLists.createAndUpdatePackingList');
        Route::post('packingLists/getFullPackingList', 'Api\PackingListController@getFullPackingList')->name('packingLists.getFullPackingList');
        Route::post('packingLists/generatePackingListDetails', 'Api\PackingListController@generatePackingListDetails')->name('packingLists.generatePackingListDetails');
        Route::post('packingLists/getCalculatedNoOfCartons', 'Api\PackingListController@getCalculatedNoOfCartons')->name('packingLists.getCalculatedNoOfCartons');
        Route::post('packingLists/getPackingListBalanceQuantity', 'Api\PackingListController@getPackingListBalanceQuantity')->name('packingLists.getPackingListBalanceQuantity');
        Route::post('packingListDetails/getPackingListLayOutReport', 'Api\PackingListController@getPackingListLayOutReport')->name('PackingListController.getPackingListLayOutReport');
        Route::post('packingLists/updateBoxScanning', 'Api\PackingListController@updateBoxScanning')->name('packingLists.updateBoxScanning');
        Route::post('packingLists/updateBoxScanningOld', 'Api\PackingListController@updateBoxScanningOld')->name('packingLists.updateBoxScanningOld');
        Route::post('packingLists/getFgScanningList', 'Api\PackingListController@getFgScanningList')->name('packingLists.getFgScanningList');
        Route::post('packingLists/getFgScanningListOld', 'Api\PackingListController@getFgScanningListOld')->name('packingLists.getFgScanningListOld');
        Route::post('packingLists/deleteFgScanningList', 'Api\PackingListController@deleteFgScanningList')->name('packingLists.deleteFgScanningList');
        Route::post('packingLists/deleteFgScanningListOld', 'Api\PackingListController@deleteFgScanningListOld')->name('packingLists.deleteFgScanningListOld');
        Route::post('packingLists/updateCurrentVPO', 'Api\PackingListController@updateCurrentVPO')->name('packingLists.updateCurrentVPO');
        Route::post('packingLists/reopenPackingList', 'Api\PackingListController@reopenPackingList')->name('packingLists.reopenPackingList');
        Route::post('packingLists/revisePackingList', 'Api\PackingListController@revisePackingList')->name('packingLists.revisePackingList');
        Route::post('packingLists/finalizeRevisePackingList', 'Api\PackingListController@finalizeRevisePackingList')->name('packingLists.finalizeRevisePackingList');
        Route::post('packingLists/get_scan_box', 'Api\PackingListController@get_scan_box')->name('packingLists.get_scan_box');
        Route::post('packingLists/getPackingListBySoc', 'Api\PackingListController@getPackingListBySoc')->name('packingLists.getPackingListBySoc');
        Route::post('packingLists/removeBox', 'Api\PackingListController@removeBox')->name('packingLists.removeBox');
        Route::post('packingLists/getBoxDetailsFG', 'Api\PackingListController@getBoxDetailsFG')->name('packingLists.getBoxDetailsFG');
        Route::post('packingLists/fgScanFinalSave', 'Api\PackingListController@fgScanFinalSave')->name('packingLists.fgScanFinalSave');
        Route::post('packingLists/getBoxDetailsBS', 'Api\PackingListController@getBoxDetailsBS')->name('packingLists.getBoxDetailsBS');
        Route::post('packingLists/getAllBoxIds', 'Api\PackingListController@getAllBoxIds')->name('packingLists.getAllBoxIds');
        Route::post('packingLists/updateCartonLocation', 'Api\PackingListController@updateCartonLocation')->name('packingLists.updateCartonLocation');
        Route::post('packingLists/getDailyTransfer', 'Api\PackingListController@getDailyTransfer')->name('packingLists.getDailyTransfer');
        Route::post('packingLists/getActivePackingList', 'Api\PackingListController@getActivePackingList')->name('packingLists.getActivePackingList');
        Route::post('packingLists/getPLStickerByMultiplePL', 'Api\PackingListController@getPLStickerByMultiplePL')->name('packingLists.getPLStickerByMultiplePL');
        // CartonPackingList
        Route::post('cartonPackingLists/addNewCarton', 'Api\CartonPackingListController@addNewCarton')->name('cartonPackingLists.addNewCarton');

        // PackingListSoc
        Route::post('packingListSoc/getSearchByPackingListSoc', 'Api\PackingListSocController@getSearchByPackingListSoc')->name('packingListSoc.getSearchByPackingListSoc');
        Route::post('packingListSoc/getSearchResultsByPackingListSoc', 'Api\PackingListSocController@getSearchResultsByPackingListSoc')->name('packingListSoc.getSearchResultsByPackingListSoc');
        Route::post('packingListSoc/getPackingListSocQuantities', 'Api\PackingListSocController@getPackingListSocQuantities')->name('packingListSoc.getPackingListSocQuantities');



        // PackingListDetails
        Route::post('packingListDetails/getFullDetails', 'Api\PackingListDetailController@getFullDetails')->name('packingListDetails.getFullDetails');
        Route::post('packingListDetails/deleteDetailsByPackingList', 'Api\PackingListDetailController@deleteDetailsByPackingList')->name('packingListDetails.deleteDetailsByPackingList');
        Route::post('packingListDetails/getPackingListSocByCarton', 'Api\PackingListDetailController@getPackingListSocByCarton')->name('packingListDetails.getPackingListSocByCarton');
        Route::post('packingListDetails/editPackingCartonQty', 'Api\PackingListDetailController@editPackingCartonQty')->name('packingListDetails.editPackingCartonQty');
        Route::post('packingListDetails/deleteCarton', 'Api\PackingListDetailController@deleteCarton')->name('packingListDetails.deleteCarton');
        Route::post('packingListDetails/getPackingListStickers', 'Api\PackingListDetailController@getPackingListStickers')->name('packingListDetails.getPackingListStickers');

        // DataFileImporter
        Route::post('dataFileImporter', 'Api\DataFileImporterController@import')->name('dataFileImporter');

        // Integrations
        Route::post('integrations/createLogEntry', 'Api\IntegrationLogController@createLogEntry')->name('integrations.createLogEntry');
        Route::post('integrations/updateLogEntry', 'Api\IntegrationLogController@updateLogEntry')->name('integrations.updateLogEntry');
        Route::post('integrations/getLogsByDate', 'Api\IntegrationLogController@getLogsByDate')->name('integrations.getLogsByDate');
        Route::post('integrations/createEntry', 'Api\IntegrationDetailController@createEntry')->name('integrations.createEntry');
        Route::post('integrations/getDetailsByLogId', 'Api\IntegrationDetailController@getDetailsByLogId')->name('integrations.getDetailsByLogId');
        Route::post('integrations/createAndUpdateCartons', 'Api\DataIntegrationController@createAndUpdateCartons')->name('integrations.createAndUpdateCartons');
        Route::post('integrations/createAndUpdateRoutes', 'Api\DataIntegrationController@createAndUpdateRoutes')->name('integrations.createAndUpdateRoutes');
        Route::post('integrations/createAndUpdateBuyers', 'Api\DataIntegrationController@createAndUpdateBuyers')->name('integrations.createAndUpdateBuyers');
        Route::post('integrations/createAndUpdateRouteOperations', 'Api\DataIntegrationController@createAndUpdateRouteOperations')->name('integrations.createAndUpdateRouteOperations');
        Route::post('integrations/createAndUpdateStyles', 'Api\DataIntegrationController@createAndUpdateStyles')->name('integrations.createAndUpdateStyles');
        Route::post('integrations/createAndUpdateStyleFabrics', 'Api\DataIntegrationController@createAndUpdateStyleFabrics')->name('integrations.createAndUpdateStyleFabrics');
        Route::post('integrations/createAndUpdateSocs', 'Api\DataIntegrationController@createAndUpdateSocs')->name('integrations.createAndUpdateSocs');
        Route::post('integrations/createAndUpdateFpos', 'Api\DataIntegrationController@createAndUpdateFpos')->name('integrations.createAndUpdateFpos');
        Route::post('integrations/createAndUpdateFpoFabrics', 'Api\DataIntegrationController@createAndUpdateFpoFabrics')->name('integrations.createAndUpdateFpoFabrics');
        Route::post('integrations/createAndUpdateEmployees', 'Api\DataIntegrationController@createAndUpdateEmployees')->name('integrations.createAndUpdateEmployees');
        Route::post('integrations/createAndUpdateTeams', 'Api\DataIntegrationController@createAndUpdateTeams')->name('integrations.createAndUpdateTeams');
        Route::post('integrations/SyncEmployeesWithTeams', 'Api\DataIntegrationController@SyncEmployeesWithTeams')->name('integrations.SyncEmployeesWithTeams');
        Route::post('integrations/createAndUpdateFrRecords', 'Api\DataIntegrationController@createAndUpdateFrRecords')->name('integrations.createAndUpdateFrRecords');
        Route::post('integrations/syncTotalTargetValues', 'Api\DataIntegrationController@syncTotalTargetValues')->name('integrations.syncTotalTargetValues');

        //Permissions
        Route::post('permissions/isAuthorized', 'Api\PermissionController@isAuthorized')->name('integrations.isAuthorized');
        Route::post('permissions/getNavigator', 'Api\PermissionController@getNavigator')->name('integrations.getNavigator');
        Route::post('permissions/getPermissions', 'Api\PermissionController@getPermissions')->name('integrations.getPermissions');
        Route::post('permissions/updatePermissions', 'Api\PermissionController@updatePermissions')->name('integrations.updatePermissions');
        Route::post('permissions/changePassword', 'Api\UserController@changePassword')->name('integrations.changePassword');

        //Queries
        Route::post('Queries/getDetailsBundlePrinting', 'Api\QueriesController@getDetailsBundlePrinting')->name('getDetailsBundlePrinting');
        Route::post('Queries/getDashBoardData', 'Api\QueriesController@getDashBoardData')->name('Queries.getDashBoardData');
        Route::post('Queries/getDashboardTemplate', 'Api\QueriesController@getDashboardTemplate')->name('Queries.getDashboardTemplate');
        Route::post('Queries/getTeamPerShift', 'Api\QueriesController@getTeamPerShift')->name('Queries.getTeamPerShift');
        Route::post('Queries/getOperation', 'Api\QueriesController@getOperation')->name('Queries.getOperation');
        Route::post('Queries/getJobCardData', 'Api\QueriesController@getJobCardData')->name('Queries.getJobCardData');

        // Marker Creation

        Route::post('MarkerPlan/getSearchByStyleFabric', 'Api\MarkerPlanController@getSearchByStyleFabric')->name('getSearchByStyleFabric');
        Route::post('MarkerPlan/generateMarkerPlan', 'Api\MarkerPlanController@generateMarkerPlan')->name('generateMarkerPlan');


        // Invoice
        Route::post('Invoice/createAndUpdateInvoice', 'Api\InvoiceController@createAndUpdateInvoice')->name('createAndUpdateInvoice');

        Route::post('Invoice/getSearchByInvoice', 'Api\InvoiceController@getSearchByInvoice')->name('getSearchByInvoice');
        Route::post('Invoice/getInvoicePrint', 'Api\InvoiceController@getInvoicePrint')->name('getInvoicePrint');
        Route::post('Invoice/getStatus', 'Api\InvoiceController@getStatus')->name('getStatus');
        Route::post('Invoice/upload', 'Api\ImageUploadController@upload')->name('upload');

        Route::post('Invoice/getInvoice', 'Api\InvoiceController@getInvoice')->name('getInvoice');
        Route::post('Invoice/setupInvoiceID', 'Api\InvoiceController@setupInvoiceID')->name('setupInvoiceID');
        Route::post('Invoice/getTransactionSummary', 'Api\InvoiceController@getTransactionSummary')->name('getTransactionSummary');
        Route::get('Invoice/getPaymentMethods/{type}', 'Api\InvoiceController@getPaymentMethods')->name('getPaymentMethods');

        // Images
        Route::post('images/deleteImage', 'Api\ImageUploadController@deleteImage')->name('deleteImage');
        Route::get('/images/{invoice_id}', 'Api\ImageUploadController@getImages')->name('getImages');

        // Day Book
        Route::post('Invoice/getPaymentMethod', 'Api\InvoiceController@getPaymentMethod')->name('getPaymentMethod');
        Route::post('Invoice/createDayBookTransaction', 'Api\InvoiceController@createDayBookTransaction')->name('createDayBookTransaction');
        Route::post('Invoice/createPaymentType', 'Api\InvoiceController@createPaymentType')->name('createPaymentType');

        // UOM
        Route::get('Uom/getUoms', 'Api\UomController@getUoms')->name('getUoms');
        Route::post('Uom/createAndUpdateUom', 'Api\UomController@createAndUpdateUom')->name('createAndUpdateUom');
        Route::post('Uom/deleteUom', 'Api\UomController@deleteUom')->name('deleteUom');

        // Batch
        Route::get('Batch/getBatches', 'Api\BatchController@getBatches')->name('getBatches');
        Route::post('Batch/createAndUpdateBatch', 'Api\BatchController@createAndUpdateBatch')->name('createAndUpdateBatch');
        Route::post('Batch/deleteBatch', 'Api\BatchController@deleteBatch')->name('deleteBatch');
        Route::post('Batch/getSearchByBatch', 'Api\BatchController@getSearchByBatch')->name('getSearchByBatch');
        Route::post('Batch/getBatchById', 'Api\BatchController@getBatchById')->name('getBatchById');
        Route::post('Batch/getCostSheetDataById', 'Api\BatchController@getCostSheetDataById')->name('getCostSheetDataById');
        Route::post('Batch/getBatchComparisonByModel', 'Api\BatchController@getBatchComparisonByModel')->name('getBatchComparisonByModel');

        // MRN
        Route::get('Mrn/getMrns', 'Api\MrnController@getMrns')->name('getMrns');
        Route::post('Mrn/createAndUpdateMrn', 'Api\MrnController@createAndUpdateMrn')->name('createAndUpdateMrn');
        Route::post('Mrn/deleteMrn', 'Api\MrnController@deleteMrn')->name('deleteMrn');
        Route::post('mrns/createAndUpdate', 'Api\MrnController@createAndUpdate')->name('mrns.createAndUpdate');
        Route::get('mrns/{id}', 'Api\MrnController@show')->name('mrns.show');
        Route::post('mrns/finalize', 'Api\MrnController@finalize')->name('mrns.finalize');
        Route::post('mrns/reopen', 'Api\MrnController@reopen')->name('mrns.reopen');
        Route::post('mrns/getSearchByMrn', 'Api\MrnController@getSearchByMrn')->name('mrns.getSearchByMrn');
        Route::post('mrns/getMrnPrint', 'Api\MrnController@getMrnPrint')->name('mrns.getMrnPrint');
        Route::post('mrns/download-details-excel', 'InventoryController@downloadMrnDetailsExcel')->name('mrns.downloadDetailsExcel');


        // Returnable
        Route::get('Returnable/getReturnables', 'Api\ReturnableController@getReturnables')->name('getReturnables');
        Route::get('returnable/getPendingReturnables', 'Api\ReturnableController@getPendingReturnables')->name('getPendingReturnables');
        Route::post('Returnable/createAndUpdateReturnable', 'Api\ReturnableController@createAndUpdateReturnable')->name('createAndUpdateReturnable');
        Route::post('Returnable/deleteReturnable', 'Api\ReturnableController@deleteReturnable')->name('deleteReturnable');

        // Inventory
        Route::get('inventory/warehouse/{id}', 'InventoryController@getWarehouseStructure')->name('inventory.warehouse.structure');
        Route::post('inventory/transfer', 'InventoryController@transferStock')->name('inventory.transfer');
        Route::get('inventory/balance', 'InventoryController@getBalance')->name('inventory.balance');
        Route::post('inventory/available-qty', 'InventoryController@getAvailableQtyByStockItem')->name('inventory.availableQty');
        Route::post('inventory/issue', 'InventoryController@issueStock')->name('inventory.issue');
        Route::delete('mrn-issuance/delete/{mrn_detail_id}', 'InventoryController@deleteIssuance')->name('mrn-issuance.delete');
        Route::post('mrn-issuance/complete', 'InventoryController@completeIssuance')->name('mrn-issuance.complete');


        // Returnable
        Route::post('inventory/saveReturnable', 'InventoryController@saveReturnable')->name('inventory.saveReturnable');
        Route::post('inventory/getReturnable', 'InventoryController@getReturnable')->name('inventory.getReturnable');
        Route::post('inventory/updateReturnable', 'InventoryController@updateReturnable')->name('inventory.updateReturnable');

        // Warehouses and Locations CRUD
        Route::get('warehouses/{id}/stickers', 'WarehouseController@printStickers')->name('warehouses.stickers');
        Route::apiResource('warehouses', 'WarehouseController');

        // Warehouse Locations CRUD
        Route::apiResource('warehouse-locations', 'WarehouseLocationController');

        // Stock Materials CRUD
        Route::get('stock-materials/search', 'StockMaterialController@search');
        Route::get('stock-materials/stickers', 'StockMaterialController@printStickers')->name('material.stickers');
        Route::get('stock-materials/stickers/{ids}', 'StockMaterialController@printStickersByIds')->name('material.stickersbyIds');
        Route::apiResource('stock-materials', 'StockMaterialController');


        // WHL Items CRUD
        Route::post('whl-items/move-bin', 'WhlItemController@moveBin')->name('whl-items.moveBin');
        Route::apiResource('whl-items', 'WhlItemController');

        // GRN Details CRUD
        Route::apiResource('grn-details', 'GrnDetailController');

        // GRNs CRUD
        Route::post('grns/addTransaction', 'GrnController@addTransaction')->name('grns.addTransaction');
        Route::post('grns/deleteTransaction', 'GrnController@deleteTransaction')->name('grns.deleteTransaction');
        Route::post('grns/complete', 'GrnController@updateStatus')->name('grns.updateStatus');
        Route::get('grns/{id}/transactions', 'GrnController@getTransactions')->name('grns.getTransactions');
        Route::post('grns/search', 'GrnController@search')->name('grns.search');
        Route::apiResource('grns', 'GrnController')->only(['index', 'show', 'store', 'destroy']);

        // MainModel CRUD
        Route::apiResource('main-models', 'MainModelController');

        // Model CRUD
        Route::apiResource('models', 'ModelController');

        // ModelStockItem CRUD
        Route::apiResource('model-stock-items', 'ModelStockItemController');

        // Supplier APIs
        Route::get('suppliers', 'Api\SupplierController@index');
        Route::get('suppliers/{id}', 'Api\SupplierController@show');
        Route::post('suppliers', 'Api\SupplierController@store');
        Route::put('suppliers/{id}', 'Api\SupplierController@update');

        // Purchase Order APIs
        Route::get('purchase-orders', 'Api\PurchaseOrderController@index');
        Route::get('purchase-orders/{id}', 'Api\PurchaseOrderController@show');
        Route::post('purchase-orders', 'Api\PurchaseOrderController@store');
        Route::put('purchase-orders/{id}', 'Api\PurchaseOrderController@update');
    });
});

// Route::apiResources([
//     'companies' => 'Api\CompanyController'
// ]);
