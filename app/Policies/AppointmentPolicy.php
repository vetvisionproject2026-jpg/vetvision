<?php

namespace App\Policies;
use App\Models\Appoinment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appoinment $appointment)
{
    // السماح للدكتور صاحب الموعد أو المريض صاحب الموعد برؤيته
    return $user->id === $appointment->doctor_id || $user->id === $appointment->user_id;
}


    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appoinment $appoinment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appoinment $appoinment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Appoinment $appoinment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Appoinment $appoinment): bool
    {
        return false;
    }
}
