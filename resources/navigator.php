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
            "caption":"MRN Issueance",
            "icon":"fas fa-hand-holding",
            "type":"node",
            "permitted" : 0,
            "path":"/mrnIssueance"
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
            "caption":"Cost Sheet",
            "icon":"fas fa-file-invoice-dollar",
            "type":"node",
            "permitted" : 0,
            "path":"/batchCreation"
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
