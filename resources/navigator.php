<?php
$navigator_json = '
[
   {
      "caption":"Dashboards",
       "id":"master-data",
       "icon":"fas fa-database",
       "type":"folder",
       "permitted" : 0,
       "nodes":[
      {
          "caption": "Floor Dashboard",
          "icon": "fas fa-tv",
          "type": "node",
          "permitted" : 0,
          "path": "/wipFloorDashboard"
        },
        {
          "caption": "Management Dashboard",
          "icon": "fas fa-chart-line",
          "type": "node",
          "permitted" : 0,
          "path": "/wipManagementDashboard"
        }
      ]
   },
    {
       "caption":"Master Data",
       "id":"master-data",
       "icon":"fas fa-database",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


         {
            "caption":"Material",
            "icon":"fas fa-cubes",
            "type":"node",
            "permitted" : 0,
            "path":"/material"
         },

          {
            "caption":"Warehouse ",
            "icon":"fas fa-warehouse",
            "type":"node",
            "permitted" : 0,
            "path":"/warehouse"
         },
          {
            "caption":"Model",
            "icon":"fas fa-cog",
            "type":"node",
            "permitted" : 0,
            "path":"/model"
         },
         {
            "caption":"Supplier",
            "icon":"fas fa-cog",
            "type":"node",
            "permitted" : 0,
            "path":"/suppliers"
         },
         {
            "caption":"Operation",
            "icon":"fas fa-cog",
            "type":"node",
            "permitted" : 0,
            "path":"/operation"
         },
         {
            "caption":"Routing",
            "icon":"fas fa-cog",
            "type":"node",
            "permitted" : 0,
            "path":"/routing"
         }


       ]
    },

    {
       "caption":"Order Management",
       "id":"order-management",
       "icon":"fas fa-clipboard-list",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


        {
            "caption":"Batch Creation",
            "icon":"fas fa-layer-group",
            "type":"node",
            "permitted" : 0,
            "path":"/batchCreation"
         }


       ]
    },

    {
       "caption":"Inventory",
       "id":"inventory-management",
       "icon":"fas fa-boxes",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


        {
            "caption":"Inventory",
            "icon":"fas fa-box-open",
            "type":"node",
            "permitted" : 0,
            "path":"/inventory"
         },
         {
            "caption":"GRN",
            "icon":"fas fa-dolly",
            "type":"node",
            "permitted" : 0,
            "path":"/grn"
         },
         {
            "caption":"Returnable",
            "icon":"fas fa-undo",
            "type":"node",
            "permitted" : 0,
            "path":"/returnable"
         },
         {
            "caption":"Stock Transfer",
            "icon":"fas fa-exchange-alt",
            "type":"node",
            "permitted" : 0,
            "path":"/stockTransfer"
         }


       ]
    },



    {
       "caption":"MRN Management",
       "id":"mrn-management",
       "icon":"fas fa-file-export",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


        {
            "caption":"MRN Creation",
            "icon":"fas fa-file-signature",
            "type":"node",
            "permitted" : 0,
            "path":"/mrnCreation"
         },
                 {
            "caption":"MRN Issuance",
            "icon":"fas fa-hand-holding",
            "type":"node",
            "permitted" : 0,
            "path":"/mrnIssuance"
         }


       ]
    },

   {
       "caption":"Production",
       "id":"production-management",
       "icon":"fas fa-calculator",
       "type":"folder",
       "permitted" : 0,
       "nodes":[

         {
            "caption":"Supermarket GRN",
            "icon":"fas fa-file-invoice-dollar",
            "type":"node",
            "permitted" : 0,
            "path":"/supermarketGrn"
         },
        {
            "caption":"Work Order Creation",
            "icon":"fas fa-file-invoice-dollar",
            "type":"node",
            "permitted" : 0,
            "path":"/workOrderCreation"
         },
         {
            "caption":"Trolley",
            "icon":"fas fa-file-invoice-dollar",
            "type":"node",
            "permitted" : 0,
            "path":"/trolley"
         }



       ]
    },

        {
       "caption":"Cost Sheet",
       "id":"cost-sheet-management",
       "icon":"fas fa-calculator",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


        {
            "caption":"CostSheet View",
            "icon":"fas fa-file-invoice-dollar",
            "type":"node",
            "permitted" : 0,
            "path":"/costSheetView"
         }


       ]
    },

   {
       "caption":"Purchase Orders",
       "id":"purchase-order-management",
       "icon":"fas fa-calculator",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


        {
            "caption":"Purchase Orders",
            "icon":"fas fa-file-invoice-dollar",
            "type":"node",
            "permitted" : 0,
            "path":"/purchaseOrder"
         }


       ]
    },


   {
       "caption":"QC Management",
       "id":"qc-management",
       "icon":"fas fa-check",
       "type":"folder",
       "permitted" : 0,
       "nodes":[


         {
            "caption":"Grn Confirmation",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/openGrns"
         }
      ]
   },

   {
       "caption":"Reports",
       "id":"report-management",
       "icon":"fas fa-book",
       "type":"folder",
       "permitted" : 0,
       "nodes":[

         {
            "caption":"Current Stock",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/currentStock"
         },
         {
            "caption":"Grn Report",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/grnReport"
         },
         {
            "caption":"Work Order Status",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/workOrderStatus"
         }
      ]
   },
   {
      "caption": "Scanning",
      "id": "scanning",
      "icon": "fas fa-qrcode",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption": "Scanning",
          "icon": "fas fa-barcode",
          "type": "node",
          "permitted" : 0,
          "path": "/productionWIPScanning"
        },
        
        {
          "caption": "Delete Scanned Bundles",
          "icon": "fas fa-eraser",
          "type": "node",
          "permitted" : 0,
          "path": "/bundleTicketAudit"
        }
      ]
    },
    {
      "caption": "Shift Management",
      "id": "shift-management",
      "icon": "fas fa-business-time",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption": "Daily Shifts",
          "icon": "fas fa-calendar-day",
          "type": "node",
          "permitted" : 0,
          "path": "/dailyShift"
        },
        {
          "caption": "Teams",
          "icon": "fas fa-users",
          "type": "node",
          "permitted" : 0,
          "path": "/team"
        },
        {
          "caption": "Shifts",
          "icon": "fas fa-clock",
          "type": "node",
          "permitted" : 0,
          "path": "/shift"
        }
      ]
    },
    {
      "caption": "User Management",
      "id": "user-management",
      "icon": "fas fa-user-tie",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption": "Create User",
          "icon": "fas fa-user-plus",
          "type": "node",
          "permitted" : 0,
          "path": "/createUser"
        },
        {
          "caption": "User Roles",
          "icon": "fas fa-user-tag",
          "type": "node",
          "permitted" : 0,
          "path": "/userRoles"
        },
        {
          "caption": "Permissions",
          "icon": "fas fa-user-lock",
          "type": "node",
          "permitted" : 0,
          "path": "/permissions"
        },
      {
          "caption": "Employee List",
          "icon": "fas fa-user-lock",
          "type": "node",
          "permitted" : 0,
          "path": "/employeeList"
        }
      ]
    }

 ]
  ';
