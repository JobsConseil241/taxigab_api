<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'],
                'password' => $data['password'],
                'role'     => $data['role'],
                'language' => $data['language'] ?? 'fr',
                'is_active' => true,
            ]);

            if ($data['role'] === 'driver') {
                Driver::create([
                    'user_id'        => $user->id,
                    'license_number' => $data['license_number'],
                    'status'         => 'pending', // sera approuvé par l'admin
                    'is_online'      => false,
                ]);
            }

            return $user;
        });

        $token = $user->createToken('registration')->plainTextToken;

        return response()->json([
            'user'  => $this->transformUser($user->fresh('driver')),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = isset($data['email'])
            ? User::where('email', $data['email'])->first()
            : User::where('phone', $data['phone'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('Account is deactivated.'),
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'api')->plainTextToken;

        return response()->json([
            'user'  => $this->transformUser($user->load('driver')),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->transformUser($request->user()->load('driver.activeVehicle')),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:120'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:32'],
            'language'   => ['sometimes', 'in:fr,en'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'email'      => ['sometimes', 'email', 'max:160', 'unique:users,email,' . $user->id],
        ]);

        $user->fill($data)->save();

        return response()->json([
            'user' => $this->transformUser($user->fresh()->load('driver.activeVehicle')),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('Current password is incorrect.'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Revoke all other tokens to force re-login on other devices.
        $current = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $current->id)->delete();

        return response()->json(['message' => 'Password updated']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    private function transformUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url,
            'language'   => $user->language,
            'is_active'  => $user->is_active,
            'driver'     => $user->driver ? [
                'id'             => $user->driver->id,
                'license_number' => $user->driver->license_number,
                'status'         => $user->driver->status,
                'is_online'      => $user->driver->is_online,
                'rating_avg'     => (float) $user->driver->rating_avg,
                'total_rides'    => (int) $user->driver->total_rides,
                'current_lat'    => $user->driver->current_lat ? (float) $user->driver->current_lat : null,
                'current_lng'    => $user->driver->current_lng ? (float) $user->driver->current_lng : null,
                'vehicle'        => $user->driver->activeVehicle ? [
                    'brand'        => $user->driver->activeVehicle->brand,
                    'model'        => $user->driver->activeVehicle->model,
                    'color'        => $user->driver->activeVehicle->color,
                    'plate_number' => $user->driver->activeVehicle->plate_number,
                ] : null,
            ] : null,
        ];
    }
}
