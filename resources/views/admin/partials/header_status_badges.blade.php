<div class="d-flex flex-wrap align-items-center gap-2">
    <span class="rg-status-badge rg-status-active">Active: {{ number_format($headerActiveUsers ?? 0) }}</span>
    <span class="rg-status-badge rg-status-pending">Inactive: {{ number_format($headerInactiveUsers ?? 0) }}</span>
    <span class="rg-status-badge rg-status-pending">Suspended: {{ number_format($headerSuspendedUsers ?? 0) }}</span>
</div>
