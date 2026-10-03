<?php

declare(strict_types=1);

namespace RafikiDB\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \RafikiDB\Client client()
 * @method static \RafikiDB\DataBuilder from(string $table)
 * @method static \RafikiDB\Auth auth()
 * @method static \RafikiDB\Realtime realtime()
 * @method static \RafikiDB\Storage storage()
 * @method static \RafikiDB\Env env()
 * @method static \RafikiDB\Secrets secrets()
 * @method static \RafikiDB\Webhooks webhooks()
 * @method static \RafikiDB\Functions functions()
 * @method static \RafikiDB\Payments payments()
 */
class RafikiDB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rafikidb';
    }
}