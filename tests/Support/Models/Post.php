<?php

namespace Dominservice\MediaKit\Tests\Support\Models;

use Dominservice\MediaKit\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasMedia;

    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;
}
