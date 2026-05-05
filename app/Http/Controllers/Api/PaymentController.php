<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Appointment;
use App\Notifications\PaymentConfirmedNotification;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function pay(Request $request, $appointmentId)
    {
        $appointment = Appointment::where('id', $appointmentId)
                                  ->where('user_id', auth()->id())
                                  ->first();

        if (!$appointment) {
            return $this->apiResponse(false, 'الموعد غير موجود!', null, 404);
        }

        if ($appointment->status !== 'confirmed') {
            return $this->apiResponse(false, 'لا يمكن الدفع إلا للمواعيد المؤكدة!', null, 400);
        }

        try {
            // 1. Authentication Request
            $authResponse = Http::post('https://accept.paymob.com/api/auth/tokens', [
                'api_key' => config('services.paymob.api_key'),
            ]);

            $authData = $authResponse->json();

            if (!isset($authData['token'])) {
                return $this->apiResponse(false, 'فشل التحقق من Paymob', $authData, 500);
            }

            $authToken = $authData['token'];

            // 2. Order Registration
            $amount = 50000;

            $orderResponse = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
                'auth_token'      => $authToken,
                'delivery_needed' => false,
                'amount_cents'    => $amount,
                'currency'        => 'EGP',
                'items'           => [],
            ]);

            $orderId = $orderResponse->json()['id'];

            // 3. Payment Key Request
            $user = auth()->user();

            $paymentKeyResponse = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
                'auth_token'     => $authToken,
                'amount_cents'   => $amount,
                'expiration'     => 3600,
                'order_id'       => $orderId,
                'currency'       => 'EGP',
                'integration_id' => config('services.paymob.integration_id'),
                'billing_data'   => [
                    'first_name'      => $user->name,
                    'last_name'       => 'N/A',
                    'email'           => $user->email,
                    'phone_number'    => $user->phone ?? '01000000000',
                    'apartment'       => 'N/A',
                    'floor'           => 'N/A',
                    'street'          => 'N/A',
                    'building'        => 'N/A',
                    'shipping_method' => 'N/A',
                    'postal_code'     => 'N/A',
                    'city'            => 'N/A',
                    'country'         => 'N/A',
                    'state'           => 'N/A',
                ],
            ]);

            $paymentToken = $paymentKeyResponse->json()['token'];

            // 4. حفظ الـ Payment
            Payment::create([
                'user_id'         => auth()->id(),
                'appointment_id'  => $appointmentId,
                'paymob_order_id' => $orderId,
                'amount'          => $amount / 100,
                'currency'        => 'EGP',
                'status'          => 'pending',
            ]);

            $iframeUrl = 'https://accept.paymob.com/api/acceptance/iframes/' .
                          config('services.paymob.iframe_id') .
                          '?payment_token=' . $paymentToken;

            return $this->apiResponse(true, 'تم إنشاء رابط الدفع بنجاح!', [
                'payment_url' => $iframeUrl,
                'order_id'    => $orderId,
            ]);

        } catch (\Exception $e) {
            return $this->apiResponse(false, 'حدث خطأ أثناء إنشاء الدفع', $e->getMessage(), 500);
        }
    }

    public function callback(Request $request)
    {
        $transactionId = $request->input('id');
        $orderId       = $request->input('order.id');
        $success       = $request->input('success');

        $payment = Payment::where('paymob_order_id', $orderId)->first();

        if ($payment) {
            $payment->update([
                'transaction_id' => $transactionId,
                'status'         => $success ? 'paid' : 'failed',
            ]);

            if ($success) {
                $payment->appointment->update(['status' => 'confirmed']);

                // ✅ إرسال notification لليوزر بعد تأكيد الدفع
                $payment->load('user');
                if ($payment->user) {
                    $payment->user->notify(new PaymentConfirmedNotification($payment));
                }
            }
        }

        return response()->json(['message' => 'تم استلام الـ Callback بنجاح']);
    }

    public function status($appointmentId)
    {
        $payment = Payment::where('appointment_id', $appointmentId)
                          ->where('user_id', auth()->id())
                          ->latest()
                          ->first();

        if (!$payment) {
            return $this->apiResponse(false, 'لا يوجد دفع لهذا الموعد!', null, 404);
        }

        return $this->apiResponse(true, 'حالة الدفع', $payment);
    }
}