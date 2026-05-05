<?php

namespace App\Traits;

trait ApiResponseTrait
{
    public function apiResponse($status = true, $message = "Success", $data = null, $code = 200)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data
        ], $code);
    }
}