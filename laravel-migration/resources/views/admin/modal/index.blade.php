@extends('layouts.admin')

@section('title', 'Modal.com Serverless Platform')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Modal.com Serverless Platform</h1>
            <p class="text-muted">System-wide serverless function and GPU workload management</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Functions</h6>
                            <h2 class="mb-0">{{ $totalFunctions }}</h2>
                            <small class="text-success">{{ $activeFunctions }} active</small>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-code fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Scheduled Jobs</h6>
                            <h2 class="mb-0">{{ $totalJobs }}</h2>
                            <small class="text-success">{{ $activeJobs }} active</small>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Invocations</h6>
                            <h2 class="mb-0">{{ number_format($totalInvocations) }}</h2>
                            <small class="text-muted">This month</small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-play-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Cost</h6>
                            <h2 class="mb-0">${{ number_format($totalCost, 2) }}</h2>
                            <small class="text-muted">This month</small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Recent Functions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Functions</h5>
                    <a href="{{ route('admin.modal.functions') }}" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Function Name</th>
                                    <th>Account</th>
                                    <th>Runtime</th>
                                    <th>GPU</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentFunctions as $function)
                                <tr>
                                    <td>
                                        <strong>{{ $function->function_name }}</strong>
                                        @if($function->description)
                                        <br><small class="text-muted">{{ Str::limit($function->description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $function->account->username ?? 'N/A' }}</td>
                                    <td><code>{{ $function->runtime }}</code></td>
                                    <td>
                                        @if($function->gpu_enabled)
                                        <span class="badge bg-success">
                                            <i class="fas fa-microchip"></i> {{ $function->gpu_type }} x{{ $function->gpu_count }}
                                        </span>
                                        @else
                                        <span class="badge bg-secondary">No GPU</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($function->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                        @elseif($function->status === 'deploying')
                                        <span class="badge bg-info">Deploying</span>
                                        @elseif($function->status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                        @else
                                        <span class="badge bg-secondary">{{ ucfirst($function->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $function->created_at->diffForHumans() }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No functions deployed yet
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.modal.config') }}" class="btn btn-outline-primary">
                            <i class="fas fa-cog"></i> Configure Modal.com
                        </a>
                        <a href="{{ route('admin.modal.functions') }}" class="btn btn-outline-info">
                            <i class="fas fa-code"></i> View All Functions
                        </a>
                        <a href="{{ route('admin.modal.jobs') }}" class="btn btn-outline-success">
                            <i class="fas fa-clock"></i> View Scheduled Jobs
                        </a>
                        <a href="{{ route('admin.modal.usage') }}" class="btn btn-outline-warning">
                            <i class="fas fa-chart-bar"></i> Usage & Billing
                        </a>
                        <a href="{{ route('admin.modal.logs') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-alt"></i> Execution Logs
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">System Status</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-6">Modal.com:</dt>
                        <dd class="col-sm-6">
                            @if($config->isActive())
                            <span class="badge bg-success">Active</span>
                            @else
                            <span class="badge bg-danger">Disabled</span>
                            @endif
                        </dd>

                        <dt class="col-sm-6">Workspace:</dt>
                        <dd class="col-sm-6">
                            <small>{{ $config->workspace_name ?: 'Not Set' }}</small>
                        </dd>

                        <dt class="col-sm-6">Configuration:</dt>
                        <dd class="col-sm-6">
                            @if($config->isConfigured())
                            <span class="badge bg-success">Configured</span>
                            @else
                            <span class="badge bg-warning">Not Configured</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
