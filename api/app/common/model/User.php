<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\enum\UserStatus;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 映射通行证本地账号，不在模型中实现认证业务流程。
 *
 * @property int $id
 * @property string $public_id
 * @property string|null $username
 * @property string|null $email
 * @property string $password_hash
 * @property string $display_name
 * @property string|null $avatar_url
 * @property string|null $phone_country_code
 * @property string|null $phone_number
 * @property UserStatus $status
 * @property DateTimeImmutable|null $email_verified_at
 * @property DateTimeImmutable|null $phone_verified_at
 * @property DateTimeImmutable|null $last_login_at
 * @property DateTimeImmutable|null $password_changed_at
 * @property DateTimeImmutable $created_at
 */
final class User extends BaseModel
{
    use SoftDeletes;

    protected $table = 'moo_users';

    protected $fillable = [
        'public_id', 'username', 'email', 'phone_country_code', 'phone_number',
        'password_hash', 'display_name', 'avatar_url', 'status',
        'email_verified_at', 'phone_verified_at', 'password_changed_at',
        'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => UserStatus::class,
        'email_verified_at' => 'immutable_datetime',
        'phone_verified_at' => 'immutable_datetime',
        'password_changed_at' => 'immutable_datetime',
        'last_login_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];
}

