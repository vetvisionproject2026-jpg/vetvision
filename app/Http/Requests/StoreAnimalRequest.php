<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:50',
            'species' => 'required|string|max:50',
            'gender'  => 'required|in:male,female',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'breed'   => 'nullable|string|max:50',
            'age'     => 'nullable|integer|min:0',
            'weight'  => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'اسم الأليف مطلوب.',
            'species.required' => 'نوع الفصيلة مطلوب.',
            'gender.in'        => 'الجنس يجب أن يكون ذكر أو أنثى.',
            'image.image'      => 'يجب رفع ملف صورة صحيح.',
            'image.max'        => 'حجم الصورة كبير جداً، الحد الأقصى 2 ميجا.',
            'breed.string'     => 'السلالة يجب أن تكون نص.',
            'age.integer'      => 'العمر يجب أن يكون رقم صحيح.',
            'age.min'          => 'العمر لا يمكن أن يكون سالب.',
            'weight.numeric'   => 'الوزن يجب أن يكون رقم.',
            'weight.min'       => 'الوزن لا يمكن أن يكون سالب.',
        ];
    }
}