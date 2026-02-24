<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\UploadedImage;

class ImageUploadController extends Controller
{
    // public function upload(Request $request)
    // {
    //     $request->validate([
    //         'image' => 'required|file|mimes:jpeg,png,jpg,gif|max:2048',
    //         'invoice_id' => 'required|integer|exists:invoices,id',
    //     ]);

    //     $image = $request->file('image');
    //     $invoice_id = $request->input('invoice_id'); // ✅ Corrected

    //     $uploadedImage = new UploadedImage();
    //     $uploadedImage->filename = $image->getClientOriginalName();
    //     $uploadedImage->mime_type = $image->getClientMimeType();
    //     $uploadedImage->data = file_get_contents($image->getRealPath());
    //     $uploadedImage->invoice_id = $invoice_id;
    //     $uploadedImage->save();

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Image uploaded and stored in DB'
    //     ]);
    // }

    // public function getImages($invoice_id)
    // {
    //     $img = UploadedImage::where('invoice_id', $invoice_id)->orderby('id','DESC')->first();

    //     // Convert binary data to base64 string for image preview
    //     // $imageData = $images->map(function ($img) {
    //     //     return [
    //     //         'filename' => $img->filename,
    //     //         'mime_type' => $img->mime_type,
    //     //         'base64' => 'data:' . $img->mime_type . ';base64,' . base64_encode($img->data),
    //     //     ];
    //     // });

    //     $imageData = [
    //             'filename' => $img->filename,
    //             'mime_type' => $img->mime_type,
    //             'base64' => 'data:' . $img->mime_type . ';base64,' . base64_encode($img->data),
    //         ];

    //     return response()->json([
    //         'status' => 'success',
    //         'images' => $imageData,
    //     ]);
    // }

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,gif|max:8192',
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        $image = $request->file('image');
        $invoice_id = $request->input('invoice_id');

        $uploadedImage = new UploadedImage();
        $uploadedImage->filename = $image->getClientOriginalName();
        $uploadedImage->mime_type = $image->getClientMimeType();
        $uploadedImage->data = file_get_contents($image->getRealPath()); // Safe
        $uploadedImage->invoice_id = $invoice_id;
        $uploadedImage->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Image uploaded and stored in DB'
        ]);
    }



    public function getImages($invoiceId)
    {
        $images = UploadedImage::where('invoice_id', $invoiceId)->get();

        $response = [];

        foreach ($images as $img) {
            $response[] = [
                'id' => $img->id,
                'filename' => $img->filename,
                'mime_type' => $img->mime_type,
                'base64' => 'data:' . $img->mime_type . ';base64,' . base64_encode($img->data),
            ];
        }

        return response()->json([
            'status' => 'success',
            'images' => $response,
        ]);
    }


    public function deleteImage(Request $request)
    {
        $id = $request->input('imageId');
        $image = UploadedImage::find($id);

        if ($image) {
            $image->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Image deleted successfully'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Image not found'
        ], 404);
    }
}
