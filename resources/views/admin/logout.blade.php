@extends('adminlte::page')

@section('title', 'Logout — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Logout</h4>
            <p class="rg-page-subtitle">Are you sure you want to leave?</p>
        </div>
    </div>
@stop

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-5 col-lg-4">
            <div class="rg-card">
                <div class="rg-card-body" style="padding: 32px 28px; text-align: center;">

                    <div class="rg-logout-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>

                    <h5 class="rg-logout-title">Sign out of RideGuide?</h5>
                    <p class="rg-logout-sub">
                        You will be redirected to the login page.<br>
                        Any unsaved changes will be lost.
                    </p>

                    <div class="d-flex gap-3 justify-content-center mt-4">
                        <a href="{{ route('admin.dashboard') }}" class="rg-btn-cancel">
                            Cancel
                        </a>

                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="rg-btn-logout">
                                <i class="fas fa-sign-out-alt me-1"></i> Yes, Logout
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

@stop

@section('css')
    <style>
        .rg-logout-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .rg-logout-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--rg-text);
            margin-bottom: 6px;
        }
        .rg-logout-sub {
            font-size: 0.82rem;
            color: var(--rg-text-muted);
            line-height: 1.6;
            margin: 0;
        }
        .rg-btn-cancel {
            display: inline-flex;
            align-items: center;
            padding: 9px 22px;
            border-radius: 8px;
            font-size: 0.845rem;
            font-weight: 500;
            background: var(--rg-table-head);
            color: var(--rg-text-muted) !important;
            border: 1px solid var(--rg-card-border);
            text-decoration: none !important;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .rg-btn-cancel:hover {
            background: var(--rg-card-border);
            color: var(--rg-text) !important;
        }
        .rg-btn-logout {
            display: inline-flex;
            align-items: center;
            padding: 9px 22px;
            border-radius: 8px;
            font-size: 0.845rem;
            font-weight: 600;
            background: #ef4444;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .rg-btn-logout:hover {
            background: #dc2626;
            color: #fff;
        }
        .gap-3 { gap: 12px !important; }
        .me-1  { margin-right: 4px; }
    </style>
@stop
