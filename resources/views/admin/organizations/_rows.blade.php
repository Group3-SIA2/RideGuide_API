@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $organizationsRestoreRoute = $panelPrefix . '.organizations.restore';
    $organizationsEditRoute = $panelPrefix . '.organizations.edit';
    $organizationsDestroyRoute = $panelPrefix . '.organizations.destroy';
@endphp

@forelse($organizations as $index => $org)
<tr>
    <td class="rg-td-index">{{ $organizations->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar" style="background: var(--rg-accent-dim); color: var(--rg-accent);">
                {{ strtoupper(substr($org->name, 0, 2)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $org->name }}</p>
            </div>
        </div>
    </td>

    <td class="rg-td-muted">
        @if(!empty($org->organization_type))
            <span>{{ $org->organization_type }}</span>
        @else
            <span class="text-muted fst-italic">—</span>
        @endif
    </td>

    <td class="rg-td-muted">
        @if($org->hqAddress)
            <span title="{{ implode(', ', array_filter([
                $org->hqAddress->floor_unit_room,
                $org->hqAddress->subdivision,
                $org->hqAddress->street,
                $org->hqAddress->barangay,
            ])) }}">
                {{ $org->hqAddress->street }}, {{ $org->hqAddress->barangay }}
            </span>
        @else
            <span class="text-muted fst-italic">—</span>
        @endif
    </td>
    <td class="rg-td-muted">{{ $org->drivers_count }}</td>
    <td>
        @if($showDeleted ?? false)
            <span class="rg-status-badge rg-status-pending">Deleted</span>
        @else
            <span class="rg-status-badge {{ $org->status === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
                {{ ucfirst($org->status) }}
            </span>
        @endif
    </td>
    <td>
        @if($showDeleted ?? false)
            <form method="POST" action="{{ route($organizationsRestoreRoute, $org->id) }}">
                @csrf
                <button type="submit" class="rg-btn rg-btn-sm"
                        style="background:rgba(34,197,94,0.12);color:#15803d;border:none;">
                    <i class="fas fa-undo"></i> Restore
                </button>
            </form>
        @else
            <div class="d-flex gap-1">
                {{-- Update Address modal trigger --}}
                <button type="button"
                        class="rg-btn rg-btn-sm rg-btn-address"
                        style="background:rgba(245,158,11,0.12);color:#b45309;border:none;"
                        title="Update Address"
                        data-org-id="{{ $org->id }}"
                        data-org-name="{{ $org->name }}"
                        data-hq-street="{{ $org->hqAddress->street ?? '' }}"
                        data-hq-barangay="{{ $org->hqAddress->barangay ?? '' }}"
                        data-hq-subdivision="{{ $org->hqAddress->subdivision ?? '' }}"
                        data-hq-floor-unit-room="{{ $org->hqAddress->floor_unit_room ?? '' }}"
                        data-hq-lat="{{ $org->hqAddress->lat ?? '' }}"
                        data-hq-lng="{{ $org->hqAddress->lng ?? '' }}"
                        onclick="openAddressModal(this)">
                    <i class="fas fa-map-marker-alt"></i>
                </button>
                <a href="{{ route($organizationsEditRoute, $org->id) }}"
                   class="rg-btn rg-btn-sm" style="background:var(--rg-accent-dim);color:var(--rg-accent);border:none;">
                    <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="{{ route($organizationsDestroyRoute, $org->id) }}"
                      onsubmit="return confirm('Delete {{ addslashes($org->name) }}? It can be restored later.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rg-btn rg-btn-sm rg-btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="7" class="rg-empty">
        {{ ($showDeleted ?? false) ? 'No deleted organizations.' : 'No organizations found.' }}
    </td>
</tr>
@endforelse
