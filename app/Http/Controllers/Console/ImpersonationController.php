<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lab404\Impersonate\Services\ImpersonateManager;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonateManager $manager) {}

    public function take(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('impersonate', $user);

        $this->manager->take($request->user(), $user);

        return redirect()->to($this->manager->getTakeRedirectTo());
    }

    public function leave(): RedirectResponse
    {
        if ($this->manager->isImpersonating()) {
            $this->manager->leave();
        }

        return redirect()->to($this->manager->getLeaveRedirectTo());
    }
}
