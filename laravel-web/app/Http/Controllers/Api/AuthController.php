<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CasebookData;
use App\Support\EmailLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => EmailLogin::RULES,
        ], EmailLogin::MESSAGES);

        $result = EmailLogin::resolve($validated['email']);

        if (! $result['verified']) {
            return response()->json([
                'status' => 'verification_sent',
                'message' => 'We sent a verification link to '.$result['user']->email.'. Open it to continue.',
                'email' => $result['user']->email,
            ]);
        }

        return response()->json(CasebookData::token($result['user']));
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string'],
        ]);

        $signed = Request::create($data['url']);

        if (! $signed->hasValidSignature()) {
            abort(403, 'This verification link is invalid or expired.');
        }

        if (! preg_match('#/email/verify/(\d+)/([A-Fa-f0-9]+)#', '/'.$signed->path(), $matches)) {
            abort(403, 'This verification link is invalid or expired.');
        }

        $user = User::query()->findOrFail($matches[1]);
        EmailLogin::markVerified($user, $matches[2]);

        return response()->json(CasebookData::token($user));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(CasebookData::user($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['status' => 'logged_out']);
    }
}
