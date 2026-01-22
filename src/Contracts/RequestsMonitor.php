<?php

namespace ChrisHenrique\RequestsMonitor\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface RequestsMonitor
{
    public function logFromRequest(Request $request, ?Model $requester = null): void;
    public function logManually(array $attributes): void;
}
