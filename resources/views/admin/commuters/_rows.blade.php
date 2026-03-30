@forelse($commuters as $index => $commuter)
@php
    $discount = $commuter->discount;
    $discountImage = $discount?->idImage;
    $discountModalId = 'commuterDiscountImagesModal_' . $commuter->id;
    $discountFrontUrl = $discountImage && $discountImage->image_front
        ? \App\Support\MediaStorage::url($discountImage->image_front)
        : null;
    $discountBackUrl = $discountImage && $discountImage->image_back
        ? \App\Support\MediaStorage::url($discountImage->image_back)
        : null;
    $hasDiscountImages = $discountFrontUrl || $discountBackUrl;
@endphp
<tr>
    <td class="rg-td-index">{{ $commuters->firstItem() + $index }}</td>
    <td>
        <div class="rg-user-cell">
            <div class="rg-avatar">
                {{ strtoupper(substr($commuter->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($commuter->user->last_name ?? '?', 0, 1)) }}
            </div>
            <div>
                <p class="rg-user-name mb-0">{{ $commuter->user->first_name ?? '—' }} {{ $commuter->user->last_name ?? '' }}</p>
            </div>
        </div>
    </td>
    <td class="rg-td-muted">{{ $commuter->user->email ?? '—' }}</td>
    <td>
        <span class="rg-role-badge">
            {{ $commuter->discount?->classificationType?->classification_name ?? 'Regular' }}
        </span>
    </td>
    <td class="rg-td-muted">{{ $commuter->discount?->ID_number ?? '—' }}</td>
    <td>
        @if($hasDiscountImages)
            <button type="button" class="btn btn-link btn-sm px-0" data-toggle="modal" data-target="#{{ $discountModalId }}">
                <i class="fas fa-id-card mr-1"></i> View ID Images
            </button>

            <div class="modal fade" id="{{ $discountModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Commuter ID Images — {{ $commuter->user->first_name ?? '' }} {{ $commuter->user->last_name ?? '' }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <p class="text-muted mb-1">ID Number: <strong>{{ $discount?->ID_number ?? 'N/A' }}</strong></p>
                                <p class="text-muted mb-0">Classification: <strong>{{ $discount?->classificationType?->classification_name ?? 'Regular' }}</strong></p>
                            </div>
                            <div class="row">
                                @if($discountFrontUrl)
                                    <div class="col-md-6 mb-3">
                                        <img src="{{ $discountFrontUrl }}" class="img-fluid rounded shadow-sm" alt="Discount ID Front">
                                        <small class="text-muted d-block mt-2">Front View</small>
                                    </div>
                                @endif
                                @if($discountBackUrl)
                                    <div class="col-md-6 mb-3">
                                        <img src="{{ $discountBackUrl }}" class="img-fluid rounded shadow-sm" alt="Discount ID Back">
                                        <small class="text-muted d-block mt-2">Back View</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <span class="text-muted">No images</span>
        @endif
    </td>
    <td class="rg-td-muted">{{ $commuter->created_at->format('M d, Y') }}</td>
</tr>
@empty
<tr>
    <td colspan="7" class="rg-empty">No commuters found.</td>
</tr>
@endforelse
