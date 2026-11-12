<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Factory;
use Symfony\Component\HttpFoundation\Response;

class FirebaseToken
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->bearerToken(), 401, 'A Firebase identity token is required.');
        abort_unless(config('firebase.project_id') && config('firebase.credentials'), 503, 'Account services are not configured.');
        $auth = (new Factory)->withServiceAccount(config('firebase.credentials'))->withProjectId(config('firebase.project_id'))->createAuth();
        try {
            $token = $auth->verifyIdToken($request->bearerToken(), true);
        } catch (FailedToVerifyToken|RevokedIdToken $exception) {
            abort(401, 'Your session has expired or is invalid.');
        }
        $claims = $token->claims();
        $uid = $claims->get('sub');
        $user = User::firstOrCreate(['firebase_uid' => $uid], [
            'full_name' => $claims->get('name', ''),
            'email' => $claims->get('email'),
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        abort_unless($user->account_status === 'active', 403, 'Your account is suspended.');
        $email = $claims->get('email');
        $verified = (bool) $claims->get('email_verified', false);
        if ($email && $email !== $user->email) {
            $user->email = $email;
            $user->email_verified_at = $verified ? now() : null;
        } elseif ($verified && ! $user->email_verified_at) {
            $user->email_verified_at = now();
        } elseif (! $verified && $user->email_verified_at) {
            $user->email_verified_at = null;
        }
        if ($user->isDirty()) {
            $user->save();
        }
        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);

        return $next($request);
    }
}
