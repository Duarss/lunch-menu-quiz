@extends('layouts.app', ['title' => $title ?? 'Selection History'])

@section('content')
<div class="space-y-6">
  <h5 class="mt-3 mb-3">Selection History</h5>
  <x-card-data id="history-weeks" title="Weeks Recorded" :value="$weeks->count()" subtitle="Total distinct weeks" icon="bx-calendar" unit="" colour="primary" />

  <x-table id="history-table" caption="Recent Selections">
    <thead>
      <tr>
        <th class="text-left">Week Code</th>
        <th class="text-left">Day</th>
        <th class="text-left">Menu</th>
        <th class="text-left">Status</th>
        <th class="text-left">Chosen At</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r['week_code'] }}</td>
          <td>{{ $r['day_label'] }}</td>
          <td>{{ $r['menu_name'] }}</td>
          <td>{{ $r['status'] }}</td>
          <td>{{ $r['chosen_label'] }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center">No selections yet.</td></tr>
      @endforelse
    </tbody>
  </x-table>

  <div class="d-flex flex-wrap gap-2">
    <x-button :url="route('masterUser.index')" label="Back to Master User" icon="bx-arrow-back" />
    @if(auth()->user()?->role == 'karyawan')
        <x-button :url="route('masterMenu.index')" label="Back to Menu" icon="bx-arrow-back" />
        <x-button :url="route('masterMenu.index')" label="Edit Upcoming Week" icon="bx-edit" />
    @endif
  </div>
</div>
@endsection
