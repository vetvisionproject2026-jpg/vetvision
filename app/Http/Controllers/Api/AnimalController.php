<?php

namespace App\Http\Controllers\Api;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Animal;
use App\Http\Resources\AnimalResource;
use App\Http\Requests\StoreAnimalRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AnimalController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $animals = Animal::where('owner_id', auth()->id())
            ->with('diagnoses')
            ->paginate(10);

        return $this->apiResponse(
            true,
            'قائمة حيواناتك الخاصة',
            AnimalResource::collection($animals)->response()->getData(true)
        );
    }

    public function store(StoreAnimalRequest $request)
    {
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('animals', 'public');
        }

        $animal = Animal::create([
            'owner_id'   => auth()->id(),
            'name'       => $request->name,
            'species'    => $request->species,
            'gender'     => $request->gender,
            'breed'      => $request->breed,
            'age'        => $request->age,
            'weight'     => $request->weight,
            'image_path' => $imagePath,
        ]);

        return $this->apiResponse(true, 'تم إضافة الحيوان بنجاح!', new AnimalResource($animal), 201);
    }

    public function show($id)
    {
        $animal = Animal::where('owner_id', auth()->id())->find($id);

        if (!$animal) {
            return $this->apiResponse(false, 'هذا الحيوان غير موجود أو ليس لديك صلاحية الوصول إليه.', null, 404);
        }

        return $this->apiResponse(true, 'تم جلب بيانات الحيوان بنجاح.', new AnimalResource($animal));
    }



    /**
     * @OA\Post(
     * path="/api/animals/{id}",
     * summary="تحديث بيانات حيوان مع صورة",
     * tags={"Animals"},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID بتاع الحيوان",
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * @OA\Property(property="name", type="string", example="Bobi"),
     * @OA\Property(property="type", type="string", example="Dog"),
     * @OA\Property(property="image", type="string", format="binary", description="صورة الحيوان")
     * )
     * )
     * ),
     * @OA\Response(response=200, description="تم التحديث بنجاح"),
     * @OA\Response(response=400, description="خطأ في البيانات")
     * )
     */
   
        public function update(Request $request, $id)
    {
        $animal = Animal::where('owner_id', auth()->id())->find($id);

        if (!$animal) {
            return $this->apiResponse(false, 'لا يمكنك تعديل بيانات هذا الحيوان.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'sometimes|string',
            'species' => 'sometimes|string',
            'gender'  => 'sometimes|in:male,female',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // إضافة فحص الصورة
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'خطأ في البيانات', $validator->errors(), 422);
        }

        $data = $request->only(['name', 'species', 'gender', 'breed', 'age', 'weight']);

        if ($request->hasFile('image')) {
            if ($animal->image_path) {
                Storage::disk('public')->delete($animal->image_path);
            }
            $data['image_path'] = $request->file('image')->store('animals', 'public');
        }

        $animal->update($data);

        return $this->apiResponse(true, 'تم تحديث بيانات الحيوان بنجاح.', new AnimalResource($animal));
    }


    public function destroy($id)
    {
        $animal = Animal::where('owner_id', auth()->id())->find($id);

        if (!$animal) {
            return $this->apiResponse(false, 'لا يمكنك حذف هذا الحيوان.', null, 403);
        }

        $animal->delete();

        return $this->apiResponse(true, 'تم حذف الحيوان بنجاح.');
    }
}