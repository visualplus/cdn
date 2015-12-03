<?php

namespace Visualplus\Cdn;

use Illuminate\Database\Eloquent\Model;

class CdnLog extends Model
{
    protected $fillable = ['path', 'filename', 'size'];
}