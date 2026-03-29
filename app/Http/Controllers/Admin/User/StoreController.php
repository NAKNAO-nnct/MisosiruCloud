<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(CreateUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->userRepository->createWithTenants(
            attributes: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
            ],
            tenantIds: array_map('intval', $validated['tenant_ids'] ?? []),
        );

        return redirect()->route('users.index')->with('success', 'ユーザを作成しました。');
    }
}
