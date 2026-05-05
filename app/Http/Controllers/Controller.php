<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="VetVision API Documentation",
 *     version="1.0.0",
 *     description="تطبيق Flutter للعيادة البيطرية - مخصص لفريق VetVision"
 * )
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="المحرك المحلي (Local Server)"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}