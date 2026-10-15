<?php

namespace Modules\Users\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Groups\Models\Group;
use Modules\Users\Http\Requests\UserRequest;
use Modules\Users\Support\UserGuard;

class UserController extends Controller
{
    public function __construct(private UserGuard $guard) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles', 'media'])
            ->when($request->string('search')->trim()->toString(), function (Builder $query, string $search) {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->when($request->string('group')->toString(), fn (Builder $query, string $group) => $query->role($group))
            ->when($request->filled('status'), function (Builder $query) use ($request) {
                $query->where('is_active', $request->string('status')->toString() === 'active');
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users::admin.index', ['users' => $users, 'groups' => $this->groupOptions()]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users::admin.form', [
            'user' => new User(['is_active' => true]),
            'groups' => $this->groupOptions(),
            'userGroups' => ['user'],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $sendLink = blank($request->input('password'));

        $user = DB::transaction(function () use ($request, $sendLink) {
            $user = new User($request->safe()->only(['name', 'email', 'locale']));
            // Created by an admin, so the address is trusted. Without a password, the user chooses one via the reset link.
            $user->forceFill([
                'password' => $sendLink ? Str::password(32) : (string) $request->input('password'),
                'is_active' => $request->boolean('is_active'),
                'email_verified_at' => now(),
            ])->save();

            $user->syncRoles($request->groups());
            $this->saveAvatar($request, $user);

            return $user;
        });

        $message = __('users::admin.created');
        if ($sendLink) {
            Password::sendResetLink(['email' => $user->email]);
            $message .= ' '.__('users::admin.reset_link_sent', ['email' => $user->email]);
        }

        return redirect()->route('admin.users.edit', $user)->with('success', $message);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users::admin.form', [
            'user' => $user,
            'groups' => $this->groupOptions(),
            'userGroups' => $user->getRoleNames()->all(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        DB::transaction(function () use ($request, $user) {
            $user->fill($request->safe()->only(['name', 'email', 'locale']));
            $user->forceFill(['is_active' => $request->boolean('is_active')]);
            if (filled($request->input('password'))) {
                $user->forceFill(['password' => (string) $request->input('password')]);
            }
            $user->save();

            $user->syncRoles($request->groups());
            $this->saveAvatar($request, $user);
        });

        return redirect()->route('admin.users.edit', $user)->with('success', __('users::admin.saved'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($error = $this->guard->deleteError($this->actor($request), $user)) {
            return back()->with('error', $error);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('users::admin.deleted'));
    }

    public function sendResetLink(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', __('users::admin.reset_link_sent', ['email' => $user->email]));
    }

    /**
     * Activate, deactivate or delete several users. Users an action is not allowed for are skipped.
     */
    public function bulk(Request $request): RedirectResponse
    {
        abort_unless(Gate::any(['users.edit', 'users.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $actor = $this->actor($request);
        $done = 0;
        $skipped = [];

        foreach (User::whereKey($request->array('ids'))->get() as $user) {
            $ability = $action === 'delete' ? 'delete' : 'update';
            $error = match ($action) {
                'delete' => $this->guard->deleteError($actor, $user),
                'deactivate' => $this->guard->deactivateError($actor, $user),
                default => null,
            };

            if ($error !== null || Gate::denies($ability, $user)) {
                $skipped[] = $user->name;

                continue;
            }

            if ($action === 'delete') {
                $user->delete();
            } else {
                $user->forceFill(['is_active' => $action === 'activate'])->save();
            }
            $done++;
        }

        $redirect = back()->with('success', trans_choice('users::admin.bulk_done', $done, ['count' => $done]));

        return $skipped === []
            ? $redirect
            : $redirect->with('warning', __('users::admin.bulk_skipped', ['names' => implode(', ', $skipped)]));
    }

    /**
     * Group options for selects: name => title. Only super admins may hand out the super-admin group.
     *
     * @return array<string, string>
     */
    private function groupOptions(): array
    {
        $actor = auth()->user();

        return Group::where('guard_name', 'web')->orderBy('id')->get()
            ->reject(fn (Group $group) => $group->isSuperAdmin() && ! ($actor instanceof User && $this->guard->isSuperAdmin($actor)))
            ->mapWithKeys(fn (Group $group) => [$group->name => $group->label()])
            ->all();
    }

    private function saveAvatar(Request $request, User $user): void
    {
        if ($request->hasFile('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        } elseif ($request->boolean('avatar_remove')) {
            $user->clearMediaCollection('avatar');
        }
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
