<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

#[Fillable(['firebase_uid', 'full_name', 'email', 'phone', 'role', 'account_status', 'profile_image_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $appends = ['profile_image_url'];

    protected function profileImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->profile_image_path ? URL::temporarySignedRoute('profile.photo', now()->addMinutes(5), ['id' => $this->id]) : null);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'registration_completed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
