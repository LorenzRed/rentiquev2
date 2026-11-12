<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function completeRegistration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:120', 'phone' => 'nullable|string|max:40',
            'account_type' => 'required|in:customer,owner', 'role' => 'prohibited',
            'account_status' => 'prohibited', 'email' => 'prohibited', 'firebase_uid' => 'prohibited',
        ]);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($user->role === 'admin', 403, 'Administrator accounts cannot change account type here.');
            abort_if($user->registration_completed_at, 409, 'Your registration is already complete.');
            $user->full_name = $data['full_name'];
            $user->phone = $data['phone'] ?? null;
            $user->role = $data['account_type'];
            $user->registration_completed_at = now();
            $user->save();

            return response()->json($user);
        });
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:120', 'phone' => 'nullable|string|max:40',
            'role' => 'prohibited', 'account_type' => 'prohibited', 'account_status' => 'prohibited',
            'email' => 'prohibited', 'firebase_uid' => 'prohibited', 'profile_image_path' => 'prohibited',
            'registration_completed_at' => 'prohibited', 'user_id' => 'prohibited',
        ]);
        $user = $request->user();
        $user->full_name = $data['full_name'];
        $user->phone = $data['phone'] ?? null;
        $user->save();

        return response()->json($user);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', 'user_id' => 'prohibited']);
        $user = $request->user();
        $folder = 'rentique/users/'.$user->id.'/profile';
        $photo = $request->file('photo');
        $path = $photo->storeAs($folder, Str::uuid().'.'.$photo->extension(), 'local');
        abort_unless($path, 500, 'Your photo could not be saved. Please try again.');
        $oldPath = $user->profile_image_path;
        try {
            $user->profile_image_path = $path;
            $user->save();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath && str_starts_with($oldPath, $folder.'/') && ! str_contains($oldPath, '..')) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json($user);
    }

    public function photo(int $id): StreamedResponse
    {
        $user = User::findOrFail($id);
        $path = $user->profile_image_path;
        abort_unless($path && str_starts_with($path, 'rentique/users/'.$user->id.'/profile/') && ! str_contains($path, '..') && ! str_contains($path, "\0"), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
