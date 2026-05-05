<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Diagnosis;
use App\Models\Animal;
use App\Services\AIService;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Validator;

class DiagnosisController extends Controller
{
    use ApiResponseTrait;

    protected $aiService;

    // حقن خدمة الذكاء الاصطناعي في الكنترولر
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * إجراء تشخيص جديد لحيوان محدد
     */
    public function diagnose(Request $request, $animalId)
    {
        // 1. التأكد أن الحيوان موجود ويخص المستخدم الحالي (Security Check)
        $animal = Animal::where('id', $animalId)
                        ->where('owner_id', auth()->id()) 
                        ->first();

        if (!$animal) {
            return $this->apiResponse(false, 'عفواً، لا يمكنك تشخيص حيوان لا تملكه أو غير موجود!', null, 403);
        }

        $validator = Validator::make($request->all(), [
    'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
], [
    'image.required' => 'من فضلك ارفع صورة!',
    'image.image'    => 'الملف المرفوع ليس صورة، يُسمح فقط بصور jpeg, png, jpg',
    'image.mimes'    => 'امتداد الملف غير مدعوم، يُسمح فقط بـ jpeg, png, jpg',
    'image.max'      => 'حجم الصورة كبير جداً، الحد الأقصى 5MB',
]);

if ($validator->fails()) {
    return $this->apiResponse(false, $validator->errors()->first(), null, 422);
}

        try {
            // 3. تخزين الصورة في المجلد المخصص
            $path = $request->file('image')->store('diagnosis_images', 'public');

            // 4. استدعاء محرك الذكاء الاصطناعي (الموجود حالياً Mock/الوهمي)
            $aiResult = $this->aiService->predict(storage_path('app/public/' . $path));

            if (!$aiResult) {
                return $this->apiResponse(false, 'فشل الاتصال بمحرك الذكاء الاصطناعي', null, 500);
            }

            // 5. حفظ التشخيص في قاعدة البيانات مع النصيحة الديناميكية
            $diagnosis = Diagnosis::create([
                'animal_id'       => $animalId,
                'user_id'   => auth()->id(),
                'image_path'      => $path,
                'result'          => $aiResult['label'], 
                'confidence'      => $aiResult['confidence_score'], 
                'recommendations' => $this->getRecommendation($aiResult['label']) // جلب النصيحة الذكية
            ]);

            return $this->apiResponse(true, 'تم التشخيص بنجاح!', [
                'animal_name' => $animal->name,
                'diagnosis'   => $diagnosis
            ]);

        } catch (\Exception $e) {
            return $this->apiResponse(false, 'حدث خطأ أثناء معالجة التشخيص', $e->getMessage(), 500);
        }
    }

    /**
     * عرض تاريخ التشخيصات لحيوان معين (History)
     */
    public function index($animalId)
    {
        // التأكد من الملكية أولاً
        $animal = Animal::where('owner_id', auth()->id())->find($animalId);

        if (!$animal) {
            return $this->apiResponse(false, 'هذا الحيوان غير موجود أو لا تملك صلاحية الوصول إليه.', null, 404);
        }

        // جلب التشخيصات مرتبة من الأحدث للأقدم
        $diagnoses = Diagnosis::where('animal_id', $animalId)
                              ->latest()
                              ->get();

        return $this->apiResponse(true, 'تاريخ التشخيصات لهذا الحيوان', $diagnoses);
    }

    /**
     * عرض تفاصيل تشخيص واحد محدد
     */
    public function show($id)
    {
        $diagnosis = Diagnosis::with('animal')->find($id);
        
        if (!$diagnosis) {
            return $this->apiResponse(false, 'هذا التشخيص غير موجود!', null, 404);
        }

        // حماية إضافية: التأكد أن صاحب الحيوان هو من يطلب التشخيص
        if ($diagnosis->animal->owner_id !== auth()->id()) {
            return $this->apiResponse(false, 'لا تملك صلاحية عرض هذا التشخيص.', null, 403);
        }

        return $this->apiResponse(true, 'تم جلب تفاصيل التشخيص بنجاح', $diagnosis);
    }

    /**
     * منطق النصائح الديناميكية (Dynamic Advice Logic)
     */
    private function getRecommendation($diseaseName)
    {
        $advices = [
            'Mange'            => 'قم بعزل الحيوان فوراً واستخدم مرهم مضاد للطفيليات الموصى به.',
            'Fungal Infection' => 'حافظ على جفاف المنطقة المصابة ونظفها بشامبو مضاد للفطريات.',
            'Injury'           => 'قم بتطهير الجرح بمحلول ملحي وتوجه لأقرب عيادة بيطرية.',
            'Normal'           => 'الحيوان يبدو بصحة جيدة جداً، استمر في نظامك الغذائي الحالي.',
            'Skin Allergy'     => 'حاول مراقبة نوع الطعام الجديد الذي قدمته لحيوانك فقد يكون مسبباً للحساسية.'
        ];

        return $advices[$diseaseName] ?? 'يرجى مراجعة الطبيب البيطري لإجراء فحص سريري دقيق.';
    }
    public function destroy($id)
{
    $diagnosis = Diagnosis::with('animal')->find($id);

    if (!$diagnosis) {
        return $this->apiResponse(false, 'التشخيص غير موجود!', null, 404);
    }

    // التأكد إن صاحب الحيوان هو اللي بيحذف
    if ($diagnosis->animal->owner_id !== auth()->id()) {
        return $this->apiResponse(false, 'مش عندك صلاحية تحذف التشخيص ده!', null, 403);
    }

    $diagnosis->delete();

    return $this->apiResponse(true, 'تم حذف التشخيص بنجاح ✅', null);
}
}