<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Style;
use Illuminate\Http\Request;
use Exception;
use App\Http\Resources\StyleResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StyleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return StyleResource::collection(Style::paginate(25));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $ret = [];
        try {
            DB::beginTransaction();
            foreach ($request->all() as $key => $value) {
                $value['size_fit'] = json_encode($value['size_fit']);
                $validator = Validator::make(
                    $value,
                    [
                        'style_code' => 'required|unique:styles|max:20',
                        'description' => 'required|max:80',
                        'size_fit' => 'json',
                        'routing_id' => 'required|exists:routings,id'
                    ]
                );

                if ($validator->fails()) {
                    DB::rollBack();
                    return response()->json(
                        [
                            'status' => 'error',
                            'message' => $validator->errors(),
                            'source' => $value
                        ],
                        400
                    );
                }

                $style = Style::create([
                    'style_code' => $value['style_code'],
                    'description' => $value['description'],
                    'size_fit' => $value['size_fit'],
                    'routing_id' => $value['routing_id'],
                ]);
                // $ret[] = new StyleResource($style);
                $ret[] = $style;
            }

            DB::commit();

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $ret
                ],
                200
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                400
            );
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Style  $style
     * @return \Illuminate\Http\Response
     */
    public function show(Style $style)
    {
        return response()->json(
            [
                'status' => 'success',
                'data' => new StyleResource($style),
            ],
            200
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Style  $style
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            DB::beginTransaction();
            foreach ($request->all() as $key => $value) {
                $value['size_fit'] = json_encode($value['size_fit']);
                $validator = Validator::make(
                    $value,
                    [
                        'style_code' => [
                            'required',
                            Rule::unique('styles')->ignore($key),
                            'max:20'
                        ],
                        'description' => 'required|max:80',
                        'size_fit' => 'json',
                        'routing_id' => 'required|exists:routings,id'
                    ]
                );

                if ($validator->fails()) {
                    DB::rollBack();
                    return response()->json(
                        [
                            'status' => 'error',
                            'message' => $validator->errors(),
                            'source' => $value
                        ],
                        400
                    );
                }

                Style::find($key)->update([
                    'style_code' => $value['style_code'],
                    'description' => $value['description'],
                    'size_fit' => $value['size_fit'],
                    'routing_id' => $value['routing_id'],
                ]);
            }
            DB::commit();

            return response()->json(
                [
                    'status' => 'success',
                    'message' => 'Resource(s) were updated!'
                ],
                200
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                401
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Style  $style
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return \App\Http\Repositories\Utilities::destroy(new Style(), $request);
    }
}
