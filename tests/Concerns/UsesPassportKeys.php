<?php

namespace Tests\Concerns;

/**
 * Give Passport an in-memory key pair, so tests never touch storage/oauth-*.key.
 */
trait UsesPassportKeys
{
    private static ?array $passportKeys = null;

    protected function setUpPassportKeys(): void
    {
        if (self::$passportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);

            self::$passportKeys = [$private, openssl_pkey_get_details($key)['key']];
        }

        config([
            'passport.private_key' => self::$passportKeys[0],
            'passport.public_key' => self::$passportKeys[1],
        ]);
    }
}
