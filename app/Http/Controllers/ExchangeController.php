<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use Illuminate\Database\Eloquent\Collection;

class ExchangeController extends Controller
{
    public function index(): Collection
    {
        return Exchange::all();
    }
}
