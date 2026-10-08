@extends('layouts.admin')
@section('title', 'Period Slots Configuration')
@section('content')
@include('timetable.partials.header', [
    'heading' => 'Period Slots Configuration',
    'description' => 'Configure standard daily period timings, breaks, and order for the school timetable.'
])

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <p style="font-size: 13px; color: var(--mute); margin: 0;">
            Periods define the timeline for every school day. Periods marked as "Break" do not allow subject scheduling.
        </p>
    </div>
    <button type="button" class="button-primary" onclick="document.getElementById('add-period-modal').showModal()" style="display: inline-flex; align-items: center; gap: 6px;">
        <span aria-hidden="true">+</span> Add New Period
    </button>
</div>

<!-- Periods Table -->
<div class="panel student-form" style="padding: 24px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
    <div class="overflow-x-auto">
        <table class="student-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--line); text-align: left; font-size: 12px; color: var(--mute); text-transform: uppercase;">
                    <th style="padding: 12px 16px;">Order</th>
                    <th style="padding: 12px 16px;">Period Name</th>
                    <th style="padding: 12px 16px;">Timing</th>
                    <th style="padding: 12px 16px;">Duration</th>
                    <th style="padding: 12px 16px;">Type</th>
                    <th style="padding: 12px 16px;">Status</th>
                    <th style="padding: 12px 16px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periods as $p)
                    <tr style="border-bottom: 1px solid var(--line); font-size: 14px;">
                        <td style="padding: 14px 16px; font-weight: 700; color: var(--mute);">
                            #{{ $p->sort_order }}
                        </td>
                        <td style="padding: 14px 16px; font-weight: 600; color: var(--ink);">
                            {{ $p->name }}
                        </td>
                        <td style="padding: 14px 16px; color: var(--ink); font-family: 'Segoe UI', Arial, sans-serif;">
                            {{ $p->formatted_time_range }}
                        </td>
                        <td style="padding: 14px 16px; color: var(--mute);">
                            {{ $p->duration_minutes }} min
                        </td>
                        <td style="padding: 14px 16px;">
                            @if($p->is_break)
                                <span class="badge" style="background: var(--bg); color: var(--mute); padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                    Break Slot
                                </span>
                            @else
                                <span class="badge" style="background: var(--soft); color: var(--teal); padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                    Academic
                                </span>
                            @endif
                        </td>
                        <td style="padding: 14px 16px;">
                            @if($p->is_active)
                                <span class="badge status-ok" style="padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                    Active
                                </span>
                            @else
                                <span class="badge" style="background: var(--bg); color: var(--mute); padding: 4px 10px; border-radius: 99px; font-size: 12px;">
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td style="padding: 14px 16px; text-align: right;">
                            <div style="display: inline-flex; gap: 8px;">
                                <button type="button" class="button-secondary" data-edit-period="{{ $p->toJson() }}" data-update-url="{{ route('timetable.periods.update', $p) }}" style="padding: 6px 12px; font-size: 12px;">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('timetable.periods.destroy', $p) }}" style="display: inline;" onsubmit="return confirm('Delete this period?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button-secondary" style="padding: 6px 12px; font-size: 12px; color: var(--danger);">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px; color: var(--mute);">
                            No periods defined yet. Click "Add New Period" above to configure your slots.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Period Modal -->
<dialog id="add-period-modal" class="progress-modal-backdrop" style="border: none; padding: 0; background: transparent; width: 100%; max-width: 480px;">
    <div style="background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="margin: 0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; color: var(--ink);">
                Add Period Slot
            </h3>
            <button type="button" onclick="document.getElementById('add-period-modal').close()" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--mute);">&times;</button>
        </div>

        <form method="POST" action="{{ route('timetable.periods.store') }}" id="add-period-form">
            @csrf
            <input type="hidden" name="creating_period" value="1">
            @if($errors->any() && old('creating_period'))<div role="alert" class="field-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div style="margin-bottom: 14px;">
                <label for="p-name" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Period Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="p-name" name="name" placeholder="e.g. Period 1, Morning Break" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label for="p-start" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Start Time <span style="color: var(--danger);">*</span></label>
                    <input type="time" id="p-start" name="start_time" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                </div>
                <div>
                    <label for="p-end" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">End Time <span style="color: var(--danger);">*</span></label>
                    <input type="time" id="p-end" name="end_time" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label for="p-sort" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Sort Order <span style="color: var(--danger);">*</span></label>
                <input type="number" id="p-sort" name="sort_order" value="{{ $nextSortOrder }}" min="1" max="10000" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                <p class="photo-help">An occupied order is allowed. Existing periods move forward to make room for the new slot.</p>
            </div>

            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--ink);">
                    <input type="checkbox" name="is_break" value="1">
                    Is Break Slot
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--ink);">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Is Active
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="button-secondary" onclick="document.getElementById('add-period-modal').close()">Cancel</button>
                <button type="submit" class="button-primary">Create Period</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit Period Modal -->
<dialog id="edit-period-modal" class="progress-modal-backdrop" style="border: none; padding: 0; background: transparent; width: 100%; max-width: 480px;">
    <div style="background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="margin: 0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; color: var(--ink);">
                Edit Period Slot
            </h3>
            <button type="button" onclick="document.getElementById('edit-period-modal').close()" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--mute);">&times;</button>
        </div>

        <form id="edit-period-form" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit-period-id" name="editing_period_id">
            @if($errors->any() && old('editing_period_id'))
                <div role="alert" class="field-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
            @endif
            <div style="margin-bottom: 14px;">
                <label for="edit-p-name" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Period Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="edit-p-name" name="name" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label for="edit-p-start" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Start Time <span style="color: var(--danger);">*</span></label>
                    <input type="time" id="edit-p-start" name="start_time" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                </div>
                <div>
                    <label for="edit-p-end" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">End Time <span style="color: var(--danger);">*</span></label>
                    <input type="time" id="edit-p-end" name="end_time" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label for="edit-p-sort" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Sort Order <span style="color: var(--danger);">*</span></label>
                <input type="number" id="edit-p-sort" name="sort_order" min="1" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
            </div>

            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--ink);">
                    <input type="checkbox" id="edit-p-break" name="is_break" value="1">
                    Is Break Slot
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--ink);">
                    <input type="checkbox" id="edit-p-active" name="is_active" value="1">
                    Is Active
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="button-secondary" onclick="document.getElementById('edit-period-modal').close()">Cancel</button>
                <button type="submit" class="button-primary">Save Changes</button>
            </div>
        </form>
    </div>
</dialog>

@if($errors->any() && old('editing_period_id'))
<script type="application/json" id="period-edit-retry">{!! json_encode(['id' => old('editing_period_id'), 'name' => old('name'), 'start_time' => old('start_time'), 'end_time' => old('end_time'), 'sort_order' => old('sort_order'), 'is_break' => (bool) old('is_break'), 'is_active' => (bool) old('is_active')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
@if($errors->any() && old('creating_period'))
<script type="application/json" id="period-create-retry">{!! json_encode(collect(old())->only(['name', 'start_time', 'end_time', 'sort_order', 'is_break', 'is_active']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
@endsection
