@extends('layouts.user')

@section('title', 'Modal.com Serverless Platform')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Modal.com Serverless Platform</h1>
            <p class="text-muted">Deploy and manage serverless functions with GPU acceleration</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">My Functions</h6>
                    <h2 class="mb-0">{{ $totalFunctions }}</h2>
                    <small class="text-success">{{ $activeFunctions }} active</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Scheduled Jobs</h6>
                    <h2 class="mb-0">{{ $totalJobs }}</h2>
                    <small class="text-success">{{ $activeJobs }} active</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Invocations (30d)</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_invocations']) }}</h2>
                    <small class="text-success">{{ number_format($stats['success_rate'], 1) }}% success rate</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Cost (30d)</h6>
                    <h2 class="mb-0">${{ number_format($stats['total_cost'], 2) }}</h2>
                    <small class="text-muted">GPU: ${{ number_format($stats['total_gpu_hours'] * 1.10, 2) }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Recent Functions -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">My Functions</h5>
                    <a href="{{ route('user.modal.functions.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Deploy New Function
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Function</th>
                                    <th>Runtime</th>
                                    <th>Resources</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentFunctions as $function)
                                <tr>
                                    <td>
                                        <strong>{{ $function->function_name }}</strong>
                                        @if($function->description)
                                        <br><small class="text-muted">{{ Str::limit($function->description, 40) }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $function->runtime }}</code></td>
                                    <td>
                                        <small>
                                            {{ $function->cpu_count }} CPU, {{ $function->memory_mb }}MB
                                            @if($function->gpu_enabled)
                                            <br><span class="badge bg-success">{{ $function->gpu_type }} x{{ $function->gpu_count }}</span>
                                            @endif
                                        </small>
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
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="invokeFunction({{ $function->id }})"
                                                {{ !$function->isActive() ? 'disabled' : '' }}>
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-code fa-3x mb-3 opacity-25"></i>
                                        <p>No functions deployed yet</p>
                                        <a href="{{ route('user.modal.functions.create') }}" class="btn btn-primary">
                                            Deploy Your First Function
                                        </a>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($recentFunctions->count() > 0)
                <div class="card-footer text-center">
                    <a href="{{ route('user.modal.functions') }}" class="btn btn-sm btn-outline-primary">
                        View All Functions
                    </a>
                </div>
                @endif
            </div>

            <!-- Recent Executions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Executions</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Function</th>
                                    <th>Started</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentExecutions as $execution)
                                <tr>
                                    <td>{{ $execution->function->function_name ?? 'N/A' }}</td>
                                    <td>{{ $execution->started_at->diffForHumans() }}</td>
                                    <td>{{ $execution->getDurationSeconds() }}s</td>
                                    <td>
                                        @if($execution->status === 'success')
                                        <span class="badge bg-success">Success</span>
                                        @elseif($execution->status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                        @else
                                        <span class="badge bg-info">{{ ucfirst($execution->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No executions yet
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
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('user.modal.functions.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Deploy New Function
                        </a>
                        <a href="{{ route('user.modal.functions') }}" class="btn btn-outline-primary">
                            <i class="fas fa-code"></i> Manage Functions
                        </a>
                        <a href="{{ route('user.modal.jobs') }}" class="btn btn-outline-info">
                            <i class="fas fa-clock"></i> Scheduled Jobs
                        </a>
                        <a href="{{ route('user.modal.usage') }}" class="btn btn-outline-warning">
                            <i class="fas fa-chart-bar"></i> Usage & Billing
                        </a>
                        <a href="{{ route('user.modal.logs') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-alt"></i> Execution Logs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Resource Usage -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Resource Usage (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">GPU Hours:</dt>
                        <dd class="col-6">{{ number_format($stats['total_gpu_hours'], 2) }}h</dd>

                        <dt class="col-6">CPU Hours:</dt>
                        <dd class="col-6">{{ number_format($stats['total_cpu_hours'], 2) }}h</dd>

                        <dt class="col-6">Invocations:</dt>
                        <dd class="col-6">{{ number_format($stats['total_invocations']) }}</dd>

                        <dt class="col-6">Success Rate:</dt>
                        <dd class="col-6">
                            <span class="badge bg-{{ $stats['success_rate'] >= 95 ? 'success' : ($stats['success_rate'] >= 80 ? 'warning' : 'danger') }}">
                                {{ number_format($stats['success_rate'], 1) }}%
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
