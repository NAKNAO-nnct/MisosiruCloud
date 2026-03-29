<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        if (!empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $this->userRepository->updateWithTenants(
            id: $id,
            attributes: $attributes,
            tenantIds: array_map('intval', $validated['tenant_ids'] ?? []),
        );

        return redirect()->route('users.index')->with('success', 'ユーザを更新しました。');
    }
}
