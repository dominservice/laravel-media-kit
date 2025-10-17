<?php

namespace Dominservice\MediaKit\Tests\Support\Models;

use Dominservice\MediaKit\Traits\HasMedia;
use Dominservice\MediaKit\Traits\HasMediaKinds;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasMedia, HasMediaKinds;

    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;
}
