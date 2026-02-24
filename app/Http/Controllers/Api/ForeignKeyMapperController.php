<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ForeignKeyMapper;
use App\Http\Resources\ForeignKeyMapperResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ForeignKeyMapperController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return ForeignKeyMapperResource::collection(ForeignKeyMapper::paginate(25));
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
                $validator = Validator::make(
                    $value,
                    [
                        'buyer_code' => 'required|unique:buyers|max:30',
                        'name' => 'required|max:150'
                    ]
                );

                if ($validator->fails()) {
                    DB::rollBack();
                    return response()->json(
                        [
                            'status' => 'error',
                            'message' => $validator->errors(),
                            'data' => $value
                        ],
                        400
                    );
                }

                $buyer = ForeignKeyMapper::create([
                    'buyer_code' => $value['buyer_code'],
                    'name' => $value['name']
                ]);
                // $ret[] = new ForeignKeyMapperResource($buyer);
                $ret[] = $buyer;
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
     *@param  \App\ForeignKeyMapper  $buyer
     * @return \Illuminate\Http\Response
     */
    public function show(ForeignKeyMapper $buyer)
    {
        return response()->json(
            [
                'status' => 'success',
                'data' => new ForeignKeyMapperResource($buyer),
            ],
            200
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            DB::beginTransaction();
            foreach ($request->all() as $key => $value) {
                $validator = Validator::make(
                    $value,
                    [
                        'buyer_code' => [
                            'required',
                            Rule::unique('buyers')->ignore($key),
                            'max:30'
                        ],
                        'name' => 'required|max:1500'
                    ]
                );

                if ($validator->fails()) {
                    DB::rollBack();
                    return response()->json(
                        [
                            'status' => 'error',
                            'message' => $validator->errors(),
                            'data' => $value
                        ],
                        400
                    );
                }

                try {
                    ForeignKeyMapper::findOrFail($key)->update([
                        'buyer_code' => $value['buyer_code'],
                        'name' => $value['name'],
                    ]);
                } catch (Exception $e) {
                    if ($e instanceof ModelNotFoundException) {
                        DB::rollBack();
                        return response()->json(
                            [
                                'status' => 'error',
                                'message' => 'Record does not exist!',
                                'data' => [$key => $value]
                            ],
                            400
                        );
                    } else {
                        throw $e;
                    }
                }
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
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return \App\Http\Repositories\Utilities::destroy(new ForeignKeyMapper(), $request);
    }
}
