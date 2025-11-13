@extends('layouts.user')

@section('title', 'Modal.com Usage & Billing')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Usage & Billing</h1>
            <p class="text-muted">Track your serverless function usage and costs</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Invocations</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_invocations']) }}</h2>
                    <small class="text-success">Last {{ $days }} days</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Success Rate</h6>
                    <h2 class="mb-0">{{ number_format($stats['success_rate'], 1) }}%</h2>
                    <small class="text-muted">
                        {{ number_format($stats['successful_invocations']) }} / {{ number_format($stats['total_invocations']) }}
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">GPU Hours</h6>
                    <h2 class="mb-0">{{ number_format($stats['total_gpu_hours'], 2) }}</h2>
                    <small class="text-muted">
                        CPU: {{ number_format($stats['total_cpu_hours'], 2) }}h
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Estimated Cost</h6>
                    <h2 class="mb-0">${{ number_format($stats['total_cost'], 2) }}</h2>
                    <small class="text-muted">Last {{ $days }} days</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Usage Chart -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Daily Usage</h5>
                    <div class="btn-group btn-group-sm">
                        <a href="?days=7" class="btn btn-outline-secondary {{ $days == 7 ? 'active' : '' }}">7 Days</a>
                        <a href="?days=30" class="btn btn-outline-secondary {{ $days == 30 ? 'active' : '' }}">30 Days</a>
                        <a href="?days=90" class="btn btn-outline-secondary {{ $days == 90 ? 'active' : '' }}">90 Days</a>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="usageChart" height="80"></canvas>
                </div>
            </div>

            <!-- Function Breakdown -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Usage by Function</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Function</th>
                                    <th>Invocations</th>
                                    <th>Success Rate</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($functionUsage as $usage)
                                <tr>
                                    <td>
                                        <strong>{{ $usage['function']->function_name ?? 'Deleted Function' }}</strong>
                                        @if($usage['function']->gpu_enabled ?? false)
                                        <br><span class="badge bg-success">GPU Enabled</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($usage['invocations']) }}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            @php
                                            $rate = $usage['invocations'] > 0 ? 100 : 0;
                                            $color = $rate >= 95 ? 'success' : ($rate >= 80 ? 'warning' : 'danger');
                                            @endphp
                                            <div class="progress-bar bg-{{ $color }}" style="width: {{ $rate }}%">
                                                {{ number_format($rate, 1) }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>${{ number_format($usage['cost'], 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No usage data available
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
            <!-- Cost Breakdown -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cost Breakdown</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">CPU Compute:</dt>
                        <dd class="col-6">${{ number_format($stats['total_cpu_hours'] * 0.01, 2) }}</dd>

                        <dt class="col-6">GPU Compute:</dt>
                        <dd class="col-6">${{ number_format($stats['total_gpu_hours'] * 1.10, 2) }}</dd>

                        <dt class="col-6">Network:</dt>
                        <dd class="col-6">$0.00</dd>

                        <dt class="col-6 border-top pt-2"><strong>Total:</strong></dt>
                        <dd class="col-6 border-top pt-2">
                            <strong>${{ number_format($stats['total_cost'], 2) }}</strong>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Tips -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cost Optimization Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Use CPU-only functions when GPU is not required</li>
                        <li>Set appropriate timeout values to avoid unnecessary charges</li>
                        <li>Monitor failed invocations to reduce wasted resources</li>
                        <li>Use smaller GPU types (T4) for development and testing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('usageChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json($chartData['labels']),
        datasets: [
            {
                label: 'Invocations',
                data: @json($chartData['invocations']),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                yAxisID: 'y',
            },
            {
                label: 'Cost ($)',
                data: @json($chartData['costs']),
                borderColor: 'rgb(255, 159, 64)',
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Invocations'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Cost ($)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            },
        }
    },
});
</script>
@endpush
@endsection
