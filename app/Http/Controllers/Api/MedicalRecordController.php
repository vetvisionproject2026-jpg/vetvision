<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Animal;
use App\Models\Prescription;
use App\Models\TreatmentRecord;
use App\Models\Diagnosis;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    use ApiResponseTrait;

    // =====================================================================
    // PRESCRIPTIONS
    // =====================================================================

    // POST /api/appointments/{id}/prescription  — doctor only
    public function storePrescription(Request $request, $appointmentId)
    {
        $doctor = auth()->user()->doctor;

        $appointment = Appointment::where('id', $appointmentId)
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$appointment) {
            return $this->apiResponse(false, 'الموعد غير موجود أو لا تملك صلاحية إضافة وصفة له', null, 404);
        }

        $request->validate([
            'medicine_name' => 'required|string|max:255',
            'dosage'        => 'required|string|max:100',
            'frequency'     => 'required|string|max:100',
            'duration_days' => 'required|integer|min:1',
            'instructions'  => 'nullable|string|max:1000',
        ], [
            'medicine_name.required' => 'اسم الدواء مطلوب',
            'dosage.required'        => 'الجرعة مطلوبة',
            'frequency.required'     => 'تكرار الجرعة مطلوب',
            'duration_days.required' => 'مدة العلاج مطلوبة',
        ]);

        $prescription = Prescription::create([
            'appointment_id' => $appointmentId,
            'animal_id'      => $appointment->animal_id,
            'doctor_id'      => $doctor->id,
            'medicine_name'  => $request->medicine_name,
            'dosage'         => $request->dosage,
            'frequency'      => $request->frequency,
            'duration_days'  => $request->duration_days,
            'instructions'   => $request->instructions,
            'prescribed_at'  => now(),
            'status'         => 'active',
        ]);

        return $this->apiResponse(true, 'تم إضافة الوصفة الطبية بنجاح', $prescription->load('doctor.user'), 201);
    }

    // GET /api/animals/{id}/prescriptions  — user only
    public function getAnimalPrescriptions($animalId)
    {
        $animal = Animal::where('id', $animalId)
            ->where('owner_id', auth()->id())
            ->first();

        if (!$animal) {
            return $this->apiResponse(false, 'الحيوان غير موجود أو لا تملك صلاحية عرضه', null, 404);
        }

        $prescriptions = Prescription::where('animal_id', $animalId)
            ->with(['doctor.user', 'appointment'])
            ->latest('prescribed_at')
            ->get();

        return $this->apiResponse(true, 'الوصفات الطبية لـ ' . $animal->name, [
            'animal'        => ['id' => $animal->id, 'name' => $animal->name, 'species' => $animal->species],
            'prescriptions' => $prescriptions,
            'total'         => $prescriptions->count(),
            'active'        => $prescriptions->where('status', 'active')->count(),
        ]);
    }

    // =====================================================================
    // PUT /api/prescriptions/{id}/mark-complete  — user only
    // اليوزر يعلّم الوصفة كمكتملة
    // =====================================================================
    public function markPrescriptionComplete($id)
    {
        $prescription = Prescription::find($id);

        if (!$prescription) {
            return $this->apiResponse(false, 'الوصفة غير موجودة', null, 404);
        }

        // التأكد إن الحيوان بتاع المستخدم ده
        $animal = Animal::where('id', $prescription->animal_id)
            ->where('owner_id', auth()->id())
            ->first();

        if (!$animal) {
            return $this->apiResponse(false, 'مش عندك صلاحية تعدل الوصفة دي', null, 403);
        }

        if ($prescription->status === 'completed') {
            return $this->apiResponse(false, 'الوصفة مكتملة بالفعل', null, 400);
        }

        if ($prescription->status === 'discontinued') {
            return $this->apiResponse(false, 'الوصفة دي متوقفة ومش ممكن تكملها', null, 400);
        }

        $prescription->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return $this->apiResponse(true, 'تم تعليم الوصفة كمكتملة ✅', [
            'prescription_id' => $prescription->id,
            'medicine'        => $prescription->medicine_name,
            'status'          => 'completed',
            'completed_at'    => $prescription->completed_at->format('Y-m-d H:i'),
        ]);
    }

    // =====================================================================
    // PUT /api/prescriptions/{id}/discontinue  — user only
    // اليوزر يوقف الوصفة (مثلاً: حساسية أو دكتور طلب)
    // =====================================================================
    public function discontinuePrescription(Request $request, $id)
    {
        $prescription = Prescription::find($id);

        if (!$prescription) {
            return $this->apiResponse(false, 'الوصفة غير موجودة', null, 404);
        }

        $animal = Animal::where('id', $prescription->animal_id)
            ->where('owner_id', auth()->id())
            ->first();

        if (!$animal) {
            return $this->apiResponse(false, 'مش عندك صلاحية تعدل الوصفة دي', null, 403);
        }

        if (in_array($prescription->status, ['completed', 'discontinued'])) {
            return $this->apiResponse(false, 'الوصفة منتهية أو متوقفة بالفعل', null, 400);
        }

        $prescription->update([
            'status' => 'discontinued',
        ]);

        return $this->apiResponse(true, 'تم إيقاف الوصفة', [
            'prescription_id' => $prescription->id,
            'medicine'        => $prescription->medicine_name,
            'status'          => 'discontinued',
        ]);
    }

    // =====================================================================
    // TREATMENT RECORDS
    // =====================================================================

    // POST /api/appointments/{id}/treatment-record  — doctor only
    public function storeTreatmentRecord(Request $request, $appointmentId)
    {
        $doctor = auth()->user()->doctor;

        $appointment = Appointment::where('id', $appointmentId)
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$appointment) {
            return $this->apiResponse(false, 'الموعد غير موجود أو لا تملك صلاحية إضافة سجل له', null, 404);
        }

        $request->validate([
            'treatment_description' => 'required|string',
            'outcome'               => 'required|in:improved,stable,worsened',
            'notes'                 => 'nullable|string|max:1000',
        ], [
            'treatment_description.required' => 'وصف العلاج مطلوب',
            'outcome.required'               => 'نتيجة العلاج مطلوبة',
            'outcome.in'                     => 'نتيجة العلاج يجب أن تكون: improved أو stable أو worsened',
        ]);

        $record = TreatmentRecord::create([
            'appointment_id'        => $appointmentId,
            'animal_id'             => $appointment->animal_id,
            'doctor_id'             => $doctor->id,
            'treatment_description' => $request->treatment_description,
            'outcome'               => $request->outcome,
            'notes'                 => $request->notes,
            'treatment_date'        => now(),
        ]);

        return $this->apiResponse(true, 'تم إضافة سجل العلاج بنجاح', $record->load('doctor.user'), 201);
    }

    // =====================================================================
    // MEDICAL HISTORY TIMELINE
    // =====================================================================

    // GET /api/animals/{id}/medical-history  — user only
    public function getMedicalHistory($animalId)
    {
        $animal = Animal::where('id', $animalId)
            ->where('owner_id', auth()->id())
            ->first();

        if (!$animal) {
            return $this->apiResponse(false, 'الحيوان غير موجود أو لا تملك صلاحية عرضه', null, 404);
        }

        $prescriptions = Prescription::where('animal_id', $animalId)
            ->with('doctor.user')
            ->get()
            ->map(fn($p) => [
                'event_type'   => 'prescription',
                'date'         => $p->prescribed_at->format('Y-m-d H:i'),
                'doctor'       => $p->doctor->user->name ?? 'غير معروف',
                'medicine'     => $p->medicine_name,
                'dosage'       => $p->dosage,
                'frequency'    => $p->frequency,
                'duration'     => $p->duration_days . ' أيام',
                'instructions' => $p->instructions,
                'status'       => $p->status,
                'completed_at' => $p->completed_at?->format('Y-m-d H:i'),
            ]);

        $treatments = TreatmentRecord::where('animal_id', $animalId)
            ->with('doctor.user')
            ->get()
            ->map(fn($t) => [
                'event_type'  => 'treatment',
                'date'        => $t->treatment_date->format('Y-m-d H:i'),
                'doctor'      => $t->doctor->user->name ?? 'غير معروف',
                'description' => $t->treatment_description,
                'outcome'     => $this->translateOutcome($t->outcome),
                'notes'       => $t->notes,
            ]);

        $diagnoses = Diagnosis::where('animal_id', $animalId)
            ->get()
            ->map(fn($d) => [
                'event_type'      => 'diagnosis',
                'date'            => $d->created_at->format('Y-m-d H:i'),
                'result'          => $d->result,
                'confidence'      => $d->confidence,
                'recommendations' => $d->recommendations,
            ]);

        $appointments = Appointment::where('animal_id', $animalId)
            ->where('status', 'completed')
            ->with('doctor.user')
            ->get()
            ->map(fn($a) => [
                'event_type' => 'appointment',
                'date'       => $a->date_time->format('Y-m-d H:i'),
                'doctor'     => $a->doctor->user->name ?? 'غير معروف',
                'type'       => $a->type,
                'reason'     => $a->reason,
                'rating'     => $a->rating,
            ]);

        $timeline = collect()
            ->merge($prescriptions)
            ->merge($treatments)
            ->merge($diagnoses)
            ->merge($appointments)
            ->sortByDesc('date')
            ->values();

        return $this->apiResponse(true, 'السجل الطبي لـ ' . $animal->name, [
            'animal'   => [
                'id'      => $animal->id,
                'name'    => $animal->name,
                'species' => $animal->species,
                'breed'   => $animal->breed,
                'age'     => $animal->age,
                'gender'  => $animal->gender,
            ],
            'summary'  => [
                'total_prescriptions'    => $prescriptions->count(),
                'active_prescriptions'   => $prescriptions->where('status', 'active')->count(),
                'total_treatments'       => $treatments->count(),
                'total_diagnoses'        => $diagnoses->count(),
                'total_appointments'     => $appointments->count(),
            ],
            'timeline' => $timeline,
        ]);
    }

    private function translateOutcome(string $outcome): string
    {
        return match($outcome) {
            'improved' => 'تحسن ✅',
            'stable'   => 'مستقر 🟡',
            'worsened' => 'تدهور ❌',
            default    => $outcome,
        };
    }
}