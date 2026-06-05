<?php

namespace App\Models;

use Laravel\Passkeys\Passkey as BasePasskey;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Passkey extends BasePasskey
{
    use DocumentModel;

    protected $connection = 'mongodb';
}
