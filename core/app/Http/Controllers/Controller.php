<?php

namespace App\Http\Controllers;

use Laramin\Utility\Onumoti;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use ValidatesRequests;
    
    public function __construct()
    {
        $className = get_called_class();
        Onumoti::mySite($this,$className);
    }

    public static function middleware()
    {
        return [];
    }

}
