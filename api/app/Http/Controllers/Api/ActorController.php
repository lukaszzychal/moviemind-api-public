<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actor;

class ActorController extends Controller
{
    public function show(int $id)
    {
        $actor = Actor::with(['bios', 'defaultBio'])->findOrFail($id);
        return response()->json($actor);
    }
}


