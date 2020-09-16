<?php

namespace Models;

class Token
{
    const OPTION_TOKEN = 'wpmelhorenvio_token';

    const OPTION_TOKEN_SANDBOX = 'wpmelhorenvio_token_sandbox';

    const OPTION_TOKEN_ENVIRONMENT = 'wpmelhorenvio_token_environment';

    const PRODUCTION = 'production';

    const SANDBOX = 'sandox';

    /**
     * function to get tokens in options wordpress.
     *
     * @return array
     */
    public function get()
    {
        return [
            'token' => get_option(self::OPTION_TOKEN, ''),
            'token_sandbox' => get_option(self::OPTION_TOKEN_SANDBOX, ''),
            'token_environment' => get_option(self::OPTION_TOKEN_ENVIRONMENT, self::PRODUCTION)
        ];
    }

    /**
     * @param string $token
     * @param string $tokenSandbox
     * @param string $token_environment
     * @return array $data
     */
    public function save($token, $tokenSandbox, $environment)
    {
        delete_option(self::OPTION_TOKEN);
        delete_option(self::OPTION_TOKEN_SANDBOX);
        delete_option(self::OPTION_TOKEN_ENVIRONMENT);

        return [
            'token' => add_option(self::OPTION_TOKEN, $token, true),
            'token_sandbox' => add_option(self::OPTION_TOKEN_SANDBOX, $tokenSandbox, true),
            'token_environment' => add_option(self::OPTION_TOKEN_ENVIRONMENT, $environment, true)
        ];
    }
}
