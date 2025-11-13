<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

class TransactionManager
{
    /**
     * @template TReturn
     *
     * @param Closure():TReturn $callback
     * @return TReturn
     */
    public function run(Closure $callback)
    {
        return DB::transaction($callback);
    }
}
