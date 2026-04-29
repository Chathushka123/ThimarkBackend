<?php
$navigator_json = '
[

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
       "caption":"Inventory Management",
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
       "caption":"Cost Sheet Management",
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
       "caption":"Reports",
       "id":"report-management",
       "icon":"fas fa-book",
       "type":"folder",
       "permitted" : 0,
       "nodes":[
         {
            "caption":"Daily Output",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/dailyOutput"
         },
         {
            "caption":"Current Stock",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/currentStock"
         },
         {
            "caption":"GRNs",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/grnPendingCompleted"
         },
         {
            "caption":"User - MRN",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/mrnActivityPerUser"
         },
         {
            "caption":"MRNs",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/mrnTurnaroundTime"
         },
         {
            "caption":"Material Consumption",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/materialConsumptionPerModel"
         },
         {
            "caption":"Purchase Orders",
            "icon":"fas fa-circle",
            "type":"node",
            "permitted" : 0,
            "path":"/purchaseOrderStatus"
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
        }
      ]
    }
 ]
  ';
