<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['output' => 'array', 'tool_calls' => 'array', 'success' => 'boolean'];
    }
}
