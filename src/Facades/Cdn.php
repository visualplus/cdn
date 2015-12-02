<?php
namespace Visualplus\Cdn\Facades;

use Illuminate\Support\Facades\Facade;

class Cdn extends Facade {
    protected static function getFacadeAccessor() { return 'cdn'; }
}