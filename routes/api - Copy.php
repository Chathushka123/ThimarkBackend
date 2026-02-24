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

        ///////////////////////////  For Excel Report  /////////////////////
        Route::get('export', 'ImportExportController@export')->name('export');
        Route::get('importExportView', 'ImportExportController@importExportView');
        Route::post('import', 'ImportExportController@import')->name('import');

        // Auth
        Route::get('user', 'Api\AuthController@user')->name('user.get');
        Route::post('logout', 'Api\AuthController@logout')->name('logout');

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

        // CombineOrder
        Route::post('combineOrders/combineFpos', 'Api\CombineOrderController@combineFpos')->name('combineOrders.combineFpos');
        Route::post('combineOrders/getConnectedFpos', 'Api\CombineOrderController@getConnectedFpos')->name('combineOrders.getConnectedFpos');
        Route::post('combineOrders/getCombineOrdersByStyleRef', 'Api\CombineOrderController@getCombineOrdersByStyleRef')->name('combineOrders.getCombineOrdersByStyleRef');
        Route::post('combineOrders/getFabricInfo', 'Api\CombineOrderController@getFabricInfo')->name('combineOrders.getFabricInfo');
        Route::post('combineOrders/generateCutPlan', 'Api\CombineOrderController@generateCutPlan')->name('combineOrders.generateCutPlan');
        Route::post('combineOrders/getSearchByStyleRefCode', 'Api\CombineOrderController@getSearchByStyleRefCode')->name('combineOrders.getSearchByStyleRefCode');
        Route::post('combineOrders/getSearchResultByStyleRefCode', 'Api\CombineOrderController@getSearchResultByStyleRefCode')->name('combineOrders.getSearchResultByStyleRefCode');
        Route::post('combineOrders/getFpoFabricAndOperation', 'Api\CombineOrderController@getFpoFabricAndOperation')->name('combineOrders.getFpoFabricAndOperation');

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

        // FpoCutPlan
        Route::post('fpoCutPlans/getPendingFpoCutPlansByCombineOrder', 'Api\FpoCutPlanController@getPendingFpoCutPlansByCombineOrder')->name('fpoCutPlans.getPendingFpoCutPlansByCombineOrder');
        Route::post('fpoCutPlans/createFppo', 'Api\FpoCutPlanController@createFppo')->name('fpoCutPlans.createFppo');
        Route::post('fpoCutPlans/manualCutUpdate', 'Api\FpoCutPlanController@manualCutUpdate')->name('fpoCutPlans.manualCutUpdate');
        Route::post('fpoCutPlans/printConsumptionReport', 'Api\FpoCutPlanController@printConsumptionReport')->name('fpoCutPlans.printConsumptionReport');


        //FPPO
        Route::post('fppos/getSumOfCutUpdates', 'Api\FppoController@getSumOfCutUpdates')->name('fppos.getSumOfCutUpdates');
        Route::post('fppos/createBundle', 'Api\FppoController@createBundle')->name('fppos.createBundle');
        Route::post('fppos/updateFppo', 'Api\FppoController@updateFppo')->name('fppos.updateFppo');
        Route::post('fppos/createBundleTickets', 'Api\FppoController@createBundleTickets')->name('fppos.createBundleTickets');
        Route::post('fppos/createManualBundle', 'Api\FppoController@createManualBundle')->name('fppos.createManualBundle');
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


        // BundleBin
        Route::post('bundleBins/getByFppo', 'Api\BundleBinController@getByFppo')->name('bundleBins.getByFppo');
        Route::post('bundleBins/createBundle', 'Api\BundleBinController@createBundle')->name('bundleBins.createBundle');

        // BundleTicket
        //Route::post('bundleTickets/scan', 'Api\BundleTicketController@scan')->name('bundleTickets.scan');
        Route::post('bundleTickets/scanByScanningSlotOld', 'Api\BundleTicketController@scanByScanningSlotOld')->name('bundleTickets.scanByScanningSlotOld');
        Route::post('bundleTickets/scanByScanningSlot', 'Api\BundleTicketController@scanByScanningSlot')->name('bundleTickets.scanByScanningSlot');
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

        //bundle ticket secondary
        Route::post('bundleTicketsSecondary/createNewRecord', 'Api\BundleTicketSecondaryController@createBundleTicketSecondary')->name('bundleTicketSecondary.createBundleTicketSecondary');
        Route::post('bundleTicketsSecondary/createNewRecordChecked', 'Api\BundleTicketSecondaryController@createBundleTicketSecondaryChecked')->name('bundleTicketSecondary.createBundleTicketSecondaryChecked');
        Route::post('bundleTicketsSecondary/createNewRecordCheckedOld', 'Api\BundleTicketSecondaryController@createBundleTicketSecondaryCheckedOld')->name('bundleTicketSecondary.createBundleTicketSecondaryCheckedOld');

        // Employee
        Route::get('employees/getEmployeeTypes', 'Api\EmployeeController@getEmployeeTypes')->name('employees.getEmployeeTypes');

        // JobCard
        Route::post('jobCards/{jobCard}/actions/{action}', 'Api\JobCardController@handleFsmAction')->name('jobCards.handleFsmAction');
        Route::post('jobCards/getSupermarketJobCards', 'Api\JobCardController@getSupermarketJobCards')->name('jobCards.getSupermarketJobCards');
        Route::post('jobCards/getProductionJobCards', 'Api\JobCardController@getProductionJobCards')->name('jobCards.getProductionJobCards');
        Route::post('jobCards/moveJobCard', 'Api\JobCardController@moveJobCard')->name('jobCards.moveJobCard');
        Route::post('jobCards/createAndUpdateJobCard', 'Api\JobCardController@createAndUpdateJobCard')->name('jobCards.createAndUpdateJobCard');
        Route::post('jobCards/getFullJobCard', 'Api\JobCardController@getFullJobCard')->name('jobCards.getFullJobCard');
        Route::post('jobCards/changeJobCardStatus', 'Api\JobCardController@changeJobCardStatus')->name('jobCards.changeJobCardStatus');
        Route::post('jobCards/printTrimsReport', 'Api\JobCardController@printTrimsReport')->name('jobCards.printTrimsReport');
        Route::post('jobCards/getJobcards', 'Api\JobCardController@getJobcards')->name('jobCards.getJobcards');
        Route::post('jobCards/printBundleStickerReport', 'Api\JobCardController@printBundleStickerReport')->name('jobCards.printBundleStickerReport');

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
        Route::post('Queries/getDashBoardData', 'Api\QueriesController@getDashBoardData')->name('Queries.getDashBoardData');
        Route::post('Queries/getDashboardTemplate', 'Api\QueriesController@getDashboardTemplate')->name('Queries.getDashboardTemplate');
        Route::post('Queries/getTeamPerShift', 'Api\QueriesController@getTeamPerShift')->name('Queries.getTeamPerShift');
        Route::post('Queries/getOperation', 'Api\QueriesController@getOperation')->name('Queries.getOperation');
    });
});

// Route::apiResources([
//     'companies' => 'Api\CompanyController'
// ]);
