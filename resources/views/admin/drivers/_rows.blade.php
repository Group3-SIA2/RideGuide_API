@forelse($drivers as $index => $driver)
<tr>
    <td class="rg-td-index">{{ $drivers->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar">
                {{ strtoupper(substr($driver->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($driver->user->last_name ?? '?', 0, 1)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $driver->user->first_name ?? '—' }} {{ $driver->user->last_name ?? '' }}</p>
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $driver->user->email ?? '—' }}</td>
    <td class="rg-td-muted">{{ $driver->organization->name ?? '—' }}</td>
    <td class="rg-td-muted">
        {{ $driver->license_number ?? '—' }}
        @php
            $license = $driver->licenseId;
            $licenseImage = $license ? $license->image : null;
            $hasLicenseImages = $licenseImage && ($licenseImage->image_front || $licenseImage->image_back);
            $licenseFront = $licenseImage && $licenseImage->image_front ? asset('storage/' . $licenseImage->image_front) : '';
            $licenseBack = $licenseImage && $licenseImage->image_back ? asset('storage/' . $licenseImage->image_back) : '';
            $driverName = trim(($driver->user->first_name ?? '') . ' ' . ($driver->user->last_name ?? '')) ?: 'Driver License';
        @endphp
        @if($hasLicenseImages)
            <button type="button"
                    class="btn btn-link btn-sm px-0 rg-view-license"
                    data-toggle="modal"
                    data-target="#driverLicensePreviewModal"
                    data-driver="{{ $driverName }}"
                    data-front="{{ $licenseFront }}"
                    data-back="{{ $licenseBack }}">
                <i class="fas fa-id-card mr-1"></i> View License
            </button>
        @endif
    </td>
    <td class="rg-td-muted">{{ $driver->franchise_number ?? '—' }}</td>
    <td>
        @php $status = $license ? $license->verification_status : 'unverified'; @endphp
        <span class="rg-status-badge {{ $status === 'verified' ? 'rg-status-active' : ($status === 'rejected' ? 'rg-status-danger' : 'rg-status-pending') }}">
            {{ ucfirst($status) }}
        </span>
    </td>
    <td class="rg-td-muted">{{ $driver->created_at->format('M d, Y') }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="rg-empty">No drivers found.</td>
</tr>
@endforelse
