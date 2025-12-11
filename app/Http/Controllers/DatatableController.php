<?php

namespace App\Http\Controllers;

use App\Models\LunchPickupWindow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class DatatableController extends Controller
{
    public function masterUser(Request $request)
    {
        $user = auth()->user();

        if ($user->role == 'admin' || $user->role == 'bm' || $user->role == 'karyawan') {
            $query = User::with('company')
                ->select(['name', 'username', 'role', 'updated_at', 'company_code'])
                ->whereIn('role', ['karyawan', 'vendor']);

            if ($request->has('search') && !empty($request->search['value'])) {
                $search = strtolower(trim($request->search['value']));

                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw('lower(username)'), 'like', "%{$search}%")
                        ->orWhere(DB::raw('lower(name)'), 'like', "%{$search}%")
                        ->orWhereHas('company', function ($companyQuery) use ($search) {
                            $companyQuery
                                ->where(DB::raw('lower(name)'), 'like', "%{$search}%")
                                ->orWhere(DB::raw('lower(code)'), 'like', "%{$search}%");
                        });
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn("name", function ($user) {
                    return $user ? $user->name : "-";
                })
                ->addColumn("username", function ($user) {
                    return $user ? $user->username : "-";
                })
                ->addColumn("company", function ($user) {
                    return $user?->company?->name ?? "-";
                })
                ->addColumn("role", function ($user) {
                    return $user ? ucfirst($user->role) : "-";
                })
                ->addColumn("updated_at", function ($user) {
                    return $user ? $user->updated_at : "-";
                })
                ->addColumn("details", function ($user) {
                    return view("datatables.detail-master-user", compact("user"))->render();
                })
                ->addColumn("action", function ($user) {
                    return view("datatables.action-master-user", compact("user"))->render();
                })
                ->rawColumns(["details", "action"])
                ->order(function ($query) {
                    $query->orderBy("name", "asc");
                })
                ->toJson();
        }
    }

    public function lunchPickupWindows(Request $request)
    {
        $this->authorize('viewAny', LunchPickupWindow::class);

        $query = LunchPickupWindow::query()->select(['id', 'date', 'start_time', 'end_time']);

        return DataTables::of($query)
            ->addColumn('date', function (LunchPickupWindow $window) {
                return ($window->date)->format('Y-m-d');
            })
            ->addColumn('start_time', function (LunchPickupWindow $window) {
                return $window ? $window->start_time : null;
            })
            ->addColumn('end_time', function (LunchPickupWindow $window) {
                return $window ? $window->end_time : null;
            })
            ->order(function ($builder) {
                $builder->orderBy('date');
            })
            ->toJson();
    }
}
