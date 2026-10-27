<?php

namespace Msilabs\Bkash;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;

/**
 * @mixin BkashClient
 */
class BkashManager
{
    /**
     * @var array<string, BkashClient>
     */
    protected array $clients = [];

    public function __construct(
        protected Config $config,
        protected CacheFactory $cache,
    ) {}

    /**
     * Get a client for an account defined in config/bkash.php.
     */
    public function account(?string $name = null): BkashClient
    {
        $name ??= $this->getDefaultAccount();

        if (! isset($this->clients[$name])) {
            $credentials = $this->config->get("bkash.accounts.{$name}");

            if (! is_array($credentials)) {
                throw new InvalidArgumentException("bKash account [{$name}] is not configured.");
            }

            $this->clients[$name] = $this->build($name, $credentials);
        }

        return $this->clients[$name];
    }

    /**
     * Get a client for credentials that are not in config, e.g. loaded from the database.
     *
     * @param  array<string, mixed>  $credentials  sandbox, app_key, app_secret, username, password, base_url
     */
    public function using(array $credentials, string $name = 'custom'): BkashClient
    {
        return $this->build($name, $credentials);
    }

    public function getDefaultAccount(): string
    {
        return $this->config->get('bkash.default', 'primary');
    }

    /**
     * @return array<int, string>
     */
    public function accounts(): array
    {
        return array_keys($this->config->get('bkash.accounts', []));
    }

    /**
     * Forget resolved clients, e.g. after changing config at runtime.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];
        } else {
            unset($this->clients[$name]);
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function build(string $name, array $credentials): BkashClient
    {
        return new BkashClient(
            $name,
            $credentials,
            $this->cache->store($this->config->get('bkash.cache.store')),
            [
                'urls' => $this->config->get('bkash.urls'),
                'timeout' => $this->config->get('bkash.timeout', 30),
                'log' => $this->config->get('bkash.log', []),
                'cache_prefix' => $this->config->get('bkash.cache.prefix', 'bkash'),
            ],
        );
    }

    /**
     * Forward calls to the default account.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->account()->{$method}(...$arguments);
    }
}
