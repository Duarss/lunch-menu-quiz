<?php


namespace App\Http\Controllers;

use App\Helpers\Project;
use App\Http\Requests\UserRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\UserService;
use App\Traits\HasResponse;
use App\Traits\HasTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HasTransaction, HasResponse;

    public function __construct(private UserService $userService) {}
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $title = 'Master Users';
        $companies = Company::orderBy('name')->pluck('name', 'code');
        $companyOptions = $companies->toArray();
        $role = auth()->user()->role ?? 'guest';

        return view('masters.user.index', compact('title', 'companyOptions', 'role'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request, UserService $service)
    {
        // dd('STORE', $request->all());

        $this->authorize('create', User::class);

        return $this->handle(__FUNCTION__, function () use ($request, $service) {
            return $service->store($request->validated());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  User $masterUser
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->company;

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function viewDetails(User $user)
    {
        $this->authorize('viewDetails', $user);

        $tz = config('app.timezone', 'Asia/Jakarta');
        $chosenMenus = $user->chosenMenus()
            ->with('menu')
            ->orderByDesc('chosen_for_day')
            ->get();

        $rows = [];
        $weeks = collect();

        foreach ($chosenMenus as $entry) {
            $day = Carbon::parse($entry->chosen_for_day, $tz);
            $weekCode = Project::monthWeekCode($day->copy()->startOfWeek(Carbon::MONDAY));
            $weeks->push($weekCode);

            $rows[] = [
                'week_code' => $weekCode,
                'day_label' => $day->format('D'),
                'menu_name' => $entry->menu?->name ?? '—',
                'status' => $entry->is_locked ? 'Locked' : 'Saved',
                'chosen_label' => $entry->chosen_at
                    ? Carbon::parse($entry->chosen_at, $tz)->format('d M Y H:i')
                    : '—',
            ];
        }

        return view('masters.user.history', [
            'title' => 'User Details - ' . $user->name,
            'user' => $user,
            'weeks' => $weeks->unique()->values(),
            'rows' => $rows,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  User $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, User $user)
    {
        // dd('UPDATE', $request->all());
        $this->authorize('update', $user);

        $user = $this->userService->update($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user,
        ]);
    }

    public function resetPassword(User $user)
    {
        $this->authorize('resetPassword', $user);

        $this->userService->resetPassword($user);

        return response()->json([
            'success' => true,
            'message' => 'Password karyawan berhasil direset menjadi username.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        if ($user) {
            $this->userService->delete($user);
            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
        }
    }

    /**
     * Change password for the currently authenticated karyawan.
     */
    public function changeOwnPassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        $user = $request->user();

        $this->userService->changeOwnPassword($user, $request->input('password'));

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ]);
    }
}
