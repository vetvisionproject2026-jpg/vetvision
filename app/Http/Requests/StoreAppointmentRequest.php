<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'user';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => 'required|integer|exists:doctors,id',
            'animal_id' => 'required|integer|exists:animals,id',
            'date_time' => [
                'required',
                'date_format:Y-m-d H:i',
                'after:now',
            ],
            'type' => 'required|in:online,clinic,home_visit',
            'reason' => 'required|string|min:5|max:255',
            'location' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'doctor_id.required' => 'يجب اختيار طبيب',
            'doctor_id.exists' => 'الطبيب المختار غير موجود',
            'animal_id.required' => 'يجب اختيار الحيوان',
            'animal_id.exists' => 'الحيوان المختار غير موجود',
            'date_time.required' => 'يجب تحديد التاريخ والوقت',
            'date_time.date_format' => 'صيغة التاريخ والوقت غير صحيحة (YYYY-MM-DD HH:mm)',
            'date_time.after' => 'يجب اختيار موعد في المستقبل',
            'type.required' => 'يجب اختيار نوع الموعد',
            'type.in' => 'نوع الموعد غير صحيح',
            'reason.required' => 'يجب تحديد سبب الزيارة',
            'reason.min' => 'سبب الزيارة يجب أن يكون 5 أحرف على الأقل',
            'reason.max' => 'سبب الزيارة يجب ألا يزيد عن 255 حرف',
            'location.max' => 'العنوان يجب ألا يزيد عن 500 حرف',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180',
            'notes.max' => 'الملاحظات يجب ألا تزيد عن 1000 حرف',
        ];
    }
}