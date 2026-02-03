<?php

namespace App\Models;

use App\Enums\UserStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class NotificationClientModel extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'notif_clients';

    protected $fillable = [
        'id',
        'username',
        'email',
        'password_hash',
        'api_key_hash',
        'status',
        'quota_per_day',
    ];

    protected $casts = [
        'status' => UserStatusEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function setPasswordHashAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['password_hash'] = $value;
            return;
        }

        $this->attributes['password_hash'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function setApiKeyHashAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['api_key_hash'] = $value;
            return;
        }

        $this->attributes['api_key_hash'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function requests(): HasMany
    {
        return $this->hasMany(NotificationRequestModel::class, 'client_id', 'id');
    }
}
