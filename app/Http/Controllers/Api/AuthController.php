<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\UserResource;
use Laravel\Socialite\Facades\Socialite; // ✅ إضافة جديدة

class AuthController extends Controller
{
    use ApiResponseTrait;

    // =====================================================================
    // ✅ SOCIAL AUTH - جديد
    // =====================================================================

    /**
     * الخطوة 1: نبعت المستخدم لصفحة Google أو Facebook عشان يسجل دخول
     * URL مثلاً: GET /api/auth/google/redirect
     */
  public function socialRedirect($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return $this->apiResponse(false, 'مزود الخدمة غير مدعوم', null, 400);
        }

        $driver = Socialite::driver($provider)->stateless();

        if ($provider === 'facebook') {
            $driver = $driver->scopes(['email', 'public_profile']);
        }

        // للمتصفح (Testing)
        if (request()->has('browser')) {
            return $driver->redirect();
        }

        // للموبايل
        $url = $driver->redirect()->getTargetUrl();
        return $this->apiResponse(true, 'تم إنشاء رابط تسجيل الدخول', ['url' => $url]);
    }

    public function socialCallback($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return $this->apiResponse(false, 'مزود الخدمة غير مدعوم', null, 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return $this->apiResponse(false, 'فشل التحقق من الحساب، حاول مرة أخرى', null, 401);
        }

        /* ✅ التعديل الجوهري:
        بنستخدم updateOrCreate بناءً على الـ email. 
        لو الإيميل موجود، هيحدث بيانات الـ provider والـ id.
        لو مش موجود، هيكريت مستخدم جديد.
        */
        $user = User::updateOrCreate(
            [
                'email' => $socialUser->getEmail(),
            ],
            [
                'name'              => $socialUser->getName() ?? 'مستخدم',
                'provider'          => $provider,
                'provider_id'       => $socialUser->getId(),
                'email_verified_at' => now(),
                'role'              => 'user', // القيمة الافتراضية
                'avatar'            => $socialUser->getAvatar(),
                // بنحط باسوورد عشوائي قوي عشان الـ Validation لو الجدول بيطلبه
                'password'          => Hash::make(Str::random(24)), 
            ]
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->apiResponse(true, 'تم تسجيل الدخول بنجاح', [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => new UserResource($user),
        ]);
    }

    // =====================================================================
    // الكود القديم - مش اتغير فيه حاجة
    // =====================================================================

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:user,doctor',
        ], [
            'name.required'     => 'الاسم مطلوب.',
            'email.required'    => 'البريد الإلكتروني مطلوب.',
            'email.unique'      => 'هذا البريد الإلكتروني مسجل بالفعل.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min'      => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
            'role.required'     => 'نوع الحساب مطلوب.',
            'role.in'           => 'نوع الحساب يجب أن يكون user أو doctor.',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'خطأ في بيانات التسجيل', $validator->errors(), 422);
        }

        $verificationCode = rand(100000, 999999);

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'role'              => $request->role,
            'verification_code' => $verificationCode,
        ]);

        \Log::info("كود تفعيل حساب {$user->email} هو: {$verificationCode}");
        \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\VerificationCodeMail($verificationCode));

        return $this->apiResponse(
            true,
            'تم إنشاء الحساب بنجاح! يرجى إدخال كود التفعيل المرسل لبريدك الإلكتروني.',
            ['user' => new UserResource($user)],
            201
        );
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code'  => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'بيانات التحقق غير صحيحة', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->verification_code !== $request->code) {
            return $this->apiResponse(false, 'كود التحقق غير صحيح، حاول مرة أخرى.', null, 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->apiResponse(true, 'تم تفعيل حسابك بنجاح! يمكنك الآن استخدام التطبيق.', [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => new UserResource($user),
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="تسجيل الدخول",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="12345678")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تسجيل الدخول بنجاح"),
     *     @OA\Response(response=401, description="بيانات غير صحيحة")
     * )
     */

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'بيانات الدخول غير مكتملة', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->apiResponse(false, 'هذا البريد الإلكتروني غير مسجل لدينا، يرجى إنشاء حساب أولاً.', null, 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return $this->apiResponse(false, 'كلمة المرور التي أدخلتها غير صحيحة، حاول مرة أخرى.', null, 401);
        }

        if ($user->email_verified_at === null) {
            return $this->apiResponse(false, 'عفواً، يجب تفعيل حسابك أولاً قبل تسجيل الدخول.', [
                'needs_verification' => true,
                'email'              => $user->email,
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->apiResponse(true, 'تم تسجيل الدخول بنجاح', [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => new UserResource($user),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email'], [
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا.',
        ]);

        $user = User::where('email', $request->email)->first();
        $code = rand(100000, 999999);

        $user->update(['verification_code' => $code]);

        \Mail::to($user->email)->send(new \App\Mail\VerificationCodeMail($code, 'كود استعادة كلمة المرور'));

        return $this->apiResponse(true, 'تم إرسال كود استعادة كلمة المرور إلى بريدك الإلكتروني.', null, 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|exists:users,email',
            'code'     => 'required|string|min:6|max:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'خطأ في البيانات', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)
                    ->where('verification_code', $request->code)
                    ->first();

        if (!$user) {
            return $this->apiResponse(false, 'الكود الذي أدخلته غير صحيح.', null, 400);
        }

        $user->update([
            'password'          => Hash::make($request->password),
            'verification_code' => null,
        ]);

        return $this->apiResponse(true, 'تم تغيير كلمة المرور بنجاح، يمكنك تسجيل الدخول الآن.');
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name'         => 'sometimes|string|max:255',
            'email'        => 'sometimes|email|unique:users,email,' . $user->id,
            'password'     => 'sometimes|confirmed|min:8',
            'old_password' => 'required_with:password',
            'avatar'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'خطأ في البيانات المرسلة', $validator->errors(), 422);
        }

        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;

        if ($request->has('password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return $this->apiResponse(false, 'كلمة المرور القديمة غير صحيحة', null, 400);
            }
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path        = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return $this->apiResponse(true, 'تم تحديث البروفايل بنجاح', [
            'user'       => $user,
            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->apiResponse(true, 'تم تسجيل الخروج بنجاح');
    }
}