<?php

namespace App\Http\Controllers\Api;

use App\HashStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HashStoreController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $body = $request->all();
        $uuid = (string) Str::uuid();

        HashStore::create(['key' => $uuid, 'source' => json_encode($body)]);

        return $uuid;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\HashStore  $hashStore
     * @return \Illuminate\Http\Response
     */
    public function getByUuid($uuid)
    {
      $hashStore = HashStore::where('key', $uuid)->firstOrFail();
      $hashStore->source = json_decode($hashStore->source);
        return response()->json(
            [
              'status' => 'success',
              'data' => $hashStore,
            ],
            200
          );
    }
}
