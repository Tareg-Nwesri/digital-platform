<?php

namespace App\Http\Controllers;

use App\Domain\Access\Services\MembershipDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HubController extends Controller
{
    public function __construct(private readonly MembershipDirectory $directory) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Hub', [
            'teams' => $this->directory->teams($user),
            'departments' => $this->directory->departments($user),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    /**
     * The legacy /dashboard URL sends people to their own home.
     */
    public function home(Request $request): RedirectResponse
    {
        return redirect()->to($this->directory->homeFor($request->user()));
    }
}
