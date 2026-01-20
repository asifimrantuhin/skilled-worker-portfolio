<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|in:customer,agent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role ?? 'customer',
        ]);

        $user->notify(new VerifyEmail);
        $tokens = $this->issueTokens($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'requires_email_verification' => true,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
            );

            $user->notify(new VerifyEmail);

            return response()->json([
                'success' => false,
                'message' => 'Email not verified. Verification link sent.',
                'verification_url' => app()->environment('local') ? $verificationUrl : null,
            ], 403);
        }

        $tokens = $this->issueTokens($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ]);
    }

    public function logout(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

        if ($refreshToken) {
            $hash = hash('sha256', $refreshToken);
            RefreshToken::where('token_hash', $hash)
                ->where('user_id', $request->user()->id)
                ->update(['revoked_at' => now()]);
        }

        optional($request->user()->currentAccessToken())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
            'email_verified' => $request->user()->hasVerifiedEmail(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $user->fill($request->only(['name', 'phone', 'address']));

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    public function listUsers(Request $request)
    {
        $users = User::withCount(['bookings', 'agentBookings'])
            ->paginate(15);

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    public function updateUserStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->is_active = $request->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'user' => $user,
        ]);
    }

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $hash = hash('sha256', $request->refresh_token);
        $stored = RefreshToken::where('token_hash', $hash)->first();

        if (! $stored || $stored->isExpired() || $stored->isRevoked()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token',
            ], 401);
        }

        $user = $stored->user;

        if (! $user->is_active) {
            $stored->update(['revoked_at' => now()]);
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $stored->update(['revoked_at' => now()]);
            return response()->json([
                'success' => false,
                'message' => 'Email not verified',
            ], 403);
        }

        $stored->update(['revoked_at' => now()]);

        $tokens = $this->issueTokens($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'user' => $user,
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ]);
    }

    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
            ]);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $user->notify(new VerifyEmail);

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent',
            'verification_url' => app()->environment('local') ? $verificationUrl : null,
        ]);
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
        ]);
    }

    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset token generated',
            'reset_token' => app()->environment('local') ? $token : null,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token',
            ], 400);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Reset token expired',
            ], 400);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        event(new PasswordReset($user));

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    protected function issueTokens(User $user, Request $request): array
    {
        optional($request->user())->currentAccessToken()?->delete();

        $accessToken = $user->createToken('access_token')->plainTextToken;
        $refreshPlain = Str::random(64);

        $user->refreshTokens()->create([
            'token_hash' => hash('sha256', $refreshPlain),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshPlain,
        ];
    }
}
