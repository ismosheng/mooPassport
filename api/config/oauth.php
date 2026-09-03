<?php

declare(strict_types=1);

return [
    'issuer' => rtrim(getenv('OAUTH_ISSUER') ?: (getenv('APP_URL') ?: 'http://127.0.0.1:8787'), '/'),
    'access_token_ttl' => (int) (getenv('OAUTH_ACCESS_TOKEN_TTL') ?: 900),
    'refresh_token_ttl' => (int) (getenv('OAUTH_REFRESH_TOKEN_TTL') ?: 2592000),
    'authorization_code_ttl' => (int) (getenv('OAUTH_AUTH_CODE_TTL') ?: 300),
    'id_token_ttl' => (int) (getenv('OIDC_ID_TOKEN_TTL') ?: 300),
    'private_key_encryption_key' => getenv('OIDC_PRIVATE_KEY_ENCRYPTION_KEY') ?: '',
    'user_data_encryption_key' => getenv('USER_DATA_ENCRYPTION_KEY') ?: '',
];
