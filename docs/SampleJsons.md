# Sample JSON schemas for various operations in the App
## Global Search:
`select ["*"]` can be used to fetch all columns
```json
{	
	"Oc" : {
		"select": ["id", "wfx_oc_no", "style_id"],
		"where": [
			{
				"field-name": "wfx_oc_no",
				"operator": "=",
				"value": "OC011"
			},
			{
				"field-name": "buyer_id",
				"operator": "=",
				"value": "1"
			}
		],
		"relations": [
      "style:id,style_code,description,size_fit,routing_id", 
      "style.routing:id,route_code,description", 
      "buyer", 
      "oc_colors:id,oc_id,garment_color,qty_json"
    ]
	},
	"Soc": {
		"select": ["*"],
		"where": [
			{
				"field-name": "wfx_soc_no",
				"operator": "=",
				"value": "SOC02"
			}
		],
		"relations": ["style"]
	}
}
```

## Model Create (Master-Detail):
Only the affected fields should be there in the request (json)
```json
{
	"Oc": {
		"wfx_oc_no": "TST_OC03",
		"qty_json": {"S":"10", "M":"20", "L":"30", "XL":"40"},
		"buyer_id": "1",
		"style_id": "11",
		"pack_color": "red",
		"relations": {
			"OcColor": [
				{
					"garment_color": "blue",
					"qty_json": {"S":"10", "M":"20", "L":"30", "XL":"40"}
				},
				{
					"garment_color": "blue",
					"qty_json": {"S":"50", "M":"60", "L":"70", "XL":"80"}
				}
			]
		}
	}
}
--------
{
	"Soc": {
			"wfx_soc_no": "",
			"qty_json": "",
			"style_id": "",
			"oc_color_id": "",
			"garment_color": "",
			"relations": {}
	}
}
```

## Model Update (Master-Detail):
Only the affected fields should be there in the request (json)
```json
{
	"Oc": {
		"1": {
			"field_1": "test 1",
			"field_2": "test 2",
			"relations": {
				"OcColor": {
					"CRE": [
						{
							"oc_color_field_1": "xx11",
							"oc_color_field_2": "xx12"
						},
						{
							"oc_color_field_1": "xx21",
							"oc_color_field_2": "xx22"
						}
					],
					"UPD": {
						"2": {
							"oc_color_field_1": "xx21",
							"oc_color_field_2": "xx22"
						},
						"5": {
							"oc_color_field_1": "xx51",
							"oc_color_field_2": "xx52"
						}
					},
					"DEL": ["1", "3"]
				}
			}
		}
	}
}
```

## ForeignKeyMapper

```json
{
	"ForeignKeyMapper": {
		"CRE": [
			{
				"model": "Oc",
				"key_mapping": {
					"buyer_code": {
						"model": "Buyer",
						"field": "buyer_id"
					},
					"style_code": {
						"model": "Style",
						"field": "style_id"
					}
				}
			},
			{
				"model": "Fpo",
				"key_mapping": {
					"wfx_soc_no": {
						"model": "Soc",
						"field": "soc_id"
					}
				}
			},
			{
				"model": "Soc",
				"key_mapping": {
					"style_code": {
						"model": "Style",
						"field": "style_id"
					}
				}
			}
		]
	}
}
```