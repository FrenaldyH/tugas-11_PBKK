<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// Issues and revokes Sanctum personal access tokens.
class AuthController extends Controller
{
    // POST /api/login: check credentials, return a Bearer token (shown only once).
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Kredensial salah.'], 401);
        }

        $token = $user->createToken($data['device_name'] ?? 'agent-access-token');

        return response()->json([
            'status' => 'success',
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->format('Y-m-d H:i:s'),
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    // POST /api/logout: revoke only the token used for this request (other devices stay logged in).
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'success', 'message' => 'Token dicabut.']);
    }
}
