<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationLogController extends Controller
{
    public function index(Request $request): View
    {
        $component = in_array($request->query('component'), IntegrationLog::COMPONENTS, true) ? $request->query('component') : null;

        return view('staff.logs', [
            'component' => $component,
            'components' => IntegrationLog::COMPONENTS,
            'logs' => IntegrationLog::when($component, fn ($q) => $q->where('component', $component))
                ->latest('id')
                ->simplePaginate(30)
                ->withQueryString(),
        ]);
    }
}
