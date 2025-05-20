<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Mail\EmailChangeVerification;
use App\Mail\PasswordChanged;
use App\Models\EmailVerificationToken;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserProfileController extends Controller
{
    /**
     * @throws \Throwable
     */
    public function show(): JsonResource
    {
        return Auth
            ::user()
            ?->load([
                'exchangeApiKeys',
                'recentActivity',
                'emailVerificationToken',
            ])
            ?->toResource(UserProfileResource::class);
    }

    /**
     * @throws \Throwable
     */
    public function update(UpdateProfileRequest $request): JsonResponse|JsonResource
    {
        $user = Auth::user();
        $data = $request->validated();

        $this->logProfileUpdate($user, $data);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $this->initiateEmailChange($user, $data['email']);

            unset($data['email']);

            return response()->json([
                'message' => 'Профіль оновлено. Для підтвердження зміни email перевірте свою поштову скриньку.'
            ]);
        }

        $user->update($data);

        return $user->toResource(UserProfileResource::class);
    }

    public function changePassword(ChangePasswordRequest $request): void
    {
        $user = Auth::user();
        $data = $request->validated();

        if (!Hash::check($data['current_password'], $user->password)) {
            ValidationException::withMessages(['current_password' => 'Поточний пароль невірний']);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        $this->logPasswordChange($user);
        $this->sendPasswordChangeNotification($user);
    }

    protected function initiateEmailChange($user, string $newEmail): void
    {
        $token = Str::random(64);

        EmailVerificationToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'new_email' => $newEmail,
                'expires_at' => now()->addHours(24)
            ]
        );

        $this->logProfileUpdate($user, ['email_change_requested' => $newEmail]);

        Mail::to($newEmail)
            ->send(new EmailChangeVerification($user, $token));
    }

    public function verifyEmailChange(string $token): JsonResponse
    {
        $verification = EmailVerificationToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Невірний або застарілий токен підтвердження'
            ], 422);
        }

        $user = $verification->user;
        $newEmail = $verification->new_email;

        $this->logProfileUpdate($user, [
            'old_email' => $user->email,
            'new_email' => $newEmail,
            'email_verified' => true
        ]);

        $user->email = $newEmail;
        $user->email_verified_at = now();
        $user->save();

        $verification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Email успішно змінено'
        ]);
    }

    public function resendVerificationEmail(): JsonResponse
    {
        $user = Auth::user();

        $verification = $user->emailVerificationToken;

        if (!$verification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Немає запиту на зміну email'
            ], 422);
        }

        $verification->update([
            'expires_at' => now()->addHours(24)
        ]);

        Mail::to($verification->new_email)
            ->send(new EmailChangeVerification($user, $verification->token));

        return response()->json([
            'status' => 'success',
            'message' => 'Лист підтвердження надіслано повторно'
        ]);
    }

    public function cancelEmailChange(): JsonResponse
    {
        $user = Auth::user();

        $verification = $user->emailVerificationToken;

        if (!$verification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Немає запиту на зміну email'
            ], 422);
        }

        $verification->delete();

        $this->logProfileUpdate($user, [
            'email_change_cancelled' => $verification->new_email
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Запит на зміну email скасовано'
        ]);
    }

    protected function logProfileUpdate($user, array $data): void
    {
        UserActivityLog::create([
            'user_id' => $user->id,
            'action' => 'profile_update',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'details' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected function logPasswordChange($user): void
    {
        UserActivityLog::create([
            'user_id' => $user->id,
            'action' => 'password_change',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'details' => [
                'changed_at' => now()->toIso8601String(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected function sendPasswordChangeNotification($user): void
    {
        Mail::to($user->email)
            ->send(new PasswordChanged($user));
    }
}
