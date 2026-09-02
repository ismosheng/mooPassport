<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射逻辑应用；OAuth 授权方式和密钥仍由其下属客户端承载。
 *
 * @property int $id
 * @property string $public_id
 * @property int $owner_user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $logo_url
 * @property string $status
 * @property \DateTimeImmutable|null $created_at
 * @property \DateTimeImmutable|null $updated_at
 */
final class Application extends BaseModel
{
    protected $table = 'moo_applications';

    protected $fillable = ['public_id', 'owner_user_id', 'name', 'description', 'logo_url', 'status'];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
