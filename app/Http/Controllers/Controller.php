<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // V1.11 (DEC-049 · G-C): Controllers may invoke Laravel Policies via
    // AuthorizesRequests. Existing controllers only opt in where the
    // Concrete Design Gate requires it (Task create/assign in I-2).
    use AuthorizesRequests;
}
