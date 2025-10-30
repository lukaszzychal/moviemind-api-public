<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;

class ActorController extends Controller
{
    public function show(int $id)
    {
        $person = Person::with(['bios', 'defaultBio', 'movies'])->findOrFail($id);
        return response()->json($person);
    }
}


