<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Select2
{
	/**
	 * Build Select2-compatible JSON response for karyawan user search.
	 */
	public static function karyawanUsers(Request $request): JsonResponse
	{
		$term = trim($request->get('term', ''));
		$query = User::query()->where('role', 'karyawan');
		if ($term !== '') {
			$query->where(function ($q) use ($term) {
				$q->where('username', 'like', "%{$term}%")
					->orWhere('name', 'like', "%{$term}%");
			});
		}
		$results = $query->orderBy('username')->limit(20)->get(['id', 'username', 'name']);
		return response()->json([
			'results' => $results->map(function ($u) {
				return [
					'id' => $u->username,
					'text' => $u->username . ' - ' . ($u->name ?? ''),
				];
			})->all(),
		]);
	}

	/**
	 * Build Select2-compatible JSON response for company search.
	 */
	public static function companies(Request $request)
	{
		$search = $request->input('search', '');

		$query = Company::query();

		if ($search !== '') {
			$query->where('name', 'like', '%' . $search . '%')
				->orWhere('code', 'like', '%' . $search . '%');
		}

		$companies = $query
			->orderBy('name')
			->limit(20)
			->get()
			->map(function ($company) {
				return [
					'id'   => $company->code,
					'text' => $company->name,
				];
			});

		return response()->json($companies);
	}
}
